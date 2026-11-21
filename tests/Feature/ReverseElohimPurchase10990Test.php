<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ProductBatch;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\StockMovement;
use App\Models\SupplierPayment;
use App\Support\AuditTrail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReverseElohimPurchase10990Test extends TestCase
{
    use RefreshDatabase;

    public function test_preview_and_execution_are_scoped_audited_and_repeatable(): void
    {
        $p = $this->fixture();
        $inactive = ProductBatch::where('is_active', false)->get()->toArray();
        $this->artisan('purchase:reverse-elohim-10990')->assertSuccessful();
        $this->assertEquals(943400, $p->fresh()->total_amount);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->execute()->assertSuccessful();
        $this->assertEquals(0, $p->fresh()->total_amount);
        $this->assertEquals(0, SupplierPayment::sum('amount'));
        $this->assertEquals(0, ProductBatch::where('client_id', 5)->sum('quantity_available'));
        $this->assertEquals(0, $p->items()->count());
        $this->assertEquals(73, $p->items()->withTrashed()->count());
        foreach ($inactive as $batch) {
            $this->assertEquals($batch, ProductBatch::findOrFail($batch['id'])->toArray());
        }
        $this->assertDatabaseHas('product_batches', ['client_id' => 6, 'quantity_available' => 10, 'is_active' => 1]);
        $log = AuditLog::sole();
        $this->assertEquals(943400, $log->old_values['purchase']['total_amount']);
        $this->assertEquals(943400, collect($log->old_values['payments'])->sum('amount'));
        $this->assertSame('2026-10-03', $log->new_values['effective_date']);
        $user = new \App\Models\User(['client_id' => 5, 'branch_id' => 5]);
        $journal = app(\App\Support\Accounting\AccountingLedgerService::class)->journalEntries(
            $user, \Carbon\Carbon::parse('2026-10-01'), \Carbon\Carbon::parse('2026-10-31')->endOfDay()
        );
        $this->assertEquals(0, $journal->sum('debit_total'));
        $this->assertEquals(0, $journal->sum('credit_total'));
        $this->assertEquals(146, StockMovement::count());
        $this->assertNull((new Purchase)->resolveRouteBinding(1708));
        $this->execute()->assertSuccessful();
        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertEquals(146, StockMovement::count());
    }

    public function test_changed_payment_or_missing_confirmation_stops_without_writes(): void
    {
        $p = $this->fixture();
        $this->artisan('purchase:reverse-elohim-10990', ['--execute' => true])->assertFailed();
        SupplierPayment::findOrFail(194)->update(['amount' => 67101]);
        $this->execute()->assertFailed();
        $this->assertEquals(943400, $p->fresh()->total_amount);
        $this->assertEquals(52, $p->items()->count());
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_reserved_stock_stops_without_writes(): void
    {
        $p = $this->fixture();
        ProductBatch::where('client_id', 5)->where('is_active', true)->first()->update(['reserved_quantity' => 1]);
        $this->execute()->assertFailed();
        $this->assertEquals(943400, $p->fresh()->total_amount);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_cancelled_sales_remain_untouched_but_active_sales_block_reversal(): void
    {
        $this->fixture();
        $batch = ProductBatch::where('client_id', 5)->where('is_active', true)->first();
        $sale = DB::table('sales')->insertGetId(['client_id' => 5, 'branch_id' => 5, 'invoice_number' => 'RINV-TEST',
            'sale_type' => 'retail', 'sale_date' => '2026-10-03', 'status' => 'pending', 'is_active' => 1]);
        DB::table('sale_items')->insert(['sale_id' => $sale, 'product_id' => $batch->product_id, 'product_batch_id' => $batch->id,
            'quantity' => 1, 'unit_price' => 10, 'total_amount' => 10]);
        $this->execute()->assertFailed();
        DB::table('sales')->where('id', $sale)->update(['status' => 'cancelled', 'is_active' => 0]);
        $before = DB::table('sales')->where('id', $sale)->first();
        $this->execute()->assertSuccessful();
        $this->assertEquals($before, DB::table('sales')->where('id', $sale)->first());
        $this->assertDatabaseCount('sale_items', 1);
    }

    public function test_audit_failure_rolls_everything_back(): void
    {
        $p = $this->fixture(true);
        $this->mock(AuditTrail::class)->shouldReceive('record')->once()->andThrow(new \RuntimeException('Audit failed'));
        $this->execute()->assertFailed();
        $this->assertEquals(943400, $p->fresh()->total_amount);
        $this->assertEquals(943400, SupplierPayment::sum('amount'));
        $this->assertEquals(52, $p->items()->count());
        $this->assertEquals(3577, ProductBatch::where('client_id', 5)->sum('quantity_available'));
        $this->assertEquals(94, StockMovement::count());
    }

    public function test_verified_late_entries_are_offset_without_altering_original_receipts(): void
    {
        $this->fixture(true);
        $ids = ProductBatch::where('client_id', 5)->where('is_active', true)->pluck('id');
        $receipts = StockMovement::whereIn('product_batch_id', $ids)->get();
        $before = $receipts->toArray();
        $this->artisan('purchase:reverse-elohim-10990')->assertSuccessful();
        $this->assertEquals(94, StockMovement::count());
        $this->execute()->assertSuccessful();
        $this->assertEquals($before, StockMovement::whereIn('id', $receipts->pluck('id'))->get()->toArray());
        foreach ($receipts as $receipt) {
            $offset = StockMovement::where('product_batch_id', $receipt->product_batch_id)->where('movement_type', 'purchase_item_removal')->sole();
            $this->assertTrue($offset->created_at->gte($receipt->created_at));
            $this->assertEquals($receipt->quantity_in, $offset->quantity_out);
            if ($receipt->created_at->format('Y-m-d') === '2026-10-04') {
                $this->assertEquals($receipt->created_at, $offset->created_at);
            }
        }
        foreach (['2026-10-03', '2026-10-04'] as $day) {
            $history = StockMovement::whereIn('product_batch_id', $ids)->whereDate('created_at', $day)->get();
            $this->assertEquals($history->sum('quantity_in'), $history->sum('quantity_out'));
        }
        $this->assertSame('2026-10-03', AuditLog::sole()->new_values['effective_date']);
        $this->assertCount(94, AuditLog::sole()->old_values['stock_movements']);
        $this->execute()->assertSuccessful();
        $this->assertEquals(146, StockMovement::count());
    }

    public function test_unverified_late_entry_pattern_is_rejected(): void
    {
        $this->fixture(true);
        $receipt = StockMovement::where('movement_type', 'purchase_in')->whereDate('created_at', '2026-10-04')->first();
        $receipt->created_at = '2026-10-03 12:00:00';
        $receipt->save();
        $this->execute()->assertFailed();
        $this->assertEquals(943400, Purchase::findOrFail(1708)->total_amount);
        $this->assertDatabaseCount('audit_logs', 0);
        $receipt->created_at = '2026-10-05 12:00:00';
        $receipt->save();
        $this->execute()->assertFailed();
        $this->assertEquals(94, StockMovement::count());
    }

    private function execute()
    {
        return $this->artisan('purchase:reverse-elohim-10990', ['--execute' => true, '--confirm' => 'ELOHIM DRUGSHOP', '--backup-confirmed' => true]);
    }

    private function fixture(bool $lateEntries = false): Purchase
    {
        DB::table('clients')->insert(['id' => 5, 'name' => 'ELOHIM DRUGSHOP']);
        DB::table('branches')->insert(['id' => 5, 'client_id' => 5, 'name' => 'Main Branch', 'code' => 'MAIN']);
        DB::table('suppliers')->insert(['id' => 557, 'client_id' => 5, 'name' => 'ZEE PHARMACEUTICALS LTD-BWEYOGERERE']);
        $product = DB::table('products')->insertGetId(['client_id' => 5, 'branch_id' => 5, 'name' => 'Test medicine']);
        $p = new Purchase(['client_id' => 5, 'branch_id' => 5, 'supplier_id' => 557, 'invoice_number' => '10990',
            'purchase_date' => '2026-10-03', 'subtotal' => 943400, 'total_amount' => 943400, 'amount_paid' => 943400,
            'balance_due' => 0, 'invoice_status' => 'fully_received', 'payment_status' => 'paid', 'is_active' => true]);
        $p->id = 1708;
        $p->save();
        for ($i = 0; $i < 73; $i++) {
            $active = $i < 52;
            $q = $i === 0 ? 3526 : 1;
            if ($lateEntries) {
                $q = $i < 2 ? 135 : ($i === 2 ? 3258 : 1);
            }
            $item = PurchaseItem::create(['purchase_id' => 1708, 'product_id' => $product, 'batch_number' => 'B' . $i,
                'quantity' => $q, 'unit_cost' => 1, 'total_cost' => $q]);
            $batch = ProductBatch::create(['client_id' => 5, 'branch_id' => 5, 'product_id' => $product, 'supplier_id' => 557,
                'purchase_item_id' => $item->id, 'batch_number' => 'B' . $i, 'quantity_received' => $active ? $q : 0,
                'quantity_available' => $active ? $q : 0, 'reserved_quantity' => 0, 'is_active' => $active]);
            $base = ['client_id' => 5, 'branch_id' => 5, 'product_id' => $product, 'product_batch_id' => $batch->id,
                'reference_type' => 'purchase', 'reference_id' => 1708, 'balance_after' => $q];
            $receipt = new StockMovement($base + ['movement_type' => 'purchase_in', 'quantity_in' => $q, 'quantity_out' => 0]);
            $receipt->created_at = $lateEntries && $i < 2 ? '2026-10-04 09:00:00' : '2026-10-03 12:00:00';
            $receipt->save();
            if (!$active) {
                $item->delete();
                StockMovement::create($base + ['movement_type' => 'purchase_item_removal', 'quantity_in' => 0, 'quantity_out' => $q]);
            }
        }
        foreach ([194 => 67100, 195 => 53300, 204 => 530900, 205 => 292100] as $id => $amount) {
            $payment = new SupplierPayment(['client_id' => 5, 'branch_id' => 5, 'supplier_id' => 557, 'purchase_id' => 1708,
                'payment_method' => 'petty_cash', 'amount' => $amount, 'payment_date' => '2026-10-03', 'status' => 'paid', 'source' => 'manual']);
            $payment->id = $id;
            $payment->save();
        }
        DB::table('clients')->insert(['id' => 6, 'name' => 'VIP PHARMACY']);
        DB::table('branches')->insert(['id' => 6, 'client_id' => 6, 'name' => 'Main Branch', 'code' => 'VIP']);
        $vipProduct = DB::table('products')->insertGetId(['client_id' => 6, 'branch_id' => 6, 'name' => 'VIP medicine']);
        ProductBatch::create(['client_id' => 6, 'branch_id' => 6, 'product_id' => $vipProduct, 'batch_number' => 'VIP', 'quantity_received' => 10, 'quantity_available' => 10, 'is_active' => true]);
        return $p;
    }
}
