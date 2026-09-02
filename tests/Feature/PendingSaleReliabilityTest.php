<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use App\Support\AccessControlBootstrapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PendingSaleReliabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_saved_pending_sale_is_visible_despite_old_filters_and_has_a_direct_confirmation_link(): void
    {
        [$user, $batch] = $this->context();
        $this->actingAs($user)->withSession(['sales.filters.pending' => ['search' => 'OLDER-INVOICE', 'date_to' => '2020-01-01']])
            ->post(route('sales.store'), $this->payload($batch))->assertRedirect(route('sales.pending'));
        $sale = Sale::firstOrFail();
        $this->get(route('sales.pending'))->assertOk()->assertSee($sale->invoice_number)
            ->assertSee(route('sales.show', $sale), false)
            ->assertViewHas('sales', fn ($sales) => $sales->contains('id', $sale->id));
    }

    public function test_two_dispenser_tabs_with_the_same_suggested_number_save_as_distinct_invoices(): void
    {
        [$user, $batch] = $this->context();
        $payload = $this->payload($batch);
        $this->actingAs($user)->post(route('sales.store'), $payload)->assertSessionHasNoErrors();
        $secondUser = User::factory()->create(['client_id' => $user->client_id, 'branch_id' => $user->branch_id, 'is_active' => true]);
        $secondUser->roles()->sync($user->roles()->pluck('roles.id'));
        $this->actingAs($secondUser)->post(route('sales.store'), $payload)->assertSessionHasNoErrors();
        $this->assertSame(2, Sale::count());
        $this->assertSame(2, Sale::distinct()->count('invoice_number'));
        $this->assertSame(2, Sale::distinct()->count('served_by'));
        $this->assertSame(2.0, (float) $batch->fresh()->reserved_quantity);
    }

    public function test_rejected_save_keeps_all_entered_medicine_rows_without_saving_or_reserving_stock(): void
    {
        [$user, $batch] = $this->context();
        $payload = $this->payload($batch);
        foreach (['product_id', 'product_batch_id', 'unit_price', 'quantity', 'discount_amount'] as $field) {
            $payload[$field][] = $payload[$field][0];
        }
        $payload['quantity'] = [999, 2];
        $payload['unit_price'] = [27, 29];
        $this->actingAs($user)->from(route('sales.create'))->post(route('sales.store'), $payload)
            ->assertRedirect(route('sales.create'))->assertSessionHasErrors('quantity.0');
        $response = $this->get(route('sales.create'))->assertOk();
        $dom = new \DOMDocument();
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertSame(2, $xpath->query('//*[@id="sale-items-body"]/tr')->length);
        $this->assertSame('999', $xpath->query('//*[@id="sale-items-body"]//input[@name="quantity[]"]')->item(0)->getAttribute('value'));
        $this->assertSame('29', $xpath->query('//*[@id="sale-items-body"]//input[@name="unit_price[]"]')->item(1)->getAttribute('value'));
        $this->assertDatabaseCount('sales', 0);
        $this->assertSame(0.0, (float) $batch->fresh()->reserved_quantity);
    }

    public function test_successful_pending_sale_has_a_creation_audit_record(): void
    {
        [$user, $batch] = $this->context();
        $this->actingAs($user)->post(route('sales.store'), $this->payload($batch))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('audit_logs', ['event_key' => 'sale.created', 'subject_id' => Sale::firstOrFail()->id, 'user_id' => $user->id]);
    }

    public function test_numbering_moves_past_existing_high_numbers_without_changing_old_sales(): void
    {
        [$user, $batch] = $this->context();
        $this->actingAs($user)->post(route('sales.store'), $this->payload($batch));
        $old = Sale::firstOrFail();
        $old->update(['invoice_number' => 'WINV-00500']);
        $this->post(route('sales.store'), $this->payload($batch))->assertSessionHasNoErrors();
        $this->assertSame('WINV-00500', $old->fresh()->invoice_number);
        $this->assertSame('RINV-00501', Sale::latest('id')->firstOrFail()->invoice_number);
    }

    public function test_rejected_pending_edit_retains_attempted_rows_but_does_not_change_saved_sale(): void
    {
        [$user, $batch] = $this->context();
        $payload = $this->payload($batch);
        $this->actingAs($user)->post(route('sales.store'), $payload);
        $sale = Sale::firstOrFail();
        $payload['_sale_form'] = 'edit-' . $sale->id;
        $payload['quantity'] = [999];
        $payload['unit_price'] = [31];
        $this->from(route('sales.edit', $sale))->put(route('sales.update', $sale), $payload)
            ->assertRedirect(route('sales.edit', $sale))->assertSessionHasErrors('quantity.0');
        $this->get(route('sales.edit', $sale))->assertOk()->assertSee('data-recovered-row="true"', false)
            ->assertSee('value="999"', false)->assertSee('value="31"', false);
        $this->assertSame(1.0, (float) $sale->items()->firstOrFail()->quantity);
        $this->assertSame(25.0, (float) $sale->fresh()->total_amount);
        $this->assertSame(1.0, (float) $batch->fresh()->reserved_quantity);
        $this->assertDatabaseMissing('audit_logs', ['event_key' => 'sale.updated']);
    }

    public function test_new_sale_stays_empty_after_leaving_a_validation_error_screen(): void
    {
        [$user, $batch] = $this->context();
        $this->actingAs($user)->from(route('sales.create'))->post(route('sales.store'), array_replace($this->payload($batch), ['quantity' => [999]]));
        $this->get(route('sales.create'))->assertSee('data-recovered-row="true"', false);
        $this->get(route('sales.pending'))->assertOk();
        $this->get(route('sales.create'))->assertOk()->assertDontSee('data-recovered-row="true"', false);
    }

    public function test_backdated_save_confirmation_links_to_the_sale_even_when_it_is_on_another_page(): void
    {
        [$user, $batch] = $this->context();
        $this->actingAs($user)->post(route('sales.store'), $this->payload($batch));
        $seed = Sale::firstOrFail();
        for ($i = 0; $i < 10; $i++) {
            $copy = $seed->replicate();
            $copy->invoice_number = 'HISTORY-' . $i;
            $copy->save();
        }
        $this->post(route('sales.store'), array_replace($this->payload($batch), ['sale_date' => '2020-01-01']))->assertSessionHasNoErrors();
        $saved = Sale::latest('id')->firstOrFail();
        $this->get(route('sales.pending'))->assertOk()->assertSee(route('sales.show', $saved), false)
            ->assertViewHas('sales', fn ($sales) => !$sales->contains('id', $saved->id));
    }

    public function test_failed_submission_does_not_expose_foreign_product_or_batch_details(): void
    {
        [$user, $batch] = $this->context();
        [$other, $foreignBatch] = $this->context();
        $foreignBatch->product->update(['name' => 'Foreign Private Product']);
        $foreignBatch->update(['batch_number' => 'FOREIGN-PRIVATE-BATCH']);
        $this->actingAs($user)->from(route('sales.create'))->post(route('sales.store'), $this->payload($foreignBatch))->assertSessionHasErrors();
        $this->get(route('sales.create'))->assertOk()->assertDontSee('Foreign Private Product')->assertDontSee('FOREIGN-PRIVATE-BATCH');
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_opening_pending_fresh_clears_old_search_but_explicit_search_still_works(): void
    {
        [$user, $batch] = $this->context();
        $this->actingAs($user)->post(route('sales.store'), $this->payload($batch));
        $sale = Sale::firstOrFail();
        $this->get(route('sales.pending', ['search' => 'NOT-THIS-INVOICE']))->assertOk()
            ->assertViewHas('sales', fn ($sales) => $sales->total() === 0);
        $this->withSession(['sales.filters.pending' => ['search' => 'NOT-THIS-INVOICE']])
            ->get(route('sales.pending'))->assertOk()->assertViewHas('sales', fn ($sales) => $sales->contains('id', $sale->id));
    }

    private function context(): array
    {
        $client = DB::table('clients')->insertGetId(['name' => 'VIP PHARMACY', 'business_mode' => 'both', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $branch = DB::table('branches')->insertGetId(['client_id' => $client, 'name' => 'Main Branch', 'code' => 'MAIN', 'is_main' => true, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $user = User::factory()->create(['client_id' => $client, 'branch_id' => $branch, 'is_active' => true]);
        app(AccessControlBootstrapper::class)->ensureForUser($user);
        $user->roles()->sync([Role::where('client_id', $client)->where('name', 'Admin')->firstOrFail()->id]);
        $product = Product::create(['client_id' => $client, 'branch_id' => $branch, 'name' => 'Recovery Test Medicine', 'is_active' => true,
            'track_batch' => true, 'track_expiry' => true, 'purchase_price' => 10, 'retail_price' => 20, 'wholesale_price' => 15]);
        $batch = ProductBatch::create(['client_id' => $client, 'branch_id' => $branch, 'product_id' => $product->id,
            'batch_number' => 'RECOVERY-001', 'expiry_date' => today()->addYear(), 'quantity_received' => 100, 'quantity_available' => 100,
            'reserved_quantity' => 0, 'is_active' => true, 'purchase_price' => 10, 'retail_price' => 20, 'wholesale_price' => 15]);
        return [$user->fresh(), $batch];
    }

    private function payload(ProductBatch $batch): array
    {
        return ['_sale_form' => 'new', 'invoice_number' => 'RINV-00001', 'sale_date' => today()->toDateString(), 'sale_type' => 'retail',
            'payment_type' => 'cash', 'product_id' => [$batch->product_id], 'product_batch_id' => [$batch->id],
            'unit_price' => [25], 'quantity' => [1], 'discount_amount' => [0], 'notes' => 'Keep this entry'];
    }
}
