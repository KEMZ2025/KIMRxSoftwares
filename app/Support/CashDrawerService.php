<?php

namespace App\Support;

use App\Models\CashDrawerDraw;
use App\Models\CashDrawerSession;
use App\Models\CashDrawerShift;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CashDrawerService
{
    public function sessionForDate(
        int $clientId,
        int $branchId,
        CarbonInterface|string|null $date = null,
        bool $create = false
    ): ?CashDrawerSession {
        if ($clientId <= 0 || $branchId <= 0) {
            return null;
        }

        $sessionDate = $this->normalizeDate($date)->toDateString();
        $baseQuery = CashDrawerSession::query()
            ->where('client_id', $clientId)
            ->where('branch_id', $branchId);

        $existingSession = $baseQuery
            ->orderByDesc('session_date')
            ->get()
            ->first(function (CashDrawerSession $session) use ($sessionDate) {
                return $session->session_date?->toDateString() === $sessionDate;
            });

        if (! $create) {
            return $existingSession;
        }

        if ($existingSession) {
            return $existingSession;
        }

        return CashDrawerSession::query()->create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'session_date' => $sessionDate,
            'opening_balance' => 0,
        ]);
    }

    public function summaryForUser(
        User $user,
        CarbonInterface|string|null $date = null,
        bool $createSession = false
    ): array {
        $settings = $user->clientSettingsModel();

        return $this->summary(
            (int) $user->client_id,
            (int) $user->branch_id,
            $date,
            $createSession,
            [
                'currency_symbol' => $settings?->currency_symbol ?: 'UGX',
                'alert_threshold' => $settings?->cash_drawer_alert_threshold,
            ]
        );
    }

    public function summary(
        int $clientId,
        int $branchId,
        CarbonInterface|string|null $date = null,
        bool $createSession = false,
        array $options = []
    ): array {
        $resolvedDate = $this->normalizeDate($date);
        $session = $this->sessionForDate($clientId, $branchId, $resolvedDate, $createSession);

        $openingBalance = round((float) ($session?->opening_balance ?? 0), 2);
        $cashSalesTotal = $this->cashSalesTotal($clientId, $branchId, $resolvedDate);
        $cashCollectionsTotal = $this->cashCollectionsTotal($clientId, $branchId, $resolvedDate);
        $drawsTotal = $this->drawTotal($session);
        $currentBalance = round($openingBalance + $cashSalesTotal + $cashCollectionsTotal - $drawsTotal, 2);

        $alertThreshold = $options['alert_threshold'] ?? null;
        $alertThreshold = $alertThreshold !== null && $alertThreshold !== ''
            ? round((float) $alertThreshold, 2)
            : null;
        $thresholdReached = $alertThreshold !== null
            && $alertThreshold > 0
            && $currentBalance >= $alertThreshold;

        return [
            'date' => $resolvedDate->toDateString(),
            'session' => $session,
            'opening_balance' => $openingBalance,
            'cash_sales_total' => $cashSalesTotal,
            'cash_collections_total' => $cashCollectionsTotal,
            'draws_total' => $drawsTotal,
            'current_balance' => $currentBalance,
            'currency_symbol' => (string) ($options['currency_symbol'] ?? 'UGX'),
            'alert_threshold' => $alertThreshold,
            'threshold_reached' => $thresholdReached,
            'threshold_gap' => $alertThreshold !== null ? round($currentBalance - $alertThreshold, 2) : null,
            'day_closed' => $this->sessionIsDayClosed($session),
            'day_closed_at' => $session?->day_closed_at,
            'day_closed_by' => $session?->dayClosedByUser,
            'day_closing_expected_balance' => round((float) ($session?->day_closing_expected_balance ?? 0), 2),
            'day_closing_counted_balance' => round((float) ($session?->day_closing_counted_balance ?? 0), 2),
            'day_closing_variance' => round((float) ($session?->day_closing_variance ?? 0), 2),
            'day_closing_note' => $session?->day_closing_note,
            'day_reopened_at' => $session?->day_reopened_at,
            'day_reopened_by' => $session?->dayReopenedByUser,
            'day_reopening_note' => $session?->day_reopening_note,
        ];
    }

    public function recordOpeningBalance(
        User $user,
        float $openingBalance,
        ?string $note,
        CarbonInterface|string|null $date = null
    ): CashDrawerSession {
        $resolvedDate = $this->normalizeDate($date);
        $session = $this->sessionForDate((int) $user->client_id, (int) $user->branch_id, $resolvedDate, true);

        $this->ensureDayIsOpen($session);
        $this->ensureNoShiftHasStarted((int) $user->client_id, (int) $user->branch_id, $resolvedDate);

        $session->fill([
            'opening_balance' => round($openingBalance, 2),
            'opening_note' => filled($note) ? trim((string) $note) : null,
            'opened_by' => $user->id,
        ])->save();

        return $session->refresh();
    }

    public function recordDraw(
        User $user,
        float $amount,
        string $reason,
        CarbonInterface|string|null $date = null
    ): CashDrawerDraw {
        $resolvedDate = $this->normalizeDate($date);
        $summary = $this->summaryForUser($user, $resolvedDate, false);
        $normalizedAmount = round($amount, 2);
        $normalizedReason = trim($reason);

        if ($normalizedAmount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Enter a valid cash draw amount greater than zero.',
            ]);
        }

        if ($normalizedReason === '') {
            throw ValidationException::withMessages([
                'reason' => 'Please state why this cash is being drawn from the drawer.',
            ]);
        }

        if ($summary['day_closed'] ?? false) {
            throw ValidationException::withMessages([
                'amount' => 'Today has already been closed. Reopen the day before recording another draw.',
            ]);
        }

        if ($normalizedAmount - (float) $summary['current_balance'] > 0.0001) {
            throw ValidationException::withMessages([
                'amount' => 'Draw amount cannot be higher than the tracked cash currently in the drawer.',
            ]);
        }

        $session = $this->sessionForDate((int) $user->client_id, (int) $user->branch_id, $resolvedDate, true);

        return CashDrawerDraw::query()->create([
            'cash_drawer_session_id' => $session->id,
            'client_id' => (int) $user->client_id,
            'branch_id' => (int) $user->branch_id,
            'amount' => $normalizedAmount,
            'reason' => $normalizedReason,
            'drawn_by' => $user->id,
            'drawn_at' => now(config('app.timezone')),
        ]);
    }

    public function activeShift(int $clientId, int $branchId): ?CashDrawerShift
    {
        if ($clientId <= 0 || $branchId <= 0) {
            return null;
        }

        return CashDrawerShift::query()
            ->where('client_id', $clientId)
            ->where('branch_id', $branchId)
            ->whereNull('closed_at')
            ->latest('opened_at')
            ->latest('id')
            ->first();
    }

    public function shiftsForDate(
        int $clientId,
        int $branchId,
        CarbonInterface|string|null $date = null,
        int $limit = 10
    ): Collection {
        if ($clientId <= 0 || $branchId <= 0) {
            return collect();
        }

        $resolvedDate = $this->normalizeDate($date);

        return CashDrawerShift::query()
            ->where('client_id', $clientId)
            ->where('branch_id', $branchId)
            ->whereDate('opened_at', $resolvedDate->toDateString())
            ->with(['openedByUser:id,name', 'closedByUser:id,name'])
            ->orderByDesc('opened_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public function openShift(
        User $user,
        float $openingBalance,
        ?string $note,
        CarbonInterface|string|null $date = null
    ): CashDrawerShift {
        $resolvedDate = $this->normalizeDate($date);
        $normalizedOpeningBalance = round($openingBalance, 2);
        $normalizedNote = filled($note) ? trim((string) $note) : null;

        if ($normalizedOpeningBalance < 0) {
            throw ValidationException::withMessages([
                'shift_opening_balance' => 'Shift opening cash cannot be negative.',
            ]);
        }

        if ($this->activeShift((int) $user->client_id, (int) $user->branch_id)) {
            throw ValidationException::withMessages([
                'shift_opening_balance' => 'Another shift is still open for this branch. Close it first before opening a new one.',
            ]);
        }

        $session = $this->sessionForDate((int) $user->client_id, (int) $user->branch_id, $resolvedDate, true);
        $this->ensureDayIsOpen($session);

        $hasShiftToday = CashDrawerShift::query()
            ->where('client_id', (int) $user->client_id)
            ->where('branch_id', (int) $user->branch_id)
            ->whereDate('opened_at', $resolvedDate->toDateString())
            ->exists();

        return DB::transaction(function () use ($user, $session, $resolvedDate, $normalizedOpeningBalance, $normalizedNote, $hasShiftToday) {
            if (! $hasShiftToday) {
                $session->fill([
                    'opening_balance' => $normalizedOpeningBalance,
                    'opening_note' => $normalizedNote,
                    'opened_by' => $user->id,
                ])->save();
            }

            return CashDrawerShift::query()->create([
                'cash_drawer_session_id' => $session->id,
                'client_id' => (int) $user->client_id,
                'branch_id' => (int) $user->branch_id,
                'opened_by' => $user->id,
                'opening_balance' => $normalizedOpeningBalance,
                'opening_note' => $normalizedNote,
                'opened_at' => now(config('app.timezone')),
            ]);
        });
    }

    public function shiftSummary(
        CashDrawerShift $shift,
        CarbonInterface|string|null $upTo = null
    ): array {
        $openedAt = $this->normalizeDateTime($shift->opened_at);
        $closedAt = $shift->closed_at
            ? $this->normalizeDateTime($shift->closed_at)
            : $this->normalizeDateTime($upTo ?? now(config('app.timezone')));

        if ($closedAt->lessThan($openedAt)) {
            $closedAt = $openedAt->copy();
        }

        $cashSalesTotal = $this->cashSalesTotalBetween(
            (int) $shift->client_id,
            (int) $shift->branch_id,
            $openedAt,
            $closedAt
        );
        $cashCollectionsTotal = $this->cashCollectionsTotalBetween(
            (int) $shift->client_id,
            (int) $shift->branch_id,
            $openedAt,
            $closedAt
        );
        $drawsTotal = $this->drawTotalBetween(
            (int) $shift->client_id,
            (int) $shift->branch_id,
            $openedAt,
            $closedAt
        );
        $expectedBalance = round(
            (float) $shift->opening_balance + $cashSalesTotal + $cashCollectionsTotal - $drawsTotal,
            2
        );
        $countedBalance = $shift->closing_counted_balance !== null
            ? round((float) $shift->closing_counted_balance, 2)
            : null;
        $variance = $countedBalance !== null
            ? round($countedBalance - $expectedBalance, 2)
            : null;

        return [
            'opened_at' => $openedAt,
            'closed_at' => $shift->closed_at ? $closedAt : null,
            'opening_balance' => round((float) $shift->opening_balance, 2),
            'cash_sales_total' => $cashSalesTotal,
            'cash_collections_total' => $cashCollectionsTotal,
            'draws_total' => $drawsTotal,
            'expected_balance' => $expectedBalance,
            'counted_balance' => $countedBalance,
            'variance' => $variance,
            'banked_amount' => round((float) ($shift->banked_amount ?? 0), 2),
            'handover_amount' => round((float) ($shift->handover_amount ?? 0), 2),
            'is_closed' => $shift->closed_at !== null,
        ];
    }

    public function closeShift(
        User $user,
        CashDrawerShift $shift,
        float $countedCash,
        float $bankedAmount = 0,
        float $handoverAmount = 0,
        ?string $note = null
    ): CashDrawerShift {
        $this->ensureShiftBelongsToUserBranch($user, $shift);

        if ($shift->closed_at !== null) {
            throw ValidationException::withMessages([
                'counted_cash' => 'This shift has already been closed.',
            ]);
        }

        $session = $shift->session()->first();
        $this->ensureDayIsOpen($session);

        $normalizedCountedCash = round($countedCash, 2);
        $normalizedBankedAmount = round($bankedAmount, 2);
        $normalizedHandoverAmount = round($handoverAmount, 2);
        $normalizedNote = filled($note) ? trim((string) $note) : null;

        if ($normalizedCountedCash < 0) {
            throw ValidationException::withMessages([
                'counted_cash' => 'Counted cash cannot be negative.',
            ]);
        }

        if ($normalizedBankedAmount < 0 || $normalizedHandoverAmount < 0) {
            throw ValidationException::withMessages([
                'banked_amount' => 'Banked and handover amounts cannot be negative.',
            ]);
        }

        if (($normalizedBankedAmount + $normalizedHandoverAmount) - $normalizedCountedCash > 0.0001) {
            throw ValidationException::withMessages([
                'banked_amount' => 'Banked plus handover cash cannot be higher than the cash counted at shift close.',
            ]);
        }

        $summary = $this->shiftSummary($shift, now(config('app.timezone')));
        $closedAt = now(config('app.timezone'));

        return DB::transaction(function () use (
            $user,
            $shift,
            $session,
            $summary,
            $closedAt,
            $normalizedCountedCash,
            $normalizedBankedAmount,
            $normalizedHandoverAmount,
            $normalizedNote
        ) {
            $shift->fill([
                'closed_by' => $user->id,
                'closing_expected_balance' => $summary['expected_balance'],
                'closing_counted_balance' => $normalizedCountedCash,
                'closing_variance' => round($normalizedCountedCash - (float) $summary['expected_balance'], 2),
                'banked_amount' => $normalizedBankedAmount,
                'handover_amount' => $normalizedHandoverAmount,
                'closing_note' => $normalizedNote,
                'closed_at' => $closedAt,
            ])->save();

            if ($normalizedBankedAmount > 0 && $session) {
                CashDrawerDraw::query()->create([
                    'cash_drawer_session_id' => $session->id,
                    'client_id' => (int) $shift->client_id,
                    'branch_id' => (int) $shift->branch_id,
                    'amount' => $normalizedBankedAmount,
                    'reason' => 'Shift close banked cash' . ($normalizedNote ? ': ' . $normalizedNote : ''),
                    'drawn_by' => $user->id,
                    'drawn_at' => $closedAt->copy()->addSecond(),
                ]);
            }

            return $shift->refresh();
        });
    }

    public function closeDay(
        User $user,
        float $countedCash,
        ?string $note,
        CarbonInterface|string|null $date = null
    ): CashDrawerSession {
        $resolvedDate = $this->normalizeDate($date);
        $session = $this->sessionForDate((int) $user->client_id, (int) $user->branch_id, $resolvedDate, true);

        $this->ensureDayIsOpen($session);

        if ($this->activeShift((int) $user->client_id, (int) $user->branch_id)) {
            throw ValidationException::withMessages([
                'day_counted_cash' => 'Close the active shift before closing the whole day.',
            ]);
        }

        $normalizedCountedCash = round($countedCash, 2);
        if ($normalizedCountedCash < 0) {
            throw ValidationException::withMessages([
                'day_counted_cash' => 'End-of-day counted cash cannot be negative.',
            ]);
        }

        $summary = $this->summaryForUser($user, $resolvedDate, false);

        $session->fill([
            'day_closed_at' => now(config('app.timezone')),
            'day_closed_by' => $user->id,
            'day_closing_expected_balance' => $summary['current_balance'],
            'day_closing_counted_balance' => $normalizedCountedCash,
            'day_closing_variance' => round($normalizedCountedCash - (float) $summary['current_balance'], 2),
            'day_closing_note' => filled($note) ? trim((string) $note) : null,
            'day_reopened_at' => null,
            'day_reopened_by' => null,
            'day_reopening_note' => null,
        ])->save();

        return $session->refresh();
    }

    public function reopenDay(
        User $user,
        string $reason,
        CarbonInterface|string|null $date = null
    ): CashDrawerSession {
        if (! $this->canReopenDay($user)) {
            throw ValidationException::withMessages([
                'day_reopen_reason' => 'Only an admin or platform owner can reopen a closed day.',
            ]);
        }

        $resolvedDate = $this->normalizeDate($date);
        $session = $this->sessionForDate((int) $user->client_id, (int) $user->branch_id, $resolvedDate, false);

        if (! $this->sessionIsDayClosed($session)) {
            throw ValidationException::withMessages([
                'day_reopen_reason' => 'This day is not currently closed.',
            ]);
        }

        $normalizedReason = trim($reason);
        if ($normalizedReason === '') {
            throw ValidationException::withMessages([
                'day_reopen_reason' => 'Please state why this day is being reopened.',
            ]);
        }

        $session->fill([
            'day_reopened_at' => now(config('app.timezone')),
            'day_reopened_by' => $user->id,
            'day_reopening_note' => $normalizedReason,
        ])->save();

        return $session->refresh();
    }

    public function canReopenDay(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission('users.manage');
    }

    public function sessionIsDayClosed(?CashDrawerSession $session): bool
    {
        if (! $session || ! $session->day_closed_at) {
            return false;
        }

        return ! $session->day_reopened_at || $session->day_reopened_at->lessThan($session->day_closed_at);
    }

    private function ensureDayIsOpen(?CashDrawerSession $session): void
    {
        if (! $this->sessionIsDayClosed($session)) {
            return;
        }

        throw ValidationException::withMessages([
            'cash_drawer' => 'Today has already been closed. Reopen the day before making more drawer changes.',
        ]);
    }

    private function ensureNoShiftHasStarted(int $clientId, int $branchId, Carbon $date): void
    {
        $shiftExists = CashDrawerShift::query()
            ->where('client_id', $clientId)
            ->where('branch_id', $branchId)
            ->whereDate('opened_at', $date->toDateString())
            ->exists();

        if (! $shiftExists) {
            return;
        }

        throw ValidationException::withMessages([
            'opening_balance' => 'A shift has already started today. Reopen the day or use the shift opening figure instead of changing the day opening balance now.',
        ]);
    }

    private function ensureShiftBelongsToUserBranch(User $user, CashDrawerShift $shift): void
    {
        if (
            (int) $shift->client_id === (int) $user->client_id
            && (int) $shift->branch_id === (int) $user->branch_id
        ) {
            return;
        }

        throw ValidationException::withMessages([
            'counted_cash' => 'That shift does not belong to your current branch context.',
        ]);
    }

    private function cashSalesTotal(int $clientId, int $branchId, Carbon $date): float
    {
        return $this->cashSalesTotalBetween(
            $clientId,
            $branchId,
            $date->copy()->startOfDay(),
            $date->copy()->endOfDay()
        );
    }

    private function cashSalesTotalBetween(int $clientId, int $branchId, CarbonInterface $from, CarbonInterface $to): float
    {
        if ($clientId <= 0 || $branchId <= 0) {
            return 0;
        }

        return round(
            Sale::query()
                ->where('client_id', $clientId)
                ->where('branch_id', $branchId)
                ->where('is_active', true)
                ->where('status', 'approved')
                ->where('amount_paid', '>', 0)
                ->whereBetween('sale_date', [$from, $to])
                ->get(['payment_method', 'amount_paid'])
                ->filter(fn (Sale $sale) => PaymentMethodBuckets::normalize($sale->payment_method) === 'cash')
                ->sum(fn (Sale $sale) => (float) $sale->amount_paid),
            2
        );
    }

    private function cashCollectionsTotal(int $clientId, int $branchId, Carbon $date): float
    {
        return $this->cashCollectionsTotalBetween(
            $clientId,
            $branchId,
            $date->copy()->startOfDay(),
            $date->copy()->endOfDay()
        );
    }

    private function cashCollectionsTotalBetween(int $clientId, int $branchId, CarbonInterface $from, CarbonInterface $to): float
    {
        if ($clientId <= 0 || $branchId <= 0) {
            return 0;
        }

        return round(
            Payment::query()
                ->where('client_id', $clientId)
                ->where('branch_id', $branchId)
                ->where('status', '!=', 'pending')
                ->whereHas('sale', function ($saleQuery) use ($from, $to) {
                    $saleQuery
                        ->where('is_active', true)
                        ->where('status', 'approved')
                        ->whereBetween('sale_date', [$from, $to]);
                })
                ->whereBetween('payment_date', [$from, $to])
                ->get(['payment_method', 'amount', 'reversal_of_payment_id'])
                ->filter(fn (Payment $payment) => PaymentMethodBuckets::normalize($payment->payment_method) === 'cash')
                ->sum(function (Payment $payment) {
                    $direction = $payment->reversal_of_payment_id ? -1 : 1;

                    return (float) $payment->amount * $direction;
                }),
            2
        );
    }

    private function drawTotal(?CashDrawerSession $session): float
    {
        if (! $session) {
            return 0;
        }

        return round((float) $session->draws()->sum('amount'), 2);
    }

    private function drawTotalBetween(int $clientId, int $branchId, CarbonInterface $from, CarbonInterface $to): float
    {
        if ($clientId <= 0 || $branchId <= 0) {
            return 0;
        }

        return round(
            (float) CashDrawerDraw::query()
                ->where('client_id', $clientId)
                ->where('branch_id', $branchId)
                ->whereBetween('drawn_at', [$from, $to])
                ->sum('amount'),
            2
        );
    }

    private function normalizeDate(CarbonInterface|string|null $date = null): Carbon
    {
        if ($date instanceof CarbonInterface) {
            return Carbon::parse($date->format('Y-m-d H:i:s'), config('app.timezone'));
        }

        if (filled($date)) {
            return Carbon::parse((string) $date, config('app.timezone'));
        }

        return Carbon::now(config('app.timezone'));
    }

    private function normalizeDateTime(CarbonInterface|string|null $date = null): Carbon
    {
        return $this->normalizeDate($date);
    }
}
