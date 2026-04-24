<?php

namespace Tests\Feature;

use App\Models\ClientSetting;
use App\Models\InsurancePayment;
use App\Models\Insurer;
use App\Models\Sale;
use App\Models\User;
use App\Support\AccessControlBootstrapper;
use App\Support\Accounting\AccountingLedgerService;
use App\Support\ClientFeatureAccess;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InsuranceModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_receivables_exclude_insurance_invoices_but_claim_desk_lists_them(): void
    {
        [$user, $clientId, $branchId] = $this->createInsuranceContext(true);
        $customerId = $this->createCustomer($clientId);
        $insurer = $this->createInsurer($clientId);

        Sale::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'customer_id' => $customerId,
            'served_by' => $user->id,
            'approved_by' => $user->id,
            'invoice_number' => 'INS-001',
            'receipt_number' => 'RCP-INS-001',
            'sale_type' => 'retail',
            'status' => 'approved',
            'payment_type' => 'insurance',
            'payment_method' => 'Cash',
            'insurer_id' => $insurer->id,
            'insurance_claim_status' => Sale::CLAIM_DRAFT,
            'insurance_covered_amount' => 300,
            'patient_copay_amount' => 100,
            'insurance_balance_due' => 300,
            'upfront_amount_paid' => 100,
            'subtotal' => 400,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 400,
            'amount_paid' => 100,
            'amount_received' => 100,
            'balance_due' => 300,
            'sale_date' => Carbon::parse('2026-04-24 09:00:00', config('app.timezone')),
            'is_active' => true,
        ]);

        Sale::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'customer_id' => $customerId,
            'served_by' => $user->id,
            'approved_by' => $user->id,
            'invoice_number' => 'CR-001',
            'receipt_number' => null,
            'sale_type' => 'retail',
            'status' => 'approved',
            'payment_type' => 'credit',
            'payment_method' => 'Credit',
            'subtotal' => 150,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 150,
            'amount_paid' => 0,
            'amount_received' => 0,
            'balance_due' => 150,
            'sale_date' => Carbon::parse('2026-04-24 10:00:00', config('app.timezone')),
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('customers.receivables'))
            ->assertOk()
            ->assertSee('CR-001')
            ->assertDontSee('INS-001');

        $this->actingAs($user)
            ->get(route('insurance.claims.index'))
            ->assertOk()
            ->assertSee('INS-001')
            ->assertDontSee('CR-001');
    }

    public function test_recording_and_reversing_insurance_remittances_updates_claim_balances(): void
    {
        [$user, $clientId, $branchId] = $this->createInsuranceContext(true);
        $sale = $this->createApprovedInsuranceSale($user, $clientId, $branchId, 300, 100);

        $this->actingAs($user)
            ->post(route('insurance.payments.store', $sale), [
                'payment_method' => 'bank',
                'amount' => 150,
                'payment_date' => '2026-04-24 11:00',
                'reference_number' => 'INS-PAY-001',
                'notes' => 'Half of claim remitted',
            ])
            ->assertRedirect(route('insurance.claims.show', $sale));

        $sale->refresh();
        $this->assertSame('150.00', $sale->insurance_balance_due);
        $this->assertSame('150.00', $sale->balance_due);
        $this->assertSame('250.00', $sale->amount_received);
        $this->assertSame(Sale::CLAIM_PART_PAID, $sale->insurance_claim_status);

        $payment = InsurancePayment::query()->whereNull('reversal_of_payment_id')->firstOrFail();

        $this->actingAs($user)
            ->post(route('insurance.payments.reverse.store', $payment), [
                'amount' => 150,
                'payment_date' => '2026-04-24 12:00',
                'reference_number' => 'INS-REV-001',
                'notes' => 'Remittance posted to wrong claim',
            ])
            ->assertRedirect(route('insurance.claims.show', $sale));

        $sale->refresh();
        $this->assertSame('300.00', $sale->insurance_balance_due);
        $this->assertSame('300.00', $sale->balance_due);
        $this->assertSame('100.00', $sale->amount_received);
        $this->assertSame(Sale::CLAIM_DRAFT, $sale->insurance_claim_status);
    }

    public function test_accounting_chart_tracks_insurance_receivable_in_its_own_account(): void
    {
        [$user, $clientId, $branchId] = $this->createInsuranceContext(true);
        $sale = $this->createApprovedInsuranceSale($user, $clientId, $branchId, 300, 100);

        InsurancePayment::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'sale_id' => $sale->id,
            'insurer_id' => $sale->insurer_id,
            'received_by' => $user->id,
            'payment_method' => 'bank',
            'amount' => 150,
            'payment_date' => Carbon::parse('2026-04-24 13:00:00', config('app.timezone')),
            'status' => 'received',
            'reference_number' => 'INS-BANK-001',
        ]);

        $sale->update([
            'insurance_balance_due' => 150,
            'balance_due' => 150,
            'amount_received' => 250,
            'amount_paid' => 250,
            'insurance_claim_status' => Sale::CLAIM_PART_PAID,
        ]);

        $chart = app(AccountingLedgerService::class)->chartOfAccounts($user, Carbon::parse('2026-04-24', config('app.timezone')));
        $insuranceReceivable = collect($chart['groupedAccounts']['assets'] ?? [])->firstWhere('code', '11100');
        $customerReceivable = collect($chart['groupedAccounts']['assets'] ?? [])->firstWhere('code', '11000');

        $this->assertNotNull($insuranceReceivable);
        $this->assertSame(150.0, round((float) $insuranceReceivable['balance'], 2));
        $this->assertNotNull($customerReceivable);
        $this->assertSame(0.0, round((float) $customerReceivable['balance'], 2));
    }

    public function test_insurance_routes_are_blocked_when_client_module_is_disabled(): void
    {
        [$user] = $this->createInsuranceContext(false);

        $this->actingAs($user)
            ->get(route('insurance.claims.index'))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('insurance.insurers.index'))
            ->assertForbidden();
    }

    private function createInsuranceContext(bool $enabled): array
    {
        $clientId = DB::table('clients')->insertGetId([
            'name' => 'Insurance Client',
            'business_mode' => 'both',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $branchId = DB::table('branches')->insertGetId([
            'client_id' => $clientId,
            'name' => 'Main Branch',
            'code' => 'INS',
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
                'insurance_enabled' => $enabled,
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

    private function createCustomer(int $clientId): int
    {
        return DB::table('customers')->insertGetId([
            'client_id' => $clientId,
            'name' => 'VIP Family',
            'phone' => '0772000000',
            'credit_limit' => 0,
            'outstanding_balance' => 0,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createInsurer(int $clientId): Insurer
    {
        return Insurer::query()->create([
            'client_id' => $clientId,
            'name' => 'Jubilee Health',
            'code' => 'JUB',
            'credit_days' => 30,
            'is_active' => true,
        ]);
    }

    private function createApprovedInsuranceSale(User $user, int $clientId, int $branchId, float $coveredAmount, float $patientTopUp): Sale
    {
        $customerId = $this->createCustomer($clientId);
        $insurer = $this->createInsurer($clientId);
        $totalAmount = $coveredAmount + $patientTopUp;

        return Sale::query()->create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'customer_id' => $customerId,
            'insurer_id' => $insurer->id,
            'served_by' => $user->id,
            'approved_by' => $user->id,
            'invoice_number' => 'INS-APP-001',
            'receipt_number' => 'RCP-INS-APP-001',
            'sale_type' => 'retail',
            'status' => 'approved',
            'payment_type' => 'insurance',
            'payment_method' => 'Cash',
            'insurance_claim_status' => Sale::CLAIM_DRAFT,
            'insurance_member_number' => 'MEM-001',
            'insurance_card_number' => 'CARD-001',
            'insurance_authorization_number' => 'AUTH-001',
            'subtotal' => $totalAmount,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => $totalAmount,
            'amount_paid' => $patientTopUp,
            'amount_received' => $patientTopUp,
            'insurance_covered_amount' => $coveredAmount,
            'patient_copay_amount' => $patientTopUp,
            'insurance_balance_due' => $coveredAmount,
            'upfront_amount_paid' => $patientTopUp,
            'balance_due' => $coveredAmount,
            'sale_date' => Carbon::parse('2026-04-24 09:00:00', config('app.timezone')),
            'is_active' => true,
        ]);
    }
}
