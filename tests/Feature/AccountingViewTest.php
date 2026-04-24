<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\ProductBatch;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockAdjustment;
use App\Models\SupplierPayment;
use App\Models\AccountingExpense;
use App\Models\FixedAsset;
use App\Models\User;
use App\Support\AccessControlBootstrapper;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AccountingViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_accounting_overview_and_chart_of_accounts_render_live_structure(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext();
        app(AccessControlBootstrapper::class)->ensureForUser($user);

        $today = Carbon::today(config('app.timezone'))->toDateString();
        $supplierId = $this->createSupplier($clientId, 'Accounting Supplier');
        $productId = $this->createProduct($clientId, $branchId, 'Accounting Product');

        ProductBatch::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'product_id' => $productId,
            'supplier_id' => $supplierId,
            'batch_number' => 'ACCT-001',
            'purchase_price' => 22,
            'retail_price' => 35,
            'wholesale_price' => 30,
            'quantity_received' => 15,
            'quantity_available' => 15,
            'reserved_quantity' => 0,
            'is_active' => true,
        ]);

        Purchase::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'supplier_id' => $supplierId,
            'invoice_number' => 'ACCT-PUR-001',
            'purchase_date' => $today,
            'subtotal' => 330,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 330,
            'amount_paid' => 0,
            'balance_due' => 330,
            'payment_type' => 'credit',
            'payment_status' => 'unpaid',
            'invoice_status' => 'received',
            'created_by' => $user->id,
            'is_active' => true,
        ]);

        $overview = $this->actingAs($user)->get(route('accounting.index', [
            'from' => $today,
            'to' => $today,
        ]));

        $overview->assertOk();
        $overview->assertSee('Accounting');
        $overview->assertSee('Account Categories');
        $overview->assertSee('Assets');
        $overview->assertSee('Liabilities');
        $overview->assertSee('Revenue');

        $chart = $this->actingAs($user)->get(route('accounting.chart'));

        $chart->assertOk();
        $chart->assertSee('Chart Of Accounts');
        $chart->assertSee('Cash on Hand');
        $chart->assertSee('Accounts Receivable');
        $chart->assertSee('Inventory - Drugs');
        $chart->assertSee('Retail Sales Revenue');
    }

    public function test_general_ledger_and_journals_include_live_pharmacy_entries(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext();
        app(AccessControlBootstrapper::class)->ensureForUser($user);

        $today = Carbon::today(config('app.timezone'));
        $customerId = $this->createCustomer($clientId, 'Ledger Customer', 100000, 40);
        $supplierId = $this->createSupplier($clientId, 'Ledger Supplier');
        $productId = $this->createProduct($clientId, $branchId, 'Ledger Product');

        $batch = ProductBatch::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'product_id' => $productId,
            'supplier_id' => $supplierId,
            'batch_number' => 'LED-001',
            'purchase_price' => 20,
            'retail_price' => 35,
            'wholesale_price' => 30,
            'quantity_received' => 20,
            'quantity_available' => 18,
            'reserved_quantity' => 0,
            'is_active' => true,
        ]);

        $purchase = Purchase::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'supplier_id' => $supplierId,
            'invoice_number' => 'LED-PUR-001',
            'purchase_date' => $today->toDateString(),
            'subtotal' => 400,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 400,
            'amount_paid' => 0,
            'balance_due' => 400,
            'payment_type' => 'credit',
            'payment_status' => 'unpaid',
            'invoice_status' => 'received',
            'created_by' => $user->id,
            'is_active' => true,
        ]);

        $sale = Sale::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'customer_id' => $customerId,
            'served_by' => $user->id,
            'invoice_number' => 'LED-SALE-001',
            'receipt_number' => 'LED-RCP-001',
            'sale_type' => 'retail',
            'status' => 'approved',
            'payment_type' => 'credit',
            'payment_method' => 'Cash',
            'subtotal' => 120,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 120,
            'amount_paid' => 40,
            'amount_received' => 40,
            'balance_due' => 80,
            'sale_date' => $today->toDateString(),
            'is_active' => true,
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $productId,
            'product_batch_id' => $batch->id,
            'quantity' => 3,
            'purchase_price' => 20,
            'unit_price' => 40,
            'discount_amount' => 0,
            'total_amount' => 120,
        ]);

        Payment::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'sale_id' => $sale->id,
            'customer_id' => $customerId,
            'received_by' => $user->id,
            'payment_method' => 'Bank',
            'amount' => 25,
            'reference_number' => 'LED-COL-001',
            'payment_date' => $today->format('Y-m-d') . ' 10:00:00',
            'status' => 'received',
            'notes' => 'Ledger collection',
        ]);

        SupplierPayment::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'supplier_id' => $supplierId,
            'purchase_id' => $purchase->id,
            'paid_by' => $user->id,
            'payment_method' => 'Cash',
            'amount' => 100,
            'reference_number' => 'LED-SUP-001',
            'payment_date' => $today->format('Y-m-d') . ' 09:00:00',
            'status' => 'paid',
            'source' => 'manual',
            'notes' => 'Ledger supplier payment',
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
            'note' => 'Damaged units',
            'adjusted_by' => $user->id,
            'adjustment_date' => $today->format('Y-m-d') . ' 11:00:00',
        ]);

        $ledger = $this->actingAs($user)->get(route('accounting.general-ledger', [
            'from' => $today->toDateString(),
            'to' => $today->toDateString(),
            'account' => '11000',
        ]));

        $ledger->assertOk();
        $ledger->assertSee('General Ledger');
        $ledger->assertSee('LED-SALE-001');
        $ledger->assertSee('LED-COL-001');
        $ledger->assertSee('Accounts Receivable');

        $journals = $this->actingAs($user)->get(route('accounting.journals', [
            'from' => $today->toDateString(),
            'to' => $today->toDateString(),
        ]));

        $journals->assertOk();
        $journals->assertSee('Journals');
        $journals->assertSee('LED-PUR-001');
        $journals->assertSee('LED-SALE-001');
        $journals->assertSee('LED-SUP-001');
        $journals->assertSee('ADJ-00001');
    }

    public function test_payment_vouchers_screen_lists_supplier_disbursements(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext();
        app(AccessControlBootstrapper::class)->ensureForUser($user);

        $today = Carbon::today(config('app.timezone'))->toDateString();
        $supplierId = $this->createSupplier($clientId, 'Voucher Supplier');

        $purchase = Purchase::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'supplier_id' => $supplierId,
            'invoice_number' => 'VCH-PUR-001',
            'purchase_date' => $today,
            'subtotal' => 600,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 600,
            'amount_paid' => 0,
            'balance_due' => 600,
            'payment_type' => 'credit',
            'payment_status' => 'unpaid',
            'invoice_status' => 'received',
            'created_by' => $user->id,
            'is_active' => true,
        ]);

        SupplierPayment::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'supplier_id' => $supplierId,
            'purchase_id' => $purchase->id,
            'paid_by' => $user->id,
            'payment_method' => 'Bank',
            'amount' => 150,
            'reference_number' => 'VCH-001',
            'payment_date' => $today . ' 13:00:00',
            'status' => 'paid',
            'source' => 'manual',
            'notes' => 'Voucher payment',
        ]);

        $response = $this->actingAs($user)->get(route('accounting.vouchers', [
            'from' => $today,
            'to' => $today,
        ]));

        $response->assertOk();
        $response->assertSee('Payment Vouchers');
        $response->assertSee('PV-00001');
        $response->assertSee('Voucher Supplier');
        $response->assertSee('VCH-PUR-001');
        $response->assertSee('150.00');
    }

    public function test_expenses_screen_lists_posted_manual_expenses(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext();
        app(AccessControlBootstrapper::class)->ensureForUser($user);

        AccountingExpense::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'account_code' => '50100',
            'expense_date' => Carbon::today(config('app.timezone'))->endOfDay(),
            'amount' => 125000,
            'payment_method' => 'Cash',
            'payee_name' => 'Landlord',
            'reference_number' => 'EXP-001',
            'description' => 'Shop rent for April',
            'entered_by' => $user->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('accounting.expenses.index'));

        $response->assertOk();
        $response->assertSee('Expenses');
        $response->assertSee('Shop rent for April');
        $response->assertSee('Landlord');
        $response->assertSee('125,000.00');
    }

    public function test_fixed_assets_screen_shows_depreciation_values(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext();
        app(AccessControlBootstrapper::class)->ensureForUser($user);

        FixedAsset::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'asset_name' => 'Office Laptop',
            'asset_category' => 'computers_it',
            'asset_code' => 'FA-001',
            'acquisition_date' => Carbon::today(config('app.timezone'))->subMonths(4)->toDateString(),
            'acquisition_cost' => 2400000,
            'salvage_value' => 0,
            'useful_life_months' => 24,
            'payment_method' => 'Bank',
            'vendor_name' => 'Tech Point',
            'reference_number' => 'FA-INV-001',
            'entered_by' => $user->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('accounting.fixed-assets.index', [
            'as_of' => Carbon::today(config('app.timezone'))->toDateString(),
        ]));

        $response->assertOk();
        $response->assertSee('Fixed Assets');
        $response->assertSee('Office Laptop');
        $response->assertSee('Computers, IT Equipment');
        $response->assertSee('2,400,000.00');
        $response->assertSee('400,000.00');
        $response->assertSee('2,000,000.00');
    }

    public function test_general_ledger_includes_manual_expense_and_fixed_asset_entries(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext();
        app(AccessControlBootstrapper::class)->ensureForUser($user);

        $today = Carbon::today(config('app.timezone'));

        AccountingExpense::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'account_code' => '50600',
            'expense_date' => $today->copy()->endOfDay(),
            'amount' => 50000,
            'payment_method' => 'Cash',
            'payee_name' => 'Stationery Hub',
            'reference_number' => 'EXP-GL-001',
            'description' => 'Office stationery',
            'entered_by' => $user->id,
            'is_active' => true,
        ]);

        FixedAsset::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'asset_name' => 'Pharmacy Shelves',
            'asset_category' => 'furniture_fixtures',
            'asset_code' => 'FA-GL-001',
            'acquisition_date' => $today->copy()->subMonths(2)->toDateString(),
            'acquisition_cost' => 1200000,
            'salvage_value' => 0,
            'useful_life_months' => 24,
            'payment_method' => 'Bank',
            'vendor_name' => 'Furni Works',
            'reference_number' => 'FA-GL-REF',
            'entered_by' => $user->id,
            'is_active' => true,
        ]);

        $ledger = $this->actingAs($user)->get(route('accounting.general-ledger', [
            'from' => $today->copy()->subMonths(2)->startOfMonth()->toDateString(),
            'to' => $today->toDateString(),
            'account' => '50800',
        ]));

        $ledger->assertOk();
        $ledger->assertSee('General Ledger');
        $ledger->assertSee('DEP-1-');

        $journals = $this->actingAs($user)->get(route('accounting.journals', [
            'from' => $today->copy()->subMonths(2)->startOfMonth()->toDateString(),
            'to' => $today->toDateString(),
            'search' => 'Office stationery',
        ]));

        $journals->assertOk();
        $journals->assertSee('Office stationery');
    }

    public function test_trial_balance_screen_balances_live_branch_accounts(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext();
        app(AccessControlBootstrapper::class)->ensureForUser($user);

        $today = $this->seedStatementDataset($user, $clientId, $branchId);

        $response = $this->actingAs($user)->get(route('accounting.trial-balance', [
            'as_of' => $today->toDateString(),
        ]));

        $response->assertOk();
        $response->assertSee('Trial Balance');
        $response->assertSee('Balanced');
        $response->assertSee('Accounts Payable');
        $response->assertSee('Retail Sales Revenue');
        $response->assertSee('520.00');
    }

    public function test_profit_and_loss_and_balance_sheet_render_balanced_financial_statements(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext();
        app(AccessControlBootstrapper::class)->ensureForUser($user);

        $today = $this->seedStatementDataset($user, $clientId, $branchId);

        $profitAndLoss = $this->actingAs($user)->get(route('accounting.profit-loss', [
            'from' => $today->toDateString(),
            'to' => $today->toDateString(),
        ]));

        $profitAndLoss->assertOk();
        $profitAndLoss->assertSee('Profit &amp; Loss', false);
        $profitAndLoss->assertSee('Sales Revenue');
        $profitAndLoss->assertSee('Gross Profit');
        $profitAndLoss->assertSee('30.00');
        $profitAndLoss->assertSee('120.00');
        $profitAndLoss->assertSee('60.00');

        $balanceSheet = $this->actingAs($user)->get(route('accounting.balance-sheet', [
            'as_of' => $today->toDateString(),
        ]));

        $balanceSheet->assertOk();
        $balanceSheet->assertSee('Balance Sheet');
        $balanceSheet->assertSee('Balanced');
        $balanceSheet->assertSee('Current Earnings To Date');
        $balanceSheet->assertSee('430.00');
        $balanceSheet->assertSee('400.00');
        $balanceSheet->assertSee('30.00');
    }

    public function test_profit_and_loss_pdf_download_renders_when_logo_is_configured_without_gd(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext();
        app(AccessControlBootstrapper::class)->ensureForUser($user);

        $today = $this->seedStatementDataset($user, $clientId, $branchId);
        $this->enablePrintLogo($clientId, 'images/kim-rx.png');

        $response = $this->actingAs($user)->get(route('accounting.profit-loss.download', [
            'from' => $today->toDateString(),
            'to' => $today->toDateString(),
            'format' => 'pdf',
        ]));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('.pdf', (string) $response->headers->get('content-disposition'));
    }

    public function test_accounting_feature_toggle_blocks_disabled_screen_even_for_authorized_user(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext();
        app(AccessControlBootstrapper::class)->ensureForUser($user);

        $today = $this->seedStatementDataset($user, $clientId, $branchId);

        DB::table('client_settings')->updateOrInsert(
            ['client_id' => $clientId],
            [
                'business_mode' => 'both',
                'accounts_enabled' => true,
                'accounting_profit_loss_enabled' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->actingAs($user)
            ->get(route('accounting.profit-loss', [
                'from' => $today->toDateString(),
                'to' => $today->toDateString(),
            ]))
            ->assertForbidden();
    }

    private function seedStatementDataset(User $user, int $clientId, int $branchId): Carbon
    {
        $today = Carbon::today(config('app.timezone'));
        $customerId = $this->createCustomer($clientId, 'Statement Customer', 100000, 80);
        $supplierId = $this->createSupplier($clientId, 'Statement Supplier');
        $productId = $this->createProduct($clientId, $branchId, 'Statement Product');

        $batch = ProductBatch::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'product_id' => $productId,
            'supplier_id' => $supplierId,
            'batch_number' => 'STM-001',
            'purchase_price' => 20,
            'retail_price' => 40,
            'wholesale_price' => 35,
            'quantity_received' => 20,
            'quantity_available' => 17,
            'reserved_quantity' => 0,
            'is_active' => true,
        ]);

        Purchase::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'supplier_id' => $supplierId,
            'invoice_number' => 'STM-PUR-001',
            'purchase_date' => $today->toDateString(),
            'subtotal' => 400,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 400,
            'amount_paid' => 0,
            'balance_due' => 400,
            'payment_type' => 'credit',
            'payment_status' => 'unpaid',
            'invoice_status' => 'received',
            'created_by' => $user->id,
            'is_active' => true,
        ]);

        $sale = Sale::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'customer_id' => $customerId,
            'served_by' => $user->id,
            'invoice_number' => 'STM-SALE-001',
            'receipt_number' => 'STM-RCP-001',
            'sale_type' => 'retail',
            'status' => 'approved',
            'payment_type' => 'credit',
            'payment_method' => 'Cash',
            'subtotal' => 120,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 120,
            'amount_paid' => 40,
            'amount_received' => 40,
            'balance_due' => 80,
            'sale_date' => $today->toDateString(),
            'is_active' => true,
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $productId,
            'product_batch_id' => $batch->id,
            'quantity' => 3,
            'purchase_price' => 20,
            'unit_price' => 40,
            'discount_amount' => 0,
            'total_amount' => 120,
        ]);

        AccountingExpense::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'account_code' => '50100',
            'expense_date' => $today->copy()->endOfDay(),
            'amount' => 30,
            'payment_method' => 'Cash',
            'payee_name' => 'Landlord',
            'reference_number' => 'STM-EXP-001',
            'description' => 'Shop rent',
            'entered_by' => $user->id,
            'is_active' => true,
        ]);

        return $today;
    }

    private function createUserContext(): array
    {
        $clientId = $this->createClient('Accounting Client');
        $branchId = $this->createBranch($clientId, 'Main Branch');

        $user = User::factory()->create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'is_active' => true,
        ]);

        return [$user, $clientId, $branchId];
    }

    private function enablePrintLogo(int $clientId, string $logoPath): void
    {
        if (Schema::hasColumn('clients', 'logo')) {
            DB::table('clients')->where('id', $clientId)->update([
                'logo' => $logoPath,
                'updated_at' => now(),
            ]);
        }

        DB::table('client_settings')->updateOrInsert(
            ['client_id' => $clientId],
            [
                'business_mode' => 'both',
                'show_logo_on_print' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
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
            'code' => 'ACC',
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
            $data['purchase_price'] = 20;
        }

        if (Schema::hasColumn('products', 'retail_price')) {
            $data['retail_price'] = 35;
        }

        if (Schema::hasColumn('products', 'wholesale_price')) {
            $data['wholesale_price'] = 30;
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
