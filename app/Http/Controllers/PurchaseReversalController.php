<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Support\PurchaseReversalService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PurchaseReversalController extends Controller
{
    private function authorizePurchase(Request $request, Purchase $purchase): void
    {
        $user = $request->user();
        abort_unless($user->hasPermission('purchases.correct_items') && $user->hasPermission('suppliers.payments.pay'), 403);
        abort_unless((int) $purchase->client_id === (int) $user->client_id && (int) $purchase->branch_id === (int) $user->branch_id, 404);
        abort_unless($purchase->is_active && $purchase->isOperational(), 404);
    }

    public function show(Request $request, Purchase $purchase)
    {
        $this->authorizePurchase($request, $purchase);
        $purchase->load(['supplier', 'supplierPayments']);
        $reversedIds = $purchase->supplierPayments->pluck('reversal_of_payment_id')->filter();
        $payments = $purchase->supplierPayments->filter(fn ($p) => (float) $p->amount > 0 && !$reversedIds->contains($p->id));
        $methods = SupplierAccountController::paymentMethodOptions();
        return view('purchases.reverse', compact('purchase', 'payments', 'methods'));
    }

    public function store(Request $request, Purchase $purchase, PurchaseReversalService $service)
    {
        $this->authorizePurchase($request, $purchase);
        $data = $request->validate([
            'action' => ['required', Rule::in(['payment', 'cancel'])],
            'payment_id' => ['required_if:action,payment', 'nullable', 'integer'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'mode' => ['required', Rule::in(['mistake', 'refund'])],
            'refund_method' => ['required', Rule::in(array_keys(SupplierAccountController::paymentMethodOptions()))],
            'effective_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'money_confirmed' => ['accepted'],
            'goods_returned' => ['accepted_if:action,cancel'],
            'confirm_invoice' => ['required_if:action,cancel', 'nullable', 'string'],
            'reverse_payments' => ['sometimes', 'boolean'],
            'late_entries_confirmed' => ['sometimes', 'boolean'],
        ]);
        if ($data['action'] === 'payment') {
            $service->reversePayment($request->user(), $purchase->id, (int) $data['payment_id'], $data);
            return back()->with('success', 'Payment reversal recorded. The invoice balance has been recalculated.');
        }
        $service->cancel($request->user(), $purchase->id, $data);
        return redirect()->route('purchases.index')->with('success', 'Purchase cancelled and removed from active purchases. Original records and reversals remain in the audit history.');
    }
}
