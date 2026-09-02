<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Sale;
use App\Models\User;
use App\Support\AccessControlBootstrapper;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MoneyReceivedReportingTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_day_collection_is_counted_once_in_dashboard_and_reports(): void
    {
        $user = $this->createUserContext();
        $today = Carbon::today(config('app.timezone'));
        $this->createSale($user, $today, 49200, 49200);
        $creditSale = $this->createSale($user, $today, 90000);
        $this->collectPayment($user, $creditSale, $today, 32500);
        $before = $creditSale->fresh()->getAttributes();

        $dashboard = $this->assertMoneyReceived($user, $today, $today, 81700, ['Cash' => 81700]);
        $cards = collect($dashboard->viewData('financeStats'))->keyBy('label');
        $this->assertEquals(139200, $cards['Sales Value']['value']);
        $this->assertEquals(57500, $cards['Credit Due']['value']);
        $this->assertEquals(49200, $dashboard->viewData('receiptSummary')['checkout']);
        $this->assertEquals(32500, $dashboard->viewData('receiptSummary')['collections']);
        $dashboard->assertDontSee('Received At Checkout');
        $dashboard->assertDontSee('Customer Collections (Net)');
        $dashboard->assertSeeInOrder(['Sales Value', 'Purchases Value', 'Money Received', 'Credit Due']);
        $this->assertEquals($before, $creditSale->fresh()->getAttributes());
        $this->assertCount(1, Payment::all());
        $this->assertCount(1, collect($dashboard->viewData('recentMoneyIn'))->where('source', 'POS Sale'));
    }

    public function test_collection_on_older_debt_is_included_on_its_payment_date(): void
    {
        $user = $this->createUserContext();
        $today = Carbon::today(config('app.timezone'));
        $yesterday = $today->copy()->subDay();
        $olderSale = $this->createSale($user, $yesterday, 100000);
        $this->createSale($user, $today, 139200, 81700);
        $this->collectPayment($user, $olderSale, $today, 32500);

        $dashboard = $this->assertMoneyReceived($user, $today, $today, 114200, ['Cash' => 114200]);
        $this->assertEquals(81700, $dashboard->viewData('receiptSummary')['checkout']);
        $this->assertEquals(32500, $dashboard->viewData('receiptSummary')['collections']);
        $this->assertMoneyReceived($user, $yesterday, $yesterday, 0, ['Cash' => 0]);
        $this->assertMoneyReceived($user, $yesterday, $today, 114200, ['Cash' => 114200]);
    }

    public function test_later_collection_does_not_inflate_the_original_sale_day(): void
    {
        $user = $this->createUserContext();
        $today = Carbon::today(config('app.timezone'));
        $yesterday = $today->copy()->subDay();
        $sale = $this->createSale($user, $yesterday, 100, 25);
        $this->collectPayment($user, $sale, $today, 20);

        $this->assertMoneyReceived($user, $yesterday, $yesterday, 25, ['Cash' => 25]);
        $this->assertMoneyReceived($user, $today, $today, 20, ['Cash' => 20]);
        $this->assertMoneyReceived($user, $yesterday, $today, 45, ['Cash' => 45]);
    }

    public function test_mixed_collections_and_reversals_keep_their_actual_methods(): void
    {
        $user = $this->createUserContext();
        $today = Carbon::today(config('app.timezone'));
        $sale = $this->createSale($user, $today, 100);
        $bankPayment = $this->collectPayment($user, $sale, $today, 30, 'bank');
        $this->collectPayment($user, $sale, $today, 20, 'mtn');
        $this->reversePayment($user, $bankPayment, $today, 10);

        $dashboard = $this->assertMoneyReceived($user, $today, $today, 40, ['Bank' => 20, 'MTN' => 20, 'Cheque' => 0]);
        $this->assertEquals(0, $dashboard->viewData('receiptSummary')['checkout']);
        $this->assertEquals(40, collect($dashboard->viewData('recentMoneyIn'))->sum('amount'));
        $this->assertCount(1, collect($dashboard->viewData('recentMoneyIn'))->where('source', 'Collection Reversal'));
    }

    public function test_reversal_of_an_older_collection_is_negative_only_on_its_own_date(): void
    {
        $user = $this->createUserContext();
        $today = Carbon::today(config('app.timezone'));
        $yesterday = $today->copy()->subDay();
        $sale = $this->createSale($user, $yesterday, 100);
        $payment = $this->collectPayment($user, $sale, $yesterday, 30);
        $this->reversePayment($user, $payment, $today, 30);

        $this->assertMoneyReceived($user, $yesterday, $yesterday, 30, ['Cash' => 30]);
        $this->assertMoneyReceived($user, $today, $today, -30, ['Cash' => -30]);
        $this->assertMoneyReceived($user, $yesterday, $today, 0, ['Cash' => 0]);
    }

    public function test_legacy_checkout_amount_is_recovered_without_changing_the_sale(): void
    {
        $user = $this->createUserContext();
        $today = Carbon::today(config('app.timezone'));
        $sale = $this->createSale($user, $today, 100, 40);
        $sale->update(['upfront_amount_paid' => 0]);
        $this->collectPayment($user, $sale, $today, 25);

        $dashboard = $this->assertMoneyReceived($user, $today, $today, 65, ['Cash' => 65]);
        $this->assertEquals(40, $dashboard->viewData('receiptSummary')['checkout']);
        $this->assertEquals(0, $sale->fresh()->upfront_amount_paid);
    }

    public function test_insurance_remittances_are_not_counted_as_checkout_receipts(): void
    {
        $user = $this->createUserContext();
        $today = Carbon::today(config('app.timezone'));
        $sale = $this->createSale($user, $today, 100, 10);
        $sale->update([
            'payment_type' => 'insurance',
            'amount_paid' => 60,
            'amount_received' => 60,
            'balance_due' => 40,
        ]);

        $dashboard = $this->assertMoneyReceived($user, $today, $today, 10, ['Cash' => 10]);
        $this->assertEquals(10, collect($dashboard->viewData('recentMoneyIn'))->sum('amount'));
    }

    public function test_receipts_exclude_other_tenants_branches_and_pending_payments(): void
    {
        $user = $this->createUserContext();
        $otherUser = $this->createUserContext();
        $today = Carbon::today(config('app.timezone'));
        $sale = $this->createSale($user, $today, 100, 40);
        $otherSale = $this->createSale($otherUser, $today, 100, 50);
        $this->collectPayment($otherUser, $otherSale, $today, 30);
        $otherBranchId = DB::table('branches')->insertGetId([
            'client_id' => $user->client_id, 'name' => 'Other Branch', 'code' => 'OTHER',
            'is_main' => false, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $branchSale = $this->createSale($user, $today, 100, 80);
        $branchSale->update(['branch_id' => $otherBranchId]);
        $pendingSale = $this->createSale($user, $today, 100, 100);
        $pendingSale->update(['status' => 'pending']);
        $inactiveSale = $this->createSale($user, $today, 100, 100);
        $inactiveSale->update(['is_active' => false]);
        Payment::create([
            'client_id' => $user->client_id, 'branch_id' => $user->branch_id,
            'sale_id' => $sale->id, 'customer_id' => $sale->customer_id, 'received_by' => $user->id,
            'payment_method' => 'Cash', 'amount' => 10, 'payment_date' => $today, 'status' => 'pending',
        ]);

        $this->assertMoneyReceived($user, $today, $today, 40, ['Cash' => 40]);
    }

    public function test_legacy_mixed_checkout_method_is_not_reported_as_cheque(): void
    {
        $user = $this->createUserContext();
        $today = Carbon::today(config('app.timezone'));
        $sale = $this->createSale($user, $today, 100, 40);
        $this->collectPayment($user, $sale, $today, 25, 'bank');

        $this->assertMoneyReceived($user, $today, $today, 65, ['Unallocated' => 40, 'Bank' => 25, 'Cheque' => 0]);
    }

    public function test_opening_receivable_collections_do_not_create_new_sales_receipts(): void
    {
        $user = $this->createUserContext();
        $today = Carbon::today(config('app.timezone'));
        $sale = $this->createSale($user, $today, 100, 20);
        $sale->update(['source' => Sale::SOURCE_OPENING_BALANCE_IMPORT]);
        $this->collectPayment($user, $sale, $today, 30);

        $dashboard = $this->assertMoneyReceived($user, $today, $today, 30, ['Cash' => 30]);
        $this->assertEquals(0, $dashboard->viewData('receiptSummary')['checkout']);
        $cards = collect($dashboard->viewData('financeStats'))->keyBy('label');
        $this->assertEquals(0, $cards['Sales Value']['value']);
    }

    public function test_print_and_csv_use_the_same_corrected_payment_totals(): void
    {
        $user = $this->createUserContext();
        $today = Carbon::today(config('app.timezone'));
        $sale = $this->createSale($user, $today, 100);
        $this->collectPayment($user, $sale, $today, 30, 'bank');
        $filters = ['period' => 'today', 'report' => 'money_methods'];

        $print = $this->actingAs($user)->get(route('reports.print', $filters));
        $print->assertOk()->assertSee('30.00')->assertDontSee('60.00');
        $this->assertEquals(30, collect($print->viewData('moneyByMethod'))->sum('amount'));
        $csv = $this->actingAs($user)->get(route('reports.download', $filters + ['format' => 'csv']));
        $csv->assertOk();
        $rows = array_map('str_getcsv', preg_split('/\r\n|\n|\r/', trim($csv->streamedContent())));
        $bankRow = collect($rows)->first(fn ($row) => ($row[0] ?? null) === 'Bank');
        $this->assertNotNull($bankRow);
        $this->assertEquals(30, (float) $bankRow[1]);
    }

    private function assertMoneyReceived(User $user, Carbon $from, Carbon $to, float $amount, array $methods)
    {
        $filters = ['period' => 'custom', 'date_from' => $from->toDateString(), 'date_to' => $to->toDateString()];
        $dashboard = $this->actingAs($user)->get(route('dashboard', $filters));
        $dashboard->assertOk();
        $reports = $this->actingAs($user)->get(route('reports.index', $filters + ['report' => 'money_methods']));
        $reports->assertOk();

        foreach ([[$dashboard, 'financeStats'], [$reports, 'headlineCards']] as [$response, $cardKey]) {
            $cards = collect($response->viewData($cardKey))->keyBy('label');
            $this->assertEqualsWithDelta($amount, $cards['Money Received']['value'], 0.001, $cardKey);
            $breakdown = collect($response->viewData('moneyByMethod'))->keyBy('label');
            $this->assertEqualsWithDelta($amount, $breakdown->sum('amount'), 0.001);
            foreach ($methods as $method => $expected) {
                $this->assertEqualsWithDelta($expected, $breakdown[$method]['amount'], 0.001, $method);
            }
        }

        return $dashboard;
    }

    private function createUserContext(): User
    {
        $clientId = DB::table('clients')->insertGetId([
            'name' => 'Receipt Test Client', 'business_mode' => 'both', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $branchId = DB::table('branches')->insertGetId([
            'client_id' => $clientId, 'name' => 'Main Branch', 'code' => 'MAIN',
            'is_main' => true, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $user = User::factory()->create(['client_id' => $clientId, 'branch_id' => $branchId, 'is_active' => true]);
        app(AccessControlBootstrapper::class)->ensureForUser($user);

        return $user;
    }

    private function createSale(User $user, Carbon $date, float $total, float $paid = 0): Sale
    {
        $customerId = DB::table('customers')->insertGetId([
            'client_id' => $user->client_id, 'name' => 'Receipt Test Customer', 'credit_limit' => 1000000,
            'outstanding_balance' => $total - $paid, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return Sale::create([
            'client_id' => $user->client_id, 'branch_id' => $user->branch_id, 'customer_id' => $customerId,
            'served_by' => $user->id, 'invoice_number' => 'TEST-INV-' . $customerId,
            'receipt_number' => 'TEST-RCP-' . $customerId, 'sale_type' => 'wholesale', 'status' => 'approved',
            'payment_type' => $paid >= $total ? 'cash' : 'credit', 'payment_method' => $paid > 0 ? 'bulky_cash' : null,
            'subtotal' => $total, 'discount_amount' => 0, 'tax_amount' => 0, 'total_amount' => $total,
            'amount_paid' => $paid, 'amount_received' => $paid, 'upfront_amount_paid' => $paid,
            'balance_due' => $total - $paid, 'sale_date' => $date->toDateString(), 'is_active' => true,
        ]);
    }

    private function collectPayment(User $user, Sale $sale, Carbon $date, float $amount, string $method = 'bulky_cash'): Payment
    {
        $this->actingAs($user)->post(route('customers.collections.store', $sale), [
            'payment_method' => $method, 'amount' => $amount,
            'payment_date' => $date->copy()->setTime(10, 0)->toDateTimeString(),
        ])->assertSessionHasNoErrors()->assertRedirect();

        return Payment::query()->where('sale_id', $sale->id)->latest('id')->firstOrFail();
    }

    private function reversePayment(User $user, Payment $payment, Carbon $date, float $amount): void
    {
        $this->actingAs($user)->post(route('customers.collections.reverse.store', $payment), [
            'amount' => $amount, 'payment_date' => $date->copy()->setTime(12, 0)->toDateTimeString(),
            'notes' => 'Test collection reversal.',
        ])->assertSessionHasNoErrors()->assertRedirect();
    }
}
