<?php

namespace Tests\Feature;

use App\Models\ProductBatch;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PurchaseItemCorrectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_item_correction_preserves_sale_product_and_reassigns_it_to_another_valid_batch(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext();
        $supplierId = $this->createSupplier($clientId, 'Supplier A');
        $actualSyrupSupplierId = $this->createSupplier($clientId, 'Supplier B');
        $oldProductId = $this->createProduct($clientId, $branchId, 'Panadol Syrup');
        $newProductId = $this->createProduct($clientId, $branchId, 'Panadol Tablets');

        $purchase = $this->createPurchase($user->id, $clientId, $branchId, $supplierId);
        $purchaseItem = $this->createPurchaseItem($purchase->id, $oldProductId, [
            'batch_number' => 'PAN-001',
            'ordered_quantity' => 4,
            'received_quantity' => 4,
            'remaining_quantity' => 0,
            'quantity' => 4,
            'unit_cost' => 10,
            'total_cost' => 40,
            'retail_price' => 15,
            'wholesale_price' => 12,
            'line_status' => 'fully_received',
        ]);

        $batch = $this->createBatch($clientId, $branchId, $oldProductId, $supplierId, $purchaseItem->id, [
            'batch_number' => 'PAN-001',
            'quantity_received' => 4,
            'quantity_available' => 3,
            'reserved_quantity' => 0,
            'purchase_price' => 10,
            'retail_price' => 15,
            'wholesale_price' => 12,
        ]);
        $replacementBatch = $this->createBatch($clientId, $branchId, $oldProductId, $actualSyrupSupplierId, null, [
            'batch_number' => 'SYRUP-REAL-001',
            'quantity_received' => 5,
            'quantity_available' => 5,
            'reserved_quantity' => 0,
            'purchase_price' => 9,
            'retail_price' => 16,
            'wholesale_price' => 13,
        ]);

        StockMovement::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'product_id' => $oldProductId,
            'product_batch_id' => $batch->id,
            'movement_type' => 'purchase_in',
            'reference_type' => 'purchase',
            'reference_id' => $purchase->id,
            'quantity_in' => 4,
            'quantity_out' => 0,
            'balance_after' => 4,
            'note' => 'Initial receive.',
            'created_by' => $user->id,
        ]);

        $sale = Sale::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'customer_id' => null,
            'served_by' => $user->id,
            'invoice_number' => 'SALE-001',
            'receipt_number' => 'RCPT-001',
            'sale_type' => 'retail',
            'status' => 'approved',
            'payment_type' => 'cash',
            'payment_method' => 'cash',
            'subtotal' => 20,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 20,
            'amount_paid' => 20,
            'amount_received' => 20,
            'balance_due' => 0,
            'sale_date' => now(),
            'notes' => 'Recorded from wrong product before correction.',
            'is_active' => true,
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $oldProductId,
            'product_batch_id' => $batch->id,
            'quantity' => 1,
            'purchase_price' => 10,
            'unit_price' => 20,
            'discount_amount' => 0,
            'total_amount' => 20,
        ]);

        $response = $this->actingAs($user)->put(
            route('purchases.items.updateCorrection', [$purchase->id, $purchaseItem->id]),
            [
                'product_id' => $newProductId,
                'batch_number' => 'PAN-TABS-001',
                'expiry_date' => '2027-06-01',
                'unit_cost' => 12,
                'retail_price' => 18,
                'wholesale_price' => 14,
                'reason' => 'Product was entered as syrup instead of tablets.',
            ]
        );

        $response->assertRedirect(route('purchases.show', $purchase->id));

        $this->assertDatabaseHas('purchase_items', [
            'id' => $purchaseItem->id,
            'product_id' => $newProductId,
            'batch_number' => 'PAN-TABS-001',
            'unit_cost' => 12,
            'total_cost' => 48,
        ]);

        $this->assertDatabaseHas('product_batches', [
            'id' => $batch->id,
            'purchase_item_id' => $purchaseItem->id,
            'product_id' => $newProductId,
            'batch_number' => 'PAN-TABS-001',
            'quantity_available' => 4,
            'purchase_price' => 12,
            'retail_price' => 18,
            'wholesale_price' => 14,
        ]);
        $this->assertDatabaseHas('product_batches', [
            'id' => $replacementBatch->id,
            'product_id' => $oldProductId,
            'batch_number' => 'SYRUP-REAL-001',
            'quantity_available' => 4,
            'purchase_price' => 9,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'product_batch_id' => $batch->id,
            'product_id' => $newProductId,
        ]);

        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $sale->id,
            'product_batch_id' => $replacementBatch->id,
            'product_id' => $oldProductId,
            'purchase_price' => 9,
        ]);

        $this->assertDatabaseHas('purchase_item_corrections', [
            'purchase_id' => $purchase->id,
            'purchase_item_id' => $purchaseItem->id,
            'old_product_id' => $oldProductId,
            'new_product_id' => $newProductId,
            'old_batch_number' => 'PAN-001',
            'new_batch_number' => 'PAN-TABS-001',
            'affected_batch_count' => 1,
            'affected_sale_count' => 1,
            'affected_sale_item_count' => 1,
        ]);

        $this->assertDatabaseHas('purchases', [
            'id' => $purchase->id,
            'subtotal' => 48,
            'total_amount' => 48,
            'balance_due' => 48,
        ]);
    }

    public function test_purchase_item_correction_blocks_product_change_when_linked_sales_have_no_other_valid_batch(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext();
        $supplierId = $this->createSupplier($clientId, 'Supplier A');
        $oldProductId = $this->createProduct($clientId, $branchId, 'Panadol Syrup');
        $newProductId = $this->createProduct($clientId, $branchId, 'Panadol Tablets');

        $purchase = $this->createPurchase($user->id, $clientId, $branchId, $supplierId);
        $purchaseItem = $this->createPurchaseItem($purchase->id, $oldProductId, [
            'batch_number' => 'PAN-LOCK-001',
            'ordered_quantity' => 3,
            'received_quantity' => 3,
            'remaining_quantity' => 0,
            'quantity' => 3,
            'unit_cost' => 10,
            'total_cost' => 30,
            'retail_price' => 15,
            'wholesale_price' => 12,
            'line_status' => 'fully_received',
        ]);

        $batch = $this->createBatch($clientId, $branchId, $oldProductId, $supplierId, $purchaseItem->id, [
            'batch_number' => 'PAN-LOCK-001',
            'quantity_received' => 3,
            'quantity_available' => 2,
            'reserved_quantity' => 0,
        ]);

        StockMovement::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'product_id' => $oldProductId,
            'product_batch_id' => $batch->id,
            'movement_type' => 'purchase_in',
            'reference_type' => 'purchase',
            'reference_id' => $purchase->id,
            'quantity_in' => 3,
            'quantity_out' => 0,
            'balance_after' => 3,
            'note' => 'Initial receive.',
            'created_by' => $user->id,
        ]);

        $sale = Sale::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'customer_id' => null,
            'served_by' => $user->id,
            'invoice_number' => 'SALE-LOCK-001',
            'receipt_number' => 'RCPT-LOCK-001',
            'sale_type' => 'retail',
            'status' => 'approved',
            'payment_type' => 'cash',
            'payment_method' => 'cash',
            'subtotal' => 20,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 20,
            'amount_paid' => 20,
            'amount_received' => 20,
            'balance_due' => 0,
            'sale_date' => now(),
            'notes' => 'No alternative syrup batch exists.',
            'is_active' => true,
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $oldProductId,
            'product_batch_id' => $batch->id,
            'quantity' => 1,
            'purchase_price' => 10,
            'unit_price' => 20,
            'discount_amount' => 0,
            'total_amount' => 20,
        ]);

        $response = $this->from(route('purchases.items.correct', [$purchase->id, $purchaseItem->id]))
            ->actingAs($user)
            ->put(route('purchases.items.updateCorrection', [$purchase->id, $purchaseItem->id]), [
                'product_id' => $newProductId,
                'batch_number' => 'PAN-TABS-LOCK-001',
                'expiry_date' => '2027-06-01',
                'unit_cost' => 12,
                'retail_price' => 18,
                'wholesale_price' => 14,
                'reason' => 'Trying to correct without another syrup batch.',
            ]);

        $response->assertRedirect(route('purchases.items.correct', [$purchase->id, $purchaseItem->id]));
        $response->assertSessionHasErrors('product_id');

        $this->assertDatabaseHas('purchase_items', [
            'id' => $purchaseItem->id,
            'product_id' => $oldProductId,
            'batch_number' => 'PAN-LOCK-001',
        ]);
        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $sale->id,
            'product_id' => $oldProductId,
            'product_batch_id' => $batch->id,
        ]);
        $this->assertDatabaseMissing('purchase_item_corrections', [
            'purchase_item_id' => $purchaseItem->id,
        ]);
    }

    public function test_purchase_item_correction_rejects_product_from_another_client(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext();
        $supplierId = $this->createSupplier($clientId, 'Supplier A');
        $productId = $this->createProduct($clientId, $branchId, 'Panadol Syrup');
        $purchase = $this->createPurchase($user->id, $clientId, $branchId, $supplierId);
        $purchaseItem = $this->createPurchaseItem($purchase->id, $productId, [
            'batch_number' => 'PAN-001',
            'ordered_quantity' => 2,
            'received_quantity' => 0,
            'remaining_quantity' => 2,
            'quantity' => 0,
            'unit_cost' => 10,
            'total_cost' => 20,
            'retail_price' => 15,
            'wholesale_price' => 12,
            'line_status' => 'pending',
        ]);

        $foreignClientId = $this->createClient('Foreign Pharmacy');
        $foreignBranchId = $this->createBranch($foreignClientId, 'Foreign Branch');
        $foreignProductId = $this->createProduct($foreignClientId, $foreignBranchId, 'Foreign Product');

        $response = $this->from(route('purchases.items.correct', [$purchase->id, $purchaseItem->id]))
            ->actingAs($user)
            ->put(route('purchases.items.updateCorrection', [$purchase->id, $purchaseItem->id]), [
                'product_id' => $foreignProductId,
                'batch_number' => 'PAN-NEW-001',
                'expiry_date' => null,
                'unit_cost' => 11,
                'retail_price' => 16,
                'wholesale_price' => 13,
                'reason' => 'Invalid cross-client correction.',
            ]);

        $response->assertRedirect(route('purchases.items.correct', [$purchase->id, $purchaseItem->id]));
        $response->assertSessionHasErrors('product_id');

        $this->assertDatabaseHas('purchase_items', [
            'id' => $purchaseItem->id,
            'product_id' => $productId,
            'batch_number' => 'PAN-001',
        ]);
    }

    public function test_purchase_item_correction_updates_unreceived_item_without_touching_sales(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext();
        $supplierId = $this->createSupplier($clientId, 'Supplier A');
        $oldProductId = $this->createProduct($clientId, $branchId, 'Panadol Syrup');
        $newProductId = $this->createProduct($clientId, $branchId, 'Panadol Tablets');
        $purchase = $this->createPurchase($user->id, $clientId, $branchId, $supplierId);
        $purchaseItem = $this->createPurchaseItem($purchase->id, $oldProductId, [
            'batch_number' => 'PAN-002',
            'ordered_quantity' => 5,
            'received_quantity' => 0,
            'remaining_quantity' => 5,
            'quantity' => 0,
            'unit_cost' => 8,
            'total_cost' => 40,
            'retail_price' => 12,
            'wholesale_price' => 10,
            'line_status' => 'pending',
        ]);

        $response = $this->actingAs($user)->put(
            route('purchases.items.updateCorrection', [$purchase->id, $purchaseItem->id]),
            [
                'product_id' => $newProductId,
                'batch_number' => 'PAN-TABS-002',
                'expiry_date' => '2027-09-01',
                'unit_cost' => 9,
                'retail_price' => 14,
                'wholesale_price' => 11,
                'reason' => 'Corrected before receiving stock.',
            ]
        );

        $response->assertRedirect(route('purchases.show', $purchase->id));

        $this->assertDatabaseHas('purchase_items', [
            'id' => $purchaseItem->id,
            'product_id' => $newProductId,
            'batch_number' => 'PAN-TABS-002',
            'unit_cost' => 9,
            'total_cost' => 45,
        ]);

        $this->assertDatabaseHas('purchase_item_corrections', [
            'purchase_item_id' => $purchaseItem->id,
            'affected_batch_count' => 0,
            'affected_sale_count' => 0,
            'affected_sale_item_count' => 0,
        ]);

        $this->assertDatabaseHas('purchases', [
            'id' => $purchase->id,
            'subtotal' => 45,
            'total_amount' => 45,
            'invoice_status' => 'draft',
        ]);
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

    private function createProduct(int $clientId, int $branchId, string $name): int
    {
        $data = [
            'name' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('products', 'client_id')) {
            $data['client_id'] = $clientId;
        }

        if (Schema::hasColumn('products', 'branch_id')) {
            $data['branch_id'] = $branchId;
        }

        if (Schema::hasColumn('products', 'purchase_price')) {
            $data['purchase_price'] = 10;
        }

        if (Schema::hasColumn('products', 'retail_price')) {
            $data['retail_price'] = 15;
        }

        if (Schema::hasColumn('products', 'wholesale_price')) {
            $data['wholesale_price'] = 12;
        }

        if (Schema::hasColumn('products', 'track_batch')) {
            $data['track_batch'] = true;
        }

        if (Schema::hasColumn('products', 'track_expiry')) {
            $data['track_expiry'] = true;
        }

        if (Schema::hasColumn('products', 'is_active')) {
            $data['is_active'] = true;
        }

        return DB::table('products')->insertGetId($data);
    }

    private function createPurchase(int $userId, int $clientId, int $branchId, int $supplierId): Purchase
    {
        return Purchase::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'supplier_id' => $supplierId,
            'invoice_number' => 'INV-CORRECT-001',
            'purchase_date' => '2026-04-18',
            'subtotal' => 40,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 40,
            'amount_paid' => 0,
            'balance_due' => 40,
            'payment_type' => 'cash',
            'payment_status' => 'pending',
            'due_date' => null,
            'invoice_status' => 'draft',
            'notes' => 'Seeded for correction tests.',
            'created_by' => $userId,
            'is_active' => true,
        ]);
    }

    private function createPurchaseItem(int $purchaseId, int $productId, array $attributes): PurchaseItem
    {
        return PurchaseItem::create(array_merge([
            'purchase_id' => $purchaseId,
            'product_id' => $productId,
        ], $attributes));
    }

    private function createBatch(
        int $clientId,
        int $branchId,
        int $productId,
        int $supplierId,
        ?int $purchaseItemId,
        array $attributes
    ): ProductBatch {
        return ProductBatch::create(array_merge([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'product_id' => $productId,
            'purchase_item_id' => $purchaseItemId,
            'supplier_id' => $supplierId,
            'batch_number' => 'BATCH-001',
            'expiry_date' => '2027-01-01',
            'purchase_price' => 10,
            'retail_price' => 15,
            'wholesale_price' => 12,
            'quantity_received' => 1,
            'quantity_available' => 1,
            'reserved_quantity' => 0,
            'is_active' => true,
        ], $attributes));
    }
}
