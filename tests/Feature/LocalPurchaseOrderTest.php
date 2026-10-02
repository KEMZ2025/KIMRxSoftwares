<?php

namespace Tests\Feature;

use App\Models\LocalPurchaseOrder;
use App\Models\User;
use App\Support\AccessControlBootstrapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class LocalPurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_lpo_lifecycle_does_not_create_purchase_stock_or_payment(): void
    {
        [$user, , , $supplierId, $productId] = $this->workspace('VIP PHARMACY');
        $token = (string) Str::uuid();
        $payload = $this->orderPayload($supplierId, $productId, $token);

        $this->actingAs($user)->get(route('lpos.index'))
            ->assertOk()->assertSee('Local Purchase Orders')
            ->assertSeeInOrder(['menu-label">Purchases', 'menu-label">LPOs'], false);
        $this->actingAs($user)->get(route('lpos.create'))
            ->assertOk()->assertSee('Save Draft LPO')
            ->assertSee('placeholder="Type supplier name"', false)
            ->assertSee('role="combobox"', false)
            ->assertSee('id="lpo-product-dialog"', false)
            ->assertSee('aria-label="Search products"', false)
            ->assertDontSee('class="product-select"', false);

        $this->actingAs($user)->post(route('lpos.store'), $payload)
            ->assertRedirect();
        $order = LocalPurchaseOrder::query()->firstOrFail();
        $this->assertSame('LPO-000001', $order->order_number);
        $this->assertSame('draft', $order->status);
        $this->assertEquals(2950, (float) $order->total_amount);
        $this->assertDatabaseHas('local_purchase_order_items', [
            'local_purchase_order_id' => $order->id,
            'description' => 'Test Medicine',
            'line_total' => 3000,
        ]);
        $this->actingAs($user)->get(route('lpos.show', $order))->assertOk()->assertSee('Issue LPO');
        $this->actingAs($user)->get(route('lpos.edit', $order))
            ->assertOk()->assertSee('Save Changes')
            ->assertSee('value="VIP PHARMACY Supplier"', false)
            ->assertSee('class="product-id" name="items[0][product_id]" value="' . $productId . '"', false)
            ->assertSee('class="product-name" value="Test Medicine"', false);

        $this->actingAs($user)->post(route('lpos.store'), $payload)
            ->assertRedirect(route('lpos.show', $order));
        $this->assertDatabaseCount('local_purchase_orders', 1);
        $this->assertDatabaseCount('local_purchase_order_items', 1);

        $this->actingAs($user)->post(route('lpos.issue', $order), ['version' => $order->version])->assertRedirect(route('lpos.show', $order));
        $this->assertSame('issued', $order->fresh()->status);
        $this->actingAs($user)->get(route('lpos.print', $order))
            ->assertOk()->assertSee('LOCAL PURCHASE ORDER')->assertSee('Test Medicine');

        $this->actingAs($user)->get(route('lpos.edit', $order))->assertStatus(409);
        $this->actingAs($user)->post(route('lpos.cancel', $order), ['version' => $order->fresh()->version, 'reason' => 'Supplier cannot deliver'])
            ->assertRedirect(route('lpos.show', $order));
        $this->assertSame('cancelled', $order->fresh()->status);

        foreach (['purchases', 'purchase_items', 'product_batches', 'supplier_payments', 'stock_movements'] as $table) {
            $this->assertSame(0, DB::table($table)->count());
        }
    }

    public function test_draft_edit_recalculates_totals_and_rejects_stale_tabs(): void
    {
        [$user, , , $supplierId, $productId] = $this->workspace('VIP PHARMACY');
        $this->actingAs($user)->post(route('lpos.store'), $this->orderPayload($supplierId, $productId, (string) Str::uuid()));
        $order = LocalPurchaseOrder::query()->firstOrFail();
        $edited = $this->orderPayload($supplierId, $productId, (string) Str::uuid());
        $edited['version'] = $order->version;
        $edited['items'][0]['quantity'] = 3;

        $this->actingAs($user)->put(route('lpos.update', $order), $edited)->assertRedirect(route('lpos.show', $order));
        $this->assertEquals(4450, (float) $order->fresh()->total_amount);
        $this->actingAs($user)->put(route('lpos.update', $order), $edited)->assertSessionHasErrors('version');
        $this->actingAs($user)->post(route('lpos.issue', $order), ['version' => $edited['version']])
            ->assertSessionHasErrors('version');
        $this->assertSame('draft', $order->fresh()->status);
        $this->assertDatabaseCount('local_purchase_order_items', 1);
    }

    public function test_lpo_rejects_supplier_and_product_from_another_client(): void
    {
        [$user, , , $supplierId, $productId] = $this->workspace('VIP PHARMACY');
        [, , , $foreignSupplierId, $foreignProductId] = $this->workspace('ELOHIM DRUGSHOP');

        $invalidSupplier = $this->orderPayload($foreignSupplierId, $productId, (string) Str::uuid());
        $this->actingAs($user)->post(route('lpos.store'), $invalidSupplier)->assertSessionHasErrors('supplier_id');

        $invalidProduct = $this->orderPayload($supplierId, $foreignProductId, (string) Str::uuid());
        $this->actingAs($user)->post(route('lpos.store'), $invalidProduct)->assertSessionHasErrors('items.0.product_id');
        $this->assertDatabaseCount('local_purchase_orders', 0);
    }

    public function test_other_client_cannot_open_or_modify_lpo(): void
    {
        [$user, , , $supplierId, $productId] = $this->workspace('VIP PHARMACY');
        [$otherUser] = $this->workspace('ELOHIM DRUGSHOP');
        $this->actingAs($user)->post(route('lpos.store'), $this->orderPayload($supplierId, $productId, (string) Str::uuid()));
        $order = LocalPurchaseOrder::query()->firstOrFail();

        $this->actingAs($otherUser)->get(route('lpos.show', $order))->assertNotFound();
        $this->actingAs($otherUser)->get(route('lpos.print', $order))->assertNotFound();
        $this->actingAs($otherUser)->post(route('lpos.issue', $order), ['version' => $order->version])->assertNotFound();
        $this->assertSame('draft', $order->fresh()->status);
    }

    public function test_another_branch_of_same_client_cannot_open_lpo(): void
    {
        [$user, $clientId, , $supplierId, $productId] = $this->workspace('VIP PHARMACY');
        $this->actingAs($user)->post(route('lpos.store'), $this->orderPayload($supplierId, $productId, (string) Str::uuid()));
        $order = LocalPurchaseOrder::query()->firstOrFail();
        $otherBranchId = DB::table('branches')->insertGetId([
            'client_id' => $clientId, 'name' => 'Second Branch', 'code' => 'SECOND',
            'business_mode' => 'inherit', 'is_main' => false, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $user->update(['branch_id' => $otherBranchId]);

        $this->actingAs($user->fresh())->get(route('lpos.show', $order))->assertNotFound();
    }

    private function orderPayload(int $supplierId, int $productId, string $token): array
    {
        return [
            'submission_token' => $token,
            'supplier_id' => $supplierId,
            'order_date' => now()->toDateString(),
            'expected_delivery_date' => now()->addDays(3)->toDateString(),
            'discount_amount' => 100,
            'tax_amount' => 50,
            'items' => [[
                'product_id' => $productId,
                'description' => 'Test Medicine',
                'unit_name' => 'Box',
                'quantity' => 2,
                'unit_price' => 1500,
            ]],
        ];
    }

    private function workspace(string $name): array
    {
        $clientId = DB::table('clients')->insertGetId([
            'name' => $name, 'business_mode' => 'both', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $branchId = DB::table('branches')->insertGetId([
            'client_id' => $clientId, 'name' => 'Main Branch', 'code' => 'MAIN',
            'business_mode' => 'inherit', 'is_main' => true, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $user = User::factory()->create(['client_id' => $clientId, 'branch_id' => $branchId, 'is_active' => true]);
        app(AccessControlBootstrapper::class)->ensureForUser($user);
        $supplierId = DB::table('suppliers')->insertGetId([
            'client_id' => $clientId, 'name' => $name . ' Supplier', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $productId = DB::table('products')->insertGetId([
            'client_id' => $clientId, 'branch_id' => $branchId, 'name' => 'Test Medicine',
            'purchase_price' => 1200, 'retail_price' => 1800, 'wholesale_price' => 1600,
            'track_batch' => true, 'track_expiry' => false, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$user, $clientId, $branchId, $supplierId, $productId];
    }
}
