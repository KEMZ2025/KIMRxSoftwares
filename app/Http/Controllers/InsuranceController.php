<?php

namespace App\Http\Controllers;

use App\Models\InsuranceClaimAdjustment;
use App\Models\InsuranceClaimBatch;
use App\Models\Insurer;
use App\Models\InsurancePayment;
use App\Models\Sale;
use App\Support\AuditTrail;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
        [$fromDate, $toDate] = $this->dateFiltersFromRequest($request);

        $query = $this->claimQueryForUser($user, $fromDate, $toDate)
            ->with(['customer:id,name,phone', 'insurer:id,name', 'servedByUser:id,name', 'insuranceClaimBatch:id,batch_number,status'])
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

        $baseQuery = $this->claimQueryForUser($user, $fromDate, $toDate);
        $grossClaims = (float) (clone $baseQuery)->sum('insurance_covered_amount');
        $outstandingClaims = (float) (clone $baseQuery)->sum('insurance_balance_due');
        $remittedAmount = max(0, $grossClaims - $outstandingClaims);
        $paidClaims = (int) (clone $baseQuery)->where('insurance_claim_status', Sale::CLAIM_PAID)->count();
        $rejectedClaims = (int) (clone $baseQuery)->where('insurance_claim_status', Sale::CLAIM_REJECTED)->count();
        $insurers = $this->insurerQueryForUser($user)->orderBy('name')->get(['id', 'name']);
        $claimStatuses = Sale::insuranceClaimStatusOptions();
        $recentBatches = $this->batchQueryForUser($user)
            ->with(['insurer:id,name'])
            ->withCount('claims')
            ->latest('id')
            ->take(5)
            ->get();

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
            'claimStatuses',
            'recentBatches',
            'fromDate',
            'toDate'
        ));
    }

    public function batches(Request $request)
    {
        $user = Auth::user();
        $clientName = $user->client?->name ?? 'No Client';
        $branchName = $user->branch?->name ?? 'No Branch';
        $status = trim((string) $request->get('status', ''));
        $insurerId = $request->integer('insurer_id');

        $batches = $this->batchQueryForUser($user)
            ->with(['insurer:id,name', 'createdByUser:id,name'])
            ->withCount('claims')
            ->withSum('claims as total_claim_amount', 'insurance_covered_amount')
            ->withSum('claims as total_outstanding_amount', 'insurance_balance_due')
            ->when($status !== '', fn (Builder $builder) => $builder->where('status', $status))
            ->when($insurerId > 0, fn (Builder $builder) => $builder->where('insurer_id', $insurerId))
            ->latest('id')
            ->paginate(12)
            ->withQueryString();

        $batchStatuses = InsuranceClaimBatch::statusOptions();
        $insurers = $this->insurerQueryForUser($user)->orderBy('name')->get(['id', 'name']);
        $draftCount = (clone $this->batchQueryForUser($user))->where('status', InsuranceClaimBatch::STATUS_DRAFT)->count();
        $submittedCount = (clone $this->batchQueryForUser($user))->where('status', InsuranceClaimBatch::STATUS_SUBMITTED)->count();
        $openCount = (clone $this->batchQueryForUser($user))->whereIn('status', [
            InsuranceClaimBatch::STATUS_DRAFT,
            InsuranceClaimBatch::STATUS_SUBMITTED,
            InsuranceClaimBatch::STATUS_RECONCILED,
        ])->count();

        return view('insurance.batches', compact(
            'batches',
            'clientName',
            'branchName',
            'status',
            'insurerId',
            'batchStatuses',
            'insurers',
            'draftCount',
            'submittedCount',
            'openCount'
        ));
    }

    public function storeBatch(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'insurer_id' => ['required', 'integer'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date'],
            'title' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        DB::beginTransaction();

        try {
            $insurer = $this->findInsurerForUser($user, $validated['insurer_id']);
            $periodStart = Carbon::parse($validated['period_start'], config('app.timezone'))->startOfDay();
            $periodEnd = Carbon::parse($validated['period_end'], config('app.timezone'))->endOfDay();

            if ($periodStart->gt($periodEnd)) {
                [$periodStart, $periodEnd] = [$periodEnd->copy()->startOfDay(), $periodStart->copy()->endOfDay()];
            }

            $eligibleClaims = $this->eligibleBatchClaimsQuery($user, $insurer, $periodStart, $periodEnd)
                ->lockForUpdate()
                ->get(['id', 'invoice_number', 'insurance_covered_amount']);

            if ($eligibleClaims->isEmpty()) {
                throw ValidationException::withMessages([
                    'period_start' => 'No eligible unbatched insurance claims were found for that insurer and date range.',
                ]);
            }

            $batch = InsuranceClaimBatch::create([
                'client_id' => $user->client_id,
                'branch_id' => $user->branch_id,
                'insurer_id' => $insurer->id,
                'created_by' => $user->id,
                'updated_by' => $user->id,
                'batch_number' => $this->generateBatchNumber($user),
                'title' => $validated['title'] ?: ($insurer->name . ' claims ' . $periodStart->format('d M Y') . ' - ' . $periodEnd->format('d M Y')),
                'status' => InsuranceClaimBatch::STATUS_DRAFT,
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'notes' => $validated['notes'] ?? null,
            ]);

            Sale::query()
                ->whereIn('id', $eligibleClaims->pluck('id'))
                ->update([
                    'insurance_claim_batch_id' => $batch->id,
                ]);

            DB::commit();

            app(AuditTrail::class)->recordSafely(
                $user,
                'insurance.manage',
                'Insurance',
                'Create Claim Batch',
                'Created insurance claim batch ' . $batch->batch_number . '.',
                [
                    'subject' => $batch,
                    'subject_label' => $batch->batch_number,
                    'new_values' => [
                        'status' => $batch->status,
                        'insurer' => $insurer->name,
                        'period_start' => $batch->period_start?->format('Y-m-d'),
                        'period_end' => $batch->period_end?->format('Y-m-d'),
                        'claim_count' => $eligibleClaims->count(),
                    ],
                ]
            );

            return redirect()
                ->route('insurance.batches.show', $batch)
                ->with('success', 'Insurance claim batch created successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function showBatch($batch)
    {
        $user = Auth::user();
        $clientName = $user->client?->name ?? 'No Client';
        $branchName = $user->branch?->name ?? 'No Branch';
        $batch = $this->findBatchForUser($user, $batch, [
            'insurer',
            'createdByUser:id,name',
            'updatedByUser:id,name',
            'claims.customer:id,name,phone',
            'claims.insurancePayments.reversals',
            'claims.insuranceClaimAdjustments',
        ]);

        $claimRows = $batch->claims
            ->sortBy('sale_date')
            ->values()
            ->map(function (Sale $claim) {
                $remitted = $this->netClaimRemitted($claim);
                $writtenOff = $this->netClaimAdjustments($claim);

                return [
                    'claim' => $claim,
                    'remitted' => $remitted,
                    'written_off' => $writtenOff,
                    'outstanding' => (float) $claim->insurance_balance_due,
                ];
            });

        $summary = [
            'claim_count' => $claimRows->count(),
            'claim_total' => round((float) $claimRows->sum(fn (array $row) => (float) $row['claim']->insurance_covered_amount), 2),
            'remitted_total' => round((float) $claimRows->sum('remitted'), 2),
            'written_off_total' => round((float) $claimRows->sum('written_off'), 2),
            'outstanding_total' => round((float) $claimRows->sum('outstanding'), 2),
        ];

        return view('insurance.batch-show', [
            'batch' => $batch,
            'clientName' => $clientName,
            'branchName' => $branchName,
            'batchStatuses' => InsuranceClaimBatch::statusOptions(),
            'claimRows' => $claimRows,
            'summary' => $summary,
        ]);
    }

    public function updateBatchStatus(Request $request, $batch)
    {
        $user = $request->user();
        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys(InsuranceClaimBatch::statusOptions()))],
            'notes' => ['nullable', 'string'],
        ]);

        DB::beginTransaction();

        try {
            $batch = $this->findLockedBatchForUser($user, $batch, ['claims']);
            $previousStatus = $batch->status;
            $outstandingTotal = round((float) $batch->claims->sum('insurance_balance_due'), 2);

            if ($validated['status'] === InsuranceClaimBatch::STATUS_CLOSED && $outstandingTotal > 0.009) {
                throw ValidationException::withMessages([
                    'status' => 'You can only close a batch after all claim balances have been remitted or written off.',
                ]);
            }

            $batch->status = $validated['status'];
            $batch->notes = $validated['notes'] ?? $batch->notes;
            $batch->updated_by = $user->id;

            if ($validated['status'] === InsuranceClaimBatch::STATUS_DRAFT) {
                $batch->submitted_at = null;
                $batch->reconciled_at = null;
                $batch->closed_at = null;
            }

            if (in_array($validated['status'], [InsuranceClaimBatch::STATUS_SUBMITTED, InsuranceClaimBatch::STATUS_RECONCILED, InsuranceClaimBatch::STATUS_CLOSED], true) && !$batch->submitted_at) {
                $batch->submitted_at = now();
            }

            if (in_array($validated['status'], [InsuranceClaimBatch::STATUS_RECONCILED, InsuranceClaimBatch::STATUS_CLOSED], true) && !$batch->reconciled_at) {
                $batch->reconciled_at = now();
            }

            if ($validated['status'] === InsuranceClaimBatch::STATUS_CLOSED && !$batch->closed_at) {
                $batch->closed_at = now();
            }

            $batch->save();

            if (in_array($validated['status'], [InsuranceClaimBatch::STATUS_SUBMITTED, InsuranceClaimBatch::STATUS_RECONCILED, InsuranceClaimBatch::STATUS_CLOSED], true)) {
                foreach ($batch->claims as $claim) {
                    $dirty = false;
                    if (!$claim->insurance_submitted_at) {
                        $claim->insurance_submitted_at = $batch->submitted_at;
                        $dirty = true;
                    }
                    if ($claim->insurance_claim_status === Sale::CLAIM_DRAFT) {
                        $claim->insurance_claim_status = Sale::CLAIM_SUBMITTED;
                        $dirty = true;
                    }
                    if ($dirty) {
                        $claim->save();
                    }
                }
            }

            DB::commit();

            app(AuditTrail::class)->recordSafely(
                $user,
                'insurance.manage',
                'Insurance',
                'Update Claim Batch Status',
                'Updated insurance claim batch ' . $batch->batch_number . ' to ' . $batch->status_label . '.',
                [
                    'subject' => $batch,
                    'subject_label' => $batch->batch_number,
                    'old_values' => [
                        'status' => $previousStatus,
                    ],
                    'new_values' => [
                        'status' => $batch->status,
                        'outstanding_total' => $outstandingTotal,
                    ],
                ]
            );

            return redirect()
                ->route('insurance.batches.show', $batch)
                ->with('success', 'Claim batch status updated successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function statements(Request $request)
    {
        $user = Auth::user();
        $clientName = $user->client?->name ?? 'No Client';
        $branchName = $user->branch?->name ?? 'No Branch';
        $insurerId = $request->integer('insurer_id');
        [$fromDate, $toDate] = $this->dateFiltersFromRequest($request);
        $asOfDate = $request->filled('as_of')
            ? Carbon::parse((string) $request->input('as_of'), config('app.timezone'))->endOfDay()
            : Carbon::today(config('app.timezone'))->endOfDay();

        $claims = $this->claimQueryForUser($user, $fromDate, $toDate)
            ->with([
                'customer:id,name,phone',
                'insurer:id,name',
                'insuranceClaimBatch:id,batch_number,status',
                'insurancePayments.reversals',
                'insuranceClaimAdjustments',
            ])
            ->when($insurerId > 0, fn (Builder $builder) => $builder->where('insurer_id', $insurerId))
            ->orderBy('sale_date')
            ->get();

        $statementRows = $this->buildStatementRows($claims, $asOfDate);
        $summaryByInsurer = $statementRows
            ->groupBy('insurer_name')
            ->map(function (Collection $rows) {
                return [
                    'claim_count' => $rows->count(),
                    'covered' => round((float) $rows->sum('covered'), 2),
                    'remitted' => round((float) $rows->sum('remitted'), 2),
                    'written_off' => round((float) $rows->sum('written_off'), 2),
                    'outstanding' => round((float) $rows->sum('outstanding'), 2),
                ];
            })
            ->sortKeys();
        $ageingTotals = $statementRows
            ->groupBy('age_bucket')
            ->map(fn (Collection $rows) => round((float) $rows->sum('outstanding'), 2))
            ->toArray();
        $insurers = $this->insurerQueryForUser($user)->orderBy('name')->get(['id', 'name']);

        return view('insurance.statements', compact(
            'clientName',
            'branchName',
            'statementRows',
            'summaryByInsurer',
            'ageingTotals',
            'insurers',
            'insurerId',
            'fromDate',
            'toDate',
            'asOfDate'
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
            'insuranceClaimBatch',
            'items.product:id,name',
            'servedByUser:id,name',
            'insurancePayments.receivedByUser:id,name',
            'insurancePayments.reversals.receivedByUser:id,name',
            'insurancePayments.originalPayment.receivedByUser:id,name',
            'insuranceClaimAdjustments.createdByUser:id,name',
        ]);

        return view('insurance.claim-show', [
            'sale' => $sale,
            'clientName' => $clientName,
            'branchName' => $branchName,
            'claimStatuses' => $this->manualClaimStatusOptions(),
            'adjustmentTypes' => InsuranceClaimAdjustment::typeOptions(),
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

    public function storeAdjustment(Request $request, $sale)
    {
        $user = $request->user();
        $validated = $request->validate([
            'adjustment_type' => ['required', Rule::in(array_keys(InsuranceClaimAdjustment::typeOptions()))],
            'amount' => ['required', 'numeric', 'gt:0'],
            'adjustment_date' => ['required', 'date'],
            'reason' => ['required', 'string'],
            'notes' => ['nullable', 'string'],
            'mark_claim_rejected' => ['nullable', 'boolean'],
        ]);

        DB::beginTransaction();

        try {
            $sale = $this->findLockedClaimForUser($user, $sale, ['insurancePayments.reversals', 'insuranceClaimAdjustments']);
            $amount = round((float) $validated['amount'], 2);
            $remaining = round((float) $sale->insurance_balance_due, 2);

            if ($amount - $remaining > 0.0001) {
                throw ValidationException::withMessages([
                    'amount' => 'Write-off exceeds the remaining insurer balance on this claim.',
                ]);
            }

            $markRejected = $request->boolean('mark_claim_rejected');
            if ($markRejected && abs($amount - $remaining) > 0.0001) {
                throw ValidationException::withMessages([
                    'mark_claim_rejected' => 'A rejected claim must write off the full remaining insurer balance.',
                ]);
            }

            $adjustmentDate = Carbon::parse($validated['adjustment_date'], config('app.timezone'));

            $adjustment = InsuranceClaimAdjustment::create([
                'client_id' => $sale->client_id,
                'branch_id' => $sale->branch_id,
                'sale_id' => $sale->id,
                'insurer_id' => $sale->insurer_id,
                'created_by' => $user->id,
                'adjustment_type' => $validated['adjustment_type'],
                'amount' => $amount,
                'adjustment_date' => $adjustmentDate,
                'reason' => $validated['reason'],
                'notes' => $validated['notes'] ?? null,
                'mark_claim_rejected' => $markRejected,
            ]);

            $sale->insurance_balance_due = max(0, round((float) $sale->insurance_balance_due - $amount, 2));
            $sale->balance_due = max(0, round((float) $sale->balance_due - $amount, 2));

            if ($markRejected) {
                $sale->insurance_rejected_at = $adjustmentDate;
                $sale->insurance_rejection_reason = $validated['reason'];
            }

            $this->syncInsuranceClaimStatus($sale);
            $sale->save();

            DB::commit();

            app(AuditTrail::class)->recordSafely(
                $user,
                'insurance.manage',
                'Insurance',
                'Record Claim Adjustment',
                'Recorded insurance claim adjustment on invoice ' . ($sale->invoice_number ?? $sale->id) . '.',
                [
                    'subject' => $sale,
                    'subject_label' => $sale->invoice_number ?? ('Sale #' . $sale->id),
                    'new_values' => [
                        'insurance_balance_due' => round((float) $sale->insurance_balance_due, 2),
                        'balance_due' => round((float) $sale->balance_due, 2),
                        'insurance_claim_status' => $sale->insurance_claim_status,
                        'insurance_rejection_reason' => $sale->insurance_rejection_reason,
                    ],
                    'context' => [
                        'insurance_claim_adjustment_id' => $adjustment->id,
                        'amount' => $amount,
                        'adjustment_type' => $adjustment->adjustment_type,
                        'mark_claim_rejected' => $markRejected,
                    ],
                ]
            );

            return redirect()
                ->route('insurance.claims.show', $sale)
                ->with('success', 'Claim adjustment recorded successfully.');
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

    private function batchQueryForUser($user): Builder
    {
        return InsuranceClaimBatch::query()
            ->where('client_id', $user->client_id)
            ->where('branch_id', $user->branch_id);
    }

    private function claimQueryForUser($user, ?Carbon $from = null, ?Carbon $to = null): Builder
    {
        return Sale::query()
            ->where('client_id', $user->client_id)
            ->where('branch_id', $user->branch_id)
            ->where('status', 'approved')
            ->where('payment_type', 'insurance')
            ->whereNotNull('insurer_id')
            ->where('is_active', true)
            ->when($from, fn (Builder $builder) => $builder->whereDate('sale_date', '>=', $from->toDateString()))
            ->when($to, fn (Builder $builder) => $builder->whereDate('sale_date', '<=', $to->toDateString()));
    }

    private function eligibleBatchClaimsQuery($user, Insurer $insurer, Carbon $periodStart, Carbon $periodEnd): Builder
    {
        return $this->claimQueryForUser($user, $periodStart, $periodEnd)
            ->where('insurer_id', $insurer->id)
            ->whereNull('insurance_claim_batch_id')
            ->whereIn('insurance_claim_status', [
                Sale::CLAIM_DRAFT,
                Sale::CLAIM_SUBMITTED,
                Sale::CLAIM_APPROVED,
                Sale::CLAIM_PART_PAID,
            ]);
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

    private function findBatchForUser($user, $batchId, array $with = []): InsuranceClaimBatch
    {
        return $this->batchQueryForUser($user)
            ->with($with)
            ->findOrFail($batchId);
    }

    private function findLockedBatchForUser($user, $batchId, array $with = []): InsuranceClaimBatch
    {
        return $this->batchQueryForUser($user)
            ->with($with)
            ->lockForUpdate()
            ->findOrFail($batchId);
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
        $netAdjustments = (float) InsuranceClaimAdjustment::query()
            ->where('sale_id', $sale->id)
            ->sum('amount');

        if ((float) $sale->insurance_balance_due <= 0.009) {
            if ($sale->insurance_rejected_at && $netReceived <= 0.009 && $netAdjustments > 0.009) {
                $sale->insurance_claim_status = Sale::CLAIM_REJECTED;
                $sale->insurance_paid_at = null;
                return;
            }

            if ($netAdjustments > 0.009) {
                $sale->insurance_claim_status = Sale::CLAIM_RECONCILED;
                $sale->insurance_paid_at = now();
                return;
            }

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

    private function dateFiltersFromRequest(Request $request): array
    {
        $fromDate = $request->filled('from')
            ? Carbon::parse((string) $request->input('from'), config('app.timezone'))->startOfDay()
            : null;
        $toDate = $request->filled('to')
            ? Carbon::parse((string) $request->input('to'), config('app.timezone'))->endOfDay()
            : null;

        if ($fromDate && $toDate && $fromDate->gt($toDate)) {
            [$fromDate, $toDate] = [$toDate->copy()->startOfDay(), $fromDate->copy()->endOfDay()];
        }

        return [$fromDate, $toDate];
    }

    private function buildStatementRows(Collection $claims, Carbon $asOfDate): Collection
    {
        return $claims->map(function (Sale $claim) use ($asOfDate) {
            $saleDate = $claim->sale_date instanceof Carbon
                ? $claim->sale_date->copy()->startOfDay()
                : Carbon::parse($claim->sale_date, config('app.timezone'))->startOfDay();
            $ageDays = $saleDate->diffInDays($asOfDate->copy()->startOfDay(), false);
            $ageDays = max(0, $ageDays);
            $remitted = $this->netClaimRemitted($claim);
            $writtenOff = $this->netClaimAdjustments($claim);

            return [
                'claim' => $claim,
                'invoice_number' => $claim->invoice_number,
                'sale_date' => $saleDate,
                'age_days' => $ageDays,
                'age_bucket' => $this->claimAgeBucket($ageDays),
                'insurer_name' => $claim->insurer?->name ?? 'Unknown Insurer',
                'patient_name' => $claim->customer?->name ?? 'Walk-in / N/A',
                'batch_number' => $claim->insuranceClaimBatch?->batch_number,
                'status_label' => $claim->claim_status_label,
                'covered' => round((float) $claim->insurance_covered_amount, 2),
                'remitted' => round($remitted, 2),
                'written_off' => round($writtenOff, 2),
                'outstanding' => round((float) $claim->insurance_balance_due, 2),
            ];
        });
    }

    private function claimAgeBucket(int $ageDays): string
    {
        return match (true) {
            $ageDays <= 30 => '0-30 Days',
            $ageDays <= 60 => '31-60 Days',
            $ageDays <= 90 => '61-90 Days',
            default => '91+ Days',
        };
    }

    private function netClaimRemitted(Sale $claim): float
    {
        $claim->loadMissing('insurancePayments.reversals');

        return round((float) $claim->insurancePayments->sum(fn (InsurancePayment $payment) => (float) $payment->display_amount), 2);
    }

    private function netClaimAdjustments(Sale $claim): float
    {
        $claim->loadMissing('insuranceClaimAdjustments');

        return round((float) $claim->insuranceClaimAdjustments->sum('amount'), 2);
    }

    private function generateBatchNumber($user): string
    {
        $prefix = 'ICB-' . str_pad((string) $user->client_id, 3, '0', STR_PAD_LEFT) . '-' . now()->format('Ymd');
        $latest = InsuranceClaimBatch::query()
            ->where('batch_number', 'like', $prefix . '-%')
            ->count() + 1;

        return $prefix . '-' . str_pad((string) $latest, 3, '0', STR_PAD_LEFT);
    }
}
