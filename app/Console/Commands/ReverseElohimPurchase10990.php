<?php

namespace App\Console\Commands;

use App\Models\Purchase;
use App\Models\ProductBatch;
use App\Models\StockMovement;
use App\Support\AuditTrail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class ReverseElohimPurchase10990 extends Command
{
    protected $signature = 'purchase:reverse-elohim-10990
        {--execute : Apply the audited net-zero correction}
        {--confirm= : Must be ELOHIM DRUGSHOP}
        {--backup-confirmed : Confirm a fresh database backup exists}';

    protected $description = 'Preview or reverse the verified full return and same-day cash refund for Elohim invoice 10990';

    public function handle(AuditTrail $audit): int
    {
        if ($this->option('execute') && ($this->option('confirm') !== 'ELOHIM DRUGSHOP' || !$this->option('backup-confirmed'))) {
            $this->error('Execution requires --confirm="ELOHIM DRUGSHOP" --backup-confirmed.');
            return self::FAILURE;
        }

        try {
            return DB::transaction(function () use ($audit) {
                $p = Purchase::whereKey(1708)->where('client_id', 5)->where('branch_id', 5)
                    ->where('invoice_number', '10990')->lockForUpdate()->sole();
                $this->check($p->client?->name === 'ELOHIM DRUGSHOP' && $p->branch?->name === 'Main Branch', 'Tenant or branch mismatch.');
                $this->check((int) $p->supplier_id === 557 && $p->purchase_date->format('Y-m-d') === '2026-10-03', 'Supplier or date mismatch.');
                if (!$p->is_active && $p->invoice_status === 'returned_refunded' && (float) $p->total_amount === 0.0) {
                    $this->info('Already reversed. No records changed.');
                    return self::SUCCESS;
                }
                $this->check($p->is_active && $p->invoice_status === 'fully_received', 'Unexpected purchase state.');
                $this->check((float) $p->total_amount === 943400.0 && (float) $p->amount_paid === 943400.0 && (float) $p->balance_due === 0.0, 'Purchase totals changed.');
                $items = $p->items()->withTrashed()->lockForUpdate()->get();
                $batches = ProductBatch::whereIn('purchase_item_id', $items->pluck('id'))->lockForUpdate()->get();
                $active = $batches->where('is_active', true);
                $this->check($items->whereNull('deleted_at')->count() === 52 && $items->count() === 73 && $batches->count() === 73 && $active->count() === 52, 'Item or batch counts changed.');
                $this->check((float) $active->sum('quantity_available') === 3577.0, 'Available quantities changed.');
                foreach ($batches as $batch) {
                    $item = $items->firstWhere('id', $batch->purchase_item_id);
                    $this->check((int) $batch->client_id === 5 && (int) $batch->branch_id === 5 && (int) $item->product_id === (int) $batch->product_id, 'Batch ownership mismatch.');
                    $this->check($batches->where('purchase_item_id', $item->id)->count() === 1, 'Multiple batches share a purchase item.');
                    $this->check((bool) $batch->is_active === ($item->deleted_at === null), 'Batch/item active state mismatch.');
                    $this->check((float) $batch->reserved_quantity === 0.0, 'Reserved stock found.');
                    $this->check((float) $batch->quantity_available === (float) $batch->quantity_received, 'Stock has been consumed or adjusted.');
                    $this->check($batch->is_active || (float) $batch->quantity_available === 0.0, 'Inactive batch still has stock.');
                }
                $sales = DB::table('sale_items')->whereIn('product_batch_id', $batches->pluck('id'))->lockForUpdate()->get();
                foreach ($sales as $row) {
                    $sale = DB::table('sales')->where('id', $row->sale_id)->lockForUpdate()->first();
                    $this->check($sale && (int) $sale->client_id === 5 && (int) $sale->branch_id === 5 && $sale->status === 'cancelled' && !$sale->is_active, 'A linked sale is not cancelled/inactive.');
                }
                $this->check(!DB::table('stock_adjustments')->whereIn('product_batch_id', $batches->pluck('id'))->exists(), 'Stock adjustments found.');
                $movements = DB::table('stock_movements')->whereIn('product_batch_id', $batches->pluck('id'))->lockForUpdate()->get();
                $this->check($movements->count() === 94 && $movements->where('movement_type', 'purchase_in')->count() === 73 && $movements->where('movement_type', 'purchase_item_removal')->count() === 21, 'Stock movement history changed.');
                foreach ($batches as $batch) {
                    $history = $movements->where('product_batch_id', $batch->id);
                    $this->check($history->where('movement_type', 'purchase_in')->count() === 1 && $history->count() === ($batch->is_active ? 1 : 2), 'Unexpected per-batch movement history.');
                    $this->check(abs($history->sum('quantity_in') - $history->sum('quantity_out') - (float) $batch->quantity_available) < 0.001, 'Movement quantity does not reconcile to stock.');
                    foreach ($history as $movement) {
                        $this->check((int) $movement->client_id === 5 && (int) $movement->branch_id === 5 && $movement->reference_type === 'purchase' && (int) $movement->reference_id === 1708, 'Movement ownership/reference mismatch.');
                        if ($batch->is_active) {
                            $this->check($movement->created_at && substr($movement->created_at, 0, 10) === '2026-10-03', 'Receipt movement is not dated 3 October; review before backdating a return.');
                        }
                    }
                }
                $payments = $p->supplierPayments()->lockForUpdate()->get();
                $expected = [194 => 67100, 195 => 53300, 204 => 530900, 205 => 292100];
                $this->check($payments->count() === 4, 'Payment count changed.');
                foreach ($payments as $payment) {
                    $this->check(isset($expected[$payment->id]) && (float) $payment->amount === (float) $expected[$payment->id], 'Payment amount or identity changed.');
                    $this->check((int) $payment->client_id === 5 && (int) $payment->branch_id === 5 && (int) $payment->supplier_id === 557, 'Payment ownership mismatch.');
                    $this->check($payment->status === 'paid' && $payment->payment_method === 'petty_cash' && $payment->payment_date->format('Y-m-d') === '2026-10-03', 'Payment status, method or date changed.');
                }

                $this->info('ELOHIM / Main Branch / invoice 10990: 52 active batches, 3577 units, UGX 943400 fully refunded in cash on 2026-10-03.');
                $this->line('Archive original values in Audit Log; net invoice and four payment amounts to zero; soft-delete 52 lines and deactivate their batches.');
                $this->line('The 21 previously removed batches, cancelled sales, product prices and VIP remain unchanged.');
                if (!$this->option('execute')) {
                    $this->info('Preview only. Nothing changed.');
                    return self::SUCCESS;
                }

                $before = ['purchase' => $p->toArray(), 'items' => $items->toArray(), 'batches' => $batches->toArray(), 'payments' => $payments->toArray()];
                $reason = 'Full goods return and UGX 943400 cash refund on 2026-10-03, confirmed by owner. Original amounts retained in audit event purchase.return_refund_corrected. Net-zero correction, not an unpaid purchase.';
                foreach ($active as $batch) {
                    $quantity = (float) $batch->quantity_available;
                    $batch->update(['quantity_received' => 0, 'quantity_available' => 0, 'reserved_quantity' => 0, 'is_active' => false]);
                    $movement = new StockMovement([
                        'client_id' => 5, 'branch_id' => 5, 'product_id' => $batch->product_id,
                        'product_batch_id' => $batch->id, 'movement_type' => 'purchase_item_removal',
                        'reference_type' => 'purchase', 'reference_id' => $p->id,
                        'quantity_in' => 0, 'quantity_out' => $quantity,
                        'balance_after' => (float) DB::table('stock_movements')->where('client_id', 5)->where('branch_id', 5)
                            ->where('product_id', $batch->product_id)->where('created_at', '<=', '2026-10-03 23:59:59')
                            ->selectRaw('COALESCE(SUM(quantity_in - quantity_out), 0) as balance')->value('balance') - $quantity,
                        'note' => $reason, 'created_by' => null,
                    ]);
                    $movement->created_at = '2026-10-03 23:59:59';
                    $movement->save();
                }
                foreach ($items->whereNull('deleted_at') as $item) {
                    $item->delete();
                }
                foreach ($payments as $payment) {
                    $payment->update(['amount' => 0, 'status' => 'refunded', 'notes' => trim($payment->notes . "\nOriginal paid amount: " . $payment->amount . '. ' . $reason)]);
                }
                $p->update(['subtotal' => 0, 'discount_amount' => 0, 'tax_amount' => 0, 'total_amount' => 0,
                    'amount_paid' => 0, 'balance_due' => 0, 'is_active' => false,
                    'invoice_status' => 'returned_refunded', 'payment_status' => 'refunded',
                    'notes' => trim($p->notes . "\n" . $reason)]);
                $audit->record(null, 'purchase.return_refund_corrected', 'Purchases', 'Full Return and Cash Refund', $reason, [
                    'subject' => $p, 'subject_label' => 'ELOHIM invoice 10990', 'reason' => $reason,
                    'old_values' => $before, 'new_values' => ['purchase' => $p->fresh()->toArray(), 'stock_removed' => 3577, 'cash_refunded' => 943400, 'effective_date' => '2026-10-03'],
                    'context' => ['command' => $this->getName(), 'backup_confirmed' => true, 'linked_cancelled_sales_unchanged' => $sales->pluck('sale_id')->all()],
                ]);
                $this->info('Correction committed with audit history. Invoice and payments now have zero net value.');
                return self::SUCCESS;
            });
        } catch (Throwable $e) {
            $this->error('Stopped; no changes saved: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function check(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }
}
