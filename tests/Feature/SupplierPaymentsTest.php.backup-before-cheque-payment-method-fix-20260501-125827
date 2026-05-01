<?php

namespace Tests\Feature;

use App\Models\Purchase;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SupplierPaymentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_routes_are_unblocked(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext();
        $supplierId = $this->createSupplier($clientId, 'Alpha Medics');

        $this->actingAs($user)
            ->get(route('suppliers.index'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('suppliers.statement'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('suppliers.payables'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('suppliers.payments.index'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('suppliers.show', $supplierId))
            ->assertOk();
    }

    public function test_supplier_payment_is_applied_to_the_selected_invoice_only(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext();
        $supplierId = $this->createSupplier($clientId, 'Beta Pharma Supply');

        $olderPurchase = $this->createPurchase($user->id, $clientId, $branchId, $supplierId, [
            'invoice_number' => 'PINV-001',
            'total_amount' => 100,
            'amount_paid' => 0,
            'balance_due' => 100,
        ]);

        $selectedPurchase = $this->createPurchase($user->id, $clientId, $branchId, $supplierId, [
            'invoice_number' => 'PINV-002',
            'total_amount' => 50,
            'amount_paid' => 0,
            'balance_due' => 50,
        ]);

        $response = $this->actingAs($user)->post(route('suppliers.payments.store', $selectedPurchase), [
            'payment_method' => 'bank',
            'amount' => 20,
            'reference_number' => 'SUP-BNK-001',
            'payment_date' => '2026-04-19 09:15:00',
            'notes' => 'Paid against second invoice only.',
        ]);

        $response->assertRedirect(route('suppliers.show', $supplierId));

        $this->assertDatabaseHas('supplier_payments', [
            'purchase_id' => $selectedPurchase->id,
            'supplier_id' => $supplierId,
            'paid_by' => $user->id,
            'payment_method' => 'bank',
            'amount' => 20,
            'reference_number' => 'SUP-BNK-001',
            'source' => 'manual',
        ]);

        $this->assertDatabaseHas('purchases', [
            'id' => $olderPurchase->id,
            'amount_paid' => 0,
            'balance_due' => 100,
        ]);

        $this->assertDatabaseHas('purchases', [
            'id' => $selectedPurchase->id,
            'amount_paid' => 20,
            'balance_due' => 30,
            'payment_status' => 'partial',
        ]);
    }

    public function test_supplier_overpayment_is_rejected_for_a_single_invoice(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext();
        $supplierId = $this->createSupplier($clientId, 'Gamma Distributors');
        $purchase = $this->createPurchase($user->id, $clientId, $branchId, $supplierId, [
            'invoice_number' => 'PINV-OVER-001',
            'total_amount' => 30,
            'amount_paid' => 0,
            'balance_due' => 30,
        ]);

        $response = $this->from(route('suppliers.payments.create', $purchase))
            ->actingAs($user)
            ->post(route('suppliers.payments.store', $purchase), [
                'payment_method' => 'mtn',
                'amount' => 35,
                'reference_number' => 'SUP-MTN-123',
                'payment_date' => '2026-04-19 10:00:00',
                'notes' => 'Should fail.',
            ]);

        $response->assertRedirect(route('suppliers.payments.create', $purchase));
        $response->assertSessionHasErrors('amount');

        $this->assertDatabaseMissing('supplier_payments', [
            'purchase_id' => $purchase->id,
            'reference_number' => 'SUP-MTN-123',
        ]);

        $this->assertDatabaseHas('purchases', [
            'id' => $purchase->id,
            'amount_paid' => 0,
            'balance_due' => 30,
        ]);
    }

    public function test_supplier_delete_deactivates_when_purchase_history_exists(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext();
        $supplierId = $this->createSupplier($clientId, 'History Supplier');
        $this->createPurchase($user->id, $clientId, $branchId, $supplierId, [
            'invoice_number' => 'PINV-HISTORY',
            'total_amount' => 80,
            'amount_paid' => 0,
            'balance_due' => 80,
        ]);

        $response = $this->actingAs($user)->delete(route('suppliers.destroy', $supplierId));

        $response->assertRedirect(route('suppliers.index'));

        $this->assertDatabaseHas('suppliers', [
            'id' => $supplierId,
            'is_active' => false,
        ]);
    }

    public function test_purchase_header_update_syncs_invoice_entry_supplier_payment(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext();
        $supplierId = $this->createSupplier($clientId, 'Sync Supplier');
        $purchase = $this->createPurchase($user->id, $clientId, $branchId, $supplierId, [
            'invoice_number' => 'PINV-SYNC-001',
            'purchase_date' => '2026-04-19',
            'total_amount' => 60,
            'amount_paid' => 0,
            'balance_due' => 60,
            'payment_type' => 'credit',
            'payment_status' => 'pending',
        ]);

        $response = $this->actingAs($user)->put(route('purchases.update', $purchase), [
            'invoice_number' => 'PINV-SYNC-001',
            'supplier_id' => $supplierId,
            'purchase_date' => '2026-04-19',
            'payment_type' => 'mixed',
            'amount_paid' => 25,
            'due_date' => '2026-04-30',
            'notes' => 'Updated amount paid from header.',
        ]);

        $response->assertRedirect(route('purchases.show', $purchase->id));

        $this->assertDatabaseHas('supplier_payments', [
            'purchase_id' => $purchase->id,
            'supplier_id' => $supplierId,
            'amount' => 25,
            'payment_method' => 'direct',
            'source' => 'invoice_entry',
        ]);

        $this->assertDatabaseHas('purchases', [
            'id' => $purchase->id,
            'amount_paid' => 25,
            'balance_due' => 35,
            'payment_status' => 'partial',
        ]);
    }

    private function createUserContext(): array
    {
        $clientId = $this->createClient('KimRx Supplier Test Client');
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

    private function createPurchase(int $userId, int $clientId, int $branchId, int $supplierId, array $attributes): Purchase
    {
        return Purchase::create(array_merge([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'supplier_id' => $supplierId,
            'invoice_number' => 'PINV-' . fake()->unique()->numerify('###'),
            'purchase_date' => '2026-04-19',
            'subtotal' => 0,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 0,
            'amount_paid' => 0,
            'balance_due' => 0,
            'payment_type' => 'credit',
            'payment_status' => 'pending',
            'due_date' => '2026-04-30',
            'invoice_status' => 'draft',
            'notes' => 'Seeded for supplier payment tests.',
            'created_by' => $userId,
            'is_active' => true,
        ], $attributes));
    }
}
