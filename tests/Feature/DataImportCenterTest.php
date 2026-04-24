<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Client;
use App\Models\ClientSetting;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Unit;
use App\Models\User;
use App\Support\AccessControlBootstrapper;
use App\Support\Accounting\AccountingLedgerService;
use App\Support\ClientFeatureAccess;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DataImportCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_preview_and_import_medicines_from_template(): void
    {
        Storage::fake('local');

        [$user, $client, $branch] = $this->createUserContext('Admin');

        $csv = implode("\n", [
            'name,strength,barcode,category_name,unit_name,purchase_price,retail_price,wholesale_price,description,track_batch,track_expiry,expiry_alert_days,is_active',
            'Paracetamol,500mg,PARA-500,Tablets,Box,1000,1500,1400,Pain relief,yes,yes,90,yes',
        ]);

        $file = UploadedFile::fake()->createWithContent('products.csv', $csv);

        $this->actingAs($user)
            ->post(route('admin.imports.preview'), [
                'dataset' => 'products',
                'import_file' => $file,
            ])
            ->assertRedirect(route('admin.imports.index'))
            ->assertSessionHas('data_import.preview_path');

        $this->actingAs($user)
            ->get(route('admin.imports.index'))
            ->assertOk()
            ->assertSee('Preview: Medicines')
            ->assertSee('Paracetamol');

        $this->actingAs($user)
            ->post(route('admin.imports.store'))
            ->assertRedirect(route('admin.imports.index'));

        $this->assertDatabaseHas('categories', [
            'client_id' => $client->id,
            'name' => 'Tablets',
        ]);

        $this->assertDatabaseHas('units', [
            'client_id' => $client->id,
            'name' => 'Box',
        ]);

        $this->assertDatabaseHas('products', [
            'client_id' => $client->id,
            'branch_id' => $branch->id,
            'name' => 'Paracetamol',
            'barcode' => 'PARA-500',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'client_id' => $client->id,
            'branch_id' => $branch->id,
            'event_key' => 'data_import.products_imported',
            'module' => 'Administration',
        ]);
    }

    public function test_admin_can_import_opening_stock_and_record_stock_movement(): void
    {
        Storage::fake('local');

        [$user, $client, $branch] = $this->createUserContext('Admin');

        $category = Category::query()->create([
            'client_id' => $client->id,
            'name' => 'Tablets',
            'is_active' => true,
        ]);

        $unit = Unit::query()->create([
            'client_id' => $client->id,
            'name' => 'Box',
            'is_active' => true,
        ]);

        $product = Product::query()->create([
            'client_id' => $client->id,
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'name' => 'Paracetamol',
            'strength' => '500mg',
            'barcode' => 'PARA-500',
            'purchase_price' => 1000,
            'retail_price' => 1500,
            'wholesale_price' => 1400,
            'track_batch' => true,
            'track_expiry' => true,
            'expiry_alert_days' => 90,
            'is_active' => true,
        ]);

        $csv = implode("\n", [
            'product_name,strength,barcode,batch_number,expiry_date,quantity,purchase_price,retail_price,wholesale_price,supplier_name,is_active',
            'Paracetamol,500mg,PARA-500,OPEN-001,2027-12-31,120,1000,1500,1400,Cipla Distributor,yes',
        ]);

        $file = UploadedFile::fake()->createWithContent('opening-stock.csv', $csv);

        $this->actingAs($user)
            ->post(route('admin.imports.preview'), [
                'dataset' => 'opening_stock',
                'import_file' => $file,
            ])
            ->assertRedirect(route('admin.imports.index'))
            ->assertSessionHas('data_import.preview_path');

        $this->actingAs($user)
            ->post(route('admin.imports.store'))
            ->assertRedirect(route('admin.imports.index'));

        $this->assertDatabaseHas('product_batches', [
            'client_id' => $client->id,
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'batch_number' => 'OPEN-001',
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'client_id' => $client->id,
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'movement_type' => 'import_opening_in',
            'reference_type' => 'data_import',
        ]);

        $this->assertDatabaseHas('suppliers', [
            'client_id' => $client->id,
            'name' => 'Cipla Distributor',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'client_id' => $client->id,
            'branch_id' => $branch->id,
            'event_key' => 'data_import.opening_stock_imported',
        ]);
    }

    public function test_admin_can_import_opening_receivables_collect_them_and_keep_live_sales_clean(): void
    {
        Storage::fake('local');

        [$user, $client, $branch] = $this->createUserContext('Admin');

        $csv = implode("\n", [
            'invoice_number,invoice_date,customer_name,customer_phone,customer_email,sale_channel,opening_balance_amount,notes,is_active',
            'LEG-INV-001,2026-03-31,VIP Family,0772000000,vipfamily@example.com,retail,350000,Legacy receivable balance,yes',
        ]);

        $file = UploadedFile::fake()->createWithContent('opening-receivables.csv', $csv);

        $this->actingAs($user)
            ->post(route('admin.imports.preview'), [
                'dataset' => 'opening_receivables',
                'import_file' => $file,
            ])
            ->assertRedirect(route('admin.imports.index'))
            ->assertSessionHas('data_import.preview_path');

        $this->actingAs($user)
            ->post(route('admin.imports.store'))
            ->assertRedirect(route('admin.imports.index'));

        $sale = Sale::query()
            ->where('client_id', $client->id)
            ->where('branch_id', $branch->id)
            ->where('invoice_number', 'LEG-INV-001')
            ->firstOrFail();

        $this->assertSame(Sale::SOURCE_OPENING_BALANCE_IMPORT, $sale->source);
        $this->assertSame('approved', $sale->status);
        $this->assertEquals(350000.0, (float) $sale->total_amount);
        $this->assertEquals(350000.0, (float) $sale->balance_due);

        $this->actingAs($user)
            ->get(route('customers.receivables'))
            ->assertOk()
            ->assertSee('LEG-INV-001')
            ->assertSee('VIP Family');

        $this->actingAs($user)
            ->get(route('sales.approved'))
            ->assertOk()
            ->assertDontSee('LEG-INV-001');

        $this->actingAs($user)
            ->post(route('customers.collections.store', $sale), [
                'payment_method' => 'petty_cash',
                'amount' => 50000,
                'payment_date' => '2026-04-02',
                'reference_number' => 'RCV-001',
                'notes' => 'First opening balance collection',
            ])
            ->assertRedirect();

        $sale->refresh();

        $this->assertEquals(50000.0, (float) $sale->amount_received);
        $this->assertEquals(50000.0, (float) $sale->amount_paid);
        $this->assertEquals(300000.0, (float) $sale->balance_due);

        $ledger = app(AccountingLedgerService::class);
        $profitAndLoss = $ledger->profitAndLoss(
            $user,
            Carbon::parse('2026-03-01', config('app.timezone')),
            Carbon::parse('2026-04-30', config('app.timezone'))
        );
        $balanceSheet = $ledger->balanceSheet(
            $user,
            Carbon::parse('2026-04-30', config('app.timezone'))->endOfDay()
        );

        $this->assertEquals(0.0, (float) $profitAndLoss['salesRevenue']);
        $this->assertEquals(0.0, (float) $profitAndLoss['netProfit']);
        $this->assertEquals(350000.0, (float) $balanceSheet['totalAssets']);
        $this->assertEquals(0.0, (float) $balanceSheet['totalLiabilities']);

        $this->assertDatabaseHas('audit_logs', [
            'client_id' => $client->id,
            'branch_id' => $branch->id,
            'event_key' => 'data_import.opening_receivables_imported',
        ]);
    }

    public function test_admin_can_import_opening_payables_pay_them_and_keep_live_purchases_clean(): void
    {
        Storage::fake('local');

        [$user, $client, $branch] = $this->createUserContext('Admin');

        $csv = implode("\n", [
            'invoice_number,purchase_date,supplier_name,supplier_phone,supplier_email,opening_balance_amount,due_date,notes,is_active',
            'LEG-SUP-001,2026-03-31,Cipla Distributor,0700000000,supplies@example.com,480000,2026-04-15,Legacy supplier balance,yes',
        ]);

        $file = UploadedFile::fake()->createWithContent('opening-payables.csv', $csv);

        $this->actingAs($user)
            ->post(route('admin.imports.preview'), [
                'dataset' => 'opening_payables',
                'import_file' => $file,
            ])
            ->assertRedirect(route('admin.imports.index'))
            ->assertSessionHas('data_import.preview_path');

        $this->actingAs($user)
            ->post(route('admin.imports.store'))
            ->assertRedirect(route('admin.imports.index'));

        $purchase = Purchase::query()
            ->where('client_id', $client->id)
            ->where('branch_id', $branch->id)
            ->where('invoice_number', 'LEG-SUP-001')
            ->firstOrFail();

        $this->assertSame(Purchase::SOURCE_OPENING_BALANCE_IMPORT, $purchase->source);
        $this->assertEquals(480000.0, (float) $purchase->total_amount);
        $this->assertEquals(480000.0, (float) $purchase->balance_due);

        $this->actingAs($user)
            ->get(route('suppliers.payables'))
            ->assertOk()
            ->assertSee('LEG-SUP-001')
            ->assertSee('Cipla Distributor');

        $this->actingAs($user)
            ->get(route('purchases.index'))
            ->assertOk()
            ->assertDontSee('LEG-SUP-001');

        $this->actingAs($user)
            ->post(route('suppliers.payments.store', $purchase), [
                'payment_method' => 'bank',
                'amount' => 80000,
                'payment_date' => '2026-04-03',
                'reference_number' => 'PAY-001',
                'notes' => 'First opening balance supplier payment',
            ])
            ->assertRedirect();

        $purchase->refresh();

        $this->assertEquals(80000.0, (float) $purchase->amount_paid);
        $this->assertEquals(400000.0, (float) $purchase->balance_due);
        $this->assertSame('partial', $purchase->payment_status);

        $ledger = app(AccountingLedgerService::class);
        $profitAndLoss = $ledger->profitAndLoss(
            $user,
            Carbon::parse('2026-03-01', config('app.timezone')),
            Carbon::parse('2026-04-30', config('app.timezone'))
        );
        $balanceSheet = $ledger->balanceSheet(
            $user,
            Carbon::parse('2026-04-30', config('app.timezone'))->endOfDay()
        );

        $this->assertEquals(0.0, (float) $profitAndLoss['costOfSales']);
        $this->assertEquals(0.0, (float) $profitAndLoss['netProfit']);
        $this->assertEquals(400000.0, (float) $balanceSheet['totalLiabilities']);

        $this->assertDatabaseHas('audit_logs', [
            'client_id' => $client->id,
            'branch_id' => $branch->id,
            'event_key' => 'data_import.opening_payables_imported',
        ]);
    }

    public function test_user_without_import_permission_cannot_open_import_center(): void
    {
        [$user] = $this->createUserContext('Cashier');

        $this->actingAs($user)
            ->get(route('admin.imports.index'))
            ->assertForbidden();
    }

    private function createUserContext(string $roleName = 'Admin'): array
    {
        [$client, $branch] = $this->createManagedClient('Import Client');

        if ($roleName === 'Admin') {
            $user = User::factory()->create([
                'client_id' => $client->id,
                'branch_id' => $branch->id,
                'is_active' => true,
            ]);

            app(AccessControlBootstrapper::class)->ensureForUser($user);

            return [$user, $client, $branch];
        }

        $seedAdmin = User::factory()->create([
            'client_id' => $client->id,
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);
        app(AccessControlBootstrapper::class)->ensureForUser($seedAdmin);

        $user = User::factory()->create([
            'client_id' => $client->id,
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);
        app(AccessControlBootstrapper::class)->ensureForUser($user);

        $role = Role::query()
            ->where('client_id', $client->id)
            ->where('name', $roleName)
            ->firstOrFail();

        $user->roles()->sync([$role->id]);

        return [$user, $client, $branch];
    }

    private function createManagedClient(string $name): array
    {
        $clientId = DB::table('clients')->insertGetId([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '', $name)) . '@example.com',
            'business_mode' => 'both',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $branchId = DB::table('branches')->insertGetId([
            'client_id' => $clientId,
            'name' => 'Main Branch',
            'code' => 'MAIN-' . $clientId,
            'business_mode' => 'inherit',
            'is_main' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        ClientSetting::query()->create(array_merge(
            [
                'client_id' => $clientId,
                'business_mode' => 'both',
                'currency_symbol' => 'UGX',
                'tax_label' => 'TIN',
            ],
            ClientFeatureAccess::defaultSettingValues()
        ));

        return [Client::query()->findOrFail($clientId), Branch::query()->findOrFail($branchId)];
    }
}
