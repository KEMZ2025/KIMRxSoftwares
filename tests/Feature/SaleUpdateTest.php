<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\ProductBatch;
use App\Models\Payment;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Support\AccessControlBootstrapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SaleUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_sale_update_rebuilds_reserved_stock_and_saves_new_lines(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext();
        $supplierId = $this->createSupplier($clientId, 'Supplier A');
        $productAId = $this->createProduct($clientId, $branchId, 'Panadol Syrup');
        $productBId = $this->createProduct($clientId, $branchId, 'Panadol Tablets');

        $batchA = $this->createBatch($clientId, $branchId, $productAId, [
            'supplier_id' => $supplierId,
            'batch_number' => 'PAN-SYR-001',
            'quantity_received' => 10,
            'quantity_available' => 10,
            'reserved_quantity' => 2,
            'purchase_price' => 8,
            'retail_price' => 20,
            'wholesale_price' => 17,
        ]);
        $batchB = $this->createBatch($clientId, $branchId, $productBId, [
            'supplier_id' => $supplierId,
            'batch_number' => 'PAN-TAB-001',
            'quantity_received' => 6,
            'quantity_available' => 6,
            'reserved_quantity' => 0,
            'purchase_price' => 5,
            'retail_price' => 15,
            'wholesale_price' => 12,
        ]);

        $sale = $this->createSale($user->id, $clientId, $branchId, [
            'status' => 'pending',
            'payment_type' => 'cash',
            'payment_method' => null,
            'subtotal' => 40,
            'total_amount' => 40,
            'amount_paid' => 0,
            'amount_received' => 0,
            'balance_due' => 40,
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $productAId,
            'product_batch_id' => $batchA->id,
            'quantity' => 2,
            'purchase_price' => 8,
            'unit_price' => 20,
            'discount_amount' => 0,
            'total_amount' => 40,
        ]);

        $response = $this->actingAs($user)->put(route('sales.update', $sale), [
            'invoice_number' => $sale->invoice_number,
            'sale_date' => '2026-04-19',
            'sale_type' => 'retail',
            'payment_type' => 'cash',
            'customer_id' => null,
            'notes' => 'Updated pending sale.',
            'product_id' => [$productAId, $productBId],
            'product_batch_id' => [$batchA->id, $batchB->id],
            'unit_price' => [20, 15],
            'quantity' => [1, 2],
            'discount_amount' => [0, 0],
        ]);

        $response->assertRedirect(route('sales.show', $sale));

        $this->assertDatabaseHas('sales', [
            'id' => $sale->id,
            'status' => 'pending',
            'subtotal' => 50,
            'total_amount' => 50,
            'amount_received' => 0,
            'balance_due' => 50,
        ]);
        $this->assertDatabaseCount('sale_items', 2);
        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $sale->id,
            'product_id' => $productAId,
            'product_batch_id' => $batchA->id,
            'quantity' => 1,
            'total_amount' => 20,
        ]);
        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $sale->id,
            'product_id' => $productBId,
            'product_batch_id' => $batchB->id,
            'quantity' => 2,
            'total_amount' => 30,
        ]);
        $this->assertDatabaseHas('product_batches', [
            'id' => $batchA->id,
            'reserved_quantity' => 1,
            'quantity_available' => 10,
        ]);
        $this->assertDatabaseHas('product_batches', [
            'id' => $batchB->id,
            'reserved_quantity' => 2,
            'quantity_available' => 6,
        ]);
    }

    public function test_approved_sale_update_saves_added_lines_and_recalculates_balance_from_preserved_payment(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext();
        $supplierId = $this->createSupplier($clientId, 'Supplier B');
        $productAId = $this->createProduct($clientId, $branchId, 'Amoxicillin');
        $productBId = $this->createProduct($clientId, $branchId, 'Ibuprofen');

        $batchA = $this->createBatch($clientId, $branchId, $productAId, [
            'supplier_id' => $supplierId,
            'batch_number' => 'AMOX-001',
            'quantity_received' => 10,
            'quantity_available' => 8,
            'reserved_quantity' => 0,
            'purchase_price' => 10,
            'retail_price' => 20,
            'wholesale_price' => 17,
        ]);
        $batchB = $this->createBatch($clientId, $branchId, $productBId, [
            'supplier_id' => $supplierId,
            'batch_number' => 'IBU-001',
            'quantity_received' => 5,
            'quantity_available' => 5,
            'reserved_quantity' => 0,
            'purchase_price' => 6,
            'retail_price' => 12,
            'wholesale_price' => 10,
        ]);

        $sale = $this->createSale($user->id, $clientId, $branchId, [
            'status' => 'approved',
            'payment_type' => 'cash',
            'payment_method' => 'Cash',
            'receipt_number' => 'RCPT-APPROVED-001',
            'subtotal' => 40,
            'total_amount' => 40,
            'amount_paid' => 40,
            'amount_received' => 40,
            'balance_due' => 0,
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $productAId,
            'product_batch_id' => $batchA->id,
            'quantity' => 2,
            'purchase_price' => 10,
            'unit_price' => 20,
            'discount_amount' => 0,
            'total_amount' => 40,
        ]);

        $response = $this->actingAs($user)->put(route('sales.updateApproved', $sale), [
            'invoice_number' => $sale->invoice_number,
            'sale_date' => '2026-04-19',
            'sale_type' => 'retail',
            'payment_type' => 'cash',
            'customer_id' => null,
            'notes' => 'Approved sale updated with extra line.',
            'product_id' => [$productAId, $productBId],
            'product_batch_id' => [$batchA->id, $batchB->id],
            'unit_price' => [20, 12],
            'quantity' => [1, 3],
            'discount_amount' => [0, 0],
        ]);

        $response->assertRedirect(route('sales.show', $sale));

        $this->assertDatabaseHas('sales', [
            'id' => $sale->id,
            'status' => 'approved',
            'receipt_number' => 'RCPT-APPROVED-001',
            'payment_method' => 'Cash',
            'subtotal' => 56,
            'total_amount' => 56,
            'amount_received' => 40,
            'amount_paid' => 40,
            'balance_due' => 16,
        ]);
        $this->assertDatabaseCount('sale_items', 2);
        $this->assertDatabaseHas('product_batches', [
            'id' => $batchA->id,
            'quantity_available' => 9,
            'reserved_quantity' => 0,
        ]);
        $this->assertDatabaseHas('product_batches', [
            'id' => $batchB->id,
            'quantity_available' => 2,
            'reserved_quantity' => 0,
        ]);
    }

    public function test_approved_sale_update_backfills_legacy_blank_payment_fields(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext();
        $supplierId = $this->createSupplier($clientId, 'Legacy Supplier');
        $productId = $this->createProduct($clientId, $branchId, 'Legacy Sale Drug');

        $batch = $this->createBatch($clientId, $branchId, $productId, [
            'supplier_id' => $supplierId,
            'batch_number' => 'LEGACY-SALE-001',
            'quantity_received' => 10,
            'quantity_available' => 8,
            'reserved_quantity' => 0,
            'purchase_price' => 10,
            'retail_price' => 20,
            'wholesale_price' => 17,
        ]);

        $sale = $this->createSale($user->id, $clientId, $branchId, [
            'status' => 'approved',
            'payment_type' => 'cash',
            'payment_method' => null,
            'receipt_number' => null,
            'subtotal' => 40,
            'total_amount' => 40,
            'amount_paid' => 40,
            'amount_received' => 0,
            'balance_due' => 0,
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $productId,
            'product_batch_id' => $batch->id,
            'quantity' => 2,
            'purchase_price' => 10,
            'unit_price' => 20,
            'discount_amount' => 0,
            'total_amount' => 40,
        ]);

        $response = $this->actingAs($user)->put(route('sales.updateApproved', $sale), [
            'invoice_number' => $sale->invoice_number,
            'sale_date' => '2026-04-20',
            'sale_type' => 'retail',
            'payment_type' => 'cash',
            'customer_id' => null,
            'notes' => 'Legacy payment fields repaired on update.',
            'product_id' => [$productId],
            'product_batch_id' => [$batch->id],
            'unit_price' => [20],
            'quantity' => [2],
            'discount_amount' => [0],
        ]);

        $response->assertRedirect(route('sales.show', $sale));

        $this->assertDatabaseHas('sales', [
            'id' => $sale->id,
            'payment_method' => 'Cash',
            'amount_received' => 40,
            'amount_paid' => 40,
            'balance_due' => 0,
        ]);
    }

    public function test_approved_credit_sale_update_moves_outstanding_balance_to_the_new_customer(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext();
        $supplierId = $this->createSupplier($clientId, 'Supplier C');
        $oldCustomerId = $this->createCustomer($clientId, 'Old Customer', 100, 20);
        $newCustomerId = $this->createCustomer($clientId, 'New Customer', 100, 0);
        $productId = $this->createProduct($clientId, $branchId, 'Cough Syrup');

        $batch = $this->createBatch($clientId, $branchId, $productId, [
            'supplier_id' => $supplierId,
            'batch_number' => 'COUGH-001',
            'quantity_received' => 5,
            'quantity_available' => 4,
            'reserved_quantity' => 0,
            'purchase_price' => 11,
            'retail_price' => 20,
            'wholesale_price' => 18,
        ]);

        $sale = $this->createSale($user->id, $clientId, $branchId, [
            'customer_id' => $oldCustomerId,
            'status' => 'approved',
            'payment_type' => 'credit',
            'payment_method' => 'Cash',
            'receipt_number' => 'RCPT-CREDIT-001',
            'subtotal' => 20,
            'total_amount' => 20,
            'amount_paid' => 0,
            'amount_received' => 0,
            'balance_due' => 20,
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $productId,
            'product_batch_id' => $batch->id,
            'quantity' => 1,
            'purchase_price' => 11,
            'unit_price' => 20,
            'discount_amount' => 0,
            'total_amount' => 20,
        ]);

        $response = $this->actingAs($user)->put(route('sales.updateApproved', $sale), [
            'invoice_number' => $sale->invoice_number,
            'sale_date' => '2026-04-19',
            'sale_type' => 'wholesale',
            'payment_type' => 'credit',
            'customer_id' => $newCustomerId,
            'notes' => 'Credit sale transferred to the correct account.',
            'product_id' => [$productId],
            'product_batch_id' => [$batch->id],
            'unit_price' => [20],
            'quantity' => [2],
            'discount_amount' => [0],
        ]);

        $response->assertRedirect(route('sales.show', $sale));

        $this->assertDatabaseHas('sales', [
            'id' => $sale->id,
            'customer_id' => $newCustomerId,
            'sale_type' => 'wholesale',
            'payment_type' => 'credit',
            'total_amount' => 40,
            'balance_due' => 40,
        ]);
        $this->assertDatabaseHas('customers', [
            'id' => $oldCustomerId,
            'outstanding_balance' => 0,
        ]);
        $this->assertDatabaseHas('customers', [
            'id' => $newCustomerId,
            'outstanding_balance' => 40,
        ]);
        $this->assertDatabaseHas('product_batches', [
            'id' => $batch->id,
            'quantity_available' => 3,
        ]);
    }

    public function test_sale_show_displays_legacy_payment_note_for_repaired_records(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext();

        $sale = $this->createSale($user->id, $clientId, $branchId, [
            'status' => 'approved',
            'payment_type' => 'credit',
            'payment_method' => 'Legacy Unspecified',
            'receipt_number' => 'LEGACY-SHOW-001',
            'subtotal' => 30,
            'total_amount' => 30,
            'amount_paid' => 30,
            'amount_received' => 30,
            'balance_due' => 0,
        ]);

        $response = $this->actingAs($user)->get(route('sales.show', $sale));

        $response->assertOk();
        $response->assertSee('Legacy repaired record');
        $response->assertSee('Legacy Payment Note');
        $response->assertSee('Legacy Unspecified');
    }

    public function test_sales_are_scoped_to_the_signed_in_branch(): void
    {
        [$user] = $this->createUserContext();
        [$foreignUser, $foreignClientId, $foreignBranchId] = $this->createUserContext('Foreign Client', 'Foreign Branch');

        $foreignSale = $this->createSale($foreignUser->id, $foreignClientId, $foreignBranchId, [
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->get(route('sales.show', $foreignSale))
            ->assertNotFound();
    }

    public function test_retail_only_branch_rejects_wholesale_transaction_on_store(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext(
            'Retail Mode Client',
            'Retail Counter',
            'both',
            'retail_only'
        );
        $supplierId = $this->createSupplier($clientId, 'Retail Mode Supplier');
        $customerId = $this->createCustomer($clientId, 'Wholesale Customer', 500, 0);
        $productId = $this->createProduct($clientId, $branchId, 'Retail Locked Drug');

        $batch = $this->createBatch($clientId, $branchId, $productId, [
            'supplier_id' => $supplierId,
            'batch_number' => 'RTL-LOCK-001',
            'quantity_received' => 10,
            'quantity_available' => 10,
            'reserved_quantity' => 0,
            'purchase_price' => 8,
            'retail_price' => 12,
            'wholesale_price' => 10,
        ]);

        $response = $this->from(route('sales.create'))
            ->actingAs($user)
            ->post(route('sales.store'), [
                'invoice_number' => 'WINV-RET-LOCK-001',
                'sale_date' => '2026-04-19',
                'sale_type' => 'wholesale',
                'payment_type' => 'cash',
                'customer_id' => $customerId,
                'notes' => 'Should be blocked by branch mode.',
                'product_id' => [$productId],
                'product_batch_id' => [$batch->id],
                'unit_price' => [10],
                'quantity' => [1],
                'discount_amount' => [0],
            ]);

        $response->assertRedirect(route('sales.create'));
        $response->assertSessionHasErrors('sale_type');

        $this->assertDatabaseMissing('sales', [
            'invoice_number' => 'WINV-RET-LOCK-001',
        ]);
    }

    public function test_wholesale_only_branch_create_screen_locks_sale_type_to_wholesale(): void
    {
        [$user] = $this->createUserContext(
            'Wholesale Mode Client',
            'Wholesale Counter',
            'both',
            'wholesale_only'
        );

        $response = $this->actingAs($user)->get(route('sales.create'));

        $response->assertOk();
        $response->assertSee('Create wholesale sale');
        $response->assertSee('This branch is Wholesale Only. New transactions here stay wholesale.');
        $response->assertSee('option value="wholesale"', false);
        $response->assertDontSee('option value="retail"', false);
    }

    public function test_legacy_wholesale_pending_sale_can_still_be_updated_in_a_retail_only_branch(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext(
            'Legacy Retail Client',
            'Retail Counter',
            'both',
            'retail_only'
        );
        $supplierId = $this->createSupplier($clientId, 'Legacy Supplier');
        $customerId = $this->createCustomer($clientId, 'Legacy Wholesale Customer', 500, 0);
        $productId = $this->createProduct($clientId, $branchId, 'Legacy Drug');

        $batch = $this->createBatch($clientId, $branchId, $productId, [
            'supplier_id' => $supplierId,
            'batch_number' => 'LEGACY-WHS-001',
            'quantity_received' => 10,
            'quantity_available' => 10,
            'reserved_quantity' => 1,
            'purchase_price' => 9,
            'retail_price' => 14,
            'wholesale_price' => 11,
        ]);

        $sale = $this->createSale($user->id, $clientId, $branchId, [
            'customer_id' => $customerId,
            'invoice_number' => 'WINV-LEGACY-001',
            'sale_type' => 'wholesale',
            'status' => 'pending',
            'payment_type' => 'cash',
            'subtotal' => 11,
            'total_amount' => 11,
            'balance_due' => 11,
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $productId,
            'product_batch_id' => $batch->id,
            'quantity' => 1,
            'purchase_price' => 9,
            'unit_price' => 11,
            'discount_amount' => 0,
            'total_amount' => 11,
        ]);

        $response = $this->actingAs($user)->put(route('sales.update', $sale), [
            'invoice_number' => $sale->invoice_number,
            'sale_date' => '2026-04-19',
            'sale_type' => 'wholesale',
            'payment_type' => 'cash',
            'customer_id' => $customerId,
            'notes' => 'Legacy wholesale invoice updated after branch mode changed.',
            'product_id' => [$productId],
            'product_batch_id' => [$batch->id],
            'unit_price' => [11],
            'quantity' => [1],
            'discount_amount' => [0],
        ]);

        $response->assertRedirect(route('sales.show', $sale));

        $this->assertDatabaseHas('sales', [
            'id' => $sale->id,
            'sale_type' => 'wholesale',
            'customer_id' => $customerId,
        ]);
        $this->assertDatabaseHas('product_batches', [
            'id' => $batch->id,
            'reserved_quantity' => 1,
        ]);
    }

    public function test_sale_store_rejects_unit_price_below_batch_purchase_price(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext();
        $supplierId = $this->createSupplier($clientId, 'Price Floor Supplier');
        $productId = $this->createProduct($clientId, $branchId, 'Price Floor Drug');

        $batch = $this->createBatch($clientId, $branchId, $productId, [
            'supplier_id' => $supplierId,
            'batch_number' => 'PRICE-FLOOR-001',
            'quantity_received' => 10,
            'quantity_available' => 10,
            'reserved_quantity' => 0,
            'purchase_price' => 15,
            'retail_price' => 20,
            'wholesale_price' => 18,
        ]);

        $response = $this->from(route('sales.create'))
            ->actingAs($user)
            ->post(route('sales.store'), [
                'invoice_number' => 'RINV-PRICE-FLOOR-001',
                'sale_date' => '2026-04-19',
                'sale_type' => 'retail',
                'payment_type' => 'cash',
                'customer_id' => null,
                'notes' => 'Should be rejected below cost.',
                'product_id' => [$productId],
                'product_batch_id' => [$batch->id],
                'unit_price' => [14],
                'quantity' => [1],
                'discount_amount' => [0],
            ]);

        $response->assertRedirect(route('sales.create'));
        $response->assertSessionHasErrors('unit_price.0');

        $this->assertDatabaseMissing('sales', [
            'invoice_number' => 'RINV-PRICE-FLOOR-001',
        ]);
    }

    public function test_sale_store_rejects_discount_that_pushes_row_below_batch_purchase_price(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext();
        $supplierId = $this->createSupplier($clientId, 'Discount Guard Supplier');
        $productId = $this->createProduct($clientId, $branchId, 'Discount Guard Drug');

        $batch = $this->createBatch($clientId, $branchId, $productId, [
            'supplier_id' => $supplierId,
            'batch_number' => 'DISC-FLOOR-001',
            'quantity_received' => 10,
            'quantity_available' => 10,
            'reserved_quantity' => 0,
            'purchase_price' => 8,
            'retail_price' => 15,
            'wholesale_price' => 12,
        ]);

        $response = $this->from(route('sales.create'))
            ->actingAs($user)
            ->post(route('sales.store'), [
                'invoice_number' => 'RINV-DISC-FLOOR-001',
                'sale_date' => '2026-04-22',
                'sale_type' => 'retail',
                'payment_type' => 'cash',
                'customer_id' => null,
                'notes' => 'Should be rejected because discount creates a loss.',
                'product_id' => [$productId],
                'product_batch_id' => [$batch->id],
                'unit_price' => [15],
                'quantity' => [2],
                'discount_amount' => [15],
            ]);

        $response->assertRedirect(route('sales.create'));
        $response->assertSessionHasErrors('discount_amount.0');

        $this->assertDatabaseMissing('sales', [
            'invoice_number' => 'RINV-DISC-FLOOR-001',
        ]);
    }

    public function test_sale_store_allows_discount_down_to_batch_purchase_price_without_loss(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext();
        $supplierId = $this->createSupplier($clientId, 'Margin Supplier');
        $productId = $this->createProduct($clientId, $branchId, 'Margin Protected Drug');

        $batch = $this->createBatch($clientId, $branchId, $productId, [
            'supplier_id' => $supplierId,
            'batch_number' => 'DISC-FLOOR-OK-001',
            'quantity_received' => 10,
            'quantity_available' => 10,
            'reserved_quantity' => 0,
            'purchase_price' => 8,
            'retail_price' => 15,
            'wholesale_price' => 12,
        ]);

        $response = $this->actingAs($user)->post(route('sales.store'), [
            'invoice_number' => 'RINV-DISC-FLOOR-OK-001',
            'sale_date' => '2026-04-22',
            'sale_type' => 'retail',
            'payment_type' => 'cash',
            'customer_id' => null,
            'notes' => 'Discounted down to batch purchase price with no loss.',
            'product_id' => [$productId],
            'product_batch_id' => [$batch->id],
            'unit_price' => [15],
            'quantity' => [2],
            'discount_amount' => [14],
        ]);

        $response->assertRedirect();

        $sale = Sale::where('invoice_number', 'RINV-DISC-FLOOR-OK-001')->first();

        $this->assertNotNull($sale);
        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $sale->id,
            'product_id' => $productId,
            'product_batch_id' => $batch->id,
            'unit_price' => 15,
            'purchase_price' => 8,
            'discount_amount' => 14,
            'total_amount' => 16,
        ]);
    }

    public function test_user_without_discount_permission_cannot_create_sale_with_discount(): void
    {
        [$admin, $clientId, $branchId] = $this->createUserContext();

        app(AccessControlBootstrapper::class)->ensureForUser($admin);

        $role = Role::create([
            'client_id' => $clientId,
            'name' => 'Sales Without Discount',
            'code' => 'client-' . $clientId . '-sales-without-discount',
            'description' => 'Can create sales but cannot discount them.',
            'is_system_role' => false,
        ]);

        $role->permissions()->sync($this->permissionIds([
            'sales.create',
            'products.view',
            'customers.view',
        ]));

        $user = User::factory()->create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'email' => 'discountlocked@example.com',
            'is_active' => true,
        ]);
        $user->roles()->sync([$role->id]);

        $supplierId = $this->createSupplier($clientId, 'Discount Lock Supplier');
        $productId = $this->createProduct($clientId, $branchId, 'Discount Lock Drug');

        $batch = $this->createBatch($clientId, $branchId, $productId, [
            'supplier_id' => $supplierId,
            'batch_number' => 'DISC-LOCK-001',
            'quantity_received' => 10,
            'quantity_available' => 10,
            'reserved_quantity' => 0,
            'purchase_price' => 8,
            'retail_price' => 15,
            'wholesale_price' => 12,
        ]);

        $response = $this->from(route('sales.create'))
            ->actingAs($user)
            ->post(route('sales.store'), [
                'invoice_number' => 'RINV-DISC-LOCK-001',
                'sale_date' => '2026-04-19',
                'sale_type' => 'retail',
                'payment_type' => 'cash',
                'customer_id' => null,
                'notes' => 'Unauthorized discount attempt.',
                'product_id' => [$productId],
                'product_batch_id' => [$batch->id],
                'unit_price' => [15],
                'quantity' => [1],
                'discount_amount' => [2],
            ]);

        $response->assertRedirect(route('sales.create'));
        $response->assertSessionHasErrors('discount_amount');

        $this->assertDatabaseMissing('sales', [
            'invoice_number' => 'RINV-DISC-LOCK-001',
        ]);
    }

    public function test_user_without_price_override_permission_cannot_create_sale_below_configured_selling_price(): void
    {
        [$admin, $clientId, $branchId] = $this->createUserContext();

        app(AccessControlBootstrapper::class)->ensureForUser($admin);

        $role = Role::create([
            'client_id' => $clientId,
            'name' => 'Sales Without Price Override',
            'code' => 'client-' . $clientId . '-sales-without-price-override',
            'description' => 'Can create sales but cannot lower price below the official selling price.',
            'is_system_role' => false,
        ]);

        $role->permissions()->sync($this->permissionIds([
            'sales.create',
            'products.view',
            'customers.view',
        ]));

        $user = User::factory()->create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'email' => 'pricefloorlocked@example.com',
            'is_active' => true,
        ]);
        $user->roles()->sync([$role->id]);

        $supplierId = $this->createSupplier($clientId, 'Selling Price Guard Supplier');
        $productId = $this->createProduct($clientId, $branchId, 'Selling Price Guard Drug');

        $batch = $this->createBatch($clientId, $branchId, $productId, [
            'supplier_id' => $supplierId,
            'batch_number' => 'SELL-GUARD-001',
            'quantity_received' => 10,
            'quantity_available' => 10,
            'reserved_quantity' => 0,
            'purchase_price' => 8,
            'retail_price' => 15,
            'wholesale_price' => 12,
        ]);

        $response = $this->from(route('sales.create'))
            ->actingAs($user)
            ->post(route('sales.store'), [
                'invoice_number' => 'RINV-SELL-GUARD-001',
                'sale_date' => '2026-04-20',
                'sale_type' => 'retail',
                'payment_type' => 'cash',
                'customer_id' => null,
                'notes' => 'Unauthorized price reduction below retail selling price.',
                'product_id' => [$productId],
                'product_batch_id' => [$batch->id],
                'unit_price' => [10],
                'quantity' => [1],
                'discount_amount' => [0],
            ]);

        $response->assertRedirect(route('sales.create'));
        $response->assertSessionHasErrors('unit_price.0');

        $this->assertDatabaseMissing('sales', [
            'invoice_number' => 'RINV-SELL-GUARD-001',
        ]);
    }

    public function test_user_with_price_override_permission_can_create_sale_down_to_purchase_price(): void
    {
        [$admin, $clientId, $branchId] = $this->createUserContext();

        app(AccessControlBootstrapper::class)->ensureForUser($admin);

        $role = Role::create([
            'client_id' => $clientId,
            'name' => 'Sales With Price Override',
            'code' => 'client-' . $clientId . '-sales-with-price-override',
            'description' => 'Can create sales and lower price down to purchase price.',
            'is_system_role' => false,
        ]);

        $role->permissions()->sync($this->permissionIds([
            'sales.create',
            'sales.price_override',
            'products.view',
            'customers.view',
        ]));

        $user = User::factory()->create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'email' => 'priceoverride@example.com',
            'is_active' => true,
        ]);
        $user->roles()->sync([$role->id]);

        $supplierId = $this->createSupplier($clientId, 'Override Supplier');
        $productId = $this->createProduct($clientId, $branchId, 'Override Drug');

        $batch = $this->createBatch($clientId, $branchId, $productId, [
            'supplier_id' => $supplierId,
            'batch_number' => 'PRICE-OVERRIDE-001',
            'quantity_received' => 10,
            'quantity_available' => 10,
            'reserved_quantity' => 0,
            'purchase_price' => 8,
            'retail_price' => 15,
            'wholesale_price' => 12,
        ]);

        $response = $this->actingAs($user)->post(route('sales.store'), [
            'invoice_number' => 'RINV-PRICE-OVERRIDE-001',
            'sale_date' => '2026-04-20',
            'sale_type' => 'retail',
            'payment_type' => 'cash',
            'customer_id' => null,
            'notes' => 'Authorized price reduction down to purchase price.',
            'product_id' => [$productId],
            'product_batch_id' => [$batch->id],
            'unit_price' => [10],
            'quantity' => [1],
            'discount_amount' => [0],
        ]);

        $response->assertRedirect();

        $sale = Sale::where('invoice_number', 'RINV-PRICE-OVERRIDE-001')->first();

        $this->assertNotNull($sale);
        $this->assertSame('pending', $sale->status);
        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $sale->id,
            'product_id' => $productId,
            'product_batch_id' => $batch->id,
            'unit_price' => 10,
            'purchase_price' => 8,
        ]);
    }

    public function test_proforma_invoice_store_saves_items_without_touching_stock_or_reserved_quantity(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext();
        $supplierId = $this->createSupplier($clientId, 'Proforma Supplier');
        $productId = $this->createProduct($clientId, $branchId, 'Quoted Drug');

        $batch = $this->createBatch($clientId, $branchId, $productId, [
            'supplier_id' => $supplierId,
            'batch_number' => 'PRO-001',
            'quantity_received' => 15,
            'quantity_available' => 15,
            'reserved_quantity' => 2,
            'purchase_price' => 10,
            'retail_price' => 18,
            'wholesale_price' => 16,
        ]);

        $response = $this->actingAs($user)->post(route('sales.proforma.store'), [
            'invoice_number' => 'PINV-00001',
            'sale_date' => '2026-04-19',
            'sale_type' => 'retail',
            'payment_type' => 'cash',
            'customer_id' => null,
            'notes' => 'Quoted for later confirmation.',
            'product_id' => [$productId],
            'product_batch_id' => [$batch->id],
            'unit_price' => [18],
            'quantity' => [3],
            'discount_amount' => [0],
        ]);

        $response->assertRedirect(route('sales.proforma'));

        $this->assertDatabaseHas('sales', [
            'invoice_number' => 'PINV-00001',
            'status' => 'proforma',
            'total_amount' => 54,
            'amount_paid' => 0,
            'amount_received' => 0,
            'balance_due' => 54,
        ]);
        $this->assertDatabaseHas('sale_items', [
            'product_id' => $productId,
            'product_batch_id' => $batch->id,
            'quantity' => 3,
            'total_amount' => 54,
        ]);
        $this->assertDatabaseHas('product_batches', [
            'id' => $batch->id,
            'quantity_available' => 15,
            'reserved_quantity' => 0,
        ]);
    }

    public function test_proforma_invoice_can_be_converted_to_pending_and_reserves_stock_at_that_stage(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext();
        $supplierId = $this->createSupplier($clientId, 'Conversion Supplier');
        $productId = $this->createProduct($clientId, $branchId, 'Convertible Drug');

        $batch = $this->createBatch($clientId, $branchId, $productId, [
            'supplier_id' => $supplierId,
            'batch_number' => 'PRO-CNV-001',
            'quantity_received' => 10,
            'quantity_available' => 10,
            'reserved_quantity' => 1,
            'purchase_price' => 11,
            'retail_price' => 20,
            'wholesale_price' => 18,
        ]);

        $sale = $this->createSale($user->id, $clientId, $branchId, [
            'invoice_number' => 'PINV-CONVERT-001',
            'status' => 'proforma',
            'sale_type' => 'retail',
            'payment_type' => 'cash',
            'subtotal' => 40,
            'total_amount' => 40,
            'balance_due' => 40,
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $productId,
            'product_batch_id' => $batch->id,
            'quantity' => 2,
            'purchase_price' => 11,
            'unit_price' => 20,
            'discount_amount' => 0,
            'total_amount' => 40,
        ]);

        $response = $this->actingAs($user)->post(route('sales.proforma.convert', $sale));

        $response->assertRedirect(route('sales.show', $sale));

        $this->assertDatabaseHas('sales', [
            'id' => $sale->id,
            'status' => 'pending',
            'total_amount' => 40,
            'balance_due' => 40,
            'amount_paid' => 0,
            'amount_received' => 0,
        ]);
        $this->assertDatabaseHas('product_batches', [
            'id' => $batch->id,
            'quantity_available' => 10,
            'reserved_quantity' => 2,
        ]);
    }

    public function test_cancelled_proforma_invoice_can_be_restored_without_any_stock_change(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext();
        $supplierId = $this->createSupplier($clientId, 'Restore Proforma Supplier');
        $productId = $this->createProduct($clientId, $branchId, 'Restorable Quotation');

        $batch = $this->createBatch($clientId, $branchId, $productId, [
            'supplier_id' => $supplierId,
            'batch_number' => 'PRO-REST-001',
            'quantity_received' => 8,
            'quantity_available' => 8,
            'reserved_quantity' => 0,
            'purchase_price' => 9,
            'retail_price' => 15,
            'wholesale_price' => 13,
        ]);

        $sale = $this->createSale($user->id, $clientId, $branchId, [
            'invoice_number' => 'PINV-RESTORE-001',
            'status' => 'proforma',
            'sale_type' => 'retail',
            'payment_type' => 'cash',
            'subtotal' => 30,
            'total_amount' => 30,
            'balance_due' => 30,
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $productId,
            'product_batch_id' => $batch->id,
            'quantity' => 2,
            'purchase_price' => 9,
            'unit_price' => 15,
            'discount_amount' => 0,
            'total_amount' => 30,
        ]);

        $this->actingAs($user)->post(route('sales.cancel', $sale), [
            'cancel_reason' => 'Client requested a revised quote.',
        ])->assertRedirect(route('sales.show', $sale));

        $this->assertDatabaseHas('sales', [
            'id' => $sale->id,
            'status' => 'cancelled',
            'cancelled_from_status' => 'proforma',
            'cancel_reason' => 'Client requested a revised quote.',
        ]);
        $this->assertDatabaseHas('product_batches', [
            'id' => $batch->id,
            'quantity_available' => 8,
            'reserved_quantity' => 0,
        ]);

        $response = $this->actingAs($user)->post(route('sales.restore', $sale), [
            'restore_reason' => 'Quote should remain active.',
        ]);

        $response->assertRedirect(route('sales.show', $sale));

        $this->assertDatabaseHas('sales', [
            'id' => $sale->id,
            'status' => 'proforma',
            'restore_reason' => 'Quote should remain active.',
        ]);
        $this->assertDatabaseHas('product_batches', [
            'id' => $batch->id,
            'quantity_available' => 8,
            'reserved_quantity' => 0,
        ]);
    }

    public function test_all_sales_screen_filters_by_date_range_and_dispenser(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext();
        $otherDispenser = User::factory()->create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'is_active' => true,
            'name' => 'Other Dispenser',
        ]);

        $matchingSale = $this->createSale($user->id, $clientId, $branchId, [
            'invoice_number' => 'SALE-MATCH-001',
            'sale_date' => '2026-04-19',
            'status' => 'approved',
        ]);
        $this->createSale($user->id, $clientId, $branchId, [
            'invoice_number' => 'SALE-OUTSIDE-DATE',
            'sale_date' => '2026-04-10',
            'status' => 'approved',
        ]);
        $this->createSale($otherDispenser->id, $clientId, $branchId, [
            'invoice_number' => 'SALE-OTHER-DISP',
            'sale_date' => '2026-04-19',
            'status' => 'approved',
        ]);

        $response = $this->actingAs($user)->get(route('sales.index', [
            'date_from' => '2026-04-18',
            'date_to' => '2026-04-20',
            'served_by' => $user->id,
        ]));

        $response->assertOk();
        $response->assertSee($matchingSale->invoice_number);
        $response->assertDontSee('SALE-OUTSIDE-DATE');
        $response->assertDontSee('SALE-OTHER-DISP');
        $response->assertSee('Apply Filters');
    }

    public function test_all_sales_screen_keeps_filters_locked_until_reset(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext();
        $otherDispenser = User::factory()->create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'is_active' => true,
            'name' => 'Other Dispenser',
        ]);

        $matchingSale = $this->createSale($user->id, $clientId, $branchId, [
            'invoice_number' => 'LOCKED-ALL-001',
            'sale_date' => '2026-04-19',
            'status' => 'approved',
        ]);
        $this->createSale($otherDispenser->id, $clientId, $branchId, [
            'invoice_number' => 'LOCKED-ALL-OTHER',
            'sale_date' => '2026-04-19',
            'status' => 'approved',
        ]);

        $this->actingAs($user)->get(route('sales.index', [
            'date_from' => '2026-04-18',
            'date_to' => '2026-04-20',
            'served_by' => $user->id,
        ]))->assertOk();

        $response = $this->actingAs($user)->get(route('sales.index'));

        $response->assertOk();
        $response->assertSee($matchingSale->invoice_number);
        $response->assertDontSee('LOCKED-ALL-OTHER');
        $response->assertSee('value="2026-04-18"', false);
        $response->assertSee('value="2026-04-20"', false);
        $response->assertSee('value="' . $user->id . '" selected', false);

        $resetResponse = $this->actingAs($user)->get(route('sales.index', ['clear_filters' => 1]));
        $resetResponse->assertOk();
        $resetResponse->assertDontSee('value="2026-04-18"', false);
        $resetResponse->assertDontSee('value="2026-04-20"', false);
    }

    public function test_approved_sales_screen_filters_by_date_range_and_dispenser(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext();
        $otherDispenser = User::factory()->create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'is_active' => true,
            'name' => 'Night Dispenser',
        ]);

        $matchingSale = $this->createSale($user->id, $clientId, $branchId, [
            'invoice_number' => 'APPROVED-MATCH-001',
            'sale_date' => '2026-04-19',
            'status' => 'approved',
        ]);
        $this->createSale($otherDispenser->id, $clientId, $branchId, [
            'invoice_number' => 'APPROVED-OTHER-DISP',
            'sale_date' => '2026-04-19',
            'status' => 'approved',
        ]);
        $this->createSale($user->id, $clientId, $branchId, [
            'invoice_number' => 'APPROVED-OUTSIDE-DATE',
            'sale_date' => '2026-04-11',
            'status' => 'approved',
        ]);
        $this->createSale($user->id, $clientId, $branchId, [
            'invoice_number' => 'PENDING-SHOULD-NOT-SHOW',
            'sale_date' => '2026-04-19',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($user)->get(route('sales.approved', [
            'date_from' => '2026-04-18',
            'date_to' => '2026-04-20',
            'served_by' => $user->id,
        ]));

        $response->assertOk();
        $response->assertSee($matchingSale->invoice_number);
        $response->assertDontSee('APPROVED-OTHER-DISP');
        $response->assertDontSee('APPROVED-OUTSIDE-DATE');
        $response->assertDontSee('PENDING-SHOULD-NOT-SHOW');
        $response->assertSee('All Dispensers');
    }

    public function test_approved_sales_screen_keeps_filters_locked_until_reset(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext();
        $otherDispenser = User::factory()->create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'is_active' => true,
            'name' => 'Night Dispenser',
        ]);

        $matchingSale = $this->createSale($user->id, $clientId, $branchId, [
            'invoice_number' => 'LOCKED-APPROVED-001',
            'sale_date' => '2026-04-19',
            'status' => 'approved',
        ]);
        $this->createSale($otherDispenser->id, $clientId, $branchId, [
            'invoice_number' => 'LOCKED-APPROVED-OTHER',
            'sale_date' => '2026-04-19',
            'status' => 'approved',
        ]);

        $this->actingAs($user)->get(route('sales.approved', [
            'date_from' => '2026-04-18',
            'date_to' => '2026-04-20',
            'served_by' => $user->id,
        ]))->assertOk();

        $response = $this->actingAs($user)->get(route('sales.approved'));

        $response->assertOk();
        $response->assertSee($matchingSale->invoice_number);
        $response->assertDontSee('LOCKED-APPROVED-OTHER');
        $response->assertSee('value="2026-04-18"', false);
        $response->assertSee('value="2026-04-20"', false);
        $response->assertSee('value="' . $user->id . '" selected', false);

        $resetResponse = $this->actingAs($user)->get(route('sales.approved', ['clear_filters' => 1]));
        $resetResponse->assertOk();
        $resetResponse->assertDontSee('value="2026-04-18"', false);
        $resetResponse->assertDontSee('value="2026-04-20"', false);
    }

    public function test_pending_sale_cancellation_captures_actor_reason_and_releases_reserved_stock(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext();
        $supplierId = $this->createSupplier($clientId, 'Supplier Cancel');
        $productId = $this->createProduct($clientId, $branchId, 'Panadol');

        $batch = $this->createBatch($clientId, $branchId, $productId, [
            'supplier_id' => $supplierId,
            'batch_number' => 'PAN-CAN-001',
            'quantity_received' => 12,
            'quantity_available' => 12,
            'reserved_quantity' => 2,
        ]);

        $sale = $this->createSale($user->id, $clientId, $branchId, [
            'status' => 'pending',
            'total_amount' => 40,
            'balance_due' => 40,
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $productId,
            'product_batch_id' => $batch->id,
            'quantity' => 2,
            'purchase_price' => 10,
            'unit_price' => 20,
            'discount_amount' => 0,
            'total_amount' => 40,
        ]);

        $response = $this->actingAs($user)->post(route('sales.cancel', $sale), [
            'cancel_reason' => 'Wrong invoice entered.',
        ]);

        $response->assertRedirect(route('sales.show', $sale));

        $sale->refresh();
        $batch->refresh();

        $this->assertSame('cancelled', $sale->status);
        $this->assertFalse((bool) $sale->is_active);
        $this->assertSame($user->id, $sale->cancelled_by);
        $this->assertSame('Wrong invoice entered.', $sale->cancel_reason);
        $this->assertSame('pending', $sale->cancelled_from_status);
        $this->assertNotNull($sale->cancelled_at);
        $this->assertSame(12.0, (float) $batch->quantity_available);
        $this->assertSame(0.0, (float) $batch->reserved_quantity);
    }

    public function test_approved_sale_cancellation_is_blocked_when_customer_collections_exist(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext();
        $supplierId = $this->createSupplier($clientId, 'Supplier Collections');
        $customerId = $this->createCustomer($clientId, 'Collections Customer', 100, 15);
        $productId = $this->createProduct($clientId, $branchId, 'Cetrizine');

        $batch = $this->createBatch($clientId, $branchId, $productId, [
            'supplier_id' => $supplierId,
            'batch_number' => 'CET-001',
            'quantity_received' => 5,
            'quantity_available' => 4,
        ]);

        $sale = $this->createSale($user->id, $clientId, $branchId, [
            'customer_id' => $customerId,
            'status' => 'approved',
            'payment_type' => 'credit',
            'payment_method' => 'Bank',
            'receipt_number' => 'RCPT-CAN-BLOCK-001',
            'total_amount' => 20,
            'amount_paid' => 5,
            'amount_received' => 5,
            'balance_due' => 15,
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $productId,
            'product_batch_id' => $batch->id,
            'quantity' => 1,
            'purchase_price' => 10,
            'unit_price' => 20,
            'discount_amount' => 0,
            'total_amount' => 20,
        ]);

        Payment::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'sale_id' => $sale->id,
            'customer_id' => $customerId,
            'received_by' => $user->id,
            'payment_method' => 'bank',
            'amount' => 5,
            'reference_number' => 'COL-001',
            'payment_date' => '2026-04-19 08:00:00',
            'status' => 'received',
            'notes' => 'Initial collection.',
        ]);

        $response = $this->from(route('sales.show', $sale))->actingAs($user)->post(route('sales.cancel', $sale), [
            'cancel_reason' => 'Customer changed mind.',
        ]);

        $response->assertRedirect(route('sales.show', $sale));
        $response->assertSessionHasErrors('cancel_reason');

        $this->assertDatabaseHas('sales', [
            'id' => $sale->id,
            'status' => 'approved',
            'is_active' => true,
        ]);
    }

    public function test_cancelled_approved_sale_can_be_restored_to_its_original_status(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext();
        $supplierId = $this->createSupplier($clientId, 'Supplier Restore');
        $customerId = $this->createCustomer($clientId, 'Restore Customer', 100, 20);
        $productId = $this->createProduct($clientId, $branchId, 'Azithromycin');

        $batch = $this->createBatch($clientId, $branchId, $productId, [
            'supplier_id' => $supplierId,
            'batch_number' => 'AZI-001',
            'quantity_received' => 5,
            'quantity_available' => 4,
            'reserved_quantity' => 0,
        ]);

        $sale = $this->createSale($user->id, $clientId, $branchId, [
            'customer_id' => $customerId,
            'status' => 'approved',
            'payment_type' => 'credit',
            'payment_method' => 'Cash',
            'receipt_number' => 'RCPT-RESTORE-001',
            'total_amount' => 20,
            'amount_paid' => 0,
            'amount_received' => 0,
            'balance_due' => 20,
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $productId,
            'product_batch_id' => $batch->id,
            'quantity' => 1,
            'purchase_price' => 10,
            'unit_price' => 20,
            'discount_amount' => 0,
            'total_amount' => 20,
        ]);

        $this->actingAs($user)->post(route('sales.cancel', $sale), [
            'cancel_reason' => 'Cancelled by mistake.',
        ])->assertRedirect(route('sales.show', $sale));

        $batch->refresh();
        $this->assertSame(5.0, (float) $batch->quantity_available);
        $this->assertDatabaseHas('customers', [
            'id' => $customerId,
            'outstanding_balance' => 0,
        ]);

        $response = $this->actingAs($user)->post(route('sales.restore', $sale), [
            'restore_reason' => 'Cancellation was entered by mistake.',
        ]);

        $response->assertRedirect(route('sales.show', $sale));

        $sale->refresh();
        $batch->refresh();

        $this->assertSame('approved', $sale->status);
        $this->assertTrue((bool) $sale->is_active);
        $this->assertSame($user->id, $sale->restored_by);
        $this->assertSame('Cancellation was entered by mistake.', $sale->restore_reason);
        $this->assertNotNull($sale->restored_at);
        $this->assertSame(4.0, (float) $batch->quantity_available);
        $this->assertDatabaseHas('customers', [
            'id' => $customerId,
            'outstanding_balance' => 20,
        ]);
    }

    public function test_cancelled_sales_screen_lists_cancel_reason_and_actor(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext();

        $sale = $this->createSale($user->id, $clientId, $branchId, [
            'invoice_number' => 'CAN-LIST-001',
            'status' => 'cancelled',
            'is_active' => false,
            'cancelled_by' => $user->id,
            'cancelled_at' => '2026-04-19 12:00:00',
            'cancel_reason' => 'Wrong batch selected.',
            'cancelled_from_status' => 'approved',
        ]);

        $response = $this->actingAs($user)->get(route('sales.cancelled'));

        $response->assertOk();
        $response->assertSee('Cancelled Sales');
        $response->assertSee($sale->invoice_number);
        $response->assertSee('Wrong batch selected.');
        $response->assertSee($user->name);
    }

    private function createUserContext(
        string $clientName = 'KimRx Test Client',
        string $branchName = 'Main Branch',
        string $clientBusinessMode = 'both',
        string $branchBusinessMode = 'inherit'
    ): array
    {
        $clientId = $this->createClient($clientName, $clientBusinessMode);
        $branchId = $this->createBranch($clientId, $branchName, $branchBusinessMode);

        $user = User::factory()->create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'is_active' => true,
        ]);

        app(AccessControlBootstrapper::class)->ensureForUser($user);

        return [$user, $clientId, $branchId];
    }

    private function createClient(string $name, string $businessMode = 'both'): int
    {
        return DB::table('clients')->insertGetId([
            'name' => $name,
            'business_mode' => $businessMode,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createBranch(int $clientId, string $name, string $businessMode = 'inherit'): int
    {
        return DB::table('branches')->insertGetId([
            'client_id' => $clientId,
            'name' => $name,
            'code' => strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $name), 0, 3)) ?: 'BRN',
            'business_mode' => $businessMode,
            'is_main' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createCustomer(int $clientId, string $name, float $creditLimit, float $outstandingBalance): int
    {
        return DB::table('customers')->insertGetId([
            'client_id' => $clientId,
            'name' => $name,
            'credit_limit' => $creditLimit,
            'outstanding_balance' => $outstandingBalance,
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
            $data['retail_price'] = 20;
        }

        if (Schema::hasColumn('products', 'wholesale_price')) {
            $data['wholesale_price'] = 17;
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

    private function createBatch(int $clientId, int $branchId, int $productId, array $attributes): ProductBatch
    {
        return ProductBatch::create(array_merge([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'product_id' => $productId,
            'supplier_id' => $this->createSupplier($clientId, 'Auto Supplier ' . fake()->unique()->numerify('###')),
            'batch_number' => 'BATCH-' . fake()->unique()->numerify('###'),
            'expiry_date' => '2027-01-01',
            'purchase_price' => 10,
            'retail_price' => 20,
            'wholesale_price' => 17,
            'quantity_received' => 1,
            'quantity_available' => 1,
            'reserved_quantity' => 0,
            'is_active' => true,
        ], $attributes));
    }

    private function createSale(int $userId, int $clientId, int $branchId, array $attributes): Sale
    {
        return Sale::create(array_merge([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'customer_id' => null,
            'served_by' => $userId,
            'invoice_number' => 'SALE-' . fake()->unique()->numerify('###'),
            'receipt_number' => null,
            'sale_type' => 'retail',
            'status' => 'pending',
            'payment_type' => 'cash',
            'payment_method' => null,
            'subtotal' => 0,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 0,
            'amount_paid' => 0,
            'amount_received' => 0,
            'balance_due' => 0,
            'sale_date' => '2026-04-19',
            'notes' => 'Seeded for sale update tests.',
            'is_active' => true,
        ], $attributes));
    }

    private function permissionIds(array $permissionKeys): array
    {
        return Permission::query()
            ->whereIn('permission_key', $permissionKeys)
            ->pluck('id')
            ->all();
    }
}
