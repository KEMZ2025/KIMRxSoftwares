<?php

namespace Tests\Feature;

use App\Models\CashDrawerDraw;
use App\Models\CashDrawerSession;
use App\Models\CashDrawerShift;
use App\Models\ClientSetting;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\User;
use App\Support\AccessControlBootstrapper;
use App\Support\CashDrawerAlerts;
use App\Support\ClientFeatureAccess;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CashDrawerFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_cash_drawer_screen_uses_opening_balance_cash_sales_cash_collections_and_draws_only(): void
    {
        [$user, $clientId, $branchId] = $this->createCashDrawerContext(250);

        $today = Carbon::today(config('app.timezone'));

        CashDrawerSession::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'session_date' => $today->toDateString(),
            'opening_balance' => 100,
            'opening_note' => 'Morning float',
            'opened_by' => $user->id,
        ]);

        Sale::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'customer_id' => null,
            'served_by' => $user->id,
            'invoice_number' => 'CASH-001',
            'receipt_number' => 'RCP-CASH-001',
            'sale_type' => 'retail',
            'status' => 'approved',
            'payment_type' => 'cash',
            'payment_method' => 'cash',
            'subtotal' => 200,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 200,
            'amount_paid' => 200,
            'amount_received' => 200,
            'balance_due' => 0,
            'sale_date' => $today->copy()->setTime(9, 0),
            'is_active' => true,
        ]);

        Sale::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'customer_id' => null,
            'served_by' => $user->id,
            'invoice_number' => 'BANK-001',
            'receipt_number' => 'RCP-BANK-001',
            'sale_type' => 'retail',
            'status' => 'approved',
            'payment_type' => 'cash',
            'payment_method' => 'bank',
            'subtotal' => 300,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 300,
            'amount_paid' => 300,
            'amount_received' => 300,
            'balance_due' => 0,
            'sale_date' => $today->copy()->setTime(10, 0),
            'is_active' => true,
        ]);

        $sameDayDebtSale = Sale::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'customer_id' => null,
            'served_by' => $user->id,
            'invoice_number' => 'DEBT-TODAY-001',
            'receipt_number' => null,
            'sale_type' => 'retail',
            'status' => 'approved',
            'payment_type' => 'credit',
            'payment_method' => 'credit',
            'subtotal' => 50,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 50,
            'amount_paid' => 0,
            'amount_received' => 0,
            'balance_due' => 50,
            'sale_date' => $today->copy()->setTime(10, 45),
            'is_active' => true,
        ]);

        $oldDebtSale = Sale::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'customer_id' => null,
            'served_by' => $user->id,
            'invoice_number' => 'DEBT-OLD-001',
            'receipt_number' => null,
            'sale_type' => 'retail',
            'status' => 'approved',
            'payment_type' => 'credit',
            'payment_method' => 'credit',
            'subtotal' => 70,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 70,
            'amount_paid' => 0,
            'amount_received' => 0,
            'balance_due' => 70,
            'sale_date' => $today->copy()->subDay()->setTime(16, 0),
            'is_active' => true,
        ]);

        Payment::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'sale_id' => $sameDayDebtSale->id,
            'customer_id' => null,
            'received_by' => $user->id,
            'payment_method' => 'cash',
            'amount' => 50,
            'payment_date' => $today->copy()->setTime(11, 0),
            'status' => 'received',
        ]);

        Payment::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'sale_id' => $sameDayDebtSale->id,
            'customer_id' => null,
            'received_by' => $user->id,
            'payment_method' => 'mtn',
            'amount' => 20,
            'payment_date' => $today->copy()->setTime(11, 20),
            'status' => 'received',
        ]);

        Payment::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'sale_id' => $oldDebtSale->id,
            'customer_id' => null,
            'received_by' => $user->id,
            'payment_method' => 'cash',
            'amount' => 70,
            'payment_date' => $today->copy()->setTime(11, 30),
            'status' => 'received',
        ]);

        $session = CashDrawerSession::query()->firstOrFail();

        CashDrawerDraw::create([
            'cash_drawer_session_id' => $session->id,
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'amount' => 80,
            'reason' => 'Bank deposit',
            'drawn_by' => $user->id,
            'drawn_at' => $today->copy()->setTime(12, 0),
        ]);

        $response = $this->actingAs($user)->get(route('cash-drawer.index'));

        $response->assertOk();
        $response->assertSee('Cash Drawer');
        $response->assertSee('UGX 100.00');
        $response->assertSee('UGX 200.00');
        $response->assertSee('UGX 50.00');
        $response->assertSee('UGX 80.00');
        $response->assertSee('UGX 270.00');
        $response->assertSee('Morning float');
        $response->assertSee('Bank deposit');
    }

    public function test_cash_draw_cannot_exceed_tracked_balance(): void
    {
        [$user, $clientId, $branchId] = $this->createCashDrawerContext(250);

        CashDrawerSession::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'session_date' => Carbon::today(config('app.timezone'))->toDateString(),
            'opening_balance' => 100,
            'opened_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->post(route('cash-drawer.draws.store'), [
            'amount' => 150,
            'reason' => 'Bank deposit',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('cash_drawer_draws', 0);
    }

    public function test_cash_drawer_alert_endpoint_returns_due_warning_once_per_signature(): void
    {
        [$user, $clientId, $branchId] = $this->createCashDrawerContext(250);

        CashDrawerSession::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'session_date' => Carbon::today(config('app.timezone'))->toDateString(),
            'opening_balance' => 100,
            'opened_by' => $user->id,
        ]);

        Sale::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'customer_id' => null,
            'served_by' => $user->id,
            'invoice_number' => 'ALERT-001',
            'receipt_number' => 'RCP-ALERT-001',
            'sale_type' => 'retail',
            'status' => 'approved',
            'payment_type' => 'cash',
            'payment_method' => 'cash',
            'subtotal' => 200,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 200,
            'amount_paid' => 200,
            'amount_received' => 200,
            'balance_due' => 0,
            'sale_date' => Carbon::today(config('app.timezone'))->copy()->setTime(9, 0),
            'is_active' => true,
        ]);

        $firstResponse = $this->actingAs($user)->getJson(route('alerts.cash-drawer'));

        $firstResponse->assertOk();
        $firstResponse->assertJson([
            'available' => true,
            'warning' => [
                'currency_symbol' => 'UGX',
                'current_balance' => 300,
                'alert_threshold' => 250,
            ],
        ]);
        $firstResponse->assertSessionHas(CashDrawerAlerts::SESSION_SIGNATURE_KEY);

        $secondResponse = $this->actingAs($user)->getJson(route('alerts.cash-drawer'));

        $secondResponse->assertOk();
        $secondResponse->assertJson(['available' => false]);
    }

    public function test_dashboard_shows_cash_drawer_warning_when_threshold_is_reached(): void
    {
        [$user, $clientId, $branchId] = $this->createCashDrawerContext(250);

        CashDrawerSession::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'session_date' => Carbon::today(config('app.timezone'))->toDateString(),
            'opening_balance' => 100,
            'opened_by' => $user->id,
        ]);

        Sale::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'customer_id' => null,
            'served_by' => $user->id,
            'invoice_number' => 'WARN-001',
            'receipt_number' => 'RCP-WARN-001',
            'sale_type' => 'retail',
            'status' => 'approved',
            'payment_type' => 'cash',
            'payment_method' => 'cash',
            'subtotal' => 200,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 200,
            'amount_paid' => 200,
            'amount_received' => 200,
            'balance_due' => 0,
            'sale_date' => Carbon::today(config('app.timezone'))->copy()->setTime(9, 0),
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Cash Drawer Alert');
        $response->assertSee('Drawer balance is UGX 300.00.');
    }

    public function test_first_shift_open_sets_day_opening_and_blocks_second_active_shift(): void
    {
        [$user, $clientId, $branchId] = $this->createCashDrawerContext(250);

        Carbon::setTestNow(Carbon::parse('2026-04-23 08:00:00', config('app.timezone')));

        try {
            $response = $this->actingAs($user)->post(route('cash-drawer.shifts.open'), [
                'shift_opening_balance' => 120,
                'shift_opening_note' => 'Morning float received from safe',
            ]);

            $response->assertRedirect(route('cash-drawer.index'));
            $this->assertDatabaseHas('cash_drawer_sessions', [
                'client_id' => $clientId,
                'branch_id' => $branchId,
                'opening_balance' => '120.00',
                'opened_by' => $user->id,
            ]);
            $this->assertDatabaseHas('cash_drawer_shifts', [
                'client_id' => $clientId,
                'branch_id' => $branchId,
                'opened_by' => $user->id,
                'opening_balance' => '120.00',
            ]);

            $secondResponse = $this->actingAs($user)->post(route('cash-drawer.shifts.open'), [
                'shift_opening_balance' => 50,
                'shift_opening_note' => 'Second shift attempt',
            ]);

            $secondResponse->assertRedirect();
            $secondResponse->assertSessionHasErrors('shift_opening_balance');
            $this->assertDatabaseCount('cash_drawer_shifts', 1);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_shift_close_reconciles_only_its_window_and_records_banked_cash_as_draw(): void
    {
        [$user, $clientId, $branchId] = $this->createCashDrawerContext(250);
        $today = Carbon::today(config('app.timezone'));

        Sale::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'customer_id' => null,
            'served_by' => $user->id,
            'invoice_number' => 'PRE-SHIFT-001',
            'receipt_number' => 'RCP-PRE-SHIFT-001',
            'sale_type' => 'retail',
            'status' => 'approved',
            'payment_type' => 'cash',
            'payment_method' => 'cash',
            'subtotal' => 60,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 60,
            'amount_paid' => 60,
            'amount_received' => 60,
            'balance_due' => 0,
            'sale_date' => $today->copy()->setTime(7, 30),
            'is_active' => true,
        ]);

        Carbon::setTestNow($today->copy()->setTime(8, 0));

        try {
            $this->actingAs($user)->post(route('cash-drawer.shifts.open'), [
                'shift_opening_balance' => 100,
                'shift_opening_note' => 'Morning shift',
            ])->assertRedirect();

            Sale::create([
                'client_id' => $clientId,
                'branch_id' => $branchId,
                'customer_id' => null,
                'served_by' => $user->id,
                'invoice_number' => 'SHIFT-SALE-001',
                'receipt_number' => 'RCP-SHIFT-SALE-001',
                'sale_type' => 'retail',
                'status' => 'approved',
                'payment_type' => 'cash',
                'payment_method' => 'cash',
                'subtotal' => 200,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'total_amount' => 200,
                'amount_paid' => 200,
                'amount_received' => 200,
                'balance_due' => 0,
                'sale_date' => $today->copy()->setTime(9, 0),
                'is_active' => true,
            ]);

            $sameDayDebtSale = Sale::create([
                'client_id' => $clientId,
                'branch_id' => $branchId,
                'customer_id' => null,
                'served_by' => $user->id,
                'invoice_number' => 'SHIFT-DEBT-001',
                'receipt_number' => null,
                'sale_type' => 'retail',
                'status' => 'approved',
                'payment_type' => 'credit',
                'payment_method' => 'credit',
                'subtotal' => 50,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'total_amount' => 50,
                'amount_paid' => 0,
                'amount_received' => 0,
                'balance_due' => 50,
                'sale_date' => $today->copy()->setTime(9, 20),
                'is_active' => true,
            ]);

            Payment::create([
                'client_id' => $clientId,
                'branch_id' => $branchId,
                'sale_id' => $sameDayDebtSale->id,
                'customer_id' => null,
                'received_by' => $user->id,
                'payment_method' => 'cash',
                'amount' => 50,
                'payment_date' => $today->copy()->setTime(10, 0),
                'status' => 'received',
            ]);

            $session = CashDrawerSession::query()->firstOrFail();

            CashDrawerDraw::create([
                'cash_drawer_session_id' => $session->id,
                'client_id' => $clientId,
                'branch_id' => $branchId,
                'amount' => 40,
                'reason' => 'Mid-shift safe transfer',
                'drawn_by' => $user->id,
                'drawn_at' => $today->copy()->setTime(10, 30),
            ]);

            Carbon::setTestNow($today->copy()->setTime(11, 0));

            $shift = CashDrawerShift::query()->firstOrFail();
            $response = $this->actingAs($user)->post(route('cash-drawer.shifts.close', $shift), [
                'counted_cash' => 300,
                'banked_amount' => 100,
                'handover_amount' => 50,
                'closing_note' => 'Banked part of the shift cash',
            ]);

            $response->assertRedirect(route('cash-drawer.index'));
            $this->assertDatabaseHas('cash_drawer_shifts', [
                'id' => $shift->id,
                'closing_expected_balance' => '310.00',
                'closing_counted_balance' => '300.00',
                'closing_variance' => '-10.00',
                'banked_amount' => '100.00',
                'handover_amount' => '50.00',
            ]);
            $this->assertDatabaseHas('cash_drawer_draws', [
                'cash_drawer_session_id' => $session->id,
                'amount' => '100.00',
                'reason' => 'Shift close banked cash: Banked part of the shift cash',
            ]);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_day_close_requires_no_active_shift_locks_drawer_and_can_be_reopened(): void
    {
        [$user, $clientId, $branchId] = $this->createCashDrawerContext(250);
        $today = Carbon::today(config('app.timezone'));

        Carbon::setTestNow($today->copy()->setTime(8, 0));

        try {
            $this->actingAs($user)->post(route('cash-drawer.shifts.open'), [
                'shift_opening_balance' => 100,
                'shift_opening_note' => 'Morning shift',
            ])->assertRedirect();

            $blockedClose = $this->actingAs($user)->post(route('cash-drawer.day.close'), [
                'day_counted_cash' => 100,
                'day_closing_note' => 'Trying too early',
            ]);

            $blockedClose->assertRedirect();
            $blockedClose->assertSessionHasErrors('day_counted_cash');

            Carbon::setTestNow($today->copy()->setTime(9, 0));

            $shift = CashDrawerShift::query()->firstOrFail();
            $this->actingAs($user)->post(route('cash-drawer.shifts.close', $shift), [
                'counted_cash' => 100,
                'banked_amount' => 0,
                'handover_amount' => 0,
                'closing_note' => 'Balanced shift',
            ])->assertRedirect();

            $this->actingAs($user)->post(route('cash-drawer.day.close'), [
                'day_counted_cash' => 100,
                'day_closing_note' => 'Balanced day',
            ])->assertRedirect(route('cash-drawer.index'));

            $session = CashDrawerSession::query()->firstOrFail()->fresh();
            $this->assertNotNull($session->day_closed_at);
            $this->assertSame('100.00', $session->day_closing_expected_balance);
            $this->assertSame('100.00', $session->day_closing_counted_balance);
            $this->assertSame('0.00', $session->day_closing_variance);

            $drawWhileClosed = $this->actingAs($user)->post(route('cash-drawer.draws.store'), [
                'amount' => 10,
                'reason' => 'Late bank deposit',
            ]);

            $drawWhileClosed->assertRedirect();
            $drawWhileClosed->assertSessionHasErrors('amount');

            $this->actingAs($user)->post(route('cash-drawer.day.reopen'), [
                'day_reopen_reason' => 'Correcting the closing after review',
            ])->assertRedirect(route('cash-drawer.index'));

            $session->refresh();
            $this->assertNotNull($session->day_reopened_at);

            $this->actingAs($user)->post(route('cash-drawer.draws.store'), [
                'amount' => 10,
                'reason' => 'Late bank deposit',
            ])->assertRedirect(route('cash-drawer.index'));

            $this->assertDatabaseHas('cash_drawer_draws', [
                'cash_drawer_session_id' => $session->id,
                'amount' => '10.00',
                'reason' => 'Late bank deposit',
            ]);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_cash_drawer_routes_and_alerts_are_blocked_when_client_module_is_disabled(): void
    {
        [$user] = $this->createCashDrawerContext(250, false);

        $this->actingAs($user)
            ->get(route('cash-drawer.index'))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('cash-drawer.shifts.open'), [
                'shift_opening_balance' => 100,
                'shift_opening_note' => 'Attempt while disabled',
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('cash-drawer.day.close'), [
                'day_counted_cash' => 0,
                'day_closing_note' => 'Attempt while disabled',
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->getJson(route('alerts.cash-drawer'))
            ->assertOk()
            ->assertJson(['available' => false]);
    }

    private function createCashDrawerContext(float $threshold, bool $enabled = true): array
    {
        $clientId = DB::table('clients')->insertGetId([
            'name' => 'Cash Drawer Client',
            'business_mode' => 'both',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $branchId = DB::table('branches')->insertGetId([
            'client_id' => $clientId,
            'name' => 'Main Branch',
            'code' => 'CDR',
            'business_mode' => 'inherit',
            'is_main' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        ClientSetting::query()->create(array_replace(
            [
                'client_id' => $clientId,
                'business_mode' => 'both',
                'currency_symbol' => 'UGX',
                'tax_label' => 'TIN',
            ],
            ClientFeatureAccess::defaultSettingValues(),
            [
                'cash_drawer_enabled' => $enabled,
                'cash_drawer_alert_threshold' => $threshold,
            ]
        ));

        $user = User::factory()->create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'is_active' => true,
        ]);

        app(AccessControlBootstrapper::class)->ensureForUser($user);

        return [$user, $clientId, $branchId];
    }
}
