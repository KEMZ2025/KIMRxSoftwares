<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cash Drawer - KIM Rx</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            display: flex;
            background:
                radial-gradient(circle at top right, rgba(16, 185, 129, 0.08), transparent 25%),
                linear-gradient(180deg, #f8fafc 0%, #eefbf5 100%);
            color: #172033;
        }
        .content {
            flex: 1;
            width: 100%;
            max-width: 100%;
            padding: 20px;
        }
        .topbar,
        .panel {
            background: rgba(255, 255, 255, 0.96);
            border-radius: 20px;
            padding: 22px;
            box-shadow: 0 18px 42px rgba(15, 23, 42, 0.08);
            border: 1px solid rgba(226, 232, 240, 0.9);
            margin-bottom: 20px;
        }
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 18px;
        }
        .eyebrow {
            margin: 0 0 8px;
            color: #475467;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        .topbar h1 {
            margin: 0 0 6px;
            font-size: clamp(28px, 3vw, 42px);
            line-height: 1.05;
        }
        .topbar p {
            margin: 0;
            color: #667085;
            font-size: 15px;
        }
        .chip-stack {
            display: grid;
            gap: 10px;
        }
        .chip {
            padding: 12px 16px;
            border-radius: 999px;
            background: linear-gradient(135deg, #dcfce7, #ecfdf3);
            color: #067647;
            font-weight: 800;
            white-space: nowrap;
            font-size: 13px;
        }
        .chip.alt {
            background: linear-gradient(135deg, #eef4ff, #f5f8ff);
            color: #1d4ed8;
        }
        .message {
            border-radius: 14px;
            padding: 14px 16px;
            margin-bottom: 16px;
            font-weight: 700;
        }
        .message.success {
            background: #dcfce7;
            color: #067647;
            border: 1px solid #abefc6;
        }
        .message.error {
            background: #fee4e2;
            color: #b42318;
            border: 1px solid #fecdca;
        }
        .banner {
            border-radius: 18px;
            padding: 18px 20px;
            margin-bottom: 20px;
            border: 1px solid #d1fadf;
            background: linear-gradient(135deg, #ecfdf3, #f7fffb);
        }
        .banner.warn {
            border-color: #fedf89;
            background: linear-gradient(135deg, #fff7db, #fffaeb);
        }
        .banner strong {
            display: block;
            margin-bottom: 8px;
        }
        .stats-grid,
        .forms-grid {
            display: grid;
            gap: 18px;
        }
        .stats-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
        .forms-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .stat-card {
            border: 1px solid #e4e7ec;
            border-radius: 18px;
            padding: 18px;
            background: linear-gradient(180deg, #ffffff, #f8fafc);
        }
        .stat-card strong {
            display: block;
            margin-bottom: 8px;
            color: #475467;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .stat-card .value {
            display: block;
            font-size: 30px;
            font-weight: 800;
            color: #172033;
        }
        .stat-card .meta {
            display: block;
            margin-top: 8px;
            font-size: 13px;
            color: #667085;
            line-height: 1.5;
        }
        .panel h2 {
            margin: 0 0 8px;
            font-size: 22px;
        }
        .panel-subtitle {
            margin: 0 0 18px;
            color: #667085;
            font-size: 14px;
            line-height: 1.5;
        }
        .field-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }
        .field.full {
            grid-column: 1 / -1;
        }
        .field label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            color: #344054;
            margin-bottom: 8px;
        }
        .field input,
        .field textarea {
            width: 100%;
            border: 1px solid #d0d5dd;
            border-radius: 14px;
            padding: 12px 14px;
            font: inherit;
            background: #fff;
        }
        .field textarea {
            min-height: 120px;
            resize: vertical;
        }
        .hint {
            margin-top: 6px;
            color: #667085;
            font-size: 12px;
            line-height: 1.5;
        }
        .error {
            margin-top: 6px;
            color: #b42318;
            font-size: 12px;
            font-weight: 700;
        }
        .actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 18px;
        }
        .btn {
            border: none;
            border-radius: 14px;
            padding: 12px 16px;
            cursor: pointer;
            font-weight: 800;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }
        .btn-primary {
            background: #067647;
            color: #fff;
        }
        .btn-light {
            background: #eef4ff;
            color: #1d4ed8;
        }
        .btn-danger {
            background: #b42318;
            color: #fff;
        }
        .status-row,
        .summary-grid {
            display: grid;
            gap: 12px;
        }
        .status-row {
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            margin-bottom: 18px;
        }
        .status-pill,
        .summary-tile {
            border: 1px solid #d0d5dd;
            border-radius: 16px;
            padding: 14px 16px;
            background: linear-gradient(180deg, #fff, #f8fafc);
        }
        .status-pill strong,
        .summary-tile strong {
            display: block;
            color: #344054;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 6px;
        }
        .status-pill span,
        .summary-tile span {
            display: block;
            color: #172033;
            font-size: 15px;
            font-weight: 700;
            line-height: 1.5;
        }
        .summary-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
            margin-bottom: 18px;
        }
        .variance-positive {
            color: #067647;
        }
        .variance-negative {
            color: #b42318;
        }
        .table-wrap {
            overflow-x: auto;
            border: 1px solid #e4e7ec;
            border-radius: 18px;
            background: #fff;
        }
        .table-wrap table {
            width: 100%;
            border-collapse: collapse;
        }
        .table-wrap th,
        .table-wrap td {
            padding: 12px 14px;
            border-bottom: 1px solid #eef2f6;
            text-align: left;
            font-size: 13px;
            vertical-align: top;
        }
        .table-wrap th {
            background: #f8fafc;
            color: #475467;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .empty-state {
            border: 1px dashed #cbd5e1;
            border-radius: 18px;
            padding: 18px;
            background: #f8fafc;
            color: #667085;
            font-size: 14px;
            line-height: 1.6;
        }
        @media (max-width: 1100px) {
            .stats-grid,
            .forms-grid,
            .field-grid,
            .summary-grid {
                grid-template-columns: 1fr;
            }
        }
        @media (max-width: 760px) {
            .topbar {
                flex-direction: column;
            }
            .actions {
                flex-direction: column;
            }
            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    @include('layouts.sidebar', [
        'clientName' => optional($client)->name ?? 'N/A',
        'branchName' => optional($branch)->name ?? 'N/A',
    ])

    @php
        $currency = $summary['currency_symbol'] ?? 'UGX';
        $formatMoney = fn ($value) => $currency . ' ' . number_format((float) $value, 2);
        $formatVariance = function ($value) use ($formatMoney) {
            $amount = (float) $value;
            $prefix = $amount > 0 ? '+' : '';

            return $prefix . $formatMoney($amount);
        };
        $dayClosed = (bool) ($summary['day_closed'] ?? false);
        $hasShiftActivity = $activeShift || $recentShifts->isNotEmpty();
    @endphp

    <main class="content" id="mainContent">
        <div class="topbar">
            <div>
                <p class="eyebrow">Operations</p>
                <h1>Cash Drawer</h1>
                <p>Track today&apos;s working drawer without changing sales, stock, or accounting logic. This page now handles the full cycle: day opening, live drawer totals, shift reconciliation, and end-of-day closing for the branch.</p>
            </div>
            <div class="chip-stack">
                <div class="chip">{{ $todayLabel }}</div>
                <div class="chip alt">Branch: {{ optional($branch)->name ?? 'N/A' }}</div>
            </div>
        </div>

        @if (session('success'))
            <div class="message success">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="message error">Please correct the highlighted cash drawer fields and try again.</div>
        @endif

        @if ($dayClosed)
            <div class="banner warn">
                <strong>Today has already been closed.</strong>
                The branch closed this drawer day at {{ optional($summary['day_closed_at'])->format('d M Y H:i') ?? 'N/A' }}
                @if ($summary['day_closed_by'])
                    by {{ $summary['day_closed_by']->name }}
                @endif
                . Expected cash was {{ $formatMoney($summary['day_closing_expected_balance']) }}, counted cash was {{ $formatMoney($summary['day_closing_counted_balance']) }}, and the variance was {{ $formatVariance($summary['day_closing_variance']) }}.
                @if ($summary['day_closing_note'])
                    Closing note: {{ $summary['day_closing_note'] }}
                @endif
                @if ($summary['day_reopened_at'])
                    Reopened at {{ $summary['day_reopened_at']->format('d M Y H:i') }}
                    @if ($summary['day_reopened_by'])
                        by {{ $summary['day_reopened_by']->name }}
                    @endif
                    . {{ $summary['day_reopening_note'] }}
                @endif
            </div>
        @endif

        <div class="banner {{ ($summary['threshold_reached'] ?? false) ? 'warn' : '' }}">
            @if ($dayClosed)
                <strong>Drawer threshold alerts pause after day closing.</strong>
                The day is already closed, so the automatic drawer alert is quiet until the day is reopened or the next operating day starts.
            @elseif (($summary['threshold_reached'] ?? false) && $summary['alert_threshold'] !== null)
                <strong>Drawer threshold reached.</strong>
                The tracked drawer balance is {{ $formatMoney($summary['current_balance']) }}, which has reached or exceeded the alert figure of {{ $formatMoney($summary['alert_threshold']) }}. Record a cash draw with a reason so excess money does not remain in the drawer.
            @elseif ($summary['alert_threshold'] !== null)
                <strong>Drawer threshold is active.</strong>
                The current tracked balance is {{ $formatMoney($summary['current_balance']) }}, and the alert threshold is {{ $formatMoney($summary['alert_threshold']) }}.
            @else
                <strong>No automatic drawer alert threshold yet.</strong>
                Set an alert amount in Settings if this branch wants automatic warnings when the drawer builds up too much cash.
            @endif
        </div>

        <section class="stats-grid">
            <div class="stat-card">
                <strong>Opening Balance</strong>
                <span class="value">{{ $formatMoney($summary['opening_balance']) }}</span>
                <span class="meta">
                    @if ($session?->opening_note)
                        Note: {{ $session->opening_note }}
                    @else
                        Starts at zero unless today&apos;s float is entered here.
                    @endif
                </span>
            </div>
            <div class="stat-card">
                <strong>Cash POS Sales</strong>
                <span class="value">{{ $formatMoney($summary['cash_sales_total']) }}</span>
                <span class="meta">Approved sales paid through cash only. Bank, MTN, Airtel, and cheque payments are excluded.</span>
            </div>
            <div class="stat-card">
                <strong>Cash Collections</strong>
                <span class="value">{{ $formatMoney($summary['cash_collections_total']) }}</span>
                <span class="meta">Cash collections received today for invoices raised today, net of any recorded reversals.</span>
            </div>
            <div class="stat-card">
                <strong>Documented Draws</strong>
                <span class="value">{{ $formatMoney($summary['draws_total']) }}</span>
                <span class="meta">Only recorded draws reduce this tracked drawer figure.</span>
            </div>
            <div class="stat-card">
                <strong>Current Drawer Balance</strong>
                <span class="value">{{ $formatMoney($summary['current_balance']) }}</span>
                <span class="meta">Opening balance + cash sales + same-day invoice cash collections - documented draws.</span>
            </div>
            <div class="stat-card">
                <strong>Alert Threshold</strong>
                <span class="value">
                    @if ($summary['alert_threshold'] !== null)
                        {{ $formatMoney($summary['alert_threshold']) }}
                    @else
                        Not Set
                    @endif
                </span>
                <span class="meta">
                    @if ($summary['alert_threshold'] !== null)
                        Gap to threshold: {{ $formatMoney($summary['threshold_gap']) }}
                    @else
                        Set this from Settings to enable automatic drawer warnings for cashiers and admins.
                    @endif
                </span>
            </div>
        </section>

        <div class="forms-grid" style="margin-top:20px;">
            <section class="panel">
                <h2>Shift Control</h2>
                <p class="panel-subtitle">Use one active shift at a time per branch. The first shift opening becomes the day&apos;s opening baseline, while each shift then reconciles its own cash window separately.</p>

                @if ($activeShift)
                    <div class="status-row">
                        <div class="status-pill">
                            <strong>Shift Status</strong>
                            <span>Open since {{ optional($activeShiftSummary['opened_at'])->format('d M Y H:i') ?? 'N/A' }}</span>
                        </div>
                        <div class="status-pill">
                            <strong>Opened By</strong>
                            <span>{{ $activeShift->openedByUser?->name ?? 'System' }}</span>
                        </div>
                        <div class="status-pill">
                            <strong>Shift Opening</strong>
                            <span>{{ $formatMoney($activeShiftSummary['opening_balance'] ?? 0) }}</span>
                        </div>
                    </div>

                    <div class="summary-grid">
                        <div class="summary-tile">
                            <strong>Cash Sales</strong>
                            <span>{{ $formatMoney($activeShiftSummary['cash_sales_total'] ?? 0) }}</span>
                        </div>
                        <div class="summary-tile">
                            <strong>Cash Collections</strong>
                            <span>{{ $formatMoney($activeShiftSummary['cash_collections_total'] ?? 0) }}</span>
                        </div>
                        <div class="summary-tile">
                            <strong>Cash Draws</strong>
                            <span>{{ $formatMoney($activeShiftSummary['draws_total'] ?? 0) }}</span>
                        </div>
                        <div class="summary-tile">
                            <strong>Expected Close</strong>
                            <span>{{ $formatMoney($activeShiftSummary['expected_balance'] ?? 0) }}</span>
                        </div>
                        <div class="summary-tile">
                            <strong>Opening Note</strong>
                            <span>{{ $activeShift->opening_note ?: 'No opening note recorded.' }}</span>
                        </div>
                        <div class="summary-tile">
                            <strong>Guidance</strong>
                            <span>Count the physical drawer, then record any banked or handover cash when closing this shift.</span>
                        </div>
                    </div>

                    @if ($canManageCashDrawer && !$dayClosed)
                        <form method="POST" action="{{ route('cash-drawer.shifts.close', $activeShift) }}">
                            @csrf

                            <div class="field-grid">
                                <div class="field">
                                    <label for="counted_cash">Counted Cash In Drawer</label>
                                    <input
                                        id="counted_cash"
                                        type="number"
                                        name="counted_cash"
                                        min="0"
                                        step="0.01"
                                        value="{{ old('counted_cash') }}"
                                    >
                                    <div class="hint">This is the physical cash counted before final handover notes are saved.</div>
                                    @error('counted_cash') <div class="error">{{ $message }}</div> @enderror
                                </div>
                                <div class="field">
                                    <label for="banked_amount">Cash Banked</label>
                                    <input
                                        id="banked_amount"
                                        type="number"
                                        name="banked_amount"
                                        min="0"
                                        step="0.01"
                                        value="{{ old('banked_amount', '0') }}"
                                    >
                                    <div class="hint">Optional. This is treated as a documented draw at shift close so the branch drawer balance stays in sync.</div>
                                    @error('banked_amount') <div class="error">{{ $message }}</div> @enderror
                                </div>
                                <div class="field">
                                    <label for="handover_amount">Cash Handed Over</label>
                                    <input
                                        id="handover_amount"
                                        type="number"
                                        name="handover_amount"
                                        min="0"
                                        step="0.01"
                                        value="{{ old('handover_amount', '0') }}"
                                    >
                                    <div class="hint">Optional. Record cash left for the next shift or supervisor handover.</div>
                                    @error('handover_amount') <div class="error">{{ $message }}</div> @enderror
                                </div>
                                <div class="field full">
                                    <label for="closing_note">Shift Closing Note</label>
                                    <textarea id="closing_note" name="closing_note" placeholder="Explain shortages, overages, deposits, or handover details...">{{ old('closing_note') }}</textarea>
                                    @error('closing_note') <div class="error">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            <div class="actions">
                                <button type="submit" class="btn btn-primary">Close Shift</button>
                            </div>
                        </form>
                    @elseif ($dayClosed)
                        <div class="empty-state">The day is already closed. Reopen the day before closing or editing shift records.</div>
                    @else
                        <div class="empty-state">You can review the active shift, but you need manage access to close it.</div>
                    @endif
                @elseif ($canManageCashDrawer && !$dayClosed)
                    <form method="POST" action="{{ route('cash-drawer.shifts.open') }}">
                        @csrf

                        <div class="field-grid">
                            <div class="field">
                                <label for="shift_opening_balance">Shift Opening Cash</label>
                                <input
                                    id="shift_opening_balance"
                                    type="number"
                                    name="shift_opening_balance"
                                    min="0"
                                    step="0.01"
                                    value="{{ old('shift_opening_balance', $hasShiftActivity ? '0' : $summary['opening_balance']) }}"
                                >
                                <div class="hint">For the first shift, this also becomes the day&apos;s opening drawer baseline. Later shifts only use it for shift reconciliation.</div>
                                @error('shift_opening_balance') <div class="error">{{ $message }}</div> @enderror
                            </div>
                            <div class="field full">
                                <label for="shift_opening_note">Shift Opening Note</label>
                                <textarea id="shift_opening_note" name="shift_opening_note" placeholder="Optional note about shift float, handover, or safe transfer...">{{ old('shift_opening_note') }}</textarea>
                                @error('shift_opening_note') <div class="error">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="actions">
                            <button type="submit" class="btn btn-primary">Open Shift</button>
                        </div>
                    </form>
                @elseif ($dayClosed)
                    <div class="empty-state">The day is closed. Reopen it before opening another shift.</div>
                @else
                    <div class="empty-state">No shift is active right now.</div>
                @endif
            </section>

            <section class="panel">
                <h2>End Of Day Closing</h2>
                <p class="panel-subtitle">Close the full branch drawer after every shift has been reconciled. This compares the branch&apos;s expected drawer balance with the physical counted cash and soft-locks the drawer day.</p>

                <div class="status-row">
                    <div class="status-pill">
                        <strong>Expected Day Close</strong>
                        <span>{{ $formatMoney($summary['current_balance']) }}</span>
                    </div>
                    <div class="status-pill">
                        <strong>Open Shift</strong>
                        <span>{{ $activeShift ? 'Yes - close it first' : 'No active shift' }}</span>
                    </div>
                    <div class="status-pill">
                        <strong>Day Status</strong>
                        <span>{{ $dayClosed ? 'Closed' : 'Open' }}</span>
                    </div>
                </div>

                @if ($dayClosed)
                    <div class="empty-state" style="margin-bottom:18px;">
                        The day is closed with counted cash {{ $formatMoney($summary['day_closing_counted_balance']) }} and variance {{ $formatVariance($summary['day_closing_variance']) }}.
                    </div>

                    @if ($canManageCashDrawer && $canReopenDay)
                        <form method="POST" action="{{ route('cash-drawer.day.reopen') }}">
                            @csrf

                            <div class="field-grid">
                                <div class="field full">
                                    <label for="day_reopen_reason">Reason For Reopening Day</label>
                                    <textarea id="day_reopen_reason" name="day_reopen_reason" placeholder="Explain why the day is being reopened and what will be corrected...">{{ old('day_reopen_reason') }}</textarea>
                                    @error('day_reopen_reason') <div class="error">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            <div class="actions">
                                <button type="submit" class="btn btn-danger">Reopen Day</button>
                            </div>
                        </form>
                    @endif
                @elseif ($activeShift)
                    <div class="empty-state">A shift is still open for this branch. Close the active shift before running end-of-day closing.</div>
                @elseif ($canManageCashDrawer)
                    <form method="POST" action="{{ route('cash-drawer.day.close') }}">
                        @csrf

                        <div class="field-grid">
                            <div class="field">
                                <label for="day_counted_cash">Counted Cash For Day Close</label>
                                <input
                                    id="day_counted_cash"
                                    type="number"
                                    name="day_counted_cash"
                                    min="0"
                                    step="0.01"
                                    value="{{ old('day_counted_cash', $summary['current_balance']) }}"
                                >
                                <div class="hint">Enter the physical cash remaining in the drawer when the branch day ends.</div>
                                @error('day_counted_cash') <div class="error">{{ $message }}</div> @enderror
                            </div>
                            <div class="field full">
                                <label for="day_closing_note">End Of Day Note</label>
                                <textarea id="day_closing_note" name="day_closing_note" placeholder="Optional note about shortage, overage, handover, or late reconciliation...">{{ old('day_closing_note') }}</textarea>
                                @error('day_closing_note') <div class="error">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="actions">
                            <button type="submit" class="btn btn-primary">Close Day</button>
                        </div>
                    </form>
                @else
                    <div class="empty-state">You can review the expected branch close, but you need manage access to close or reopen the day.</div>
                @endif
            </section>
        </div>

        <div class="forms-grid" style="margin-top:20px;">
            <section class="panel">
                <h2>Opening Balance</h2>
                <p class="panel-subtitle">Branches that start with float can enter it here. Branches that start at zero can leave it at zero. This stays separate from sales and accounting records.</p>

                @if ($dayClosed)
                    <div class="empty-state">Today is closed. Reopen the day before changing the opening balance.</div>
                @elseif ($canManageCashDrawer && !$hasShiftActivity)
                    <form method="POST" action="{{ route('cash-drawer.opening.update') }}">
                        @csrf
                        @method('PUT')

                        <div class="field-grid">
                            <div class="field">
                                <label for="opening_balance">Today&apos;s Opening Cash</label>
                                <input
                                    id="opening_balance"
                                    type="number"
                                    name="opening_balance"
                                    min="0"
                                    step="0.01"
                                    value="{{ old('opening_balance', $summary['opening_balance']) }}"
                                >
                                @error('opening_balance') <div class="error">{{ $message }}</div> @enderror
                            </div>
                            <div class="field full">
                                <label for="opening_note">Opening Note</label>
                                <textarea id="opening_note" name="opening_note" placeholder="Optional note about today&apos;s float or opening drawer cash...">{{ old('opening_note', $session?->opening_note) }}</textarea>
                                <div class="hint">
                                    @if ($session?->openedByUser)
                                        Last saved by {{ $session->openedByUser->name }}.
                                    @else
                                        Optional. This helps explain where the opening float came from.
                                    @endif
                                </div>
                                @error('opening_note') <div class="error">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="actions">
                            <button type="submit" class="btn btn-primary">Save Opening Balance</button>
                        </div>
                    </form>
                @elseif ($canManageCashDrawer)
                    <div class="empty-state">A shift has already started today. The first shift opening now defines the day&apos;s opening baseline, so this field stays locked for the rest of the day.</div>
                @else
                    <div class="empty-state">You can view today&apos;s drawer position, but you need manage access to update the opening balance.</div>
                @endif
            </section>

            <section class="panel">
                <h2>Record Cash Draw</h2>
                <p class="panel-subtitle">When the drawer has built up too much cash, draw it out here and state the reason clearly. Examples include bank deposit, safe transfer, or owner pickup.</p>

                @if ($dayClosed)
                    <div class="empty-state">Today is closed. Reopen the day before recording another cash draw.</div>
                @elseif ($canManageCashDrawer)
                    <form method="POST" action="{{ route('cash-drawer.draws.store') }}">
                        @csrf

                        <div class="field-grid">
                            <div class="field">
                                <label for="amount">Draw Amount</label>
                                <input
                                    id="amount"
                                    type="number"
                                    name="amount"
                                    min="0.01"
                                    step="0.01"
                                    value="{{ old('amount') }}"
                                >
                                <div class="hint">The system will reject an amount that is higher than the tracked drawer balance.</div>
                                @error('amount') <div class="error">{{ $message }}</div> @enderror
                            </div>
                            <div class="field full">
                                <label for="reason">Reason For Drawing Cash</label>
                                <textarea id="reason" name="reason" placeholder="State why this money is being drawn from the drawer...">{{ old('reason') }}</textarea>
                                @error('reason') <div class="error">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="actions">
                            <button type="submit" class="btn btn-primary">Record Draw</button>
                        </div>
                    </form>
                @else
                    <div class="empty-state">You can review today&apos;s drawer totals, but you need manage access to record a draw.</div>
                @endif
            </section>
        </div>

        <section class="panel">
            <h2>Recent Shift History</h2>
            <p class="panel-subtitle">Each shift keeps its own opening, expected close, counted cash, and variance without changing the branch day totals.</p>

            @if ($recentShifts->isNotEmpty())
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Opened</th>
                                <th>Opened By</th>
                                <th>Status</th>
                                <th>Opening</th>
                                <th>Expected Close</th>
                                <th>Counted</th>
                                <th>Variance</th>
                                <th>Closed By</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recentShifts as $shift)
                                @php
                                    $shiftSummary = $shift->summary_snapshot ?? [];
                                    $shiftClosed = (bool) ($shiftSummary['is_closed'] ?? false);
                                    $shiftVariance = (float) ($shiftSummary['variance'] ?? 0);
                                @endphp
                                <tr>
                                    <td>{{ optional($shiftSummary['opened_at'] ?? null)->format('d M Y H:i') ?? 'N/A' }}</td>
                                    <td>{{ $shift->openedByUser?->name ?? 'System' }}</td>
                                    <td>{{ $shiftClosed ? 'Closed' : 'Open' }}</td>
                                    <td>{{ $formatMoney($shiftSummary['opening_balance'] ?? 0) }}</td>
                                    <td>{{ $formatMoney($shiftSummary['expected_balance'] ?? 0) }}</td>
                                    <td>
                                        @if (($shiftSummary['counted_balance'] ?? null) !== null)
                                            {{ $formatMoney($shiftSummary['counted_balance']) }}
                                        @else
                                            Waiting
                                        @endif
                                    </td>
                                    <td class="{{ $shiftVariance < 0 ? 'variance-negative' : ($shiftVariance > 0 ? 'variance-positive' : '') }}">
                                        @if (($shiftSummary['counted_balance'] ?? null) !== null)
                                            {{ $formatVariance($shiftSummary['variance']) }}
                                        @else
                                            Waiting
                                        @endif
                                    </td>
                                    <td>{{ $shift->closedByUser?->name ?? 'Not closed yet' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="empty-state">No shift activity has been recorded for today yet.</div>
            @endif
        </section>

        <section class="panel">
            <h2>Today&apos;s Draw History</h2>
            <p class="panel-subtitle">This is the audit trail for cash already taken out of the drawer today.</p>

            @if ($recentDraws->isNotEmpty())
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Amount</th>
                                <th>Reason</th>
                                <th>Recorded By</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recentDraws as $draw)
                                <tr>
                                    <td>{{ optional($draw->drawn_at)->format('d M Y H:i') }}</td>
                                    <td>{{ $formatMoney($draw->amount) }}</td>
                                    <td>{{ $draw->reason }}</td>
                                    <td>{{ $draw->drawnByUser?->name ?? 'System' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="empty-state">No cash draws have been recorded for today yet.</div>
            @endif
        </section>

        <section class="panel">
            <h2>How This Figure Works</h2>
            <p class="panel-subtitle">This keeps the drawer feature operational and safe.</p>
            <div class="empty-state">
                The drawer figure here only follows today&apos;s opening balance, approved cash-only sales, cash collections received today for invoices raised today, and documented cash draws. Shift closing then reconciles only the transactions that happened inside that shift window, while end-of-day closing reconciles the whole branch drawer for the day. None of this rewrites your sales, stock, or accounting logic. Non-cash methods like bank, MTN, Airtel, and cheque payments do not enter this drawer balance.
            </div>
        </section>
    </main>
</body>
</html>
