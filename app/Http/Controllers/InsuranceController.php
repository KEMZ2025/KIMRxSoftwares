<?php

namespace App\Http\Controllers;

use App\Models\Insurer;
use App\Models\InsurancePayment;
use App\Models\Sale;
use App\Support\AuditTrail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InsuranceController extends Controller
{
    public function insurers(Request $request)
    {
        $user = Auth::user();
        $clientName = $user->client?->name ?? 'No Client';
        $branchName = $user->branch?->name ?? 'No Branch';
        $search = trim((string) $request->get('search', ''));

        $query = $this->insurerQueryForUser($user)
            ->withCount(['sales as claim_count' => function ($builder) use ($user) {
                $builder->where('branch_id', $user->branch_id)
                    ->where('status', 'approved')
                    ->where('payment_type', 'insurance')
                    ->where('is_active', true);
            }])
            ->when($search !== '', function (Builder $builder) use ($search) {
                $builder->where(function (Builder $inner) use ($search) {
                    $inner->where('name', 'like', '%' . $search . '%')
                        ->orWhere('code', 'like', '%' . $search . '%')
                        ->orWhere('contact_person', 'like', '%' . $search . '%')
                        ->orWhere('phone', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%');
                });
            });

        $insurers = $query
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        $activeCount = (clone $this->insurerQueryForUser($user))->where('is_active', true)->count();
        $claimCount = $this->claimQueryForUser($user)->count();

        return view('insurance.insurers', compact(
            'insurers',
            'clientName',
            'branchName',
            'search',
            'activeCount',
            'claimCount'
        ));
    }

    public function storeInsurer(Request $request)
    {
        $user = $request->user();
        $validated = $this->validateInsurer($request, $user);

        $insurer = Insurer::create([
            'client_id' => $user->client_id,
            'name' => $validated['name'],
            'code' => $validated['code'] ?? null,
            'contact_person' => $validated['contact_person'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'address' => $validated['address'] ?? null,
            'credit_days' => (int) ($validated['credit_days'] ?? 30),
            'notes' => $validated['notes'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        app(AuditTrail::class)->recordSafely(
            $user,
            'insurance.manage',
            'Insurance',
            'Create Insurer',
            'Created insurer ' . $insurer->name . '.',
            [
                'subject' => $insurer,
                'subject_label' => $insurer->name,
                'new_values' => $insurer->only([
                    'name',
                    'code',
                    'contact_person',
                    'phone',
                    'email',
                    'credit_days',
                    'is_active',
                ]),
            ]
        );

        return redirect()
            ->route('insurance.insurers.index')
            ->with('success', 'Insurer created successfully.');
    }

    public function updateInsurer(Request $request, $insurer)
    {
        $user = $request->user();
        $insurer = $this->findInsurerForUser($user, $insurer);
        $validated = $this->validateInsurer($request, $user, $insurer);
        $beforeAudit = $insurer->only([
            'name',
            'code',
            'contact_person',
            'phone',
            'email',
            'credit_days',
            'is_active',
        ]);

        $insurer->fill([
            'name' => $validated['name'],
            'code' => $validated['code'] ?? null,
            'contact_person' => $validated['contact_person'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'address' => $validated['address'] ?? null,
            'credit_days' => (int) ($validated['credit_days'] ?? 30),
            'notes' => $validated['notes'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);
        $insurer->save();

        app(AuditTrail::class)->recordSafely(
            $user,
            'insurance.manage',
            'Insurance',
            'Update Insurer',
            'Updated insurer ' . $insurer->name . '.',
            [
                'subject' => $insurer,
                'subject_label' => $insurer->name,
                'old_values' => $beforeAudit,
                'new_values' => $insurer->only([
                    'name',
                    'code',
                    'contact_person',
                    'phone',
                    'email',
                    'credit_days',
                    'is_active',
                ]),
            ]
        );

        return redirect()
            ->route('insurance.insurers.index')
            ->with('success', 'Insurer updated successfully.');
    }

    public function claims(Request $request)
    {
        $user = Auth::user();
        $clientName = $user->client?->name ?? 'No Client';
        $branchName = $user->branch?->name ?? 'No Branch';
        $search = trim((string) $request->get('search', ''));
        $status = trim((string) $request->get('status', ''));
        $insurerId = $request->integer('insurer_id');

        $query = $this->claimQueryForUser($user)
            ->with(['customer:id,name,phone', 'insurer:id,name', 'servedByUser:id,name'])
            ->when($search !== '', function (Builder $builder) use ($search) {
                $builder->where(function (Builder $inner) use ($search) {
                    $inner->where('invoice_number', 'like', '%' . $search . '%')
                        ->orWhere('insurance_member_number', 'like', '%' . $search . '%')
                        ->orWhere('insurance_card_number', 'like', '%' . $search . '%')
                        ->orWhere('insurance_authorization_number', 'like', '%' . $search . '%')
                        ->orWhereHas('customer', fn (Builder $customerQuery) => $customerQuery->where('name', 'like', '%' . $search . '%'))
                        ->orWhereHas('insurer', fn (Builder $insurerQuery) => $insurerQuery->where('name', 'like', '%' . $search . '%'));
                });
            })
            ->when($status !== '', fn (Builder $builder) => $builder->where('insurance_claim_status', $status))
            ->when($insurerId > 0, fn (Builder $builder) => $builder->where('insurer_id', $insurerId));

        $claims = $query
            ->latest('sale_date')
            ->latest('id')
            ->paginate(12)
            ->withQueryString();

        $baseQuery = $this->claimQueryForUser($user);
        $grossClaims = (float) (clone $baseQuery)->sum('insurance_covered_amount');
        $outstandingClaims = (float) (clone $baseQuery)->sum('insurance_balance_due');
        $remittedAmount = max(0, $grossClaims - $outstandingClaims);
        $paidClaims = (int) (clone $baseQuery)->where('insurance_claim_status', Sale::CLAIM_PAID)->count();
        $rejectedClaims = (int) (clone $baseQuery)->where('insurance_claim_status', Sale::CLAIM_REJECTED)->count();
        $insurers = $this->insurerQueryForUser($user)->orderBy('name')->get(['id', 'name']);
        $claimStatuses = Sale::insuranceClaimStatusOptions();

        return view('insurance.claims', compact(
            'claims',
            'clientName',
            'branchName',
            'search',
            'status',
            'insurerId',
            'grossClaims',
            'outstandingClaims',
            'remittedAmount',
            'paidClaims',
            'rejectedClaims',
            'insurers',
            'claimStatuses'
        ));
    }

    public function showClaim($sale)
    {
        $user = Auth::user();
        $clientName = $user->client?->name ?? 'No Client';
        $branchName = $user->branch?->name ?? 'No Branch';
        $sale = $this->findClaimForUser($user, $sale, [
            'customer:id,name,phone,email,address',
            'insurer',
            'items.product:id,name',
            'servedByUser:id,name',
            'insurancePayments.receivedByUser:id,name',
            'insurancePayments.reversals.receivedByUser:id,name',
            'insurancePayments.originalPayment.receivedByUser:id,name',
        ]);

        return view('insurance.claim-show', [
            'sale' => $sale,
            'clientName' => $clientName,
            'branchName' => $branchName,
            'claimStatuses' => $this->manualClaimStatusOptions(),
            'paymentMethods' => CustomerAccountController::paymentMethodOptions(),
        ]);
    }

    public function updateClaimStatus(Request $request, $sale)
    {
        $user = $request->user();
        $validated = $request->validate([
            'insurance_claim_status' => ['required', Rule::in(array_keys($this->manualClaimStatusOptions()))],
            'insurance_status_notes' => ['nullable', 'string'],
        ]);

        $sale = $this->findLockedClaimForUser($user, $sale);
        $beforeAudit = [
            'insurance_claim_status' => $sale->insurance_claim_status,
            'insurance_status_notes' => $sale->insurance_status_notes,
        ];

        $sale->insurance_claim_status = $validated['insurance_claim_status'];
        $sale->insurance_status_notes = $validated['insurance_status_notes'] ?? null;
        $sale->insurance_submitted_at = $validated['insurance_claim_status'] === Sale::CLAIM_SUBMITTED ? now() : ($validated['insurance_claim_status'] === Sale::CLAIM_DRAFT ? null : $sale->insurance_submitted_at);
        $sale->insurance_approved_at = $validated['insurance_claim_status'] === Sale::CLAIM_APPROVED ? now() : ($validated['insurance_claim_status'] === Sale::CLAIM_DRAFT ? null : $sale->insurance_approved_at);
        $sale->insurance_rejected_at = $validated['insurance_claim_status'] === Sale::CLAIM_REJECTED ? now() : ($validated['insurance_claim_status'] === Sale::CLAIM_DRAFT ? null : $sale->insurance_rejected_at);
        if (in_array($validated['insurance_claim_status'], [Sale::CLAIM_DRAFT, Sale::CLAIM_SUBMITTED, Sale::CLAIM_APPROVED, Sale::CLAIM_REJECTED], true)) {
            $sale->insurance_paid_at = null;
        }
        $sale->save();

        app(AuditTrail::class)->recordSafely(
            $user,
            'insurance.manage',
            'Insurance',
            'Update Claim Status',
            'Updated insurance claim status for invoice ' . ($sale->invoice_number ?? $sale->id) . '.',
            [
                'subject' => $sale,
                'subject_label' => $sale->invoice_number ?? ('Sale #' . $sale->id),
                'old_values' => $beforeAudit,
                'new_values' => [
                    'insurance_claim_status' => $sale->insurance_claim_status,
                    'insurance_status_notes' => $sale->insurance_status_notes,
                ],
            ]
        );

        return redirect()
            ->route('insurance.claims.show', $sale)
            ->with('success', 'Insurance claim status updated.');
    }

    public function createPayment($sale)
    {
        $user = Auth::user();
        $clientName = $user->client?->name ?? 'No Client';
        $branchName = $user->branch?->name ?? 'No Branch';
        $sale = $this->findClaimForUser($user, $sale, ['customer:id,name', 'insurer:id,name']);

        return view('insurance.receive-payment', [
            'sale' => $sale,
            'clientName' => $clientName,
            'branchName' => $branchName,
            'paymentMethods' => CustomerAccountController::paymentMethodOptions(),
        ]);
    }

    public function storePayment(Request $request, $sale)
    {
        $user = $request->user();
        $validated = $request->validate([
            'payment_method' => ['required', Rule::in(array_keys(CustomerAccountController::paymentMethodOptions()))],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'payment_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        DB::beginTransaction();

        try {
            $sale = $this->findLockedClaimForUser($user, $sale, ['insurancePayments.reversals']);

            if ((float) $sale->insurance_balance_due <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'This claim is already fully remitted.',
                ]);
            }

            $amount = round((float) $validated['amount'], 2);

            if ($amount - (float) $sale->insurance_balance_due > 0.0001) {
                throw ValidationException::withMessages([
                    'amount' => 'Remittance exceeds the remaining insurer balance on this invoice.',
                ]);
            }

            $payment = InsurancePayment::create([
                'client_id' => $sale->client_id,
                'branch_id' => $sale->branch_id,
                'sale_id' => $sale->id,
                'insurer_id' => $sale->insurer_id,
                'received_by' => $user->id,
                'payment_method' => $validated['payment_method'],
                'amount' => $amount,
                'reference_number' => $validated['reference_number'] ?? null,
                'payment_date' => $validated['payment_date'],
                'status' => 'received',
                'notes' => $validated['notes'] ?? null,
            ]);

            $sale->amount_received = round((float) $sale->amount_received + $amount, 2);
            $sale->amount_paid = min((float) $sale->amount_received, (float) $sale->total_amount);
            $sale->balance_due = max(0, round((float) $sale->total_amount - (float) $sale->amount_paid, 2));
            $sale->insurance_balance_due = max(0, round((float) $sale->insurance_balance_due - $amount, 2));
            $this->syncInsuranceClaimStatus($sale);
            $sale->save();

            DB::commit();

            app(AuditTrail::class)->recordSafely(
                $user,
                'insurance.manage',
                'Insurance',
                'Record Insurance Remittance',
                'Recorded insurer remittance on invoice ' . ($sale->invoice_number ?? $sale->id) . '.',
                [
                    'subject' => $sale,
                    'subject_label' => $sale->invoice_number ?? ('Sale #' . $sale->id),
                    'new_values' => [
                        'insurance_balance_due' => round((float) $sale->insurance_balance_due, 2),
                        'balance_due' => round((float) $sale->balance_due, 2),
                        'insurance_claim_status' => $sale->insurance_claim_status,
                    ],
                    'context' => [
                        'insurance_payment_id' => $payment->id,
                        'amount' => $amount,
                        'payment_method' => $validated['payment_method'],
                    ],
                ]
            );

            return redirect()
                ->route('insurance.claims.show', $sale)
                ->with('success', 'Insurance remittance recorded successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function createPaymentReversal($payment)
    {
        $user = Auth::user();
        $clientName = $user->client?->name ?? 'No Client';
        $branchName = $user->branch?->name ?? 'No Branch';
        $payment = $this->findReversiblePaymentForUser($user, $payment, ['sale.customer', 'insurer', 'receivedByUser', 'reversals.receivedByUser']);

        abort_if((float) $payment->available_to_reverse <= 0, 404);

        return view('insurance.reverse-payment', compact('payment', 'clientName', 'branchName'));
    }

    public function storePaymentReversal(Request $request, $payment)
    {
        $user = $request->user();
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_date' => ['required', 'date'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'notes' => ['required', 'string'],
        ]);

        DB::beginTransaction();

        try {
            $payment = $this->findLockedReversiblePaymentForUser($user, $payment, ['sale', 'reversals']);
            $availableToReverse = (float) $payment->available_to_reverse;

            if ($availableToReverse <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'This remittance has already been fully reversed.',
                ]);
            }

            $amount = round((float) $validated['amount'], 2);
            if ($amount - $availableToReverse > 0.0001) {
                throw ValidationException::withMessages([
                    'amount' => 'Reversal exceeds the remaining reversible amount on this remittance.',
                ]);
            }

            $sale = $this->findLockedClaimForUser($user, $payment->sale_id, ['insurancePayments.reversals']);

            $reversal = InsurancePayment::create([
                'client_id' => $sale->client_id,
                'branch_id' => $sale->branch_id,
                'sale_id' => $sale->id,
                'insurer_id' => $sale->insurer_id,
                'received_by' => $user->id,
                'reversal_of_payment_id' => $payment->id,
                'payment_method' => $payment->payment_method,
                'amount' => $amount,
                'reference_number' => $validated['reference_number'] ?? null,
                'payment_date' => $validated['payment_date'],
                'status' => 'reversal',
                'notes' => $validated['notes'],
            ]);

            $sale->amount_received = max(0, round((float) $sale->amount_received - $amount, 2));
            $sale->amount_paid = min((float) $sale->amount_received, (float) $sale->total_amount);
            $sale->balance_due = max(0, round((float) $sale->total_amount - (float) $sale->amount_paid, 2));
            $sale->insurance_balance_due = round((float) $sale->insurance_balance_due + $amount, 2);
            $this->syncInsuranceClaimStatus($sale);
            $sale->save();

            DB::commit();

            app(AuditTrail::class)->recordSafely(
                $user,
                'insurance.manage',
                'Insurance',
                'Reverse Insurance Remittance',
                'Reversed insurer remittance on invoice ' . ($sale->invoice_number ?? $sale->id) . '.',
                [
                    'subject' => $sale,
                    'subject_label' => $sale->invoice_number ?? ('Sale #' . $sale->id),
                    'new_values' => [
                        'insurance_balance_due' => round((float) $sale->insurance_balance_due, 2),
                        'balance_due' => round((float) $sale->balance_due, 2),
                        'insurance_claim_status' => $sale->insurance_claim_status,
                    ],
                    'context' => [
                        'original_insurance_payment_id' => $payment->id,
                        'reversal_insurance_payment_id' => $reversal->id,
                        'amount' => $amount,
                    ],
                ]
            );

            return redirect()
                ->route('insurance.claims.show', $sale)
                ->with('success', 'Insurance remittance reversal recorded successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function insurerQueryForUser($user): Builder
    {
        return Insurer::query()
            ->where('client_id', $user->client_id);
    }

    private function claimQueryForUser($user): Builder
    {
        return Sale::query()
            ->where('client_id', $user->client_id)
            ->where('branch_id', $user->branch_id)
            ->where('status', 'approved')
            ->where('payment_type', 'insurance')
            ->whereNotNull('insurer_id')
            ->where('is_active', true);
    }

    private function findInsurerForUser($user, $insurerId): Insurer
    {
        return $this->insurerQueryForUser($user)->findOrFail($insurerId);
    }

    private function findClaimForUser($user, $saleId, array $with = []): Sale
    {
        return $this->claimQueryForUser($user)
            ->with($with)
            ->findOrFail($saleId);
    }

    private function findLockedClaimForUser($user, $saleId, array $with = []): Sale
    {
        return $this->claimQueryForUser($user)
            ->with($with)
            ->lockForUpdate()
            ->findOrFail($saleId);
    }

    private function findReversiblePaymentForUser($user, $paymentId, array $with = []): InsurancePayment
    {
        return $this->reversiblePaymentQueryForUser($user)
            ->with($with)
            ->findOrFail($paymentId);
    }

    private function findLockedReversiblePaymentForUser($user, $paymentId, array $with = []): InsurancePayment
    {
        return $this->reversiblePaymentQueryForUser($user)
            ->with($with)
            ->lockForUpdate()
            ->findOrFail($paymentId);
    }

    private function reversiblePaymentQueryForUser($user): Builder
    {
        return InsurancePayment::query()
            ->where('client_id', $user->client_id)
            ->where('branch_id', $user->branch_id)
            ->whereNull('reversal_of_payment_id')
            ->where('status', 'received');
    }

    private function syncInsuranceClaimStatus(Sale $sale): void
    {
        $netReceived = (float) InsurancePayment::query()
            ->where('sale_id', $sale->id)
            ->selectRaw("COALESCE(SUM(CASE WHEN reversal_of_payment_id IS NULL AND status = 'received' THEN amount WHEN reversal_of_payment_id IS NOT NULL AND status = 'reversal' THEN -amount ELSE 0 END), 0) as net_received")
            ->value('net_received');

        if ((float) $sale->insurance_balance_due <= 0.009) {
            $sale->insurance_claim_status = Sale::CLAIM_PAID;
            $sale->insurance_paid_at = now();
            return;
        }

        $sale->insurance_paid_at = null;

        if ($netReceived > 0.009) {
            $sale->insurance_claim_status = Sale::CLAIM_PART_PAID;
            return;
        }

        if ($sale->insurance_rejected_at) {
            $sale->insurance_claim_status = Sale::CLAIM_REJECTED;
            return;
        }

        if ($sale->insurance_approved_at) {
            $sale->insurance_claim_status = Sale::CLAIM_APPROVED;
            return;
        }

        if ($sale->insurance_submitted_at) {
            $sale->insurance_claim_status = Sale::CLAIM_SUBMITTED;
            return;
        }

        $sale->insurance_claim_status = Sale::CLAIM_DRAFT;
    }

    private function manualClaimStatusOptions(): array
    {
        return [
            Sale::CLAIM_DRAFT => 'Draft',
            Sale::CLAIM_SUBMITTED => 'Submitted',
            Sale::CLAIM_APPROVED => 'Approved',
            Sale::CLAIM_REJECTED => 'Rejected',
        ];
    }

    private function validateInsurer(Request $request, $user, ?Insurer $existing = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('insurers', 'name')
                    ->where(fn ($query) => $query->where('client_id', $user->client_id))
                    ->ignore($existing?->id),
            ],
            'code' => ['nullable', 'string', 'max:100'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'credit_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
