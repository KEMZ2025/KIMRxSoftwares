<?php

namespace App\Support;

use App\Models\{ProductBatch, Purchase, PurchaseCancellation, StockMovement, SupplierPayment, User};
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseReversalService
{
    public function reversePayment(User $user, int $purchaseId, int $paymentId, array $data): void
    {
        DB::transaction(function () use ($user, $purchaseId, $paymentId, $data) {
            $purchase = $this->purchase($user, $purchaseId);
            $this->reverseLocked($user, $purchase, $paymentId, $data);
        });
    }

    public function cancel(User $user, int $purchaseId, array $data): void
    {
        DB::transaction(function () use ($user, $purchaseId, $data) {
            $p = $this->purchase($user, $purchaseId);
            $this->require(!empty($data['goods_returned']), 'Confirm all goods were returned or never received.');
            $this->require($data['confirm_invoice'] === $p->invoice_number, 'Invoice number confirmation does not match.');
            $date = Carbon::parse($data['effective_date'])->endOfDay();
            $this->require($date->gte($p->purchase_date), 'Cancellation cannot precede the purchase date.');
            $items = $p->items()->withTrashed()->lockForUpdate()->get();
            $batches = ProductBatch::whereIn('purchase_item_id', $items->pluck('id'))->lockForUpdate()->get();
            $active = $batches->where('is_active', true);
            foreach ($batches as $batch) {
                $item = $items->firstWhere('id', $batch->purchase_item_id);
                $this->require((int) $batch->client_id === (int) $user->client_id && (int) $batch->branch_id === (int) $user->branch_id
                    && (int) $batch->product_id === (int) $item->product_id, 'Batch ownership mismatch.');
                $this->require((float) $batch->reserved_quantity === 0.0, 'This purchase has reserved stock.');
                $this->require(abs((float) $batch->quantity_available - (float) $batch->quantity_received) < 0.001, 'Some stock has been used or adjusted.');
                $this->require($batch->is_active || (float) $batch->quantity_available === 0.0, 'An inactive batch still contains stock.');
                $this->require(!$batch->is_active || !$item->trashed(), 'An active batch belongs to a removed purchase line.');
            }
            foreach ($items->whereNull('deleted_at') as $item) {
                $this->require(abs((float) $item->received_quantity - (float) $active->where('purchase_item_id', $item->id)->sum('quantity_received')) < 0.001, 'Received quantities do not reconcile to batches.');
            }
            $saleRows = DB::table('sale_items')->whereIn('product_batch_id', $batches->pluck('id'))->lockForUpdate()->get();
            foreach ($saleRows as $row) {
                $sale = DB::table('sales')->where('id', $row->sale_id)->lockForUpdate()->first();
                $this->require($sale && $sale->status === 'cancelled' && !$sale->is_active
                    && (int) $sale->client_id === (int) $user->client_id && (int) $sale->branch_id === (int) $user->branch_id, 'A batch is linked to a sale that is not cancelled.');
            }
            $this->require(!DB::table('stock_adjustments')->whereIn('product_batch_id', $batches->pluck('id'))->exists(), 'Stock adjustments require review before cancellation.');
            $movements = StockMovement::whereIn('product_batch_id', $batches->pluck('id'))->lockForUpdate()->get();
            $offsets = [];
            foreach ($active as $batch) {
                $history = $movements->where('product_batch_id', $batch->id);
                $this->require($history->isNotEmpty(), 'Receipt history is missing.');
                foreach ($history as $movement) {
                    $this->require($movement->movement_type === 'purchase_in' && $movement->reference_type === 'purchase'
                        && (int) $movement->reference_id === (int) $p->id && (int) $movement->client_id === (int) $p->client_id
                        && (int) $movement->branch_id === (int) $p->branch_id, 'This batch has other stock movements.');
                }
                $this->require(abs((float) $history->sum('quantity_in') - (float) $history->sum('quantity_out') - (float) $batch->quantity_available) < 0.001, 'Receipt history does not reconcile to stock.');
                $lastReceipt = $history->max('created_at');
                $this->require($lastReceipt !== null, 'Receipt timestamp is missing.');
                if ($lastReceipt->gt($date)) {
                    $this->require(!empty($data['late_entries_confirmed']), 'Some receipts were entered after this date. Confirm they were late entries or correct the date.');
                }
                $offsets[$batch->id] = $lastReceipt->gt($date) ? $lastReceipt->copy() : $date->copy();
            }
            $payments = $p->supplierPayments()->lockForUpdate()->get();
            $this->require(abs((float) $payments->sum('amount') - (float) $p->amount_paid) < 0.001, 'Payment records do not match the invoice.');
            $snapshot = ['purchase' => $p->toArray(), 'items' => $items->toArray(), 'batches' => $batches->toArray(), 'payments' => $payments->toArray(), 'movements' => $movements->toArray()];
            if ((float) $p->amount_paid > 0) {
                $this->require(!empty($data['reverse_payments']), 'Reverse or refund the recorded payments first.');
                foreach ($payments->where('amount', '>', 0) as $payment) {
                    if (!SupplierPayment::where('reversal_of_payment_id', $payment->id)->exists()) {
                        $this->reverseLocked($user, $p, $payment->id, $data);
                    }
                }
            }
            $this->require(abs((float) $p->fresh()->amount_paid) < 0.001, 'The invoice still has a net payment.');
            foreach ($active->sortBy(fn ($batch) => $offsets[$batch->id]->getTimestamp()) as $batch) {
                $quantity = (float) $batch->quantity_available;
                $batch->update(['quantity_available' => 0, 'quantity_received' => 0, 'reserved_quantity' => 0, 'is_active' => false]);
                $at = $offsets[$batch->id];
                $balance = (float) StockMovement::where('client_id', $p->client_id)->where('branch_id', $p->branch_id)
                    ->where('product_id', $batch->product_id)->where('created_at', '<=', $at)
                    ->selectRaw('COALESCE(SUM(quantity_in - quantity_out), 0) as balance')->value('balance');
                $movement = new StockMovement(['client_id' => $p->client_id, 'branch_id' => $p->branch_id, 'product_id' => $batch->product_id,
                    'product_batch_id' => $batch->id, 'movement_type' => 'purchase_item_removal', 'reference_type' => 'purchase', 'reference_id' => $p->id,
                    'quantity_in' => 0, 'quantity_out' => $quantity, 'balance_after' => $balance - $quantity,
                    'created_by' => $user->id, 'note' => 'Purchase cancelled: ' . $data['reason'] . '. Effective date: ' . $date->toDateString()]);
                $movement->created_at = $at;
                $movement->save();
            }
            foreach ($items->whereNull('deleted_at') as $item) {
                $item->delete();
            }
            PurchaseCancellation::create(['client_id' => $p->client_id, 'branch_id' => $p->branch_id, 'purchase_id' => $p->id,
                'created_by' => $user->id, 'effective_at' => $date, 'amount' => $p->total_amount, 'reason' => $data['reason'], 'snapshot' => $snapshot]);
            $p->update(['is_active' => false, 'invoice_status' => 'returned_refunded', 'payment_status' => 'cancelled', 'amount_paid' => 0, 'balance_due' => 0]);
            app(AuditTrail::class)->record($user, 'purchase.cancelled', 'Purchases', 'Cancel Purchase', 'Cancelled purchase ' . $p->invoice_number,
                ['subject' => $p, 'reason' => $data['reason'], 'old_values' => $snapshot, 'new_values' => $p->fresh()->toArray(), 'context' => $data]);
        });
    }

    private function reverseLocked(User $user, Purchase $p, int $paymentId, array $data): void
    {
        $payment = $p->supplierPayments()->whereKey($paymentId)->lockForUpdate()->firstOrFail();
        $this->require((int) $payment->client_id === (int) $p->client_id && (int) $payment->branch_id === (int) $p->branch_id
            && (int) $payment->supplier_id === (int) $p->supplier_id, 'Payment ownership mismatch.');
        $this->require((float) $payment->amount > 0 && !$payment->reversal_of_payment_id, 'Only an original positive payment can be reversed.');
        $this->require(!SupplierPayment::where('reversal_of_payment_id', $payment->id)->exists(), 'This payment is already reversed.');
        $this->require(in_array($data['mode'], ['mistake', 'refund'], true), 'Choose a reversal reason.');
        $this->require(!empty($data['money_confirmed']), 'Confirm the payment was an error or the refund was received.');
        $total = (float) $p->supplierPayments()->sum('amount');
        $this->require(abs($total - (float) $p->amount_paid) < 0.001 && $total + 0.001 >= (float) $payment->amount, 'Payments do not reconcile to the invoice.');
        $at = $data['mode'] === 'mistake' ? $payment->payment_date->copy() : Carbon::parse($data['effective_date'])->endOfDay();
        $this->require($at->gte($p->purchase_date), 'Refund date cannot precede the purchase.');
        $this->require($at->gte($payment->payment_date) || !empty($data['late_entries_confirmed']), 'Payment was recorded after this refund date. Confirm late entry or correct the date.');
        if ($at->lt($payment->payment_date)) {
            $at = $payment->payment_date->copy();
        }
        $before = $payment->toArray();
        // Freeze an invoice-entry voucher so later header edits cannot rewrite a reversed payment.
        if ($payment->source === 'invoice_entry') {
            $payment->update(['source' => 'reversed_invoice_entry']);
        }
        $reversal = SupplierPayment::create(['client_id' => $p->client_id, 'branch_id' => $p->branch_id, 'supplier_id' => $p->supplier_id,
            'purchase_id' => $p->id, 'paid_by' => $user->id, 'reversal_of_payment_id' => $payment->id,
            'amount' => -(float) $payment->amount, 'payment_method' => $data['mode'] === 'mistake' ? $payment->payment_method : $data['refund_method'],
            'payment_date' => $at, 'reference_number' => 'REV-' . $payment->id, 'status' => 'reversal', 'source' => 'reversal',
            'notes' => $data['mode'] . ': ' . $data['reason'] . '. Confirmed effective date: ' . $data['effective_date']]);
        $paid = round($total - (float) $payment->amount, 2);
        $p->update(['amount_paid' => $paid, 'balance_due' => max(0, (float) $p->total_amount - $paid),
            'payment_status' => $paid <= 0 ? 'pending' : ($paid >= (float) $p->total_amount ? 'paid' : 'partial')]);
        app(AuditTrail::class)->record($user, 'suppliers.payment_reversed', 'Suppliers', 'Reverse Supplier Payment', 'Reversed supplier payment ' . $payment->id,
            ['subject' => $p, 'reason' => $data['reason'], 'old_values' => $before, 'new_values' => $reversal->toArray(), 'context' => $data]);
    }

    private function purchase(User $user, int $id): Purchase
    {
        abort_unless($user->hasPermission('purchases.correct_items') && $user->hasPermission('suppliers.payments.pay'), 403);
        $p = Purchase::where('client_id', $user->client_id)->where('branch_id', $user->branch_id)->whereKey($id)->lockForUpdate()->firstOrFail();
        $this->require($p->is_active && $p->isOperational() && !$p->cancellation()->exists() && $p->invoice_status !== 'returned_refunded', 'This purchase is already cancelled or is an imported balance.');
        return $p;
    }

    private function require(bool $condition, string $message): void
    {
        if (!$condition) {
            throw ValidationException::withMessages(['correction' => $message]);
        }
    }
}
