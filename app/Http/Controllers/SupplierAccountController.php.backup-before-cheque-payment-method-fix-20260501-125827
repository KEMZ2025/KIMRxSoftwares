<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Models\SupplierPayment;
use App\Support\AuditTrail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SupplierAccountController extends Controller
{
    public function payables(Request $request)
    {
        $user = Auth::user();
        $clientName = $user->client?->name ?? 'No Client';
        $branchName = $user->branch?->name ?? 'No Branch';
        $search = trim((string) $request->get('search', ''));

        $query = $this->payablePurchaseQueryForUser($user)
            ->with(['supplier', 'items.product', 'supplierPayments.paidByUser'])
            ->when($search !== '', function (Builder $purchaseQuery) use ($search) {
                $purchaseQuery->where(function (Builder $invoiceQuery) use ($search) {
                    $invoiceQuery->where('invoice_number', 'like', '%' . $search . '%')
                        ->orWhereHas('supplier', function (Builder $supplierQuery) use ($search) {
                            $supplierQuery->where('name', 'like', '%' . $search . '%')
                                ->orWhere('contact_person', 'like', '%' . $search . '%')
                                ->orWhere('phone', 'like', '%' . $search . '%');
                        })
                        ->orWhereHas('items.product', function (Builder $productQuery) use ($search) {
                            $productQuery->where('name', 'like', '%' . $search . '%');
                        });
                });
            });

        $outstandingAmount = (float) (clone $query)->sum('balance_due');
        $invoiceCount = (clone $query)->count();
        $supplierCount = (clone $query)->select('supplier_id')->distinct()->count('supplier_id');

        $payables = $query
            ->latest('purchase_date')
            ->paginate(12)
            ->withQueryString();

        return view('suppliers.payables', compact(
            'payables',
            'outstandingAmount',
            'invoiceCount',
            'supplierCount',
            'search',
            'clientName',
            'branchName'
        ));
    }

    public function payments(Request $request)
    {
        $user = Auth::user();
        $clientName = $user->client?->name ?? 'No Client';
        $branchName = $user->branch?->name ?? 'No Branch';
        $search = trim((string) $request->get('search', ''));
        $paymentMethods = self::paymentMethodOptions();

        $query = $this->paymentQueryForUser($user)
            ->with(['supplier', 'purchase', 'paidByUser'])
            ->when($search !== '', function (Builder $paymentQuery) use ($search) {
                $paymentQuery->where(function (Builder $innerQuery) use ($search) {
                    $innerQuery->where('reference_number', 'like', '%' . $search . '%')
                        ->orWhere('payment_method', 'like', '%' . $search . '%')
                        ->orWhereHas('supplier', function (Builder $supplierQuery) use ($search) {
                            $supplierQuery->where('name', 'like', '%' . $search . '%')
                                ->orWhere('phone', 'like', '%' . $search . '%');
                        })
                        ->orWhereHas('purchase', function (Builder $purchaseQuery) use ($search) {
                            $purchaseQuery->where('invoice_number', 'like', '%' . $search . '%');
                        });
                });
            });

        $totalPaid = (float) (clone $query)->sum('amount');
        $paymentCount = (clone $query)->count();
        $supplierCount = (clone $query)->select('supplier_id')->distinct()->count('supplier_id');
        $invoiceCount = (clone $query)->whereNotNull('purchase_id')->select('purchase_id')->distinct()->count('purchase_id');
        $manualCount = (clone $query)->where('source', 'manual')->count();
        $invoiceEntryCount = (clone $query)->where('source', 'invoice_entry')->count();
        $openBalance = (float) (clone $this->payablePurchaseQueryForUser($user))->sum('balance_due');

        $payments = $query
            ->latest('payment_date')
            ->paginate(12)
            ->withQueryString();

        return view('suppliers.payments', compact(
            'payments',
            'totalPaid',
            'paymentCount',
            'supplierCount',
            'invoiceCount',
            'manualCount',
            'invoiceEntryCount',
            'openBalance',
            'paymentMethods',
            'search',
            'clientName',
            'branchName'
        ));
    }

    public function createPayment($purchase)
    {
        $user = Auth::user();
        $clientName = $user->client?->name ?? 'No Client';
        $branchName = $user->branch?->name ?? 'No Branch';
        $purchase = $this->findPayablePurchaseForUser($user, $purchase, [
            'supplier',
            'items.product',
            'supplierPayments.paidByUser',
        ]);
        $paymentMethods = self::paymentMethodOptions();

        return view('suppliers.pay-invoice', compact(
            'purchase',
            'paymentMethods',
            'clientName',
            'branchName'
        ));
    }

    public function storePayment(Request $request, $purchase)
    {
        $user = Auth::user();
        $paymentMethods = array_keys(self::paymentMethodOptions());
        $recordedPayment = null;
        $beforeAudit = null;

        $validated = $request->validate([
            'payment_method' => ['required', Rule::in($paymentMethods)],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'payment_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        DB::beginTransaction();

        try {
            $purchase = $this->findLockedPayablePurchaseForUser($user, $purchase, ['supplier']);

            if (!$purchase->supplier_id || !$purchase->supplier) {
                throw ValidationException::withMessages([
                    'amount' => 'This invoice is not linked to a supplier account.',
                ]);
            }

            if ((float) $purchase->balance_due <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'This supplier invoice is already fully paid.',
                ]);
            }

            $beforeAudit = [
                'amount_paid' => round((float) $purchase->amount_paid, 2),
                'balance_due' => round((float) $purchase->balance_due, 2),
                'payment_status' => $purchase->payment_status,
            ];

            $amount = (float) $validated['amount'];

            if ($amount > (float) $purchase->balance_due) {
                throw ValidationException::withMessages([
                    'amount' => 'Payment exceeds the remaining balance on invoice ' . ($purchase->invoice_number ?? $purchase->id) . '.',
                ]);
            }

            $recordedPayment = SupplierPayment::create([
                'client_id' => $purchase->client_id,
                'branch_id' => $purchase->branch_id,
                'supplier_id' => $purchase->supplier_id,
                'purchase_id' => $purchase->id,
                'paid_by' => $user->id,
                'payment_method' => $validated['payment_method'],
                'amount' => $amount,
                'reference_number' => $validated['reference_number'] ?? null,
                'payment_date' => $validated['payment_date'],
                'status' => 'paid',
                'source' => 'manual',
                'notes' => $validated['notes'] ?? null,
            ]);

            $purchase->amount_paid = (float) $purchase->amount_paid + $amount;
            $purchase->balance_due = max(0, (float) $purchase->total_amount - (float) $purchase->amount_paid);

            if ((float) $purchase->amount_paid <= 0) {
                $purchase->payment_status = 'pending';
            } elseif ((float) $purchase->amount_paid >= (float) $purchase->total_amount) {
                $purchase->payment_status = 'paid';
            } else {
                $purchase->payment_status = 'partial';
            }

            $purchase->save();

            DB::commit();

            app(AuditTrail::class)->recordSafely(
                $user,
                'suppliers.payment_recorded',
                'Suppliers',
                'Record Supplier Payment',
                'Recorded supplier payment on invoice ' . ($purchase->invoice_number ?? $purchase->id) . '.',
                [
                    'subject' => $purchase,
                    'subject_label' => $purchase->invoice_number ?? ('Purchase #' . $purchase->id),
                    'reason' => $validated['notes'] ?? null,
                    'old_values' => $beforeAudit,
                    'new_values' => [
                        'amount_paid' => round((float) $purchase->amount_paid, 2),
                        'balance_due' => round((float) $purchase->balance_due, 2),
                        'payment_status' => $purchase->payment_status,
                    ],
                    'context' => [
                        'supplier_payment_id' => $recordedPayment?->id,
                        'payment_method' => $validated['payment_method'],
                        'amount' => round($amount, 2),
                        'payment_date' => $validated['payment_date'],
                    ],
                ]
            );

            return redirect()
                ->route('suppliers.show', $purchase->supplier_id)
                ->with('success', 'Payment recorded against supplier invoice ' . ($purchase->invoice_number ?? $purchase->id) . '.');
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public static function paymentMethodOptions(): array
    {
        return [
            'bulky_cash' => 'Bulky Cash',
            'petty_cash' => 'Petty Cash',
            'mtn' => 'MTN',
            'airtel' => 'Airtel',
            'bank' => 'Bank',
            'direct' => 'Direct',
            'cheque' => 'Cheque',
            'other' => 'Other',
        ];
    }

    private function purchaseQueryForUser($user): Builder
    {
        return Purchase::query()
            ->where('client_id', $user->client_id)
            ->where('branch_id', $user->branch_id)
            ->where('is_active', true)
            ->whereNotNull('supplier_id');
    }

    private function payablePurchaseQueryForUser($user): Builder
    {
        return $this->purchaseQueryForUser($user)
            ->where('balance_due', '>', 0);
    }

    private function paymentQueryForUser($user): Builder
    {
        return SupplierPayment::query()
            ->where('client_id', $user->client_id)
            ->where('branch_id', $user->branch_id);
    }

    private function findPayablePurchaseForUser($user, $purchaseId, array $with = []): Purchase
    {
        return $this->payablePurchaseQueryForUser($user)
            ->with($with)
            ->findOrFail($purchaseId);
    }

    private function findLockedPayablePurchaseForUser($user, $purchaseId, array $with = []): Purchase
    {
        return $this->payablePurchaseQueryForUser($user)
            ->with($with)
            ->lockForUpdate()
            ->findOrFail($purchaseId);
    }
}
