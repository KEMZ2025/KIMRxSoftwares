<?php

namespace Tests\Feature;

use App\Models\{AuditLog, ProductBatch, Purchase, PurchaseCancellation, PurchaseItem, StockMovement, SupplierPayment, User};
use App\Support\{AuditTrail, PurchaseReversalService};
use App\Support\Accounting\AccountingLedgerService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PurchaseReversalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $client = DB::table('clients')->insertGetId(['name' => 'Test Pharmacy', 'is_active' => true]);
        $branch = DB::table('branches')->insertGetId(['client_id' => $client, 'name' => 'Main Branch', 'code' => 'M' . $client, 'is_active' => true]);
        $user = User::factory()->create(['client_id' => $client, 'branch_id' => $branch, 'is_super_admin' => true]);
        $supplier = DB::table('suppliers')->insertGetId(['client_id' => $client, 'name' => 'Test Supplier']);
        $product = DB::table('products')->insertGetId(['client_id' => $client, 'branch_id' => $branch, 'name' => 'Medicine']);
        $p = Purchase::create(['client_id' => $client, 'branch_id' => $branch, 'supplier_id' => $supplier,
            'invoice_number' => 'PUR-' . $client, 'purchase_date' => '2026-09-01', 'subtotal' => 100, 'total_amount' => 100,
            'amount_paid' => 100, 'balance_due' => 0, 'invoice_status' => 'fully_received', 'payment_status' => 'paid', 'is_active' => true]);
        $item = PurchaseItem::create(['purchase_id' => $p->id, 'product_id' => $product, 'batch_number' => 'B1',
            'quantity' => 10, 'received_quantity' => 10, 'unit_cost' => 10, 'total_cost' => 100]);
        $batch = ProductBatch::create(['client_id' => $client, 'branch_id' => $branch, 'product_id' => $product,
            'purchase_item_id' => $item->id, 'supplier_id' => $supplier, 'batch_number' => 'B1',
            'quantity_received' => 10, 'quantity_available' => 10, 'reserved_quantity' => 0, 'is_active' => true]);
        $receipt = new StockMovement(['client_id' => $client, 'branch_id' => $branch, 'product_id' => $product,
            'product_batch_id' => $batch->id, 'movement_type' => 'purchase_in', 'reference_type' => 'purchase',
            'reference_id' => $p->id, 'quantity_in' => 10, 'quantity_out' => 0, 'balance_after' => 10]);
        $receipt->created_at = '2026-09-01 10:00:00';
        $receipt->save();
        $payment = SupplierPayment::create(['client_id' => $client, 'branch_id' => $branch, 'supplier_id' => $supplier,
            'purchase_id' => $p->id, 'paid_by' => $user->id, 'amount' => 100, 'payment_method' => 'petty_cash',
            'payment_date' => '2026-09-01 12:00:00', 'status' => 'paid', 'source' => 'invoice_entry']);
        return [$user, $p, $batch, $payment];
    }

    private function data(Purchase $p): array
    {
        return ['mode' => 'refund', 'effective_date' => '2026-09-03', 'reason' => 'All medicines returned and money refunded.',
            'money_confirmed' => 1, 'goods_returned' => 1, 'reverse_payments' => 1, 'refund_method' => 'petty_cash',
            'confirm_invoice' => $p->invoice_number, 'late_entries_confirmed' => 0];
    }

    public function test_full_cancellation_preserves_originals_and_balances_accounting(): void
    {
        [$user, $p, $batch, $payment] = $this->fixture();
        [$otherUser, $otherPurchase, $otherBatch] = $this->fixture();
        app(PurchaseReversalService::class)->cancel($user, $p->id, $this->data($p));
        $this->assertFalse($p->fresh()->is_active);
        $this->assertFalse(Purchase::operational()->whereKey($p->id)->exists());
        $this->assertEquals(100, $p->fresh()->total_amount);
        $this->assertEquals(0, $p->fresh()->balance_due);
        $this->assertEquals(0, $batch->fresh()->quantity_available);
        $this->assertEquals(100, $payment->fresh()->amount);
        $this->assertEquals(0, $p->supplierPayments()->sum('amount'));
        $this->assertDatabaseHas('supplier_payments', ['reversal_of_payment_id' => $payment->id, 'amount' => -100]);
        $this->assertEquals(100, PurchaseCancellation::sole()->snapshot['purchase']['total_amount']);
        $this->assertEquals(10, $otherBatch->fresh()->quantity_available);
        $this->assertTrue($otherPurchase->fresh()->is_active);
        $ledger = app(AccountingLedgerService::class);
        $before = $ledger->journalEntries($user, Carbon::parse('2026-09-01'), Carbon::parse('2026-09-02')->endOfDay());
        $after = $ledger->journalEntries($user, Carbon::parse('2026-09-01'), Carbon::parse('2026-09-04')->endOfDay());
        $inventoryBefore = collect($before->pluck('lines')->flatten(1))->where('account_code', '12100');
        $this->assertEquals(100, $inventoryBefore->sum('debit') - $inventoryBefore->sum('credit'));
        foreach (collect($after->pluck('lines')->flatten(1))->groupBy('account_code') as $lines) {
            $this->assertEquals($lines->sum('debit'), $lines->sum('credit'));
        }
        $this->assertDatabaseCount('audit_logs', 2);
        try {
            app(PurchaseReversalService::class)->cancel($user, $p->id, $this->data($p));
            $this->fail('Second cancellation should fail.');
        } catch (ValidationException $e) {
            $this->assertDatabaseCount('purchase_cancellations', 1);
            $this->assertEquals(2, $p->supplierPayments()->count());
        }
    }

    public function test_payment_reversal_restores_balance_and_cannot_be_repeated(): void
    {
        [$user, $p, $batch, $payment] = $this->fixture();
        app(PurchaseReversalService::class)->reversePayment($user, $p->id, $payment->id, $this->data($p));
        $this->assertEquals(100, $p->fresh()->balance_due);
        $this->assertEquals(0, $p->fresh()->amount_paid);
        $this->assertEquals('reversed_invoice_entry', $payment->fresh()->source);
        $this->assertEquals(10, $batch->fresh()->quantity_available);
        $this->expectException(ValidationException::class);
        app(PurchaseReversalService::class)->reversePayment($user, $p->id, $payment->id, $this->data($p));
    }

    public function test_reserved_stock_blocks_every_change(): void
    {
        [$user, $p, $batch] = $this->fixture();
        $batch->update(['reserved_quantity' => 1]);
        try {
            app(PurchaseReversalService::class)->cancel($user, $p->id, $this->data($p));
            $this->fail('Reserved stock must block cancellation.');
        } catch (ValidationException $e) {
            $this->assertEquals(100, $p->fresh()->amount_paid);
            $this->assertDatabaseCount('purchase_cancellations', 0);
            $this->assertDatabaseCount('audit_logs', 0);
        }
    }

    public function test_audit_failure_rolls_back_payments_and_stock(): void
    {
        [$user, $p, $batch] = $this->fixture();
        $this->mock(AuditTrail::class)->shouldReceive('record')->andThrow(new \RuntimeException('Audit failed'));
        try {
            app(PurchaseReversalService::class)->cancel($user, $p->id, $this->data($p));
            $this->fail('Audit failure must stop the transaction.');
        } catch (\RuntimeException $e) {
            $this->assertEquals(100, $p->fresh()->amount_paid);
            $this->assertEquals(10, $batch->fresh()->quantity_available);
            $this->assertDatabaseCount('supplier_payments', 1);
            $this->assertDatabaseCount('purchase_cancellations', 0);
        }
    }

    public function test_cross_tenant_purchase_cannot_be_reversed(): void
    {
        [$user] = $this->fixture();
        [, $p, , $payment] = $this->fixture();
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        app(PurchaseReversalService::class)->reversePayment($user, $p->id, $payment->id, $this->data($p));
    }

    public function test_form_renders_for_authorized_user(): void
    {
        [$user, $p] = $this->fixture();
        $this->actingAs($user)->withSession(['super_admin.context' => ['client_id' => $user->client_id, 'branch_id' => $user->branch_id]])
            ->get(route('purchases.reversal', $p))->assertOk()->assertSee('Confirm cancellation');
    }

    public function test_active_sales_block_but_cancelled_sales_are_preserved(): void
    {
        [$user, $p, $batch] = $this->fixture();
        $sale = DB::table('sales')->insertGetId(['client_id' => $user->client_id, 'branch_id' => $user->branch_id,
            'invoice_number' => 'SALE1', 'sale_type' => 'retail', 'sale_date' => '2026-09-02', 'status' => 'pending', 'is_active' => 1]);
        DB::table('sale_items')->insert(['sale_id' => $sale, 'product_id' => $batch->product_id,
            'product_batch_id' => $batch->id, 'quantity' => 1, 'unit_price' => 12, 'total_amount' => 12]);
        try {
            app(PurchaseReversalService::class)->cancel($user, $p->id, $this->data($p));
            $this->fail('Active sale must block cancellation.');
        } catch (ValidationException $e) {
            $this->assertDatabaseCount('supplier_payments', 1);
        }
        DB::table('sales')->where('id', $sale)->update(['status' => 'cancelled', 'is_active' => 0]);
        app(PurchaseReversalService::class)->cancel($user, $p->id, $this->data($p));
        $this->assertDatabaseHas('sales', ['id' => $sale, 'status' => 'cancelled']);
        $this->assertDatabaseCount('sale_items', 1);
    }

    public function test_confirmed_late_entries_do_not_create_stock_or_cash_before_receipt(): void
    {
        [$user, $p, $batch, $payment] = $this->fixture();
        StockMovement::where('product_batch_id', $batch->id)->update(['created_at' => '2026-09-04 10:00:00']);
        $payment->update(['payment_date' => '2026-09-04 10:30:00']);
        try {
            app(PurchaseReversalService::class)->cancel($user, $p->id, $this->data($p));
            $this->fail('Late entries must require confirmation.');
        } catch (ValidationException $e) {
            $this->assertDatabaseCount('purchase_cancellations', 0);
        }
        $data = $this->data($p);
        $data['late_entries_confirmed'] = 1;
        app(PurchaseReversalService::class)->cancel($user, $p->id, $data);
        $this->assertSame('2026-09-04 10:00:00', StockMovement::where('movement_type', 'purchase_item_removal')->sole()->created_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-04 10:30:00', SupplierPayment::where('reversal_of_payment_id', $payment->id)->sole()->payment_date->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-03', PurchaseCancellation::sole()->effective_at->toDateString());
    }

    public function test_authorized_post_cancels_and_validation_preserves_values(): void
    {
        [$user, $p] = $this->fixture();
        $this->actingAs($user)->withSession(['super_admin.context' => ['client_id' => $user->client_id, 'branch_id' => $user->branch_id]]);
        $data = $this->data($p) + ['action' => 'cancel'];
        $data['confirm_invoice'] = 'WRONG';
        $this->from(route('purchases.reversal', $p))->post(route('purchases.reversal.store', $p), $data)
            ->assertSessionHasErrors('correction')->assertSessionHasInput('reason', $data['reason']);
        $this->assertDatabaseCount('purchase_cancellations', 0);
        $data['confirm_invoice'] = $p->invoice_number;
        $this->post(route('purchases.reversal.store', $p), $data)->assertRedirect(route('purchases.index'));
        $this->assertDatabaseCount('purchase_cancellations', 1);
    }

    public function test_unprivileged_user_is_rejected(): void
    {
        [$user, $p, , $payment] = $this->fixture();
        $restricted = \Mockery::mock(User::class)->makePartial();
        $restricted->setRawAttributes($user->getAttributes());
        $restricted->shouldReceive('hasPermission')->with('purchases.correct_items')->andReturn(false);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(PurchaseReversalService::class)->reversePayment($restricted, $p->id, $payment->id, $this->data($p));
    }

    public function test_existing_item_removal_works_after_payment_reversal(): void
    {
        [$user, $p, $batch, $payment] = $this->fixture();
        PurchaseItem::create(['purchase_id' => $p->id, 'product_id' => $batch->product_id, 'batch_number' => 'UNRECEIVED',
            'quantity' => 0, 'received_quantity' => 0, 'unit_cost' => 0, 'total_cost' => 0]);
        app(PurchaseReversalService::class)->reversePayment($user, $p->id, $payment->id, $this->data($p));
        $this->actingAs($user)->withSession(['super_admin.context' => ['client_id' => $user->client_id, 'branch_id' => $user->branch_id]])
            ->delete(route('purchases.items.destroy', [$p, $batch->purchase_item_id]), ['reason' => 'All these items were returned.'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertEquals(0, $batch->fresh()->quantity_available);
        $this->assertEquals(100, $payment->fresh()->amount);
        $this->assertEquals(0, $p->supplierPayments()->sum('amount'));
        $this->assertEquals(0, $p->fresh()->total_amount);
    }

    public function test_cancelled_purchase_cannot_be_updated_by_stale_request(): void
    {
        [$user, $p] = $this->fixture();
        app(PurchaseReversalService::class)->cancel($user, $p->id, $this->data($p));
        $this->expectException(ValidationException::class);
        $p->update(['total_amount' => 500]);
    }
}
