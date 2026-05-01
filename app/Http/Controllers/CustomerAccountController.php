<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Sale;
use App\Support\AuditTrail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CustomerAccountController extends Controller
{
    public function receivables(Request $request)
    {
        $user = Auth::user();
        $clientName = $user->client?->name ?? 'No Client';
        $branchName = $user->branch?->name ?? 'No Branch';
        $search = trim((string) $request->get('search', ''));

        $query = $this->receivableSaleQueryForUser($user)
            ->with(['customer', 'items.product', 'payments.receivedByUser'])
            ->when($search !== '', function (Builder $saleQuery) use ($search) {
                $saleQuery->where(function (Builder $innerQuery) use ($search) {
                    $innerQuery->where('invoice_number', 'like', '%' . $search . '%')
                        ->orWhere('receipt_number', 'like', '%' . $search . '%')
                        ->orWhereHas('customer', function (Builder $customerQuery) use ($search) {
                            $customerQuery->where('name', 'like', '%' . $search . '%')
                                ->orWhere('phone', 'like', '%' . $search . '%');
                        })
                        ->orWhereHas('items.product', function (Builder $productQuery) use ($search) {
                            $productQuery->where('name', 'like', '%' . $search . '%');
                        });
                });
            });

        $outstandingAmount = (float) (clone $query)->sum('balance_due');
        $invoiceCount = (clone $query)->count();
        $customerCount = (clone $query)->select('customer_id')->distinct()->count('customer_id');

        $receivables = $query
            ->latest('sale_date')
            ->paginate(12)
            ->withQueryString();

        return view('customers.receivables', compact(
            'receivables',
            'outstandingAmount',
            'invoiceCount',
            'customerCount',
            'search',
            'clientName',
            'branchName'
        ));
    }

    public function collections(Request $request)
    {
        $user = Auth::user();
        $clientName = $user->client?->name ?? 'No Client';
        $branchName = $user->branch?->name ?? 'No Branch';
        $search = trim((string) $request->get('search', ''));

        $query = $this->paymentQueryForUser($user)
            ->with(['customer', 'sale.customer', 'receivedByUser', 'originalPayment.receivedByUser', 'reversals.receivedByUser'])
            ->when($search !== '', function (Builder $paymentQuery) use ($search) {
                $paymentQuery->where(function (Builder $innerQuery) use ($search) {
                    $innerQuery->where('reference_number', 'like', '%' . $search . '%')
                        ->orWhere('payment_method', 'like', '%' . $search . '%')
                        ->orWhereHas('customer', function (Builder $customerQuery) use ($search) {
                            $customerQuery->where('name', 'like', '%' . $search . '%')
                                ->orWhere('phone', 'like', '%' . $search . '%');
                        })
                        ->orWhereHas('sale', function (Builder $saleQuery) use ($search) {
                            $saleQuery->where('invoice_number', 'like', '%' . $search . '%')
                                ->orWhere('receipt_number', 'like', '%' . $search . '%');
                        });
                });
            });

        $grossReceived = (float) (clone $query)->whereNull('reversal_of_payment_id')->sum('amount');
        $totalReversed = (float) (clone $query)->whereNotNull('reversal_of_payment_id')->sum('amount');
        $netCollected = max(0, $grossReceived - $totalReversed);
        $collectionCount = (clone $query)->whereNull('reversal_of_payment_id')->count();
        $reversalCount = (clone $query)->whereNotNull('reversal_of_payment_id')->count();
        $customerCount = (clone $query)->select('customer_id')->distinct()->count('customer_id');
        $paymentMethods = self::paymentMethodOptions();

        $collections = $query
            ->latest('payment_date')
            ->paginate(12)
            ->withQueryString();

        return view('customers.collections', compact(
            'collections',
            'grossReceived',
            'totalReversed',
            'netCollected',
            'collectionCount',
            'reversalCount',
            'customerCount',
            'paymentMethods',
            'search',
            'clientName',
            'branchName'
        ));
    }

    public function createCollection($sale)
    {
        $user = Auth::user();
        $clientName = $user->client?->name ?? 'No Client';
        $branchName = $user->branch?->name ?? 'No Branch';
        $sale = $this->findCollectableSaleForUser($user, $sale, ['customer', 'items.product', 'items.batch', 'payments.receivedByUser', 'payments.reversals.receivedByUser', 'payments.originalPayment.receivedByUser']);
        $paymentMethods = self::paymentMethodOptions();

        return view('customers.collect-payment', compact(
            'sale',
            'paymentMethods',
            'clientName',
            'branchName'
        ));
    }

    public function storeCollection(Request $request, $sale)
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
            $sale = $this->findLockedCollectableSaleForUser($user, $sale, ['customer']);

            if (!$sale->customer_id) {
                throw ValidationException::withMessages([
                    'amount' => 'This invoice is not linked to a customer account.',
                ]);
            }

            if ((float) $sale->balance_due <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'This invoice is already fully paid.',
                ]);
            }

            $beforeAudit = [
                'amount_received' => round((float) $sale->amount_received, 2),
                'amount_paid' => round((float) $sale->amount_paid, 2),
                'balance_due' => round((float) $sale->balance_due, 2),
                'payment_method' => $sale->payment_method,
            ];

            $amount = (float) $validated['amount'];

            if ($amount > (float) $sale->balance_due) {
                throw ValidationException::withMessages([
                    'amount' => 'Payment exceeds the remaining balance on invoice ' . ($sale->invoice_number ?? $sale->id) . '.',
                ]);
            }

            $recordedPayment = Payment::create([
                'client_id' => $sale->client_id,
                'branch_id' => $sale->branch_id,
                'sale_id' => $sale->id,
                'customer_id' => $sale->customer_id,
                'received_by' => $user->id,
                'payment_method' => $validated['payment_method'],
                'amount' => $amount,
                'reference_number' => $validated['reference_number'] ?? null,
                'payment_date' => $validated['payment_date'],
                'status' => 'received',
                'notes' => $validated['notes'] ?? null,
            ]);

            $previousAmountReceived = (float) $sale->amount_received;
            $newAmountReceived = $previousAmountReceived + $amount;

            $sale->amount_received = $newAmountReceived;
            $sale->amount_paid = min($newAmountReceived, (float) $sale->total_amount);
            $sale->balance_due = max(0, (float) $sale->total_amount - (float) $sale->amount_paid);
            $sale->payment_method = $this->mergeSalePaymentMethod($sale->payment_method, $validated['payment_method'], $previousAmountReceived);
            $sale->save();

            if ($sale->customer_id) {
                $this->syncCustomerOutstandingBalance($sale->customer_id, $sale->client_id);
            }

            DB::commit();

            app(AuditTrail::class)->recordSafely(
                $user,
                'customers.collection_recorded',
                'Customers',
                'Record Customer Collection',
                'Recorded customer payment on invoice ' . ($sale->invoice_number ?? $sale->id) . '.',
                [
                    'subject' => $sale,
                    'subject_label' => $sale->invoice_number ?? ('Sale #' . $sale->id),
                    'reason' => $validated['notes'] ?? null,
                    'old_values' => $beforeAudit,
                    'new_values' => [
                        'amount_received' => round((float) $sale->amount_received, 2),
                        'amount_paid' => round((float) $sale->amount_paid, 2),
                        'balance_due' => round((float) $sale->balance_due, 2),
                        'payment_method' => $sale->payment_method,
                    ],
                    'context' => [
                        'payment_id' => $recordedPayment?->id,
                        'payment_method' => $validated['payment_method'],
                        'amount' => round($amount, 2),
                        'payment_date' => $validated['payment_date'],
                    ],
                ]
            );

            return redirect()
                ->route('customers.show', $sale->customer_id)
                ->with('success', 'Payment recorded against invoice ' . ($sale->invoice_number ?? $sale->id) . '.');
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function createReversal($payment)
    {
        $user = Auth::user();
        $clientName = $user->client?->name ?? 'No Client';
        $branchName = $user->branch?->name ?? 'No Branch';

        $payment = $this->findReversiblePaymentForUser($user, $payment, [
            'sale.customer',
            'sale.items.product',
            'sale.items.batch',
            'receivedByUser',
            'reversals.receivedByUser',
        ]);

        abort_if((float) $payment->available_to_reverse <= 0, 404);

        return view('customers.reverse-payment', compact(
            'payment',
            'clientName',
            'branchName'
        ));
    }

    public function storeReversal(Request $request, $payment)
    {
        $user = Auth::user();
        $reversalPayment = null;
        $beforeAudit = null;

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_date' => ['required', 'date'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'notes' => ['required', 'string'],
        ]);

        DB::beginTransaction();

        try {
            $payment = $this->findLockedReversiblePaymentForUser($user, $payment, [
                'sale.customer',
                'reversals',
            ]);

            if (!$payment->sale_id || !$payment->sale || !$payment->sale->customer_id) {
                throw ValidationException::withMessages([
                    'amount' => 'Only invoice-linked customer payments can be reversed here.',
                ]);
            }

            $availableToReverse = (float) $payment->available_to_reverse;

            if ($availableToReverse <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'This payment has already been fully reversed.',
                ]);
            }

            $amount = (float) $validated['amount'];

            if ($amount > $availableToReverse) {
                throw ValidationException::withMessages([
                    'amount' => 'Reversal exceeds the remaining reversible amount on this invoice payment.',
                ]);
            }

            $sale = Sale::query()
                ->where('client_id', $user->client_id)
                ->where('branch_id', $user->branch_id)
                ->where('is_active', true)
                ->with(['customer', 'payments.reversals'])
                ->lockForUpdate()
                ->findOrFail($payment->sale_id);

            $beforeAudit = [
                'amount_received' => round((float) $sale->amount_received, 2),
                'amount_paid' => round((float) $sale->amount_paid, 2),
                'balance_due' => round((float) $sale->balance_due, 2),
                'payment_method' => $sale->payment_method,
                'reversed_payment_id' => $payment->id,
            ];

            $reversalPayment = Payment::create([
                'client_id' => $sale->client_id,
                'branch_id' => $sale->branch_id,
                'sale_id' => $sale->id,
                'customer_id' => $sale->customer_id,
                'received_by' => $user->id,
                'reversal_of_payment_id' => $payment->id,
                'payment_method' => $payment->payment_method,
                'amount' => $amount,
                'reference_number' => $validated['reference_number'] ?? null,
                'payment_date' => $validated['payment_date'],
                'status' => 'reversal',
                'notes' => $validated['notes'],
            ]);

            $sale->unsetRelation('payments');
            $sale->load('payments.reversals');

            $currentPaymentMethod = $sale->payment_method;
            $newAmountReceived = max(0, (float) $sale->amount_received - $amount);

            $sale->amount_received = $newAmountReceived;
            $sale->amount_paid = min($newAmountReceived, (float) $sale->total_amount);
            $sale->balance_due = max(0, (float) $sale->total_amount - (float) $sale->amount_paid);
            $sale->payment_method = $this->recalculateSalePaymentMethod($sale, $currentPaymentMethod);
            $sale->save();

            $this->syncCustomerOutstandingBalance($sale->customer_id, $sale->client_id);

            DB::commit();

            app(AuditTrail::class)->recordSafely(
                $user,
                'customers.collection_reversed',
                'Customers',
                'Reverse Customer Collection',
                'Reversed customer payment on invoice ' . ($sale->invoice_number ?? $sale->id) . '.',
                [
                    'subject' => $sale,
                    'subject_label' => $sale->invoice_number ?? ('Sale #' . $sale->id),
                    'reason' => $validated['notes'],
                    'old_values' => $beforeAudit,
                    'new_values' => [
                        'amount_received' => round((float) $sale->amount_received, 2),
                        'amount_paid' => round((float) $sale->amount_paid, 2),
                        'balance_due' => round((float) $sale->balance_due, 2),
                        'payment_method' => $sale->payment_method,
                    ],
                    'context' => [
                        'original_payment_id' => $payment->id,
                        'reversal_payment_id' => $reversalPayment?->id,
                        'payment_method' => $payment->payment_method,
                        'amount' => round($amount, 2),
                        'payment_date' => $validated['payment_date'],
                    ],
                ]
            );

            return redirect()
                ->route('customers.collections.create', $sale->id)
                ->with('success', 'Payment reversal recorded against invoice ' . ($sale->invoice_number ?? $sale->id) . '.');
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
            'cheque' => 'Cheque',
        ];
    }

    private function receivableSaleQueryForUser($user): Builder
    {
        return Sale::query()
            ->where('client_id', $user->client_id)
            ->where('branch_id', $user->branch_id)
            ->where('status', 'approved')
            ->whereNotNull('customer_id')
            ->where('payment_type', '!=', 'insurance')
            ->where('balance_due', '>', 0)
            ->where('is_active', true);
    }

    private function paymentQueryForUser($user): Builder
    {
        return Payment::query()
            ->where('client_id', $user->client_id)
            ->where('branch_id', $user->branch_id)
            ->where(function (Builder $paymentQuery) {
                $paymentQuery->where('status', 'received')
                    ->orWhereNotNull('reversal_of_payment_id');
            });
    }

    private function findCollectableSaleForUser($user, $saleId, array $with = []): Sale
    {
        return $this->receivableSaleQueryForUser($user)
            ->with($with)
            ->findOrFail($saleId);
    }

    private function findLockedCollectableSaleForUser($user, $saleId, array $with = []): Sale
    {
        return $this->receivableSaleQueryForUser($user)
            ->with($with)
            ->lockForUpdate()
            ->findOrFail($saleId);
    }

    private function findReversiblePaymentForUser($user, $paymentId, array $with = []): Payment
    {
        return $this->reversiblePaymentQueryForUser($user)
            ->with($with)
            ->findOrFail($paymentId);
    }

    private function findLockedReversiblePaymentForUser($user, $paymentId, array $with = []): Payment
    {
        return $this->reversiblePaymentQueryForUser($user)
            ->with($with)
            ->lockForUpdate()
            ->findOrFail($paymentId);
    }

    private function reversiblePaymentQueryForUser($user): Builder
    {
        return Payment::query()
            ->where('client_id', $user->client_id)
            ->where('branch_id', $user->branch_id)
            ->whereNull('reversal_of_payment_id')
            ->where('status', 'received')
            ->whereNotNull('sale_id')
            ->whereHas('sale', function (Builder $saleQuery) {
                $saleQuery->where('is_active', true)
                    ->where('payment_type', '!=', 'insurance')
                    ->whereNotNull('customer_id');
            });
    }

    private function recalculateSalePaymentMethod(Sale $sale, ?string $fallbackMethod = null): ?string
    {
        $sale->loadMissing('payments.reversals');

        $activeMethods = $sale->payments
            ->whereNull('reversal_of_payment_id')
            ->filter(function (Payment $payment) {
                return (float) $payment->available_to_reverse > 0.009;
            })
            ->pluck('payment_method')
            ->filter()
            ->unique()
            ->values();

        if ($activeMethods->count() > 1) {
            return 'mixed';
        }

        if ($activeMethods->count() === 1) {
            return $activeMethods->first();
        }

        if ((float) $sale->amount_paid <= 0) {
            return null;
        }

        return $fallbackMethod;
    }

    private function mergeSalePaymentMethod(?string $currentMethod, string $newMethod, float $previousAmountReceived): string
    {
        if ($previousAmountReceived <= 0 || !$currentMethod) {
            return $newMethod;
        }

        if (strtolower($currentMethod) === 'mixed' || strtolower($currentMethod) === strtolower($newMethod)) {
            return $currentMethod;
        }

        return 'mixed';
    }

    private function syncCustomerOutstandingBalance(int $customerId, int $clientId): void
    {
        $outstandingBalance = (float) Sale::query()
            ->where('client_id', $clientId)
            ->where('customer_id', $customerId)
            ->where('status', 'approved')
            ->where('payment_type', '!=', 'insurance')
            ->where('is_active', true)
            ->sum('balance_due');

        Customer::query()
            ->where('client_id', $clientId)
            ->whereKey($customerId)
            ->update([
                'outstanding_balance' => max(0, $outstandingBalance),
            ]);
    }
}
