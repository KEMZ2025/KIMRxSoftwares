<?php

namespace Tests\Feature;

use App\Models\ClientSetting;
use App\Models\Payment;
use App\Models\ProductBatch;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Support\AccessControlBootstrapper;
use App\Support\ClientFeatureAccess;
use App\Support\InventoryExpiryAlerts;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DashboardViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_live_cards_charts_and_method_breakdown(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext();
        app(AccessControlBootstrapper::class)->ensureForUser($user);
        $today = Carbon::today(config('app.timezone'))->toDateString();

        $customerId = $this->createCustomer($clientId, 'Birungi Pharmacy', 150000, 20);
        $supplierId = $this->createSupplier($clientId, 'Main Supplier');
        $productId = $this->createProduct($clientId, $branchId, 'Curamol Caplets');

        $sale = Sale::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'customer_id' => $customerId,
            'served_by' => $user->id,
            'invoice_number' => 'DASH-SALE-001',
            'receipt_number' => 'RCP-DASH-001',
            'sale_type' => 'retail',
            'status' => 'approved',
            'payment_type' => 'cash',
            'payment_method' => 'MTN',
            'subtotal' => 60,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 60,
            'amount_paid' => 40,
            'amount_received' => 40,
            'balance_due' => 20,
            'sale_date' => $today,
            'notes' => 'Dashboard seeded sale.',
            'is_active' => true,
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $productId,
            'product_batch_id' => null,
            'quantity' => 4,
            'purchase_price' => 10,
            'unit_price' => 15,
            'discount_amount' => 0,
            'total_amount' => 60,
        ]);

        Purchase::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'supplier_id' => $supplierId,
            'invoice_number' => 'DASH-PUR-001',
            'purchase_date' => $today,
            'subtotal' => 70,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 70,
            'amount_paid' => 20,
            'balance_due' => 50,
            'payment_type' => 'credit',
            'payment_status' => 'partial',
            'invoice_status' => 'received',
            'notes' => 'Dashboard seeded purchase.',
            'created_by' => $user->id,
            'is_active' => true,
        ]);

        Payment::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'sale_id' => $sale->id,
            'customer_id' => $customerId,
            'received_by' => $user->id,
            'payment_method' => 'Bank',
            'amount' => 15,
            'reference_number' => 'BANK-DASH-001',
            'payment_date' => $today . ' 10:00:00',
            'status' => 'received',
            'notes' => 'Dashboard seeded collection.',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard', [
            'period' => 'today',
        ]));

        $response->assertOk();
        $response->assertSee('Money Received By Method');
        $response->assertSee('Sales vs Purchases Trend');
        $response->assertSee('Fast Moving Drugs');
        $response->assertSee('Recent Money Received');
        $response->assertSee('MTN');
        $response->assertSee('Bank');
        $response->assertSee('Curamol Caplets');
        $response->assertSee('RCP-DASH-001');
    }

    public function test_dashboard_shows_expiry_warning_on_first_request_in_current_reminder_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-22 09:00:00', config('app.timezone')));

        [$user, $clientId, $branchId] = $this->createUserContext();
        app(AccessControlBootstrapper::class)->ensureForUser($user);

        $supplierId = $this->createSupplier($clientId, 'Expiry Warning Supplier');
        $productId = $this->createProduct($clientId, $branchId, 'Soon Expiring Tabs');

        if (Schema::hasColumn('products', 'expiry_alert_days')) {
            DB::table('products')->where('id', $productId)->update(['expiry_alert_days' => 14]);
        }

        ProductBatch::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'product_id' => $productId,
            'supplier_id' => $supplierId,
            'batch_number' => 'EXP-WARN-001',
            'purchase_price' => 10,
            'retail_price' => 16,
            'wholesale_price' => 14,
            'expiry_date' => Carbon::today(config('app.timezone'))->copy()->addDays(3)->toDateString(),
            'quantity_received' => 10,
            'quantity_available' => 6,
            'reserved_quantity' => 0,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Expiry Warning');
        $response->assertSee('Soon Expiring Tabs');
        $response->assertSessionHas('inventory_expiry.last_slot', InventoryExpiryAlerts::currentReminderSlotKey());

        Carbon::setTestNow();
    }

    public function test_dashboard_does_not_repeat_expiry_warning_inside_same_reminder_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-22 09:30:00', config('app.timezone')));

        [$user, $clientId, $branchId] = $this->createUserContext();
        app(AccessControlBootstrapper::class)->ensureForUser($user);

        $supplierId = $this->createSupplier($clientId, 'Expiry Warning Supplier');
        $productId = $this->createProduct($clientId, $branchId, 'Soon Expiring Tabs');

        if (Schema::hasColumn('products', 'expiry_alert_days')) {
            DB::table('products')->where('id', $productId)->update(['expiry_alert_days' => 14]);
        }

        ProductBatch::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'product_id' => $productId,
            'supplier_id' => $supplierId,
            'batch_number' => 'EXP-WARN-002',
            'purchase_price' => 10,
            'retail_price' => 16,
            'wholesale_price' => 14,
            'expiry_date' => Carbon::today(config('app.timezone'))->copy()->addDays(3)->toDateString(),
            'quantity_received' => 10,
            'quantity_available' => 6,
            'reserved_quantity' => 0,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['inventory_expiry.last_slot' => InventoryExpiryAlerts::currentReminderSlotKey()])
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee('Soon Expiring Tabs');

        Carbon::setTestNow();
    }

    public function test_dashboard_does_not_show_expiry_warning_before_first_shift_time(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-22 07:30:00', config('app.timezone')));

        [$user, $clientId, $branchId] = $this->createUserContext();
        app(AccessControlBootstrapper::class)->ensureForUser($user);

        $supplierId = $this->createSupplier($clientId, 'Early Shift Supplier');
        $productId = $this->createProduct($clientId, $branchId, 'Early Shift Tabs');

        if (Schema::hasColumn('products', 'expiry_alert_days')) {
            DB::table('products')->where('id', $productId)->update(['expiry_alert_days' => 14]);
        }

        ProductBatch::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'product_id' => $productId,
            'supplier_id' => $supplierId,
            'batch_number' => 'EXP-WARN-003',
            'purchase_price' => 10,
            'retail_price' => 16,
            'wholesale_price' => 14,
            'expiry_date' => Carbon::today(config('app.timezone'))->copy()->addDays(2)->toDateString(),
            'quantity_received' => 10,
            'quantity_available' => 6,
            'reserved_quantity' => 0,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee('Early Shift Tabs');
        $response->assertSessionMissing('inventory_expiry.last_slot');

        Carbon::setTestNow();
    }

    public function test_dashboard_does_not_show_expiry_warning_when_client_disables_the_feature(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-22 09:00:00', config('app.timezone')));

        [$user, $clientId, $branchId] = $this->createUserContext();
        app(AccessControlBootstrapper::class)->ensureForUser($user);
        $this->setExpiryAlertFeature($clientId, false);

        $supplierId = $this->createSupplier($clientId, 'Silent Supplier');
        $productId = $this->createProduct($clientId, $branchId, 'Silent Expiry Tabs');

        if (Schema::hasColumn('products', 'expiry_alert_days')) {
            DB::table('products')->where('id', $productId)->update(['expiry_alert_days' => 14]);
        }

        ProductBatch::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'product_id' => $productId,
            'supplier_id' => $supplierId,
            'batch_number' => 'EXP-WARN-004',
            'purchase_price' => 10,
            'retail_price' => 16,
            'wholesale_price' => 14,
            'expiry_date' => Carbon::today(config('app.timezone'))->copy()->addDays(2)->toDateString(),
            'quantity_received' => 10,
            'quantity_available' => 6,
            'reserved_quantity' => 0,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee('Silent Expiry Tabs');
        $response->assertSessionMissing(InventoryExpiryAlerts::SESSION_SLOT_KEY);

        Carbon::setTestNow();
    }

    public function test_expiry_alert_endpoint_returns_due_warning_once_per_slot(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-22 13:05:00', config('app.timezone')));

        [$user, $clientId, $branchId] = $this->createUserContext();
        app(AccessControlBootstrapper::class)->ensureForUser($user);

        $supplierId = $this->createSupplier($clientId, 'Shift Supplier');
        $productId = $this->createProduct($clientId, $branchId, 'Shift Reminder Tabs');

        if (Schema::hasColumn('products', 'expiry_alert_days')) {
            DB::table('products')->where('id', $productId)->update(['expiry_alert_days' => 14]);
        }

        ProductBatch::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'product_id' => $productId,
            'supplier_id' => $supplierId,
            'batch_number' => 'EXP-WARN-005',
            'purchase_price' => 10,
            'retail_price' => 16,
            'wholesale_price' => 14,
            'expiry_date' => Carbon::today(config('app.timezone'))->copy()->addDays(2)->toDateString(),
            'quantity_received' => 10,
            'quantity_available' => 6,
            'reserved_quantity' => 0,
            'is_active' => true,
        ]);

        $firstResponse = $this->actingAs($user)->getJson(route('alerts.expiry-reminder'));

        $firstResponse->assertOk();
        $firstResponse->assertJson([
            'available' => true,
            'warning' => [
                'count' => 1,
            ],
        ]);
        $firstResponse->assertJsonPath('warning.slot_key', InventoryExpiryAlerts::currentReminderSlotKey());
        $firstResponse->assertSessionHas(
            InventoryExpiryAlerts::SESSION_SLOT_KEY,
            InventoryExpiryAlerts::currentReminderSlotKey()
        );

        $secondResponse = $this->actingAs($user)->getJson(route('alerts.expiry-reminder'));

        $secondResponse->assertOk();
        $secondResponse->assertJson(['available' => false]);

        Carbon::setTestNow();
    }

    public function test_expiry_alert_endpoint_respects_disabled_client_feature(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-22 18:10:00', config('app.timezone')));

        [$user, $clientId, $branchId] = $this->createUserContext();
        app(AccessControlBootstrapper::class)->ensureForUser($user);
        $this->setExpiryAlertFeature($clientId, false);

        $supplierId = $this->createSupplier($clientId, 'Disabled Supplier');
        $productId = $this->createProduct($clientId, $branchId, 'Disabled Reminder Tabs');

        if (Schema::hasColumn('products', 'expiry_alert_days')) {
            DB::table('products')->where('id', $productId)->update(['expiry_alert_days' => 14]);
        }

        ProductBatch::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'product_id' => $productId,
            'supplier_id' => $supplierId,
            'batch_number' => 'EXP-WARN-006',
            'purchase_price' => 10,
            'retail_price' => 16,
            'wholesale_price' => 14,
            'expiry_date' => Carbon::today(config('app.timezone'))->copy()->addDays(2)->toDateString(),
            'quantity_received' => 10,
            'quantity_available' => 6,
            'reserved_quantity' => 0,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->getJson(route('alerts.expiry-reminder'));

        $response->assertOk();
        $response->assertJson(['available' => false]);
        $response->assertSessionMissing(InventoryExpiryAlerts::SESSION_SLOT_KEY);

        Carbon::setTestNow();
    }

    private function createUserContext(): array
    {
        $clientId = $this->createClient('Dashboard Client');
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
            'code' => 'MBR',
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
            $data['retail_price'] = 15;
        }

        if (Schema::hasColumn('products', 'wholesale_price')) {
            $data['wholesale_price'] = 13;
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

    private function setExpiryAlertFeature(int $clientId, bool $enabled): void
    {
        ClientSetting::query()->updateOrCreate(
            ['client_id' => $clientId],
            array_replace(
                ['business_mode' => 'both'],
                ClientFeatureAccess::defaultSettingValues(),
                ['expiry_alerts_enabled' => $enabled]
            )
        );
    }
}
