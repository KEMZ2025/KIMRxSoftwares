<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CustomerCollectionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_is_applied_to_the_selected_invoice_only(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext();
        $customerId = $this->createCustomer($clientId, 'City Clinic', 300, 150);

        $olderSale = $this->createSale($user->id, $clientId, $branchId, $customerId, [
            'invoice_number' => 'INV-001',
            'total_amount' => 100,
            'amount_paid' => 0,
            'amount_received' => 0,
            'balance_due' => 100,
        ]);

        $selectedSale = $this->createSale($user->id, $clientId, $branchId, $customerId, [
            'invoice_number' => 'INV-002',
            'total_amount' => 50,
            'amount_paid' => 0,
            'amount_received' => 0,
            'balance_due' => 50,
        ]);

        $response = $this->actingAs($user)->post(route('customers.collections.store', $selectedSale), [
            'payment_method' => 'bank',
            'amount' => 20,
            'reference_number' => 'BNK-REF-001',
            'payment_date' => '2026-04-19 09:15:00',
            'notes' => 'Paid for the second invoice only.',
        ]);

        $response->assertRedirect(route('customers.show', $customerId));

        $this->assertDatabaseHas('payments', [
            'sale_id' => $selectedSale->id,
            'customer_id' => $customerId,
            'received_by' => $user->id,
            'payment_method' => 'bank',
            'amount' => 20,
            'reference_number' => 'BNK-REF-001',
        ]);

        $this->assertDatabaseHas('sales', [
            'id' => $olderSale->id,
            'amount_paid' => 0,
            'amount_received' => 0,
            'balance_due' => 100,
        ]);

        $this->assertDatabaseHas('sales', [
            'id' => $selectedSale->id,
            'amount_paid' => 20,
            'amount_received' => 20,
            'balance_due' => 30,
            'payment_method' => 'bank',
        ]);

        $this->assertDatabaseHas('customers', [
            'id' => $customerId,
            'outstanding_balance' => 130,
        ]);
    }

    public function test_overpayment_is_rejected_for_a_single_invoice(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext();
        $customerId = $this->createCustomer($clientId, 'Upcountry Pharmacy', 200, 30);
        $sale = $this->createSale($user->id, $clientId, $branchId, $customerId, [
            'invoice_number' => 'INV-OVER-001',
            'total_amount' => 30,
            'amount_paid' => 0,
            'amount_received' => 0,
            'balance_due' => 30,
        ]);

        $response = $this->from(route('customers.collections.create', $sale))
            ->actingAs($user)
            ->post(route('customers.collections.store', $sale), [
                'payment_method' => 'mtn',
                'amount' => 35,
                'reference_number' => 'MTN-123',
                'payment_date' => '2026-04-19 10:00:00',
                'notes' => 'Should fail.',
            ]);

        $response->assertRedirect(route('customers.collections.create', $sale));
        $response->assertSessionHasErrors('amount');

        $this->assertDatabaseMissing('payments', [
            'sale_id' => $sale->id,
            'reference_number' => 'MTN-123',
        ]);

        $this->assertDatabaseHas('sales', [
            'id' => $sale->id,
            'amount_paid' => 0,
            'balance_due' => 30,
        ]);

        $this->assertDatabaseHas('customers', [
            'id' => $customerId,
            'outstanding_balance' => 30,
        ]);
    }

    public function test_customer_routes_are_unblocked(): void
    {
        [$user] = $this->createUserContext();

        $this->actingAs($user)
            ->get(route('customers.index'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('customers.receivables'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('customers.collections.index'))
            ->assertOk();
    }

    public function test_customer_statement_uses_live_invoice_balance_and_syncs_the_customer_record(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext();
        $customerId = $this->createCustomer($clientId, 'Birungi Pharmacy', 150000, 0);

        $this->createSale($user->id, $clientId, $branchId, $customerId, [
            'invoice_number' => 'WINV-235543',
            'total_amount' => 20700,
            'amount_paid' => 10000,
            'amount_received' => 10000,
            'balance_due' => 10700,
        ]);

        $response = $this->actingAs($user)->get(route('customers.show', $customerId));

        $response->assertOk();
        $response->assertSeeTextInOrder([
            'Outstanding Balance',
            '10,700.00',
        ]);

        $this->assertDatabaseHas('customers', [
            'id' => $customerId,
            'outstanding_balance' => 10700,
        ]);
    }

    public function test_partial_reversal_only_restores_the_selected_invoice_payment(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext();
        $customerId = $this->createCustomer($clientId, 'Account Customer', 500, 130);

        $olderSale = $this->createSale($user->id, $clientId, $branchId, $customerId, [
            'invoice_number' => 'INV-OLDER',
            'total_amount' => 100,
            'amount_paid' => 0,
            'amount_received' => 0,
            'balance_due' => 100,
        ]);

        $selectedSale = $this->createSale($user->id, $clientId, $branchId, $customerId, [
            'invoice_number' => 'INV-REV-001',
            'total_amount' => 50,
            'amount_paid' => 20,
            'amount_received' => 20,
            'balance_due' => 30,
            'payment_method' => 'bank',
        ]);

        $payment = $this->createPayment($user->id, $clientId, $branchId, $customerId, $selectedSale->id, [
            'payment_method' => 'bank',
            'amount' => 20,
            'reference_number' => 'PAY-REV-001',
            'payment_date' => '2026-04-19 12:00:00',
        ]);

        $response = $this->actingAs($user)->post(route('customers.collections.reverse.store', $payment), [
            'amount' => 5,
            'payment_date' => '2026-04-19 13:00:00',
            'reference_number' => 'REV-001',
            'notes' => 'Wrong amount posted to this invoice.',
        ]);

        $response->assertRedirect(route('customers.collections.create', $selectedSale));

        $this->assertDatabaseHas('payments', [
            'sale_id' => $selectedSale->id,
            'reversal_of_payment_id' => $payment->id,
            'amount' => 5,
            'status' => 'reversal',
            'reference_number' => 'REV-001',
        ]);

        $this->assertDatabaseHas('sales', [
            'id' => $olderSale->id,
            'amount_paid' => 0,
            'amount_received' => 0,
            'balance_due' => 100,
        ]);

        $this->assertDatabaseHas('sales', [
            'id' => $selectedSale->id,
            'amount_paid' => 15,
            'amount_received' => 15,
            'balance_due' => 35,
            'payment_method' => 'bank',
        ]);

        $this->assertDatabaseHas('customers', [
            'id' => $customerId,
            'outstanding_balance' => 135,
        ]);
    }

    public function test_full_reversal_restores_the_full_invoice_balance(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext();
        $customerId = $this->createCustomer($clientId, 'Reverse Whole Amount', 1000, 0);

        $sale = $this->createSale($user->id, $clientId, $branchId, $customerId, [
            'invoice_number' => 'INV-REV-FULL',
            'total_amount' => 60,
            'amount_paid' => 60,
            'amount_received' => 60,
            'balance_due' => 0,
            'payment_method' => 'mtn',
        ]);

        $payment = $this->createPayment($user->id, $clientId, $branchId, $customerId, $sale->id, [
            'payment_method' => 'mtn',
            'amount' => 60,
            'reference_number' => 'MTN-REV-FULL',
            'payment_date' => '2026-04-19 14:00:00',
        ]);

        $response = $this->actingAs($user)->post(route('customers.collections.reverse.store', $payment), [
            'amount' => 60,
            'payment_date' => '2026-04-19 14:30:00',
            'notes' => 'Payment was posted to the wrong invoice.',
        ]);

        $response->assertRedirect(route('customers.collections.create', $sale));

        $this->assertDatabaseHas('payments', [
            'sale_id' => $sale->id,
            'reversal_of_payment_id' => $payment->id,
            'amount' => 60,
            'status' => 'reversal',
        ]);

        $this->assertDatabaseHas('sales', [
            'id' => $sale->id,
            'amount_paid' => 0,
            'amount_received' => 0,
            'balance_due' => 60,
            'payment_method' => null,
        ]);

        $this->assertDatabaseHas('customers', [
            'id' => $customerId,
            'outstanding_balance' => 60,
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

    private function createSale(int $userId, int $clientId, int $branchId, int $customerId, array $attributes): Sale
    {
        return Sale::create(array_merge([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'customer_id' => $customerId,
            'served_by' => $userId,
            'invoice_number' => 'INV-' . fake()->unique()->numerify('###'),
            'receipt_number' => 'RCPT-' . fake()->unique()->numerify('###'),
            'sale_type' => 'wholesale',
            'status' => 'approved',
            'payment_type' => 'credit',
            'payment_method' => null,
            'subtotal' => 0,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 0,
            'amount_paid' => 0,
            'amount_received' => 0,
            'balance_due' => 0,
            'sale_date' => '2026-04-19 08:00:00',
            'notes' => 'Seeded for customer collections tests.',
            'is_active' => true,
        ], $attributes));
    }

    private function createPayment(int $userId, int $clientId, int $branchId, int $customerId, int $saleId, array $attributes): Payment
    {
        return Payment::create(array_merge([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'sale_id' => $saleId,
            'customer_id' => $customerId,
            'received_by' => $userId,
            'payment_method' => 'bank',
            'amount' => 0,
            'reference_number' => null,
            'payment_date' => '2026-04-19 12:00:00',
            'status' => 'received',
            'notes' => 'Seeded for customer payment tests.',
        ], $attributes));
    }
}
