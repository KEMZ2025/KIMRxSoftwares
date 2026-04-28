<?php

namespace Tests\Feature;

use App\Models\Purchase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PurchasePricingGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_store_rejects_wholesale_price_below_unit_cost(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext();
        $supplierId = $this->createSupplier($clientId, 'Guard Supplier');
        $productId = $this->createProduct($clientId, $branchId, 'Panadol Tablets', 8, 10, 9);

        $response = $this->from(route('purchases.create'))
            ->actingAs($user)
            ->post(route('purchases.store'), [
                'invoice_number' => 'INV-GUARD-001',
                'supplier_id' => $supplierId,
                'purchase_date' => '2026-04-19',
                'payment_type' => 'credit',
                'amount_paid' => 0,
                'due_date' => '2026-04-25',
                'notes' => 'Should be blocked.',
                'product_id' => [$productId],
                'batch_number' => ['BATCH-GUARD-001'],
                'expiry_date' => ['2027-01-01'],
                'ordered_quantity' => [5],
                'received_now_quantity' => [5],
                'unit_cost' => [12],
                'retail_price' => [14],
                'wholesale_price' => [11],
            ]);

        $response->assertRedirect(route('purchases.create'));
        $response->assertSessionHasErrors('wholesale_price.0');

        $this->assertDatabaseMissing('purchases', [
            'invoice_number' => 'INV-GUARD-001',
        ]);
    }

    public function test_purchase_store_allows_inline_price_correction_and_updates_product_prices(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext();
        $supplierId = $this->createSupplier($clientId, 'Corrected Supplier');
        $productId = $this->createProduct($clientId, $branchId, 'Amoxicillin Syrup', 8, 10, 9);

        $response = $this->actingAs($user)->post(route('purchases.store'), [
            'invoice_number' => 'INV-GUARD-002',
            'supplier_id' => $supplierId,
            'purchase_date' => '2026-04-19',
            'payment_type' => 'cash',
            'amount_paid' => 24,
            'due_date' => null,
            'notes' => 'Inline selling prices corrected before save.',
            'product_id' => [$productId],
            'batch_number' => ['BATCH-GUARD-002'],
            'expiry_date' => ['2027-02-01'],
            'ordered_quantity' => [2],
            'received_now_quantity' => [2],
            'unit_cost' => [12],
            'retail_price' => [16],
            'wholesale_price' => [13],
        ]);

        $response->assertRedirect(route('purchases.index'));

        $purchase = Purchase::query()->where('invoice_number', 'INV-GUARD-002')->firstOrFail();

        $this->assertDatabaseHas('purchase_items', [
            'purchase_id' => $purchase->id,
            'product_id' => $productId,
            'unit_cost' => 12,
            'total_cost' => 24,
            'retail_price' => 16,
            'wholesale_price' => 13,
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $productId,
            'purchase_price' => 12,
            'retail_price' => 16,
            'wholesale_price' => 13,
        ]);
    }

    public function test_purchase_store_can_be_entered_by_line_total_instead_of_unit_cost(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext();
        $supplierId = $this->createSupplier($clientId, 'Line Total Supplier');
        $productId = $this->createProduct($clientId, $branchId, 'Cefuroxime Tabs', 8, 10, 9);

        $response = $this->actingAs($user)->post(route('purchases.store'), [
            'invoice_number' => 'INV-GUARD-LINE-001',
            'supplier_id' => $supplierId,
            'purchase_date' => '2026-04-19',
            'payment_type' => 'cash',
            'amount_paid' => 24,
            'due_date' => null,
            'notes' => 'Saved by line total entry mode.',
            'product_id' => [$productId],
            'batch_number' => ['BATCH-GUARD-LINE-001'],
            'expiry_date' => ['2027-02-01'],
            'ordered_quantity' => [2],
            'received_now_quantity' => [2],
            'unit_cost' => [''],
            'line_total' => [24],
            'cost_entry_mode' => ['line_total'],
            'retail_price' => [16],
            'wholesale_price' => [13],
        ]);

        $response->assertRedirect(route('purchases.index'));

        $purchase = Purchase::query()->where('invoice_number', 'INV-GUARD-LINE-001')->firstOrFail();

        $this->assertDatabaseHas('purchase_items', [
            'purchase_id' => $purchase->id,
            'product_id' => $productId,
            'unit_cost' => 12,
            'total_cost' => 24,
            'retail_price' => 16,
            'wholesale_price' => 13,
        ]);
    }

    public function test_purchase_store_rejects_expiry_date_that_is_already_past(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext();
        $supplierId = $this->createSupplier($clientId, 'Expired Supplier');
        $productId = $this->createProduct($clientId, $branchId, 'Expired Guard Drug', 8, 10, 9);

        $response = $this->from(route('purchases.create'))
            ->actingAs($user)
            ->post(route('purchases.store'), [
                'invoice_number' => 'INV-GUARD-EXPIRED-001',
                'supplier_id' => $supplierId,
                'purchase_date' => '2026-04-19',
                'payment_type' => 'cash',
                'amount_paid' => 0,
                'due_date' => null,
                'notes' => 'Expired date should be blocked.',
                'product_id' => [$productId],
                'batch_number' => ['BATCH-EXPIRED-001'],
                'expiry_date' => ['2026-04-18'],
                'ordered_quantity' => [2],
                'received_now_quantity' => [2],
                'unit_cost' => [12],
                'retail_price' => [16],
                'wholesale_price' => [13],
            ]);

        $response->assertRedirect(route('purchases.create'));
        $response->assertSessionHasErrors('expiry_date.0');

        $this->assertDatabaseMissing('purchases', [
            'invoice_number' => 'INV-GUARD-EXPIRED-001',
        ]);
    }

    public function test_purchase_store_requires_expiry_date_for_tracked_products(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext();
        $supplierId = $this->createSupplier($clientId, 'Tracked Expiry Supplier');
        $productId = $this->createProduct($clientId, $branchId, 'Tracked Expiry Drug', 8, 10, 9);

        $response = $this->from(route('purchases.create'))
            ->actingAs($user)
            ->post(route('purchases.store'), [
                'invoice_number' => 'INV-GUARD-EXP-REQ-001',
                'supplier_id' => $supplierId,
                'purchase_date' => '2026-04-19',
                'payment_type' => 'cash',
                'amount_paid' => 0,
                'due_date' => null,
                'notes' => 'Tracked expiry should be required.',
                'product_id' => [$productId],
                'batch_number' => ['BATCH-EXP-REQ-001'],
                'expiry_date' => [''],
                'ordered_quantity' => [2],
                'received_now_quantity' => [2],
                'unit_cost' => [12],
                'retail_price' => [16],
                'wholesale_price' => [13],
            ]);

        $response->assertRedirect(route('purchases.create'));
        $response->assertSessionHasErrors('expiry_date.0');

        $this->assertDatabaseMissing('purchases', [
            'invoice_number' => 'INV-GUARD-EXP-REQ-001',
        ]);
    }

    public function test_add_items_rejects_below_cost_selling_price(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext();
        $supplierId = $this->createSupplier($clientId, 'Invoice Supplier');
        $productId = $this->createProduct($clientId, $branchId, 'Diclofenac Tabs', 7, 9, 8);
        $purchase = $this->createPurchase($user->id, $clientId, $branchId, $supplierId);

        $response = $this->from(route('purchases.add-items', $purchase))
            ->actingAs($user)
            ->post(route('purchases.storeAddedItems', $purchase), [
                'product_id' => [$productId],
                'batch_number' => ['BATCH-GUARD-003'],
                'expiry_date' => ['2027-03-01'],
                'ordered_quantity' => [3],
                'received_now_quantity' => [3],
                'unit_cost' => [11],
                'retail_price' => [15],
                'wholesale_price' => [10],
            ]);

        $response->assertRedirect(route('purchases.add-items', $purchase));
        $response->assertSessionHasErrors('wholesale_price.0');

        $this->assertDatabaseMissing('purchase_items', [
            'purchase_id' => $purchase->id,
            'batch_number' => 'BATCH-GUARD-003',
        ]);
    }

    public function test_add_items_rejects_expiry_date_that_is_already_past(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext();
        $supplierId = $this->createSupplier($clientId, 'Invoice Supplier');
        $productId = $this->createProduct($clientId, $branchId, 'Expiry Added Drug', 7, 9, 8);
        $purchase = $this->createPurchase($user->id, $clientId, $branchId, $supplierId);

        $response = $this->from(route('purchases.add-items', $purchase))
            ->actingAs($user)
            ->post(route('purchases.storeAddedItems', $purchase), [
                'product_id' => [$productId],
                'batch_number' => ['BATCH-GUARD-EXPIRED-ADD'],
                'expiry_date' => ['2026-04-18'],
                'ordered_quantity' => [3],
                'received_now_quantity' => [3],
                'unit_cost' => [11],
                'retail_price' => [15],
                'wholesale_price' => [12],
            ]);

        $response->assertRedirect(route('purchases.add-items', $purchase));
        $response->assertSessionHasErrors('expiry_date.0');

        $this->assertDatabaseMissing('purchase_items', [
            'purchase_id' => $purchase->id,
            'batch_number' => 'BATCH-GUARD-EXPIRED-ADD',
        ]);
    }

    public function test_add_items_requires_expiry_date_for_tracked_products(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext();
        $supplierId = $this->createSupplier($clientId, 'Invoice Supplier');
        $productId = $this->createProduct($clientId, $branchId, 'Tracked Add Expiry Drug', 7, 9, 8);
        $purchase = $this->createPurchase($user->id, $clientId, $branchId, $supplierId);

        $response = $this->from(route('purchases.add-items', $purchase))
            ->actingAs($user)
            ->post(route('purchases.storeAddedItems', $purchase), [
                'product_id' => [$productId],
                'batch_number' => ['BATCH-GUARD-EXP-REQ-ADD'],
                'expiry_date' => [''],
                'ordered_quantity' => [3],
                'received_now_quantity' => [3],
                'unit_cost' => [11],
                'retail_price' => [15],
                'wholesale_price' => [12],
            ]);

        $response->assertRedirect(route('purchases.add-items', $purchase));
        $response->assertSessionHasErrors('expiry_date.0');

        $this->assertDatabaseMissing('purchase_items', [
            'purchase_id' => $purchase->id,
            'batch_number' => 'BATCH-GUARD-EXP-REQ-ADD',
        ]);
    }

    public function test_add_items_can_be_entered_by_line_total_instead_of_unit_cost(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext();
        $supplierId = $this->createSupplier($clientId, 'Invoice Supplier');
        $productId = $this->createProduct($clientId, $branchId, 'Line Total Added Drug', 7, 9, 8);
        $purchase = $this->createPurchase($user->id, $clientId, $branchId, $supplierId);

        $response = $this->actingAs($user)
            ->post(route('purchases.storeAddedItems', $purchase), [
                'product_id' => [$productId],
                'batch_number' => ['BATCH-GUARD-LINE-ADD'],
                'expiry_date' => ['2027-03-01'],
                'ordered_quantity' => [3],
                'received_now_quantity' => [3],
                'unit_cost' => [''],
                'line_total' => [33],
                'cost_entry_mode' => ['line_total'],
                'retail_price' => [15],
                'wholesale_price' => [12],
            ]);

        $response->assertRedirect(route('purchases.show', $purchase));

        $this->assertDatabaseHas('purchase_items', [
            'purchase_id' => $purchase->id,
            'product_id' => $productId,
            'batch_number' => 'BATCH-GUARD-LINE-ADD',
            'unit_cost' => 11,
            'total_cost' => 33,
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

    private function createProduct(
        int $clientId,
        int $branchId,
        string $name,
        float $purchasePrice,
        float $retailPrice,
        float $wholesalePrice
    ): int {
        return DB::table('products')->insertGetId([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'name' => $name,
            'purchase_price' => $purchasePrice,
            'retail_price' => $retailPrice,
            'wholesale_price' => $wholesalePrice,
            'track_batch' => true,
            'track_expiry' => true,
            'expiry_alert_days' => 90,
            'is_active' => true,
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
            'invoice_number' => 'INV-BASE-001',
            'purchase_date' => '2026-04-18',
            'subtotal' => 0,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 0,
            'amount_paid' => 0,
            'balance_due' => 0,
            'payment_type' => 'credit',
            'payment_status' => 'pending',
            'due_date' => null,
            'invoice_status' => 'draft',
            'notes' => 'Seeded for pricing guard tests.',
            'created_by' => $userId,
            'is_active' => true,
        ]);
    }
}
