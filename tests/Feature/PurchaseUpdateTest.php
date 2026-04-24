<?php

namespace Tests\Feature;

use App\Models\ProductBatch;
use App\Models\Purchase;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PurchaseUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_supplier_change_cascades_to_unsold_linked_batches(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext();
        $currentSupplierId = $this->createSupplier($clientId, 'Supplier A');
        $newSupplierId = $this->createSupplier($clientId, 'Supplier B');
        $productId = $this->createProduct('Amoxicillin');

        $purchase = $this->createPurchase($user->id, $clientId, $branchId, $currentSupplierId);
        $batch = $this->createBatch($clientId, $branchId, $productId, $currentSupplierId, 12, 12, 0);
        $this->linkBatchToPurchase($user->id, $clientId, $branchId, $productId, $purchase->id, $batch->id, 12);

        $response = $this->actingAs($user)->put(route('purchases.update', $purchase), [
            'invoice_number' => 'INV-UPDATED-001',
            'supplier_id' => $newSupplierId,
            'purchase_date' => '2026-04-19',
            'payment_type' => 'credit',
            'amount_paid' => 10,
            'due_date' => '2026-04-25',
            'notes' => 'Supplier corrected before any sale.',
        ]);

        $response->assertRedirect(route('purchases.show', $purchase));

        $this->assertDatabaseHas('purchases', [
            'id' => $purchase->id,
            'supplier_id' => $newSupplierId,
            'invoice_number' => 'INV-UPDATED-001',
            'payment_type' => 'credit',
        ]);

        $this->assertDatabaseHas('product_batches', [
            'id' => $batch->id,
            'supplier_id' => $newSupplierId,
        ]);
    }

    public function test_purchase_supplier_change_is_blocked_once_linked_stock_is_reserved(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext();
        $currentSupplierId = $this->createSupplier($clientId, 'Supplier A');
        $newSupplierId = $this->createSupplier($clientId, 'Supplier B');
        $productId = $this->createProduct('Ibuprofen');

        $purchase = $this->createPurchase($user->id, $clientId, $branchId, $currentSupplierId);
        $batch = $this->createBatch($clientId, $branchId, $productId, $currentSupplierId, 10, 10, 3);
        $this->linkBatchToPurchase($user->id, $clientId, $branchId, $productId, $purchase->id, $batch->id, 10);
        $this->createPendingSaleForBatch($user->id, $clientId, $branchId, $productId, $batch->id, 3);

        $response = $this->from(route('purchases.edit', $purchase))
            ->actingAs($user)
            ->put(route('purchases.update', $purchase), [
                'invoice_number' => $purchase->invoice_number,
                'supplier_id' => $newSupplierId,
                'purchase_date' => '2026-04-19',
                'payment_type' => 'cash',
                'amount_paid' => 0,
                'due_date' => null,
                'notes' => 'Attempted locked supplier change.',
            ]);

        $response->assertRedirect(route('purchases.edit', $purchase));
        $response->assertSessionHasErrors('supplier_id');

        $this->assertDatabaseHas('purchases', [
            'id' => $purchase->id,
            'supplier_id' => $currentSupplierId,
        ]);

        $this->assertDatabaseHas('product_batches', [
            'id' => $batch->id,
            'supplier_id' => $currentSupplierId,
            'reserved_quantity' => 3,
        ]);
    }

    public function test_purchase_update_rejects_supplier_from_another_client(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext();
        $currentSupplierId = $this->createSupplier($clientId, 'Supplier A');
        $purchase = $this->createPurchase($user->id, $clientId, $branchId, $currentSupplierId);

        $foreignClientId = $this->createClient('Foreign Pharmacy');
        $foreignSupplierId = $this->createSupplier($foreignClientId, 'Foreign Supplier');

        $response = $this->from(route('purchases.edit', $purchase))
            ->actingAs($user)
            ->put(route('purchases.update', $purchase), [
                'invoice_number' => $purchase->invoice_number,
                'supplier_id' => $foreignSupplierId,
                'purchase_date' => '2026-04-19',
                'payment_type' => 'cash',
                'amount_paid' => 0,
                'due_date' => null,
                'notes' => 'Cross-client supplier attempt.',
            ]);

        $response->assertRedirect(route('purchases.edit', $purchase));
        $response->assertSessionHasErrors('supplier_id');

        $this->assertDatabaseHas('purchases', [
            'id' => $purchase->id,
            'supplier_id' => $currentSupplierId,
        ]);
    }

    public function test_purchase_index_and_details_show_the_invoice_entrant(): void
    {
        [$viewer, $clientId, $branchId] = $this->createUserContext();
        $entrant = User::factory()->create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'is_active' => true,
            'name' => 'Invoice Entrant',
        ]);

        $supplierId = $this->createSupplier($clientId, 'Supplier A');
        $purchase = $this->createPurchase($entrant->id, $clientId, $branchId, $supplierId);

        $this->actingAs($viewer)
            ->get(route('purchases.index'))
            ->assertOk()
            ->assertSee('Invoice Entrant');

        $this->actingAs($viewer)
            ->get(route('purchases.show', $purchase))
            ->assertOk()
            ->assertSee('Invoice Entrant');
    }

    private function createUserContext(): array
    {
        $clientId = $this->createClient('KimRx Test Client');
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

    private function createProduct(string $name): int
    {
        return DB::table('products')->insertGetId([
            'name' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createPurchase(int $userId, int $clientId, int $branchId, int $supplierId): Purchase
    {
        return Purchase::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'supplier_id' => $supplierId,
            'invoice_number' => 'INV-TEST-001',
            'purchase_date' => '2026-04-18',
            'subtotal' => 120,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 120,
            'amount_paid' => 0,
            'balance_due' => 120,
            'payment_type' => 'cash',
            'payment_status' => 'pending',
            'due_date' => null,
            'invoice_status' => 'fully_received',
            'notes' => 'Seeded for purchase update tests.',
            'created_by' => $userId,
            'is_active' => true,
        ]);
    }

    private function createBatch(
        int $clientId,
        int $branchId,
        int $productId,
        int $supplierId,
        float $quantityReceived,
        float $quantityAvailable,
        float $reservedQuantity
    ): ProductBatch {
        return ProductBatch::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'product_id' => $productId,
            'supplier_id' => $supplierId,
            'batch_number' => 'BATCH-' . fake()->unique()->numerify('###'),
            'expiry_date' => '2027-01-01',
            'purchase_price' => 10,
            'retail_price' => 15,
            'wholesale_price' => 12,
            'quantity_received' => $quantityReceived,
            'quantity_available' => $quantityAvailable,
            'reserved_quantity' => $reservedQuantity,
            'is_active' => true,
        ]);
    }

    private function linkBatchToPurchase(
        int $userId,
        int $clientId,
        int $branchId,
        int $productId,
        int $purchaseId,
        int $batchId,
        float $quantityIn
    ): void {
        StockMovement::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'product_id' => $productId,
            'product_batch_id' => $batchId,
            'movement_type' => 'purchase_in',
            'reference_type' => 'purchase',
            'reference_id' => $purchaseId,
            'quantity_in' => $quantityIn,
            'quantity_out' => 0,
            'balance_after' => $quantityIn,
            'note' => 'Linked to purchase test fixture.',
            'created_by' => $userId,
        ]);
    }

    private function createPendingSaleForBatch(
        int $userId,
        int $clientId,
        int $branchId,
        int $productId,
        int $batchId,
        float $quantity
    ): void {
        $saleId = DB::table('sales')->insertGetId([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'served_by' => $userId,
            'invoice_number' => 'PSALE-' . fake()->unique()->numerify('###'),
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
            'notes' => 'Pending sale reserving stock for purchase update test.',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('sale_items')->insert([
            'sale_id' => $saleId,
            'product_id' => $productId,
            'product_batch_id' => $batchId,
            'quantity' => $quantity,
            'purchase_price' => 10,
            'unit_price' => 15,
            'discount_amount' => 0,
            'total_amount' => $quantity * 15,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
