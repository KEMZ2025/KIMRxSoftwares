<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ClientSetting;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Role;
use App\Models\StockRequest;
use App\Models\User;
use App\Support\AccessControlBootstrapper;
use App\Support\StockRequestBook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class StockRequestBookTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispenser_records_unlisted_medicine_without_changing_stock_or_sales(): void
    {
        $user = $this->context();
        $product = $this->product($user);
        $batch = $this->batch($user, $product, ['quantity_available' => 9, 'reserved_quantity' => 2]);
        $before = $batch->fresh()->getAttributes();
        $this->actingAs($user)->postJson(route('stock-requests.store'), $this->payload())
            ->assertCreated()->assertJsonPath('message', 'Request recorded.');
        $entry = StockRequest::firstOrFail();
        $this->assertSame($user->id, $entry->requested_by_user_id);
        $this->assertSame($user->client_id, $entry->client_id);
        $this->assertSame($user->branch_id, $entry->branch_id);
        $this->assertSame('pending', $entry->status);
        $this->assertSame('20.00', $entry->quantity);
        $this->assertSame($before, $batch->fresh()->getAttributes());
        $this->assertDatabaseCount('products', 1);
        foreach (['sales', 'sale_items', 'purchases', 'stock_movements', 'stock_adjustments'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertDatabaseHas('audit_logs', ['event_key' => 'stock_request.created', 'subject_id' => $entry->id, 'user_id' => $user->id]);
    }

    public function test_linked_medicine_uses_canonical_name_strength_and_unit(): void
    {
        $user = $this->context();
        $product = $this->product($user);
        $this->actingAs($user)->postJson(route('stock-requests.store'), $this->payload([
            'product_id' => $product->id, 'medicine_name' => 'Incorrect name', 'unit_name' => 'Box',
        ]))->assertCreated();
        $this->assertDatabaseHas('stock_requests', [
            'product_id' => $product->id, 'medicine_name' => $product->name,
            'strength' => '500mg', 'unit_name' => 'Tablet', 'dosage_form' => null,
        ]);
    }

    public function test_retries_are_idempotent_and_changed_payload_is_not_silently_accepted(): void
    {
        $user = $this->context();
        $payload = $this->payload();
        $first = $this->actingAs($user)->postJson(route('stock-requests.store'), $payload)->assertCreated();
        $this->postJson(route('stock-requests.store'), $payload)->assertOk()->assertJsonPath('id', $first->json('id'));
        $this->postJson(route('stock-requests.store'), array_replace($payload, ['quantity' => 40]))
            ->assertUnprocessable()->assertJsonValidationErrors('submission_token');
        $this->assertDatabaseCount('stock_requests', 1);
        $this->assertSame(1, AuditLog::where('event_key', 'stock_request.created')->count());
    }

    public function test_quantity_requires_unit_and_invalid_values_are_rejected(): void
    {
        $user = $this->context();
        $this->actingAs($user)->postJson(route('stock-requests.store'), $this->payload(['unit_name' => null]))
            ->assertUnprocessable()->assertJsonValidationErrors('unit_name');
        foreach ([0, -1, 'wrong', '1.123', 100000000] as $quantity) {
            $this->postJson(route('stock-requests.store'), $this->payload(['quantity' => $quantity]))->assertUnprocessable();
        }
        $this->postJson(route('stock-requests.store'), $this->payload(['medicine_name' => '   ']))->assertUnprocessable();
        $this->postJson(route('stock-requests.store'), $this->payload(['quantity' => null, 'unit_name' => null]))->assertCreated();
        $this->assertDatabaseCount('stock_requests', 1);
    }

    public function test_other_clients_cannot_access_any_request_book_endpoint(): void
    {
        $vip = $this->context();
        $entry = $this->entry($vip);
        $other = $this->context('Another Pharmacy', 'Admin');
        $this->actingAs($other)->get(route('stock-requests.index'))->assertForbidden();
        $this->getJson(route('stock-requests.products', ['q' => 'Test']))->assertForbidden();
        $this->get(route('stock-requests.show', $entry))->assertForbidden();
        $this->postJson(route('stock-requests.store'), $this->payload())->assertForbidden();
        $this->putJson(route('stock-requests.update', $entry), ['status' => 'closed', 'version' => 1])->assertForbidden();
        $this->get(route('sales.create'))->assertOk()->assertDontSee('data-stock-request-open', false);
    }

    public function test_branches_and_products_are_scoped_to_the_current_workspace(): void
    {
        $vip = $this->context('VIP PHARMACY', 'Admin');
        $entry = $this->entry($vip);
        $other = $this->context('Other Pharmacy');
        $foreignProduct = $this->product($other, 'Foreign medicine');
        $this->actingAs($vip)->postJson(route('stock-requests.store'), $this->payload(['product_id' => $foreignProduct->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('product_id');
        $this->getJson(route('stock-requests.products', ['q' => 'Foreign']))->assertExactJson(['products' => []]);
        $branch = DB::table('branches')->insertGetId([
            'client_id' => $vip->client_id, 'name' => 'Second Branch', 'code' => 'SECOND',
            'is_active' => true, 'is_main' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $vip->update(['branch_id' => $branch]);
        $vip = $vip->fresh();
        $this->actingAs($vip)->get(route('stock-requests.show', $entry))->assertNotFound();
        $this->putJson(route('stock-requests.update', $entry), ['status' => 'closed', 'version' => 1])->assertNotFound();
        $this->get(route('stock-requests.index'))->assertOk()->assertViewHas('rows', fn ($rows) => $rows->total() === 0);
    }

    public function test_permissions_distinguish_recording_reading_and_managing(): void
    {
        $user = $this->context();
        $entry = $this->entry($user);
        $this->actingAs($user)->get(route('stock-requests.show', $entry))->assertOk()->assertDontSee('Save Changes');
        $this->putJson(route('stock-requests.update', $entry), ['status' => 'ordered', 'version' => 1])->assertForbidden();
        $role = Role::create(['client_id' => $user->client_id, 'name' => 'Stock Reader', 'code' => 'reader']);
        $role->permissions()->sync(Permission::where('permission_key', 'stock.view')->pluck('id'));
        $user->roles()->sync([$role->id]);
        $this->actingAs($user->fresh())->get(route('stock-requests.index'))->assertOk()->assertDontSee('data-stock-request-open', false);
        $this->postJson(route('stock-requests.store'), $this->payload())->assertForbidden();
        ClientSetting::where('client_id', $user->client_id)->update(['inventory_enabled' => false]);
        $this->actingAs($user->fresh())->get(route('stock-requests.index'))->assertForbidden();
    }

    public function test_product_search_is_empty_until_typed_and_includes_zero_stock_products(): void
    {
        $user = $this->context();
        $product = $this->product($user, 'Test empty medicine');
        $this->product($user, 'Test inactive medicine')->update(['is_active' => false]);
        $this->actingAs($user)->getJson(route('stock-requests.products'))->assertExactJson(['products' => []]);
        $this->getJson(route('stock-requests.products', ['q' => 'T']))->assertExactJson(['products' => []]);
        $this->getJson(route('stock-requests.products', ['q' => 'Test']))->assertOk()
            ->assertJsonCount(1, 'products')->assertJsonPath('products.0.id', $product->id)
            ->assertJsonMissingPath('products.0.purchase_price');
    }

    public function test_available_is_live_branch_stock_excluding_expired_inactive_and_reserved_batches(): void
    {
        $user = $this->context();
        $product = $this->product($user);
        $entry = $this->entry($user, ['product_id' => $product->id, 'status' => 'ordered']);
        $this->batch($user, $product, ['expiry_date' => today()->subDay(), 'quantity_available' => 100]);
        $this->batch($user, $product, ['is_active' => false, 'quantity_available' => 100]);
        $this->batch($user, $product, ['expiry_date' => null, 'quantity_available' => 100]);
        $batch = $this->batch($user, $product, ['quantity_available' => 8, 'reserved_quantity' => 0]);
        $sale = DB::table('sales')->insertGetId([
            'client_id' => $user->client_id, 'branch_id' => $user->branch_id, 'served_by' => $user->id,
            'invoice_number' => 'REQUEST-TEST', 'sale_type' => 'retail', 'status' => 'pending',
            'payment_type' => 'cash', 'sale_date' => today(), 'is_active' => true,
        ]);
        DB::table('sale_items')->insert([
            'sale_id' => $sale, 'product_id' => $product->id, 'product_batch_id' => $batch->id,
            'quantity' => 8, 'purchase_price' => 10, 'unit_price' => 20, 'total_amount' => 160,
        ]);
        $before = $batch->fresh()->getAttributes();
        $row = StockRequestBook::forUser($user)->findOrFail($entry->id);
        $this->assertSame('ordered', $row->display_status);
        $this->assertEquals(0, $row->free_stock);
        $this->assertSame($before, $batch->fresh()->getAttributes());
        DB::table('sale_items')->where('sale_id', $sale)->update(['quantity' => 5]);
        $row = StockRequestBook::forUser($user)->findOrFail($entry->id);
        $this->assertSame('available', $row->display_status);
        $this->assertEquals(3, $row->free_stock);
        $this->actingAs($user)->get(route('stock-requests.index', ['status' => 'available']))->assertOk()
            ->assertViewHas('rows', fn ($rows) => $rows->total() === 1);
        $entry->update(['status' => 'closed']);
        $this->assertSame('closed', StockRequestBook::forUser($user)->findOrFail($entry->id)->display_status);
    }

    public function test_procurement_groups_matching_requests_without_combining_strengths_or_units(): void
    {
        $user = $this->context();
        $this->entry($user, ['medicine_name' => 'Test Medicine', 'strength' => '500mg', 'quantity' => 20]);
        $this->entry($user, ['medicine_name' => 'test medicine', 'strength' => '500mg', 'quantity' => 30]);
        $this->entry($user, ['medicine_name' => 'Test Medicine', 'strength' => '500mg', 'quantity' => null]);
        $this->entry($user, ['medicine_name' => 'Test Medicine', 'strength' => '250mg', 'quantity' => 10]);
        $this->entry($user, ['medicine_name' => 'Test Medicine', 'strength' => '500mg', 'quantity' => 2, 'unit_name' => 'Box']);
        $this->entry($user, ['medicine_name' => 'Closed Medicine', 'status' => 'closed']);
        $this->actingAs($user)->get(route('stock-requests.index', ['view' => 'procurement']))->assertOk()
            ->assertViewHas('rows', function ($rows) {
                $this->assertSame(3, $rows->total());
                $top = $rows->first();
                $this->assertEquals(3, $top->request_count);
                $this->assertEquals(50, $top->quantity);
                $this->assertEquals(1, $top->unspecified_count);
                return true;
            });
    }

    public function test_manager_updates_follow_up_with_history_and_stale_update_protection(): void
    {
        $user = $this->context('VIP PHARMACY', 'Admin');
        $entry = $this->entry($user);
        $this->actingAs($user)->put(route('stock-requests.update', $entry), ['status' => 'ordered', 'version' => 1, 'note' => 'Supplier contacted'])
            ->assertRedirect(route('stock-requests.show', $entry));
        $this->assertSame('ordered', $entry->fresh()->status);
        $this->assertSame(2, $entry->fresh()->version);
        $this->putJson(route('stock-requests.update', $entry), ['status' => 'closed', 'version' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors('version');
        $this->get(route('stock-requests.show', $entry))->assertOk()->assertSee('Supplier contacted');
        $this->put(route('stock-requests.update', $entry), ['status' => 'closed', 'version' => 2])->assertRedirect();
        $this->put(route('stock-requests.update', $entry), ['status' => 'pending', 'version' => 3])->assertRedirect();
        $this->assertSame(3, AuditLog::where('event_key', 'stock_request.updated')->count());
        $this->assertDatabaseCount('purchases', 0);
    }

    public function test_linking_requires_matching_units_and_does_not_overwrite_original_request(): void
    {
        $user = $this->context('VIP PHARMACY', 'Admin');
        $product = $this->product($user);
        $entry = $this->entry($user, ['unit_name' => 'Box']);
        $this->actingAs($user)->putJson(route('stock-requests.update', $entry), ['status' => 'pending', 'version' => 1, 'product_id' => $product->id])
            ->assertUnprocessable()->assertJsonValidationErrors('product_id');
        $entry->update(['unit_name' => 'Tablet']);
        $this->put(route('stock-requests.update', $entry), ['status' => 'ordered', 'version' => 1, 'product_id' => $product->id])->assertRedirect();
        $this->assertSame($product->id, $entry->fresh()->product_id);
        $this->assertSame('Requested Medicine', $entry->fresh()->medicine_name);
        $this->assertSame('20.00', $entry->fresh()->quantity);
    }

    public function test_filtering_and_pagination_support_end_date_without_start_date(): void
    {
        $user = $this->context();
        for ($i = 0; $i < 23; $i++) $this->entry($user);
        $this->entry($user, ['medicine_name' => 'Old medicine', 'created_at' => today()->subDays(3)]);
        $this->actingAs($user)->get(route('stock-requests.index'))->assertOk()->assertViewHas('rows', fn ($rows) => $rows->total() === 24 && $rows->count() === 20);
        $this->get(route('stock-requests.index', ['page' => 2]))->assertOk()->assertViewHas('rows', fn ($rows) => $rows->count() === 4);
        $this->get(route('stock-requests.index', ['to' => today()->subDay()->format('Y-m-d')]))->assertOk()
            ->assertViewHas('rows', fn ($rows) => $rows->total() === 1);
        $this->get(route('stock-requests.index', ['search' => 'Old']))->assertOk()->assertSee('Old medicine');
    }

    public function test_vip_dispensing_includes_independent_request_form_and_other_clients_do_not(): void
    {
        $user = $this->context();
        $this->actingAs($user)->get(route('sales.create'))->assertOk()
            ->assertSee('data-stock-request-open', false)->assertSee('data-stock-request-form', false);
        $this->assertStringContainsString("window.addEventListener('submit'", file_get_contents(public_path('js/stock-requests.js')));
        $this->assertStringContainsString('event.stopImmediatePropagation()', file_get_contents(public_path('js/stock-requests.js')));
    }

    public function test_available_stock_is_not_taken_from_another_branch_or_inactive_product(): void
    {
        $user = $this->context();
        $product = $this->product($user);
        $entry = $this->entry($user, ['product_id' => $product->id]);
        $branch = DB::table('branches')->insertGetId(['client_id' => $user->client_id, 'name' => 'Other Branch', 'code' => 'OTHER', 'is_active' => true]);
        $this->batch($user, $product, ['branch_id' => $branch, 'quantity_available' => 100]);
        $this->assertSame('pending', StockRequestBook::forUser($user)->findOrFail($entry->id)->display_status);
        $batch = $this->batch($user, $product, ['quantity_available' => 4]);
        $this->assertSame('available', StockRequestBook::forUser($user)->findOrFail($entry->id)->display_status);
        $product->update(['is_active' => false]);
        $this->assertSame('pending', StockRequestBook::forUser($user)->findOrFail($entry->id)->display_status);
        $product->update(['is_active' => true]);
        $batch->update(['quantity_available' => 0]);
        $this->assertSame('pending', StockRequestBook::forUser($user)->findOrFail($entry->id)->display_status);
    }

    public function test_tenant_restore_remaps_request_author_product_and_grouping_key(): void
    {
        $source = $this->context();
        $sourceProduct = $this->product($source);
        $entry = $this->entry($source, ['product_id' => $sourceProduct->id]);
        $destination = $this->context('Restored VIP');
        $destinationProduct = $this->product($destination);
        $mappings = ['branches' => [$source->branch_id => $destination->branch_id],
            'users' => [$source->id => $destination->id], 'products' => [$sourceProduct->id => $destinationProduct->id]];
        $service = app(\App\Support\ClientExportService::class);
        $row = (array) DB::table('stock_requests')->find($entry->id);
        $ready = new \ReflectionMethod($service, 'rowCanBeImported');
        $this->assertTrue($ready->invoke($service, $row, $mappings));
        $import = new \ReflectionMethod($service, 'importTableRow');
        $restored = $import->invokeArgs($service, ['stock_requests', $row, $destination->client, &$mappings]);
        $this->assertSame($destination->client_id, $restored['client_id']);
        $this->assertSame($destination->branch_id, $restored['branch_id']);
        $this->assertSame($destination->id, $restored['requested_by_user_id']);
        $this->assertSame($destinationProduct->id, $restored['product_id']);
        $this->assertSame(StockRequest::groupingKey($restored), $restored['request_key']);
        $this->assertNotSame($entry->request_key, $restored['request_key']);
    }

    public function test_tenant_restore_keeps_request_audit_history_attached_to_restored_request(): void
    {
        $source = $this->context();
        $entry = $this->entry($source);
        $audit = app(\App\Support\AuditTrail::class)->record($source, 'stock_request.created', 'Stock Requests', 'Created', 'Requested medicine.', ['subject' => $entry]);
        $destination = $this->context('Restored VIP');
        $newEntry = $this->entry($destination);
        $mappings = ['branches' => [$source->branch_id => $destination->branch_id],
            'users' => [$source->id => $destination->id], 'stock_requests' => [$entry->id => $newEntry->id]];
        $service = app(\App\Support\ClientExportService::class);
        $row = (array) DB::table('audit_logs')->find($audit->id);
        $this->assertTrue((new \ReflectionMethod($service, 'rowCanBeImported'))->invoke($service, $row, $mappings));
        $restored = (new \ReflectionMethod($service, 'importTableRow'))->invokeArgs($service, ['audit_logs', $row, $destination->client, &$mappings]);
        $this->assertSame($newEntry->id, $restored['subject_id']);
        $this->assertSame($destination->id, $restored['user_id']);
        $this->assertSame($destination->client_id, $restored['client_id']);
    }

    private function context(string $name = 'VIP PHARMACY', string $roleName = 'Dispenser'): User
    {
        $client = DB::table('clients')->insertGetId(['name' => $name, 'business_mode' => 'both', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $branch = DB::table('branches')->insertGetId(['client_id' => $client, 'name' => 'Main Branch', 'code' => 'MAIN', 'is_active' => true, 'is_main' => true, 'created_at' => now(), 'updated_at' => now()]);
        $user = User::factory()->create(['name' => 'Request Test Staff', 'client_id' => $client, 'branch_id' => $branch, 'is_active' => true]);
        app(AccessControlBootstrapper::class)->ensureForUser($user);
        $user->roles()->sync([Role::where('client_id', $client)->where('name', $roleName)->firstOrFail()->id]);
        return $user->fresh();
    }

    private function product(User $user, string $name = 'Test Medicine'): Product
    {
        $unit = DB::table('units')->insertGetId(['client_id' => $user->client_id, 'name' => 'Tablet', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        return Product::create(['client_id' => $user->client_id, 'branch_id' => $user->branch_id, 'unit_id' => $unit, 'name' => $name,
            'strength' => '500mg', 'is_active' => true, 'track_expiry' => true, 'track_batch' => true, 'purchase_price' => 10, 'retail_price' => 20, 'wholesale_price' => 15]);
    }

    private function batch(User $user, Product $product, array $values = []): ProductBatch
    {
        return ProductBatch::create($values + ['client_id' => $user->client_id, 'branch_id' => $user->branch_id,
            'product_id' => $product->id, 'batch_number' => (string) Str::uuid(), 'expiry_date' => today()->addYear(),
            'quantity_received' => 10, 'quantity_available' => 10, 'reserved_quantity' => 0, 'is_active' => true,
            'purchase_price' => 10, 'retail_price' => 20, 'wholesale_price' => 15]);
    }

    private function payload(array $values = []): array
    {
        return $values + ['submission_token' => (string) Str::uuid(), 'medicine_name' => 'Requested Medicine',
            'strength' => '500mg', 'dosage_form' => 'Tablet', 'quantity' => 20, 'unit_name' => 'Tablet', 'note' => 'Requested at counter'];
    }

    private function entry(User $user, array $values = []): StockRequest
    {
        $values = $this->payload($values) + ['client_id' => $user->client_id, 'branch_id' => $user->branch_id, 'requested_by_user_id' => $user->id, 'status' => 'pending'];
        $entry = StockRequest::create($values + ['request_key' => StockRequest::groupingKey($values)]);
        if (isset($values['created_at'])) $entry->forceFill(['created_at' => $values['created_at']])->save();
        return $entry;
    }
}
