<?php

namespace App\Http\Controllers;

use App\Models\CashDrawerSession;
use App\Models\CashDrawerShift;
use App\Support\AuditTrail;
use App\Support\CashDrawerAlerts;
use App\Support\CashDrawerService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CashDrawerController extends Controller
{
    public function __construct(
        protected CashDrawerService $cashDrawer,
    ) {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $client = $user->client;
        $branch = $user->branch;

        abort_unless($client && $branch, 404);

        $today = Carbon::today(config('app.timezone'));
        $summary = $this->cashDrawer->summaryForUser($user, $today, false);
        $session = $summary['session'] instanceof CashDrawerSession
            ? $summary['session']->load([
                'openedByUser:id,name',
                'dayClosedByUser:id,name',
                'dayReopenedByUser:id,name',
            ])
            : null;
        $recentDraws = $session
            ? $session->draws()->with('drawnByUser:id,name')->limit(10)->get()
            : collect();
        $activeShift = $this->cashDrawer->activeShift((int) $user->client_id, (int) $user->branch_id);
        if ($activeShift) {
            $activeShift->load(['openedByUser:id,name', 'closedByUser:id,name']);
        }

        $recentShifts = $this->cashDrawer
            ->shiftsForDate((int) $user->client_id, (int) $user->branch_id, $today, 8)
            ->map(function (CashDrawerShift $shift) {
                $shift->setAttribute('summary_snapshot', $this->cashDrawer->shiftSummary($shift));

                return $shift;
            });

        return view('cash-drawer.index', [
            'user' => $user,
            'client' => $client,
            'branch' => $branch,
            'summary' => $summary,
            'session' => $session,
            'recentDraws' => $recentDraws,
            'activeShift' => $activeShift,
            'activeShiftSummary' => $activeShift ? $this->cashDrawer->shiftSummary($activeShift) : null,
            'recentShifts' => $recentShifts,
            'canManageCashDrawer' => $user->hasPermission('cash_drawer.manage'),
            'canReopenDay' => $this->cashDrawer->canReopenDay($user),
            'todayLabel' => $today->format('D, d M Y'),
        ]);
    }

    public function updateOpening(Request $request)
    {
        $user = $request->user();
        $today = Carbon::today(config('app.timezone'));
        $validated = $request->validate([
            'opening_balance' => ['required', 'numeric', 'min:0'],
            'opening_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $beforeSummary = $this->cashDrawer->summaryForUser($user, $today, false);
        $session = $this->cashDrawer->recordOpeningBalance(
            $user,
            (float) $validated['opening_balance'],
            $validated['opening_note'] ?? null,
            $today
        );
        $afterSummary = $this->cashDrawer->summaryForUser($user, $today, false);

        app(AuditTrail::class)->recordSafely(
            $user,
            'cash_drawer.opening_updated',
            'Cash Drawer',
            'Update Opening Balance',
            'Updated the day opening balance for the cash drawer.',
            [
                'subject' => $session,
                'old_values' => [
                    'opening_balance' => round((float) ($beforeSummary['opening_balance'] ?? 0), 2),
                    'opening_note' => $beforeSummary['session']?->opening_note,
                ],
                'new_values' => [
                    'opening_balance' => round((float) $session->opening_balance, 2),
                    'opening_note' => $session->opening_note,
                ],
                'context' => [
                    'current_balance' => round((float) ($afterSummary['current_balance'] ?? 0), 2),
                    'session_date' => $session->session_date?->toDateString(),
                ],
            ]
        );

        return redirect()
            ->route('cash-drawer.index')
            ->with('success', 'Opening drawer balance saved for today.');
    }

    public function openShift(Request $request)
    {
        $user = $request->user();
        $today = Carbon::today(config('app.timezone'));
        $validated = $request->validate([
            'shift_opening_balance' => ['required', 'numeric', 'min:0'],
            'shift_opening_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $shift = $this->cashDrawer->openShift(
            $user,
            (float) $validated['shift_opening_balance'],
            $validated['shift_opening_note'] ?? null,
            $today
        );

        app(AuditTrail::class)->recordSafely(
            $user,
            'cash_drawer.shift_opened',
            'Cash Drawer',
            'Open Shift',
            'Opened a new cash drawer shift.',
            [
                'subject' => $shift,
                'old_values' => [
                    'active_shift' => false,
                ],
                'new_values' => [
                    'opening_balance' => round((float) $shift->opening_balance, 2),
                    'opening_note' => $shift->opening_note,
                    'opened_at' => $shift->opened_at?->format('Y-m-d H:i:s'),
                ],
                'context' => [
                    'session_date' => $today->toDateString(),
                ],
            ]
        );

        return redirect()
            ->route('cash-drawer.index')
            ->with('success', 'Shift opened successfully.');
    }

    public function closeShift(Request $request, CashDrawerShift $shift)
    {
        $user = $request->user();
        $beforeSummary = $this->cashDrawer->shiftSummary($shift);
        $validated = $request->validate([
            'counted_cash' => ['required', 'numeric', 'min:0'],
            'banked_amount' => ['nullable', 'numeric', 'min:0'],
            'handover_amount' => ['nullable', 'numeric', 'min:0'],
            'closing_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $shift = $this->cashDrawer->closeShift(
            $user,
            $shift,
            (float) $validated['counted_cash'],
            (float) ($validated['banked_amount'] ?? 0),
            (float) ($validated['handover_amount'] ?? 0),
            $validated['closing_note'] ?? null,
        );
        $afterSummary = $this->cashDrawer->shiftSummary($shift);

        app(AuditTrail::class)->recordSafely(
            $user,
            'cash_drawer.shift_closed',
            'Cash Drawer',
            'Close Shift',
            'Closed a cash drawer shift.',
            [
                'subject' => $shift,
                'reason' => $validated['closing_note'] ?? null,
                'old_values' => $beforeSummary,
                'new_values' => $afterSummary,
                'context' => [
                    'counted_cash' => round((float) $validated['counted_cash'], 2),
                    'banked_amount' => round((float) ($validated['banked_amount'] ?? 0), 2),
                    'handover_amount' => round((float) ($validated['handover_amount'] ?? 0), 2),
                ],
            ]
        );

        return redirect()
            ->route('cash-drawer.index')
            ->with('success', 'Shift closed successfully.');
    }

    public function closeDay(Request $request)
    {
        $user = $request->user();
        $today = Carbon::today(config('app.timezone'));
        $validated = $request->validate([
            'day_counted_cash' => ['required', 'numeric', 'min:0'],
            'day_closing_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $beforeSummary = $this->cashDrawer->summaryForUser($user, $today, false);
        $session = $this->cashDrawer->closeDay(
            $user,
            (float) $validated['day_counted_cash'],
            $validated['day_closing_note'] ?? null,
            $today
        );

        app(AuditTrail::class)->recordSafely(
            $user,
            'cash_drawer.day_closed',
            'Cash Drawer',
            'Close Day',
            'Closed the cash drawer for the day.',
            [
                'subject' => $session,
                'reason' => $validated['day_closing_note'] ?? null,
                'old_values' => [
                    'day_closed' => (bool) ($beforeSummary['day_closed'] ?? false),
                    'current_balance' => round((float) ($beforeSummary['current_balance'] ?? 0), 2),
                ],
                'new_values' => [
                    'day_closed' => true,
                    'day_closed_at' => $session->day_closed_at?->format('Y-m-d H:i:s'),
                    'day_closing_expected_balance' => round((float) $session->day_closing_expected_balance, 2),
                    'day_closing_counted_balance' => round((float) $session->day_closing_counted_balance, 2),
                    'day_closing_variance' => round((float) $session->day_closing_variance, 2),
                ],
                'context' => [
                    'counted_cash' => round((float) $validated['day_counted_cash'], 2),
                    'session_date' => $today->toDateString(),
                ],
            ]
        );

        return redirect()
            ->route('cash-drawer.index')
            ->with('success', 'End-of-day closing saved successfully.');
    }

    public function reopenDay(Request $request)
    {
        abort_unless($this->cashDrawer->canReopenDay($request->user()), 403);
        $user = $request->user();
        $today = Carbon::today(config('app.timezone'));

        $validated = $request->validate([
            'day_reopen_reason' => ['required', 'string', 'max:1000'],
        ]);

        $beforeSummary = $this->cashDrawer->summaryForUser($user, $today, false);
        $session = $this->cashDrawer->reopenDay(
            $user,
            $validated['day_reopen_reason'],
            $today
        );

        app(AuditTrail::class)->recordSafely(
            $user,
            'cash_drawer.day_reopened',
            'Cash Drawer',
            'Reopen Day',
            'Reopened a previously closed cash drawer day.',
            [
                'subject' => $session,
                'reason' => $validated['day_reopen_reason'],
                'old_values' => [
                    'day_closed' => (bool) ($beforeSummary['day_closed'] ?? false),
                    'day_closed_at' => ($beforeSummary['day_closed_at'] ?? null)?->format('Y-m-d H:i:s'),
                ],
                'new_values' => [
                    'day_reopened_at' => $session->day_reopened_at?->format('Y-m-d H:i:s'),
                    'day_reopening_note' => $session->day_reopening_note,
                ],
                'context' => [
                    'session_date' => $today->toDateString(),
                ],
            ]
        );

        return redirect()
            ->route('cash-drawer.index')
            ->with('success', 'The day has been reopened successfully.');
    }

    public function storeDraw(Request $request)
    {
        $user = $request->user();
        $today = Carbon::today(config('app.timezone'));
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $beforeSummary = $this->cashDrawer->summaryForUser($user, $today, false);
        $draw = $this->cashDrawer->recordDraw(
            $user,
            (float) $validated['amount'],
            $validated['reason'],
            $today
        );
        $afterSummary = $this->cashDrawer->summaryForUser($user, $today, false);

        app(AuditTrail::class)->recordSafely(
            $user,
            'cash_drawer.draw_recorded',
            'Cash Drawer',
            'Record Cash Draw',
            'Recorded a cash drawer draw.',
            [
                'subject' => $draw,
                'reason' => $validated['reason'],
                'old_values' => [
                    'current_balance' => round((float) ($beforeSummary['current_balance'] ?? 0), 2),
                    'draws_total' => round((float) ($beforeSummary['draws_total'] ?? 0), 2),
                ],
                'new_values' => [
                    'current_balance' => round((float) ($afterSummary['current_balance'] ?? 0), 2),
                    'draws_total' => round((float) ($afterSummary['draws_total'] ?? 0), 2),
                ],
                'context' => [
                    'amount' => round((float) $draw->amount, 2),
                    'drawn_at' => $draw->drawn_at?->format('Y-m-d H:i:s'),
                ],
            ]
        );

        return redirect()
            ->route('cash-drawer.index')
            ->with('success', 'Cash draw recorded successfully.');
    }

    public function reminder(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['available' => false]);
        }

        $warning = CashDrawerAlerts::pullDueWarning($request, $user);

        if (! $warning) {
            return response()->json([
                'available' => false,
                'poll_seconds' => CashDrawerAlerts::pollSeconds(),
            ]);
        }

        return response()->json([
            'available' => true,
            'warning' => $warning,
            'poll_seconds' => CashDrawerAlerts::pollSeconds(),
        ]);
    }
}
