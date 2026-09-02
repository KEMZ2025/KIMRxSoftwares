<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CashDrawerSession;
use App\Models\Customer;
use App\Models\EfrisDocument;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Support\AccessControlBootstrapper;
use App\Support\AuditTrail;
use App\Support\CashDrawerService;
use App\Support\MoneyReceivedReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ApprovedPaymentCorrectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_unreceived_cash_becomes_credit_without_changing_stock_or_receipt(): void
    {
        [$user, $sale, $customer, $batch] = $this->context();
        $beforeSale = $sale->getAttributes();
        $beforeBatch = $batch->getAttributes();
        $beforeItems = $sale->items()->get()->toArray();
        $payload = $this->payload($user, $sale, $customer);
        $this->put(route('sales.correctApprovedPayment', $sale), $payload)
            ->assertRedirect(route('sales.show', $sale))->assertSessionHasNoErrors();

        $sale->refresh();
        $this->assertSame('credit', $sale->payment_type);
        $this->assertSame('approved', $sale->status);
        $this->assertEquals(0, $sale->amount_paid);
        $this->assertEquals(0, $sale->upfront_amount_paid);
        $this->assertEquals(100, $sale->balance_due);
        $this->assertNull($sale->payment_method);
        $this->assertEquals(100, $customer->fresh()->outstanding_balance);
        foreach (['invoice_number', 'receipt_number', 'sale_date', 'approved_at', 'approved_by', 'total_amount', 'notes'] as $field) {
            $this->assertSame($beforeSale[$field], $sale->getAttributes()[$field]);
        }
        $this->assertSame($beforeBatch, $batch->fresh()->getAttributes());
        $this->assertSame($beforeItems, $sale->items()->get()->toArray());
        $this->assertDatabaseCount('payments', 0);
        $audit = AuditLog::where('event_key', 'sales.approved_payment_corrected')->sole();
        $this->assertSame($user->id, $audit->user_id);
        $this->assertEquals(100, $audit->old_values['amount_paid']);
        $this->assertEquals(0, $audit->new_values['amount_paid']);
        $this->assertSame('Phone promise recorded as received.', $audit->reason);
        $this->get(route('customers.receivables'))->assertOk()->assertSee($sale->invoice_number);
        $this->assertReceipts($user, 0);
    }

    public function test_part_payment_at_approval_leaves_only_the_remainder_as_credit(): void
    {
        [$user, $sale, $customer] = $this->context();
        $this->put(route('sales.correctApprovedPayment', $sale), $this->payload($user, $sale, $customer, 40))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('sales', ['id' => $sale->id, 'amount_received' => 40, 'amount_paid' => 40,
            'upfront_amount_paid' => 40, 'balance_due' => 60, 'payment_type' => 'credit', 'payment_method' => 'Cash']);
        $this->assertEquals(60, $customer->fresh()->outstanding_balance);
        $this->assertReceipts($user, 40);
    }

    public function test_money_received_later_uses_the_existing_collection_workflow_and_date(): void
    {
        [$user, $sale, $customer] = $this->context();
        $sale->update(['sale_date' => today()->subDay()]);
        $this->put(route('sales.correctApprovedPayment', $sale), $this->payload($user, $sale, $customer))
            ->assertSessionHasNoErrors();
        $this->post(route('customers.collections.store', $sale), [
            'payment_method' => 'petty_cash', 'amount' => 30, 'payment_date' => today()->toDateString(),
        ])->assertSessionHasNoErrors();
        $this->assertEquals(70, $sale->fresh()->balance_due);
        $this->assertEquals(70, $customer->fresh()->outstanding_balance);
        $this->assertDatabaseHas('payments', ['sale_id' => $sale->id, 'amount' => 30, 'status' => 'received']);
        $todayReport = MoneyReceivedReport::summarize(
            Sale::where('client_id', $user->client_id)->whereDate('sale_date', today()),
            Payment::where('client_id', $user->client_id)->whereDate('payment_date', today())
        );
        $this->assertEquals(0, $todayReport['checkout']);
        $this->assertEquals(30, $todayReport['collections']);
        $this->assertEquals(0, MoneyReceivedReport::checkoutSales(Sale::whereKey($sale->id))->sum('checkout_amount'));
    }

    public function test_repeated_or_stale_submission_cannot_change_payment_twice(): void
    {
        [$user, $sale, $customer] = $this->context();
        $payload = $this->payload($user, $sale, $customer, 40);
        $this->put(route('sales.correctApprovedPayment', $sale), $payload)->assertSessionHasNoErrors();
        $payload['corrected_amount_received'] = 0;
        $this->put(route('sales.correctApprovedPayment', $sale), $payload)->assertSessionHasErrorsIn('paymentCorrection');
        $this->assertEquals(40, $sale->fresh()->amount_paid);
        $this->assertEquals(60, $customer->fresh()->outstanding_balance);
        $this->assertSame(1, AuditLog::where('event_key', 'sales.approved_payment_corrected')->count());
    }

    public function test_label_only_credit_sale_can_still_have_its_false_payment_corrected(): void
    {
        [$user, $sale, $customer] = $this->context();
        $sale->update(['payment_type' => 'credit']);
        $this->put(route('sales.correctApprovedPayment', $sale), $this->payload($user, $sale, $customer))
            ->assertSessionHasNoErrors();
        $this->assertEquals(100, $customer->fresh()->outstanding_balance);
    }

    public function test_invalid_amounts_missing_reason_and_unconfirmed_corrections_are_rejected(): void
    {
        [$user, $sale, $customer] = $this->context();
        $payload = $this->payload($user, $sale, $customer);
        foreach ([['corrected_amount_received' => -1], ['corrected_amount_received' => 100],
            ['corrected_amount_received' => 150], ['corrected_amount_received' => '0.001'],
            ['correction_reason' => ''], ['confirm_unreceived_payment' => 0], ['correction_customer_id' => null]] as $override) {
            $this->put(route('sales.correctApprovedPayment', $sale), array_replace($payload, $override))
                ->assertSessionHasErrorsIn('paymentCorrection');
            $this->assertEquals(100, $sale->fresh()->amount_paid);
        }
        $this->assertEquals(0, $customer->fresh()->outstanding_balance);
    }

    public function test_foreign_customer_and_foreign_sale_are_rejected(): void
    {
        [$user, $sale, $customer] = $this->context();
        [$other, $foreignSale, $foreignCustomer] = $this->context();
        $payload = $this->payload($user, $sale, $customer);
        $this->put(route('sales.correctApprovedPayment', $sale), array_replace($payload, ['correction_customer_id' => $foreignCustomer->id]))
            ->assertSessionHasErrorsIn('paymentCorrection');
        $this->put(route('sales.correctApprovedPayment', $foreignSale), $payload)->assertNotFound();
        $this->assertEquals(100, $sale->fresh()->amount_paid);
        $this->assertEquals(100, $foreignSale->fresh()->amount_paid);
    }

    public function test_existing_collections_are_never_overwritten(): void
    {
        [$user, $sale, $customer] = $this->context();
        $payload = $this->payload($user, $sale, $customer);
        Payment::create(['sale_id' => $sale->id, 'client_id' => $user->client_id, 'branch_id' => $user->branch_id,
            'customer_id' => $customer->id, 'received_by' => $user->id, 'amount' => 20, 'payment_method' => 'cash',
            'payment_date' => today(), 'status' => 'received']);
        $this->put(route('sales.correctApprovedPayment', $sale), $payload)->assertSessionHasErrorsIn('paymentCorrection');
        $this->assertEquals(100, $sale->fresh()->amount_paid);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_other_branch_receipts_and_inactive_customers_are_rejected(): void
    {
        [$user, $sale, $customer] = $this->context();
        $payload = $this->payload($user, $sale, $customer);
        $customer->update(['is_active' => false]);
        $this->put(route('sales.correctApprovedPayment', $sale), $payload)->assertSessionHasErrorsIn('paymentCorrection');
        $customer->update(['is_active' => true]);
        $otherBranch = DB::table('branches')->insertGetId(['client_id' => $user->client_id, 'name' => 'Other Branch',
            'code' => 'OTHER', 'is_active' => true, 'is_main' => false, 'created_at' => now(), 'updated_at' => now()]);
        $sale->update(['branch_id' => $otherBranch]);
        $this->put(route('sales.correctApprovedPayment', $sale), $payload)->assertNotFound();
        $this->assertEquals(100, $sale->fresh()->amount_paid);
    }

    public function test_further_correction_preserves_other_debts_and_moves_only_this_balance(): void
    {
        [$user, $sale, $customer] = $this->context();
        $customer->update(['outstanding_balance' => 25]);
        $this->put(route('sales.correctApprovedPayment', $sale), $this->payload($user, $sale, $customer, 40))
            ->assertSessionHasNoErrors();
        $this->assertEquals(85, $customer->fresh()->outstanding_balance);
        $otherCustomer = Customer::create(['client_id' => $user->client_id, 'name' => 'Correct Phone Customer',
            'is_active' => true, 'outstanding_balance' => 10]);
        $this->put(route('sales.correctApprovedPayment', $sale), $this->payload($user, $sale->fresh(), $otherCustomer, 20))
            ->assertSessionHasNoErrors();
        $this->assertEquals(25, $customer->fresh()->outstanding_balance);
        $this->assertEquals(90, $otherCustomer->fresh()->outstanding_balance);
    }

    public function test_correction_does_not_apply_item_or_invoice_changes_in_the_request(): void
    {
        [$user, $sale, $customer, $batch] = $this->context();
        $payload = $this->payload($user, $sale, $customer);
        $this->put(route('sales.correctApprovedPayment', $sale), $payload + [
            'invoice_number' => 'REPLACED', 'receipt_number' => 'REPLACED', 'quantity' => [99],
            'unit_price' => [1], 'sale_date' => '2001-01-01', 'status' => 'pending',
        ])->assertSessionHasNoErrors();
        $this->assertEquals('RINV-00100', $sale->fresh()->invoice_number);
        $this->assertEquals('RCPT-000100', $sale->fresh()->receipt_number);
        $this->assertEquals(98, $batch->fresh()->quantity_available);
        $this->assertEquals(2, $sale->items()->sole()->quantity);
        $this->assertEquals(100, $sale->fresh()->total_amount);
        $this->assertEquals(today()->toDateString(), $sale->fresh()->sale_date->toDateString());
    }

    public function test_fiscal_invoice_and_closed_cash_day_are_protected(): void
    {
        [$user, $sale, $customer] = $this->context();
        $payload = $this->payload($user, $sale, $customer);
        $document = EfrisDocument::create(['sale_id' => $sale->id, 'client_id' => $user->client_id,
            'branch_id' => $user->branch_id, 'environment' => 'sandbox', 'document_kind' => 'invoice',
            'status' => 'accepted', 'next_action' => 'complete']);
        $this->put(route('sales.correctApprovedPayment', $sale), $payload)->assertSessionHasErrorsIn('paymentCorrection');
        $document->delete();
        CashDrawerSession::create(['client_id' => $user->client_id, 'branch_id' => $user->branch_id,
            'session_date' => today(), 'day_closed_at' => now()]);
        $this->put(route('sales.correctApprovedPayment', $sale), $payload)->assertSessionHasErrorsIn('paymentCorrection');
        $this->assertEquals(100, $sale->fresh()->amount_paid);
    }

    public function test_cancelled_and_insurance_sales_are_protected(): void
    {
        [$user, $sale, $customer] = $this->context();
        $payload = $this->payload($user, $sale, $customer);
        $sale->update(['status' => 'cancelled']);
        $this->put(route('sales.correctApprovedPayment', $sale), $payload)->assertSessionHasErrorsIn('paymentCorrection');
        $sale->update(['status' => 'approved', 'payment_type' => 'insurance']);
        $this->put(route('sales.correctApprovedPayment', $sale), $payload)->assertSessionHasErrorsIn('paymentCorrection');
        $this->assertEquals(100, $sale->fresh()->amount_paid);
    }

    public function test_staff_without_approved_edit_permission_cannot_correct_payments(): void
    {
        [$user, $sale, $customer] = $this->context();
        $payload = $this->payload($user, $sale, $customer);
        $role = Role::create(['client_id' => $user->client_id, 'name' => 'Read Only Test', 'code' => 'read-only-test']);
        $user->roles()->sync([$role->id]);
        $this->actingAs($user->fresh())->put(route('sales.correctApprovedPayment', $sale), $payload)->assertForbidden();
        $this->assertEquals(100, $sale->fresh()->amount_paid);
    }

    public function test_audit_failure_rolls_back_payment_and_customer_balance(): void
    {
        [$user, $sale, $customer] = $this->context();
        $payload = $this->payload($user, $sale, $customer);
        $this->mock(AuditTrail::class)->shouldReceive('record')->once()->andThrow(new \RuntimeException('Audit unavailable'));
        $this->put(route('sales.correctApprovedPayment', $sale), $payload)->assertStatus(500);
        $this->assertEquals(100, $sale->fresh()->amount_paid);
        $this->assertEquals(0, $customer->fresh()->outstanding_balance);
    }

    private function payload(User $user, Sale $sale, Customer $customer, float $received = 0): array
    {
        $page = $this->actingAs($user)->get(route('sales.editApproved', $sale))->assertOk();
        $page->assertSee('Correct Payment')->assertSee('Actually received at approval');
        return ['payment_correction_token' => $page->viewData('paymentCorrectionToken'),
            'correction_customer_id' => $customer->id, 'corrected_amount_received' => $received,
            'correction_reason' => 'Phone promise recorded as received.', 'confirm_unreceived_payment' => 1];
    }

    private function assertReceipts(User $user, float $expected): void
    {
        $summary = MoneyReceivedReport::summarize(Sale::where('client_id', $user->client_id), Payment::where('client_id', $user->client_id));
        $this->assertEquals($expected, $summary['total']);
        $this->assertEquals($expected, collect($summary['byMethod'])->firstWhere('key', 'cash')['amount']);
        $this->assertEquals($expected, app(CashDrawerService::class)->summary($user->client_id, $user->branch_id)['cash_sales_total']);
    }

    private function context(): array
    {
        $client = DB::table('clients')->insertGetId(['name' => 'VIP PHARMACY', 'business_mode' => 'both', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $branch = DB::table('branches')->insertGetId(['client_id' => $client, 'name' => 'Main', 'code' => 'MAIN', 'is_main' => true, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $user = User::factory()->create(['client_id' => $client, 'branch_id' => $branch, 'is_active' => true]);
        app(AccessControlBootstrapper::class)->ensureForUser($user);
        $user->roles()->sync([Role::where('client_id', $client)->where('name', 'Admin')->firstOrFail()->id]);
        $customer = Customer::create(['client_id' => $client, 'name' => 'Phone Customer', 'is_active' => true, 'outstanding_balance' => 0]);
        $product = Product::create(['client_id' => $client, 'branch_id' => $branch, 'name' => 'Correction Test Medicine',
            'is_active' => true, 'purchase_price' => 10, 'retail_price' => 50, 'wholesale_price' => 40]);
        $batch = ProductBatch::create(['client_id' => $client, 'branch_id' => $branch, 'product_id' => $product->id,
            'batch_number' => 'CORRECTION-001', 'expiry_date' => today()->addYear(), 'quantity_received' => 100,
            'quantity_available' => 98, 'reserved_quantity' => 0, 'is_active' => true, 'purchase_price' => 10,
            'retail_price' => 50, 'wholesale_price' => 40]);
        $sale = Sale::create(['client_id' => $client, 'branch_id' => $branch, 'served_by' => $user->id, 'approved_by' => $user->id,
            'invoice_number' => 'RINV-00100', 'receipt_number' => 'RCPT-000100', 'sale_type' => 'retail',
            'status' => 'approved', 'payment_type' => 'cash', 'payment_method' => 'Cash', 'subtotal' => 100,
            'total_amount' => 100, 'discount_amount' => 0, 'tax_amount' => 0, 'amount_paid' => 100,
            'amount_received' => 100, 'upfront_amount_paid' => 100, 'balance_due' => 0, 'sale_date' => today(),
            'approved_at' => now(), 'notes' => 'Original phone order', 'is_active' => true]);
        SaleItem::create(['sale_id' => $sale->id, 'product_id' => $product->id, 'product_batch_id' => $batch->id,
            'quantity' => 2, 'purchase_price' => 10, 'unit_price' => 50, 'discount_amount' => 0, 'total_amount' => 100]);
        return [$user->fresh(), $sale->fresh(), $customer, $batch->fresh()];
    }
}
