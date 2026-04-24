<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StockAdjustmentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_routes_are_unblocked(): void
    {
        [$user] = $this->createUserContext();
        $batch = $this->createBatchForUser($user, [
            'batch_number' => 'BATCH-OPEN-001',
        ]);

        $this->actingAs($user)
            ->get(route('stock.index'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('stock.adjust.create', $batch->id))
            ->assertOk();
    }

    public function test_stock_increase_adjustment_updates_batch_and_logs_history(): void
    {
        [$user] = $this->createUserContext();
        $batch = $this->createBatchForUser($user, [
            'batch_number' => 'BATCH-INC-001',
            'quantity_received' => 50,
            'quantity_available' => 50,
            'reserved_quantity' => 5,
        ]);
        $this->createPendingSaleForBatch($user, $batch, 5);

        $response = $this->actingAs($user)->post(route('stock.adjust.store', $batch->id), [
            'direction' => 'increase',
            'reason' => 'count_gain',
            'quantity' => 10,
            'adjustment_date' => '2026-04-19 15:00:00',
            'note' => 'Physical count found extra stock.',
        ]);

        $response->assertRedirect(route('stock.index'));

        $this->assertDatabaseHas('product_batches', [
            'id' => $batch->id,
            'quantity_received' => 60,
            'quantity_available' => 60,
            'reserved_quantity' => 5,
        ]);

        $this->assertDatabaseHas('stock_adjustments', [
            'product_batch_id' => $batch->id,
            'direction' => 'increase',
            'reason' => 'count_gain',
            'quantity' => 10,
            'quantity_available_before' => 50,
            'quantity_available_after' => 60,
            'adjusted_by' => $user->id,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'product_batch_id' => $batch->id,
            'movement_type' => 'adjustment_in',
            'reference_type' => 'stock_adjustment',
            'quantity_in' => 10,
            'quantity_out' => 0,
            'balance_after' => 60,
            'created_by' => $user->id,
        ]);
    }

    public function test_stock_decrease_adjustment_cannot_touch_reserved_stock(): void
    {
        [$user] = $this->createUserContext();
        $batch = $this->createBatchForUser($user, [
            'batch_number' => 'BATCH-DEC-001',
            'quantity_received' => 40,
            'quantity_available' => 40,
            'reserved_quantity' => 15,
        ]);
        $this->createPendingSaleForBatch($user, $batch, 15);

        $response = $this->from(route('stock.adjust.create', $batch->id))
            ->actingAs($user)
            ->post(route('stock.adjust.store', $batch->id), [
                'direction' => 'decrease',
                'reason' => 'damaged',
                'quantity' => 30,
                'adjustment_date' => '2026-04-19 16:00:00',
                'note' => 'Should fail because only 25 is free.',
            ]);

        $response->assertRedirect(route('stock.adjust.create', $batch->id));
        $response->assertSessionHasErrors('quantity');

        $this->assertDatabaseMissing('stock_adjustments', [
            'product_batch_id' => $batch->id,
            'direction' => 'decrease',
            'quantity' => 30,
        ]);

        $this->assertDatabaseHas('product_batches', [
            'id' => $batch->id,
            'quantity_received' => 40,
            'quantity_available' => 40,
            'reserved_quantity' => 15,
        ]);
    }

    public function test_stock_and_sale_batch_views_sync_stale_reserved_quantity_when_no_pending_sale_exists(): void
    {
        [$user] = $this->createUserContext();
        $batch = $this->createBatchForUser($user, [
            'batch_number' => 'BATCH-STALE-001',
            'quantity_received' => 25,
            'quantity_available' => 25,
            'reserved_quantity' => 12,
        ]);

        $this->actingAs($user)
            ->get(route('stock.index'))
            ->assertOk();

        $this->assertDatabaseHas('product_batches', [
            'id' => $batch->id,
            'reserved_quantity' => 0,
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('products.sale-batches', $batch->product_id));

        $response->assertOk();
        $response->assertJsonFragment([
            'id' => $batch->id,
            'reserved_quantity' => 0,
            'free_stock' => 25,
        ]);
    }

    private function createUserContext(): array
    {
        $clientId = $this->createClient('KimRx Stock Test Client');
        $branchId = $this->createBranch($clientId, 'Main Branch');

        $user = User::factory()->create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'is_active' => true,
        ]);

        return [$user, $clientId, $branchId];
    }

    private function createClient(string $name): int
    {
        return DB::table('clients')->insertGetId([
            'name' => $name,
            'business_mode' => 'both',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createBranch(int $clientId, string $name): int
    {
        return DB::table('branches')->insertGetId([
            'client_id' => $clientId,
            'name' => $name,
            'code' => strtoupper(substr($name, 0, 3)),
            'is_main' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createSupplier(int $clientId, string $name): int
    {
        return DB::table('suppliers')->insertGetId([
            'client_id' => $clientId,
            'name' => $name,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createBatchForUser(User $user, array $overrides = []): ProductBatch
    {
        $supplierId = $this->createSupplier($user->client_id, 'Stock Supplier ' . fake()->unique()->numerify('###'));

        $product = Product::create([
            'client_id' => $user->client_id,
            'branch_id' => $user->branch_id,
            'name' => 'Stock Product ' . fake()->unique()->numerify('###'),
            'strength' => '500mg',
            'barcode' => fake()->unique()->numerify('########'),
            'purchase_price' => 100,
            'retail_price' => 150,
            'wholesale_price' => 130,
            'track_batch' => true,
            'track_expiry' => true,
            'is_active' => true,
        ]);

        $purchase = Purchase::create([
            'client_id' => $user->client_id,
            'branch_id' => $user->branch_id,
            'supplier_id' => $supplierId,
            'invoice_number' => 'STK-PINV-' . fake()->unique()->numerify('###'),
            'purchase_date' => '2026-04-19',
            'subtotal' => 5000,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 5000,
            'amount_paid' => 0,
            'balance_due' => 5000,
            'payment_type' => 'credit',
            'payment_status' => 'pending',
            'due_date' => '2026-04-30',
            'invoice_status' => 'fully_received',
            'notes' => 'Seeded for stock tests.',
            'created_by' => $user->id,
            'is_active' => true,
        ]);

        $purchaseItem = PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'batch_number' => $overrides['batch_number'] ?? 'BATCH-' . fake()->unique()->numerify('###'),
            'expiry_date' => '2026-12-31',
            'ordered_quantity' => $overrides['quantity_received'] ?? 20,
            'received_quantity' => $overrides['quantity_received'] ?? 20,
            'remaining_quantity' => 0,
            'quantity' => $overrides['quantity_received'] ?? 20,
            'unit_cost' => 100,
            'total_cost' => 2000,
            'retail_price' => 150,
            'wholesale_price' => 130,
            'line_status' => 'fully_received',
        ]);

        return ProductBatch::create(array_merge([
            'client_id' => $user->client_id,
            'branch_id' => $user->branch_id,
            'product_id' => $product->id,
            'purchase_item_id' => $purchaseItem->id,
            'supplier_id' => $supplierId,
            'batch_number' => $purchaseItem->batch_number,
            'expiry_date' => '2026-12-31',
            'purchase_price' => 100,
            'retail_price' => 150,
            'wholesale_price' => 130,
            'quantity_received' => 20,
            'quantity_available' => 20,
            'reserved_quantity' => 0,
            'is_active' => true,
        ], $overrides));
    }

    private function createPendingSaleForBatch(User $user, ProductBatch $batch, float $quantity): void
    {
        $saleId = DB::table('sales')->insertGetId([
            'client_id' => $user->client_id,
            'branch_id' => $user->branch_id,
            'served_by' => $user->id,
            'invoice_number' => 'STK-SALE-' . fake()->unique()->numerify('###'),
            'sale_type' => 'retail',
            'status' => 'pending',
            'payment_type' => 'cash',
            'payment_method' => null,
            'subtotal' => $quantity * 15,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => $quantity * 15,
            'amount_paid' => 0,
            'amount_received' => 0,
            'balance_due' => $quantity * 15,
            'sale_date' => '2026-04-19',
            'notes' => 'Pending sale reserving stock for adjustment test.',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('sale_items')->insert([
            'sale_id' => $saleId,
            'product_id' => $batch->product_id,
            'product_batch_id' => $batch->id,
            'quantity' => $quantity,
            'purchase_price' => 100,
            'unit_price' => 150,
            'discount_amount' => 0,
            'total_amount' => $quantity * 150,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
