<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\ProductBatch;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockAdjustment;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Support\AccessControlBootstrapper;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReportsViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_screen_renders_profit_loss_damage_and_balance_sections(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext();
        app(AccessControlBootstrapper::class)->ensureForUser($user);

        $today = Carbon::today(config('app.timezone'))->toDateString();
        $customerId = $this->createCustomer($clientId, 'Birungi Pharmacy', 250000, 80);
        $supplierId = $this->createSupplier($clientId, 'Main Supplier');
        $productId = $this->createProduct($clientId, $branchId, 'Paracetamol Tabs');
        $purchaseMedicineId = $this->createProduct($clientId, $branchId, 'Purchase Report Capsule');
        $outOfStockProductId = $this->createProduct($clientId, $branchId, 'Out Of Stock Syrup');
        $criticalProductId = $this->createProduct($clientId, $branchId, 'Critical Painkiller');

        if (Schema::hasColumn('products', 'expiry_alert_days')) {
            DB::table('products')->where('id', $criticalProductId)->update(['expiry_alert_days' => 30]);
        }

        $batch = ProductBatch::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'product_id' => $productId,
            'supplier_id' => $supplierId,
            'batch_number' => 'RPT-BATCH-001',
            'purchase_price' => 18,
            'retail_price' => 30,
            'wholesale_price' => 25,
            'quantity_received' => 20,
            'quantity_available' => 18,
            'reserved_quantity' => 0,
            'is_active' => true,
        ]);

        ProductBatch::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'product_id' => $outOfStockProductId,
            'supplier_id' => $supplierId,
            'batch_number' => 'RPT-OUT-001',
            'purchase_price' => 12,
            'retail_price' => 22,
            'wholesale_price' => 20,
            'expiry_date' => Carbon::today(config('app.timezone'))->copy()->addMonths(6)->toDateString(),
            'quantity_received' => 10,
            'quantity_available' => 0,
            'reserved_quantity' => 0,
            'is_active' => true,
        ]);

        ProductBatch::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'product_id' => $criticalProductId,
            'supplier_id' => $supplierId,
            'batch_number' => 'RPT-CRIT-001',
            'purchase_price' => 15,
            'retail_price' => 28,
            'wholesale_price' => 24,
            'expiry_date' => Carbon::today(config('app.timezone'))->copy()->addDays(5)->toDateString(),
            'quantity_received' => 8,
            'quantity_available' => 4,
            'reserved_quantity' => 0,
            'is_active' => true,
        ]);

        $sale = Sale::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'customer_id' => $customerId,
            'served_by' => $user->id,
            'invoice_number' => 'RPT-SALE-001',
            'receipt_number' => 'RCP-RPT-001',
            'sale_type' => 'retail',
            'status' => 'approved',
            'payment_type' => 'credit',
            'payment_method' => 'MTN',
            'subtotal' => 150,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 150,
            'amount_paid' => 50,
            'amount_received' => 50,
            'balance_due' => 100,
            'sale_date' => $today,
            'notes' => 'Seeded report sale.',
            'is_active' => true,
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $productId,
            'product_batch_id' => $batch->id,
            'quantity' => 5,
            'purchase_price' => 18,
            'unit_price' => 30,
            'discount_amount' => 0,
            'total_amount' => 150,
        ]);

        $purchase = Purchase::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'supplier_id' => $supplierId,
            'invoice_number' => 'RPT-PUR-001',
            'purchase_date' => $today,
            'subtotal' => 220,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 220,
            'amount_paid' => 80,
            'balance_due' => 140,
            'payment_type' => 'credit',
            'payment_status' => 'partial',
            'invoice_status' => 'received',
            'notes' => 'Seeded report purchase.',
            'created_by' => $user->id,
            'is_active' => true,
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $purchaseMedicineId,
            'batch_number' => 'RPT-PUR-LINE-001',
            'ordered_quantity' => 12,
            'received_quantity' => 12,
            'remaining_quantity' => 0,
            'quantity' => 12,
            'unit_cost' => 18,
            'total_cost' => 216,
            'retail_price' => 28,
            'wholesale_price' => 24,
            'line_status' => 'fully_received',
        ]);

        Payment::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'sale_id' => $sale->id,
            'customer_id' => $customerId,
            'received_by' => $user->id,
            'payment_method' => 'Bank',
            'amount' => 25,
            'reference_number' => 'BANK-RPT-001',
            'payment_date' => $today . ' 10:00:00',
            'status' => 'received',
            'notes' => 'Seeded report collection.',
        ]);

        SupplierPayment::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'supplier_id' => $supplierId,
            'purchase_id' => $purchase->id,
            'paid_by' => $user->id,
            'payment_method' => 'Cash',
            'amount' => 80,
            'reference_number' => 'SUP-RPT-001',
            'payment_date' => $today . ' 09:00:00',
            'status' => 'paid',
            'source' => 'manual',
            'notes' => 'Seeded supplier payment.',
        ]);

        StockAdjustment::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'product_id' => $productId,
            'product_batch_id' => $batch->id,
            'purchase_id' => $purchase->id,
            'direction' => 'decrease',
            'reason' => 'damaged',
            'quantity' => 2,
            'quantity_received_before' => 20,
            'quantity_received_after' => 18,
            'quantity_available_before' => 18,
            'quantity_available_after' => 16,
            'reserved_quantity_before' => 0,
            'reserved_quantity_after' => 0,
            'note' => 'Damaged in store',
            'adjusted_by' => $user->id,
            'adjustment_date' => $today . ' 11:00:00',
        ]);

        $response = $this->actingAs($user)->get(route('reports.index', [
            'period' => 'today',
        ]));

        $response->assertOk();
        $response->assertSee('Profit &amp; Loss Snapshot', false);
        $response->assertSee('Damaged Goods Report');
        $response->assertSee('Legacy Unspecified Payment Review');
        $response->assertSee('Current Outstanding Receivables');
        $response->assertSee('Current Outstanding Payables');
        $response->assertSee('Top Performer');
        $response->assertSee('Top Customers By Channel');
        $response->assertSee('Retail Sales');
        $response->assertSee('Overall Net Profit');
        $response->assertSee($user->name);
        $response->assertSee('Range Sales Detail');
        $response->assertSee('Range Purchase Detail');
        $response->assertSee('Money Impact By Reason');
        $response->assertSee('Purchase Report Capsule x 12');
        $response->assertSee('Out Of Stock Medicines');
        $response->assertSee('Likely Money To Lose');
        $response->assertSee('Paracetamol Tabs');
        $response->assertSee('Out Of Stock Syrup');
        $response->assertSee('Critical Painkiller');
        $response->assertSee('150.00');
        $response->assertSee('60.00');
        $response->assertSee('36.00');
        $response->assertSee('24.00');
        $response->assertSee('75.00');
        $response->assertSee('60.00');
    }

    public function test_reports_screen_filters_selected_window_for_sales_and_damage(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext();
        app(AccessControlBootstrapper::class)->ensureForUser($user);

        $today = Carbon::today(config('app.timezone'));
        $supplierId = $this->createSupplier($clientId, 'Date Filter Supplier');
        $customerId = $this->createCustomer($clientId, 'Date Filter Customer', 50000, 0);
        $currentProductId = $this->createProduct($clientId, $branchId, 'Current Product');
        $oldProductId = $this->createProduct($clientId, $branchId, 'Old Product');

        $currentBatch = ProductBatch::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'product_id' => $currentProductId,
            'supplier_id' => $supplierId,
            'batch_number' => 'CUR-001',
            'purchase_price' => 10,
            'retail_price' => 20,
            'wholesale_price' => 18,
            'quantity_received' => 10,
            'quantity_available' => 9,
            'reserved_quantity' => 0,
            'is_active' => true,
        ]);

        $oldBatch = ProductBatch::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'product_id' => $oldProductId,
            'supplier_id' => $supplierId,
            'batch_number' => 'OLD-001',
            'purchase_price' => 9,
            'retail_price' => 19,
            'wholesale_price' => 16,
            'quantity_received' => 10,
            'quantity_available' => 9,
            'reserved_quantity' => 0,
            'is_active' => true,
        ]);

        $currentSale = Sale::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'customer_id' => $customerId,
            'served_by' => $user->id,
            'invoice_number' => 'CUR-SALE-001',
            'sale_type' => 'retail',
            'status' => 'approved',
            'payment_type' => 'cash',
            'payment_method' => 'Cash',
            'subtotal' => 40,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 40,
            'amount_paid' => 40,
            'amount_received' => 40,
            'balance_due' => 0,
            'sale_date' => $today->toDateString(),
            'is_active' => true,
        ]);

        SaleItem::create([
            'sale_id' => $currentSale->id,
            'product_id' => $currentProductId,
            'product_batch_id' => $currentBatch->id,
            'quantity' => 2,
            'purchase_price' => 10,
            'unit_price' => 20,
            'discount_amount' => 0,
            'total_amount' => 40,
        ]);

        $oldSale = Sale::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'customer_id' => $customerId,
            'served_by' => $user->id,
            'invoice_number' => 'OLD-SALE-001',
            'sale_type' => 'retail',
            'status' => 'approved',
            'payment_type' => 'cash',
            'payment_method' => 'Cash',
            'subtotal' => 38,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 38,
            'amount_paid' => 38,
            'amount_received' => 38,
            'balance_due' => 0,
            'sale_date' => $today->copy()->subDays(7)->toDateString(),
            'is_active' => true,
        ]);

        SaleItem::create([
            'sale_id' => $oldSale->id,
            'product_id' => $oldProductId,
            'product_batch_id' => $oldBatch->id,
            'quantity' => 2,
            'purchase_price' => 9,
            'unit_price' => 19,
            'discount_amount' => 0,
            'total_amount' => 38,
        ]);

        StockAdjustment::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'product_id' => $oldProductId,
            'product_batch_id' => $oldBatch->id,
            'direction' => 'decrease',
            'reason' => 'damaged',
            'quantity' => 1,
            'quantity_received_before' => 10,
            'quantity_received_after' => 9,
            'quantity_available_before' => 9,
            'quantity_available_after' => 8,
            'reserved_quantity_before' => 0,
            'reserved_quantity_after' => 0,
            'note' => 'Old damaged stock',
            'adjusted_by' => $user->id,
            'adjustment_date' => $today->copy()->subDays(7)->format('Y-m-d H:i:s'),
        ]);

        $response = $this->actingAs($user)->get(route('reports.index', [
            'period' => 'custom',
            'date_from' => $today->toDateString(),
            'date_to' => $today->toDateString(),
        ]));

        $response->assertOk();
        $response->assertSee('Current Product');
        $response->assertDontSee('Old Product');
    }

    public function test_reports_screen_lists_legacy_unspecified_sales_separately(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext();
        app(AccessControlBootstrapper::class)->ensureForUser($user);

        $today = Carbon::today(config('app.timezone'))->toDateString();
        $customerId = $this->createCustomer($clientId, 'Legacy Customer', 100000, 0);

        Sale::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'customer_id' => $customerId,
            'served_by' => $user->id,
            'invoice_number' => 'LEGACY-RPT-001',
            'receipt_number' => 'LRCPT-001',
            'sale_type' => 'retail',
            'status' => 'approved',
            'payment_type' => 'credit',
            'payment_method' => 'Legacy Unspecified',
            'subtotal' => 80,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 80,
            'amount_paid' => 80,
            'amount_received' => 80,
            'balance_due' => 0,
            'sale_date' => $today,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('reports.index', [
            'period' => 'today',
        ]));

        $response->assertOk();
        $response->assertSee('Legacy Unspecified Payment Review');
        $response->assertSee('LEGACY-RPT-001');
        $response->assertSee('LRCPT-001');
        $response->assertSee('Legacy Unspecified');
    }

    public function test_reports_section_download_exports_sales_csv(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext();
        app(AccessControlBootstrapper::class)->ensureForUser($user);

        $today = Carbon::today(config('app.timezone'))->toDateString();
        $customerId = $this->createCustomer($clientId, 'CSV Customer', 50000, 0);

        Sale::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'customer_id' => $customerId,
            'served_by' => $user->id,
            'invoice_number' => 'CSV-SALE-001',
            'receipt_number' => 'CSV-RCP-001',
            'sale_type' => 'retail',
            'status' => 'approved',
            'payment_type' => 'cash',
            'payment_method' => 'Cash',
            'subtotal' => 90,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 90,
            'amount_paid' => 90,
            'amount_received' => 90,
            'balance_due' => 0,
            'sale_date' => $today,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('reports.download', [
            'section' => 'sales',
            'format' => 'csv',
            'period' => 'today',
        ]));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('CSV-SALE-001', $response->streamedContent());
        $this->assertStringContainsString('Sales Invoice', $response->streamedContent());
    }

    public function test_reports_print_renders_new_profitability_sections(): void
    {
        [$user] = $this->createUserContext();
        app(AccessControlBootstrapper::class)->ensureForUser($user);

        $response = $this->actingAs($user)->get(route('reports.print', [
            'period' => 'today',
            'autoprint' => 0,
        ]));

        $response->assertOk();
        $response->assertSee('Retail And Wholesale Profit Summary');
        $response->assertSee('Stock Adjustment Money Impact');
        $response->assertSee('Business Mode:');
    }

    public function test_reports_screen_shows_retail_and_wholesale_profitability_with_net_profit_after_expenses(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext();
        app(AccessControlBootstrapper::class)->ensureForUser($user);

        $today = Carbon::today(config('app.timezone'))->toDateString();
        $supplierId = $this->createSupplier($clientId, 'Mode Supplier');
        $retailCustomerId = $this->createCustomer($clientId, 'Retail Giant', 50000, 0);
        $wholesaleCustomerId = $this->createCustomer($clientId, 'Wholesale Giant', 50000, 0);
        $retailProductId = $this->createProduct($clientId, $branchId, 'Retail Product');
        $wholesaleProductId = $this->createProduct($clientId, $branchId, 'Wholesale Product');

        $retailBatch = ProductBatch::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'product_id' => $retailProductId,
            'supplier_id' => $supplierId,
            'batch_number' => 'RET-001',
            'purchase_price' => 10,
            'retail_price' => 25,
            'wholesale_price' => 20,
            'quantity_received' => 20,
            'quantity_available' => 20,
            'reserved_quantity' => 0,
            'is_active' => true,
        ]);

        $wholesaleBatch = ProductBatch::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'product_id' => $wholesaleProductId,
            'supplier_id' => $supplierId,
            'batch_number' => 'WHO-001',
            'purchase_price' => 20,
            'retail_price' => 35,
            'wholesale_price' => 30,
            'quantity_received' => 20,
            'quantity_available' => 20,
            'reserved_quantity' => 0,
            'is_active' => true,
        ]);

        $retailSale = Sale::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'customer_id' => $retailCustomerId,
            'served_by' => $user->id,
            'invoice_number' => 'RET-SALE-001',
            'sale_type' => 'retail',
            'status' => 'approved',
            'payment_type' => 'cash',
            'payment_method' => 'Cash',
            'subtotal' => 100,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 100,
            'amount_paid' => 100,
            'amount_received' => 100,
            'balance_due' => 0,
            'sale_date' => $today,
            'is_active' => true,
        ]);

        SaleItem::create([
            'sale_id' => $retailSale->id,
            'product_id' => $retailProductId,
            'product_batch_id' => $retailBatch->id,
            'quantity' => 4,
            'purchase_price' => 10,
            'unit_price' => 25,
            'discount_amount' => 0,
            'total_amount' => 100,
        ]);

        $wholesaleSale = Sale::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'customer_id' => $wholesaleCustomerId,
            'served_by' => $user->id,
            'invoice_number' => 'WHO-SALE-001',
            'sale_type' => 'wholesale',
            'status' => 'approved',
            'payment_type' => 'cash',
            'payment_method' => 'Bank',
            'subtotal' => 180,
            'discount_amount' => 10,
            'tax_amount' => 0,
            'total_amount' => 180,
            'amount_paid' => 180,
            'amount_received' => 180,
            'balance_due' => 0,
            'sale_date' => $today,
            'is_active' => true,
        ]);

        SaleItem::create([
            'sale_id' => $wholesaleSale->id,
            'product_id' => $wholesaleProductId,
            'product_batch_id' => $wholesaleBatch->id,
            'quantity' => 6,
            'purchase_price' => 20,
            'unit_price' => 30,
            'discount_amount' => 10,
            'total_amount' => 180,
        ]);

        DB::table('accounting_expenses')->insert([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'account_code' => 'EXP-001',
            'expense_date' => $today . ' 12:00:00',
            'amount' => 30,
            'payment_method' => 'Cash',
            'payee_name' => 'Power Utility',
            'description' => 'Electricity',
            'entered_by' => $user->id,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('reports.index', [
            'period' => 'today',
        ]));

        $response->assertOk();
        $response->assertSee('Retail Sales');
        $response->assertSee('Wholesale Sales');
        $response->assertSee('Overall Net Profit');
        $response->assertSee('Retail Customers');
        $response->assertSee('Wholesale Customers');
        $response->assertSee('Retail Giant');
        $response->assertSee('Wholesale Giant');
        $response->assertSee('Operating Expenses');
        $response->assertSee('90.00');
    }

    public function test_reports_screen_hides_retail_channel_when_client_is_wholesale_only(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext();

        DB::table('clients')
            ->where('id', $clientId)
            ->update([
                'business_mode' => 'wholesale_only',
                'updated_at' => now(),
            ]);

        $user = $user->fresh();
        app(AccessControlBootstrapper::class)->ensureForUser($user);

        $today = Carbon::today(config('app.timezone'))->toDateString();
        $supplierId = $this->createSupplier($clientId, 'Wholesale Supplier');
        $customerId = $this->createCustomer($clientId, 'Wholesale Customer', 100000, 0);
        $productId = $this->createProduct($clientId, $branchId, 'Wholesale Only Product');

        $batch = ProductBatch::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'product_id' => $productId,
            'supplier_id' => $supplierId,
            'batch_number' => 'WHO-ONLY-001',
            'purchase_price' => 14,
            'retail_price' => 20,
            'wholesale_price' => 18,
            'quantity_received' => 15,
            'quantity_available' => 15,
            'reserved_quantity' => 0,
            'is_active' => true,
        ]);

        $sale = Sale::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'customer_id' => $customerId,
            'served_by' => $user->id,
            'invoice_number' => 'WHO-ONLY-SALE',
            'sale_type' => 'wholesale',
            'status' => 'approved',
            'payment_type' => 'cash',
            'payment_method' => 'Cash',
            'subtotal' => 90,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 90,
            'amount_paid' => 90,
            'amount_received' => 90,
            'balance_due' => 0,
            'sale_date' => $today,
            'is_active' => true,
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $productId,
            'product_batch_id' => $batch->id,
            'quantity' => 5,
            'purchase_price' => 14,
            'unit_price' => 18,
            'discount_amount' => 0,
            'total_amount' => 90,
        ]);

        $response = $this->actingAs($user)->get(route('reports.index', [
            'period' => 'today',
        ]));

        $response->assertOk();
        $response->assertSee('Wholesale Sales');
        $response->assertDontSee('Retail Sales');
        $response->assertSee('Wholesale Customers');
        $response->assertDontSee('Retail Customers');
    }

    public function test_reports_screen_filters_adjustments_by_reason_and_direction_with_money_impact(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext();
        app(AccessControlBootstrapper::class)->ensureForUser($user);

        $today = Carbon::today(config('app.timezone'))->toDateString();
        $supplierId = $this->createSupplier($clientId, 'Adjustment Supplier');
        $expiredProductId = $this->createProduct($clientId, $branchId, 'Expired Loss Product');
        $foundProductId = $this->createProduct($clientId, $branchId, 'Found Gain Product');

        $expiredBatch = ProductBatch::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'product_id' => $expiredProductId,
            'supplier_id' => $supplierId,
            'batch_number' => 'EXP-001',
            'purchase_price' => 15,
            'retail_price' => 20,
            'wholesale_price' => 18,
            'quantity_received' => 10,
            'quantity_available' => 8,
            'reserved_quantity' => 0,
            'is_active' => true,
        ]);

        $foundBatch = ProductBatch::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'product_id' => $foundProductId,
            'supplier_id' => $supplierId,
            'batch_number' => 'FND-001',
            'purchase_price' => 12,
            'retail_price' => 18,
            'wholesale_price' => 16,
            'quantity_received' => 10,
            'quantity_available' => 8,
            'reserved_quantity' => 0,
            'is_active' => true,
        ]);

        StockAdjustment::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'product_id' => $expiredProductId,
            'product_batch_id' => $expiredBatch->id,
            'direction' => 'decrease',
            'reason' => 'expired',
            'quantity' => 2,
            'quantity_received_before' => 10,
            'quantity_received_after' => 8,
            'quantity_available_before' => 8,
            'quantity_available_after' => 6,
            'reserved_quantity_before' => 0,
            'reserved_quantity_after' => 0,
            'note' => 'Expired stock write-off',
            'adjusted_by' => $user->id,
            'adjustment_date' => $today . ' 09:00:00',
        ]);

        StockAdjustment::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'product_id' => $foundProductId,
            'product_batch_id' => $foundBatch->id,
            'direction' => 'increase',
            'reason' => 'found_stock',
            'quantity' => 1,
            'quantity_received_before' => 10,
            'quantity_received_after' => 11,
            'quantity_available_before' => 8,
            'quantity_available_after' => 9,
            'reserved_quantity_before' => 0,
            'reserved_quantity_after' => 0,
            'note' => 'Found one extra unit',
            'adjusted_by' => $user->id,
            'adjustment_date' => $today . ' 10:00:00',
        ]);

        $response = $this->actingAs($user)->get(route('reports.index', [
            'period' => 'custom',
            'date_from' => $today,
            'date_to' => $today,
            'adjustment_direction' => 'decrease',
            'adjustment_reason' => 'expired',
        ]));

        $response->assertOk();
        $response->assertSee('Money Impact By Reason');
        $response->assertSee('Expired Loss Product');
        $response->assertDontSee('Found Gain Product');
        $response->assertSee('Loss Affecting Profit');
        $response->assertSee('30.00');
    }

    public function test_reports_section_download_exports_purchase_csv_with_medicines_bought(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext();
        app(AccessControlBootstrapper::class)->ensureForUser($user);

        $today = Carbon::today(config('app.timezone'))->toDateString();
        $supplierId = $this->createSupplier($clientId, 'CSV Purchase Supplier');
        $productId = $this->createProduct($clientId, $branchId, 'CSV Purchase Medicine');

        $purchase = Purchase::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'supplier_id' => $supplierId,
            'invoice_number' => 'CSV-PUR-001',
            'purchase_date' => $today,
            'subtotal' => 240,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 240,
            'amount_paid' => 120,
            'balance_due' => 120,
            'payment_type' => 'credit',
            'payment_status' => 'partial',
            'invoice_status' => 'received',
            'created_by' => $user->id,
            'is_active' => true,
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $productId,
            'batch_number' => 'CSV-PUR-LINE-001',
            'ordered_quantity' => 8,
            'received_quantity' => 8,
            'remaining_quantity' => 0,
            'quantity' => 8,
            'unit_cost' => 30,
            'total_cost' => 240,
            'retail_price' => 42,
            'wholesale_price' => 38,
            'line_status' => 'fully_received',
        ]);

        $response = $this->actingAs($user)->get(route('reports.download', [
            'section' => 'purchases',
            'format' => 'csv',
            'period' => 'today',
        ]));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('Purchase Invoice', $response->streamedContent());
        $this->assertStringContainsString('CSV-PUR-001', $response->streamedContent());
        $this->assertStringContainsString('CSV Purchase Medicine x 8', $response->streamedContent());
    }

    public function test_reports_module_toggle_blocks_reports_route(): void
    {
        [$user, $clientId] = $this->createUserContext();
        app(AccessControlBootstrapper::class)->ensureForUser($user);

        DB::table('client_settings')->updateOrInsert(
            ['client_id' => $clientId],
            [
                'business_mode' => 'both',
                'reports_enabled' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->actingAs($user)
            ->get(route('reports.index'))
            ->assertForbidden();
    }

    private function createUserContext(): array
    {
        $clientId = $this->createClient('Reports Client');
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
            'code' => 'RPT',
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
            $data['wholesale_price'] = 18;
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
}
