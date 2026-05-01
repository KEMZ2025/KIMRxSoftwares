<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - KIM Rx</title>
    <style>
        * { box-sizing: border-box; }

        :root {
            --page-bg: #f3f7fb;
            --panel-bg: #ffffff;
            --panel-soft: #f7fafc;
            --line: #dde6ef;
            --text-main: #132238;
            --text-soft: #667085;
            --teal: #0f8a94;
            --teal-deep: #0c6d75;
            --teal-soft: #e7fbfb;
            --blue: #2563eb;
            --blue-soft: #eef4ff;
            --violet: #7c3aed;
            --violet-soft: #f4edff;
            --amber: #d97706;
            --amber-soft: #fff5dd;
            --rose: #dc2626;
            --rose-soft: #fff1f1;
            --slate: #475467;
            --slate-soft: #eef2f7;
            --emerald: #15803d;
            --emerald-soft: #ecfdf3;
            --shadow-soft: 0 18px 42px rgba(15, 23, 42, 0.08);
            --radius-lg: 22px;
            --radius-md: 16px;
            --radius-sm: 12px;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            color: var(--text-main);
            background:
                radial-gradient(circle at top right, rgba(15, 138, 148, 0.12), transparent 24%),
                linear-gradient(180deg, #f9fbfe 0%, var(--page-bg) 100%);
        }

        .layout {
            display: flex;
            min-height: 100vh;
        }

        .content {
            flex: 1;
            width: 100%;
            max-width: 100%;
            margin-left: 260px;
            padding: 24px;
            transition: margin-left 0.3s ease;
        }

        .content.expanded {
            margin-left: 80px;
        }

        .dashboard-topbar,
        .panel,
        .report-panel,
        .summary-card,
        .finance-card {
            background: var(--panel-bg);
            border: 1px solid rgba(219, 228, 238, 0.85);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-soft);
        }

        .dashboard-topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            padding: 18px 22px;
            margin-bottom: 18px;
        }

        .topbar-title h1 {
            margin: 0;
            font-size: 30px;
            line-height: 1.05;
        }

        .topbar-title p {
            margin: 6px 0 0;
            color: var(--text-soft);
            font-size: 14px;
        }

        .topbar-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .meta-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 999px;
            background: var(--panel-soft);
            border: 1px solid var(--line);
            color: #344054;
            font-size: 13px;
            font-weight: 700;
        }

        a.meta-pill {
            text-decoration: none;
        }

        .support-link {
            background: var(--teal-soft);
            color: var(--teal-deep);
        }

        .logout-form button,
        .preset-btn,
        .apply-btn,
        .reset-link {
            border: none;
            border-radius: 999px;
            cursor: pointer;
            text-decoration: none;
            transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
        }

        .logout-form button {
            padding: 10px 14px;
            background: #102033;
            color: white;
            font-weight: bold;
        }

        .logout-form button:hover,
        .preset-btn:hover,
        .apply-btn:hover,
        .reset-link:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 20px rgba(15, 23, 42, 0.12);
        }

        .overview-grid,
        .middle-grid,
        .table-grid {
            display: grid;
            gap: 18px;
            margin-bottom: 18px;
        }

        .overview-grid {
            grid-template-columns: minmax(320px, 0.84fr) minmax(460px, 1.26fr);
        }

        .middle-grid {
            grid-template-columns: minmax(0, 1.45fr) minmax(360px, 0.95fr);
        }

        .table-grid {
            grid-template-columns: minmax(0, 1.08fr) minmax(0, 1fr);
        }

        .panel {
            padding: 18px;
        }

        .panel-kicker {
            color: var(--text-soft);
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.02em;
        }

        .panel-title {
            margin: 6px 0 0;
            font-size: 23px;
        }

        .panel-subtitle {
            margin: 6px 0 0;
            color: var(--text-soft);
            font-size: 13px;
            line-height: 1.45;
            display: none;
        }

        .summary-panel {
            padding: 18px;
        }

        .summary-head,
        .report-head,
        .panel-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 16px;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
        }

        .summary-card,
        .finance-card {
            padding: 16px 16px 16px 18px;
            position: relative;
            overflow: hidden;
        }

        .summary-card::before,
        .finance-card::before {
            content: "";
            position: absolute;
            inset: 0 auto 0 0;
            width: 5px;
            border-radius: 999px;
        }

        .tone-teal::before { background: linear-gradient(180deg, #14b8a6, var(--teal)); }
        .tone-blue::before { background: linear-gradient(180deg, #60a5fa, var(--blue)); }
        .tone-violet::before { background: linear-gradient(180deg, #a78bfa, var(--violet)); }
        .tone-amber::before { background: linear-gradient(180deg, #fbbf24, var(--amber)); }
        .tone-rose::before { background: linear-gradient(180deg, #fb7185, var(--rose)); }
        .tone-slate::before { background: linear-gradient(180deg, #94a3b8, var(--slate)); }
        .tone-emerald::before { background: linear-gradient(180deg, #4ade80, var(--emerald)); }
        .tone-cash::before { background: linear-gradient(180deg, #38bdf8, #0284c7); }
        .tone-mtn::before { background: linear-gradient(180deg, #facc15, #ca8a04); }
        .tone-airtel::before { background: linear-gradient(180deg, #fb7185, #e11d48); }
        .tone-bank::before { background: linear-gradient(180deg, #34d399, #059669); }
        .tone-cheque::before { background: linear-gradient(180deg, #c4b5fd, #7c3aed); }

        .card-label {
            color: var(--text-soft);
            font-size: 13px;
            font-weight: 700;
        }

        .card-value {
            margin-top: 12px;
            font-size: 29px;
            font-weight: 800;
            line-height: 1.03;
            overflow-wrap: anywhere;
            word-break: break-word;
        }

        .card-note {
            margin-top: 10px;
            color: var(--text-soft);
            font-size: 12.5px;
            line-height: 1.45;
            display: none;
        }

        .report-panel {
            padding: 18px;
            background:
                linear-gradient(145deg, rgba(15, 138, 148, 0.96), rgba(10, 98, 112, 0.96)),
                #0d7380;
            color: white;
        }

        .report-panel .panel-kicker,
        .report-panel .panel-title,
        .report-panel .panel-subtitle,
        .report-panel .meta-pill,
        .report-table,
        .report-table th,
        .report-table td,
        .method-card .card-label,
        .method-card .card-value,
        .method-card .card-note {
            color: white;
        }

        .report-panel .meta-pill {
            background: rgba(255, 255, 255, 0.12);
            border-color: rgba(255, 255, 255, 0.2);
        }

        .report-panel .preset-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 8px;
            margin-bottom: 12px;
        }

        .preset-btn {
            padding: 10px 10px;
            background: rgba(255, 255, 255, 0.12);
            color: white;
            font-weight: 700;
            font-size: 12px;
        }

        .preset-btn.active {
            background: white;
            color: var(--teal-deep);
        }

        .report-custom-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
            margin-bottom: 12px;
        }

        .field label {
            display: block;
            margin-bottom: 6px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.85);
        }

        .field input {
            width: 100%;
            padding: 11px 12px;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.22);
            background: rgba(255, 255, 255, 0.12);
            color: white;
            font-size: 13px;
        }

        .field input::-webkit-calendar-picker-indicator {
            filter: invert(1);
        }

        .report-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 14px;
        }

        .apply-btn {
            padding: 10px 14px;
            background: #ffffff;
            color: var(--teal-deep);
            font-weight: 800;
        }

        .reset-link {
            padding: 10px 14px;
            background: rgba(255, 255, 255, 0.12);
            color: white;
            font-weight: 700;
        }

        .report-table-wrap {
            overflow-x: auto;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.16);
            background: rgba(255, 255, 255, 0.08);
            margin-bottom: 14px;
        }

        .report-table {
            width: 100%;
            border-collapse: collapse;
        }

        .report-table th,
        .report-table td {
            padding: 11px 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.12);
            text-align: left;
            font-size: 13px;
            white-space: nowrap;
        }

        .report-table th {
            font-size: 11px;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.82);
        }

        .report-table tr:last-child td {
            border-bottom: none;
        }

        .method-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 10px;
        }

        .method-card {
            min-height: 112px;
            border-radius: 16px;
            padding: 14px;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.16);
        }

        .method-card .card-value {
            font-size: 22px;
            margin-top: 10px;
        }

        .chart-shell {
            height: 330px;
            padding: 14px 16px 8px;
            border-radius: 18px;
            border: 1px solid var(--line);
            background: linear-gradient(180deg, #fcfeff 0%, #f6f9fc 100%);
        }

        .chart-shell svg {
            width: 100%;
            height: 100%;
            display: block;
        }

        .insight-panel {
            display: flex;
            flex-direction: column;
        }

        .finance-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .finance-card .card-value {
            font-size: 24px;
        }

        .channels-panel {
            margin-top: 14px;
            padding: 16px;
            border-radius: 18px;
            background: var(--panel-soft);
            border: 1px solid var(--line);
        }

        .channels-head {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
            margin-bottom: 14px;
        }

        .channels-head h4 {
            margin: 0;
            font-size: 16px;
        }

        .channels-head span {
            color: var(--text-soft);
            font-size: 12px;
            font-weight: 700;
            display: none;
        }

        .method-bars {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .method-row {
            display: grid;
            grid-template-columns: 74px minmax(0, 1fr) auto;
            gap: 10px;
            align-items: center;
        }

        .method-row strong {
            font-size: 12.5px;
        }

        .bar-track {
            height: 11px;
            border-radius: 999px;
            background: #e7eef6;
            overflow: hidden;
        }

        .bar-fill {
            height: 100%;
            border-radius: inherit;
        }

        .bar-fill.cash { background: linear-gradient(90deg, #38bdf8, #0284c7); }
        .bar-fill.mtn { background: linear-gradient(90deg, #facc15, #ca8a04); }
        .bar-fill.airtel { background: linear-gradient(90deg, #fb7185, #e11d48); }
        .bar-fill.bank { background: linear-gradient(90deg, #34d399, #059669); }
        .bar-fill.cheque { background: linear-gradient(90deg, #c4b5fd, #7c3aed); }

        .bar-amount {
            font-size: 12.5px;
            font-weight: 700;
            color: var(--text-main);
            white-space: nowrap;
        }

        .table-wrap {
            overflow-x: auto;
            max-width: 100%;
            border: 1px solid var(--line);
            border-radius: 16px;
            background: #fff;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 12px 14px;
            border-bottom: 1px solid #edf2f7;
            text-align: left;
            vertical-align: top;
            font-size: 13px;
        }

        th {
            background: #f8fbff;
            color: var(--text-soft);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        tr:last-child td {
            border-bottom: none;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
        }

        .badge-pos {
            background: var(--teal-soft);
            color: var(--teal);
        }

        .badge-collection {
            background: var(--blue-soft);
            color: var(--blue);
        }

        .empty-state {
            padding: 28px 18px;
            color: var(--text-soft);
            text-align: center;
            font-size: 14px;
        }

        @media (max-width: 1460px) {
            .summary-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .method-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 1200px) {
            .overview-grid,
            .middle-grid,
            .table-grid {
                grid-template-columns: 1fr;
            }

            .finance-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .method-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 900px) {
            .content,
            .content.expanded {
                margin-left: 0;
                padding: 16px;
            }

            .layout {
                flex-direction: column;
            }

            .dashboard-topbar {
                flex-direction: column;
                align-items: flex-start;
            }

            .topbar-actions {
                justify-content: flex-start;
            }

            .summary-grid,
            .finance-grid,
            .method-grid,
            .report-panel .preset-grid,
            .report-custom-grid {
                grid-template-columns: 1fr;
            }

            .topbar-title h1 {
                font-size: 26px;
            }
        }
    </style>
</head>
<body>
    @php
        $headlineMap = collect($headlineStats)->keyBy('label');
        $financeMap = collect($financeStats)->keyBy('label');
        $reportRows = [
            ['label' => 'Sales Value', 'amount' => $financeMap['Sales Value']['value'] ?? 0, 'note' => 'Approved sales'],
            ['label' => 'Purchases Value', 'amount' => $financeMap['Purchases Value']['value'] ?? 0, 'note' => 'Purchase invoices'],
            ['label' => 'Money Received', 'amount' => $financeMap['Money Received']['value'] ?? 0, 'note' => 'POS + collections'],
            ['label' => 'Credit Due', 'amount' => $financeMap['Credit Due']['value'] ?? 0, 'note' => 'Unpaid balance created'],
        ];
        $focusedFinanceCards = [
            $financeMap['Money Received'] ?? null,
            $financeMap['Supplier Paid'] ?? null,
            $financeMap['Open Receivables'] ?? null,
            $financeMap['Open Payables'] ?? null,
            $financeMap['Credit Due'] ?? null,
            $financeMap['Sales vs Purchases'] ?? null,
        ];
    @endphp

    <div class="layout">
        @include('layouts.sidebar')

        <main
            class="content"
            id="mainContent"
            data-dashboard-period="{{ $filters['period'] }}"
            data-dashboard-timezone="{{ config('app.timezone', 'Africa/Nairobi') }}"
            data-dashboard-rendered-day="{{ now(config('app.timezone', 'Africa/Nairobi'))->toDateString() }}"
            data-dashboard-refresh-ms="300000"
        >
            <section class="dashboard-topbar">
                <div class="topbar-title">
                    <h1>Dashboard</h1>
                    <p>Welcome to KIM Rx | {{ $rangeLabel }}</p>
                </div>

                <div class="topbar-actions">
                    <span class="meta-pill" id="liveDateStamp">{{ now(config('app.timezone', 'Africa/Nairobi'))->format('D, d M Y H:i') }}</span>
                    <span class="meta-pill">User: {{ $user->name }}</span>
                    @if (Route::has('support.index'))
                        <a href="{{ route('support.index') }}" class="meta-pill support-link">Need Support?</a>
                    @endif

                    <form class="logout-form" method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit">Logout</button>
                    </form>
                </div>
            </section>

            <section class="overview-grid">
                <article class="summary-panel panel">
                    <div class="summary-head">
                        <div>
                            <div class="panel-kicker">Quick Snapshot</div>
                            <h2 class="panel-title">Branch Overview</h2>
                            <p class="panel-subtitle">The most important stock and account signals for this branch are kept together here.</p>
                        </div>

                        <span class="meta-pill">{{ $rangeLabel }}</span>
                    </div>

                    <div class="summary-grid">
                        @foreach($headlineStats as $card)
                            <article class="summary-card tone-{{ $card['tone'] }}">
                                <div class="card-label">{{ $card['label'] }}</div>
                                <div class="card-value" data-countup data-target="{{ number_format((float) $card['value'], 0, '.', '') }}" data-decimals="0">
                                    {{ number_format((float) $card['value']) }}
                                </div>
                                <div class="card-note">{{ $card['note'] }}</div>
                            </article>
                        @endforeach
                    </div>
                </article>

                <form class="report-panel" method="GET" action="{{ route('dashboard') }}">
                    <div class="report-head">
                        <div>
                            <div class="panel-kicker">Window Report</div>
                            <h2 class="panel-title">Report Summary</h2>
                            <p class="panel-subtitle">The whole dashboard below follows this same filter and method breakdown.</p>
                        </div>

                        <span class="meta-pill">{{ $rangeLabel }}</span>
                    </div>

                    <div class="preset-grid">
                        <button type="submit" name="period" value="today" class="preset-btn {{ $filters['period'] === 'today' ? 'active' : '' }}">Today</button>
                        <button type="submit" name="period" value="this_week" class="preset-btn {{ $filters['period'] === 'this_week' ? 'active' : '' }}">This Week</button>
                        <button type="submit" name="period" value="this_month" class="preset-btn {{ $filters['period'] === 'this_month' ? 'active' : '' }}">This Month</button>
                        <button type="submit" name="period" value="custom" class="preset-btn {{ $filters['period'] === 'custom' ? 'active' : '' }}">Custom</button>
                    </div>

                    <div class="report-custom-grid">
                        <div class="field">
                            <label for="date_from">Date From</label>
                            <input type="date" id="date_from" name="date_from" value="{{ $filters['date_from'] }}">
                        </div>

                        <div class="field">
                            <label for="date_to">Date To</label>
                            <input type="date" id="date_to" name="date_to" value="{{ $filters['date_to'] }}">
                        </div>
                    </div>

                    <div class="report-actions">
                        <button type="submit" name="period" value="custom" class="apply-btn">Apply Range</button>
                        <a href="{{ route('dashboard') }}" class="reset-link">Reset</a>
                    </div>

                    <div class="report-table-wrap">
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>Metric</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($reportRows as $row)
                                    <tr>
                                        <td>{{ $row['label'] }}</td>
                                        <td data-countup data-target="{{ number_format((float) $row['amount'], 2, '.', '') }}" data-decimals="2">
                                            {{ number_format((float) $row['amount'], 2) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="method-grid">
                        @foreach($moneyByMethod as $card)
                            <article class="method-card tone-{{ $card['tone'] }}">
                                <div class="card-label">{{ $card['label'] }}</div>
                                <div class="card-value" data-countup data-target="{{ number_format((float) $card['amount'], 2, '.', '') }}" data-decimals="2">
                                    {{ number_format((float) $card['amount'], 2) }}
                                </div>
                                <div class="card-note">{{ $rangeLabel }}</div>
                            </article>
                        @endforeach
                    </div>
                </form>
            </section>

            <section class="middle-grid">
                <article class="panel">
                    <div class="panel-head">
                        <div>
                            <div class="panel-kicker">Performance Graph</div>
                            <h2 class="panel-title">Sales vs Purchases Trend</h2>
                            <p class="panel-subtitle">{{ $trendChart['subtitle'] }}</p>
                        </div>

                        <span class="meta-pill">Sales and purchases compared together</span>
                    </div>

                    <div class="chart-shell">
                        <svg id="trendChart" viewBox="0 0 760 320" aria-label="Sales and purchases trend chart"></svg>
                    </div>
                </article>

                <aside class="insight-panel panel">
                    <div class="panel-head">
                        <div>
                            <div class="panel-kicker">Finance Focus</div>
                            <h2 class="panel-title">Collections and Balances</h2>
                            <p class="panel-subtitle">The branch money position is grouped here instead of being scattered around the page.</p>
                        </div>

                        <span class="meta-pill">Total Received: {{ number_format((float) $totalReceived, 2) }}</span>
                    </div>

                    <div class="finance-grid">
                        @foreach($focusedFinanceCards as $card)
                            @if($card)
                                <article class="finance-card tone-{{ $card['tone'] }}">
                                    <div class="card-label">{{ $card['label'] }}</div>
                                    <div class="card-value" data-countup data-target="{{ number_format((float) $card['value'], 2, '.', '') }}" data-decimals="2">
                                        {{ number_format((float) $card['value'], 2) }}
                                    </div>
                                    <div class="card-note">{{ $card['note'] }}</div>
                                </article>
                            @endif
                        @endforeach
                    </div>

                    @php
                        $barTotal = max((float) $totalReceived, 0.01);
                    @endphp

                    <div class="channels-panel">
                        <div class="channels-head">
                            <h4>Money Received By Method</h4>
                            <span>All methods add up to the total above</span>
                        </div>

                        <div class="method-bars">
                            @foreach($moneyByMethod as $method)
                                @php
                                    $percentage = max(0, min(100, ((float) $method['amount'] / $barTotal) * 100));
                                @endphp
                                <div class="method-row">
                                    <strong>{{ $method['label'] }}</strong>
                                    <div class="bar-track">
                                        <div class="bar-fill {{ $method['key'] }}" style="width: {{ number_format($percentage, 2, '.', '') }}%;"></div>
                                    </div>
                                    <span class="bar-amount" data-countup data-target="{{ number_format((float) $method['amount'], 2, '.', '') }}" data-decimals="2">
                                        {{ number_format((float) $method['amount'], 2) }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </aside>
            </section>

            <section class="table-grid">
                <article class="panel">
                    <div class="panel-head">
                        <div>
                            <div class="panel-kicker">Top Movers</div>
                            <h2 class="panel-title">Fast Moving Drugs</h2>
                            <p class="panel-subtitle">Top products by quantity sold in the selected report window.</p>
                        </div>
                    </div>

                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Drug</th>
                                    <th>Quantity</th>
                                    <th>Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($topMovingProducts as $item)
                                    <tr>
                                        <td>{{ $item->name }}</td>
                                        <td>{{ number_format((float) $item->total_quantity, 2) }}</td>
                                        <td>{{ number_format((float) $item->total_revenue, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="empty-state">No approved sales were found in this range yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </article>

                <article class="panel">
                    <div class="panel-head">
                        <div>
                            <div class="panel-kicker">Collections Feed</div>
                            <h2 class="panel-title">Recent Money Received</h2>
                            <p class="panel-subtitle">Latest POS receipts and customer collections inside the current filter.</p>
                        </div>
                    </div>

                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Source</th>
                                    <th>Reference</th>
                                    <th>Party</th>
                                    <th>Method</th>
                                    <th>Amount</th>
                                    <th>By</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentMoneyIn as $entry)
                                    <tr>
                                        <td>{{ $entry['date'] }}</td>
                                        <td>
                                            <span class="badge {{ $entry['source'] === 'POS Sale' ? 'badge-pos' : 'badge-collection' }}">
                                                {{ $entry['source'] }}
                                            </span>
                                        </td>
                                        <td>{{ $entry['reference'] }}</td>
                                        <td>{{ $entry['party'] }}</td>
                                        <td>{{ $entry['method'] }}</td>
                                        <td>{{ number_format((float) $entry['amount'], 2) }}</td>
                                        <td>{{ $entry['actor'] }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="empty-state">No money has been received in the selected range yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </article>
            </section>
        </main>
    </div>

    <script>
        (function () {
            const dashboardRoot = document.getElementById('mainContent');
            const liveDateStamp = document.getElementById('liveDateStamp');
            const livePeriod = dashboardRoot?.dataset.dashboardPeriod || 'today';
            const liveTimezone = dashboardRoot?.dataset.dashboardTimezone || 'Africa/Nairobi';
            const refreshMs = Number(dashboardRoot?.dataset.dashboardRefreshMs || 300000);
            let renderedDay = dashboardRoot?.dataset.dashboardRenderedDay || '';
            const shouldAutoRefresh = ['today', 'this_week', 'this_month'].includes(livePeriod);

            const dateFormatter = new Intl.DateTimeFormat('en-GB', {
                timeZone: liveTimezone,
                weekday: 'short',
                day: '2-digit',
                month: 'short',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            });

            const dayFormatter = new Intl.DateTimeFormat('en-CA', {
                timeZone: liveTimezone,
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
            });

            const updateLiveClock = () => {
                if (liveDateStamp) {
                    liveDateStamp.textContent = dateFormatter.format(new Date());
                }

                if (!shouldAutoRefresh) {
                    return;
                }

                const currentDay = dayFormatter.format(new Date());

                if (renderedDay && currentDay !== renderedDay) {
                    window.location.reload();
                }
            };

            updateLiveClock();
            setInterval(updateLiveClock, 1000);

            if (shouldAutoRefresh) {
                setInterval(() => {
                    if (document.visibilityState === 'visible') {
                        window.location.reload();
                    }
                }, refreshMs);
            }

            const countupNodes = document.querySelectorAll('[data-countup]');

            const formatCountValue = (value, decimals) => Number(value).toLocaleString(undefined, {
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals,
            });

            countupNodes.forEach((node, index) => {
                const target = Number(node.dataset.target || 0);
                const decimals = Number(node.dataset.decimals || 0);
                const duration = 900 + Math.min(index * 60, 480);

                node.textContent = formatCountValue(0, decimals);

                const start = performance.now();

                const tick = (now) => {
                    const progress = Math.min((now - start) / duration, 1);
                    const eased = 1 - Math.pow(1 - progress, 3);
                    const value = target * eased;

                    node.textContent = formatCountValue(progress >= 1 ? target : value, decimals);

                    if (progress < 1) {
                        requestAnimationFrame(tick);
                    }
                };

                requestAnimationFrame(tick);
            });

            const chartData = @json($trendChart);
            const svg = document.getElementById('trendChart');

            if (!svg) {
                return;
            }

            const width = 760;
            const height = 320;
            const padding = { top: 24, right: 18, bottom: 44, left: 54 };
            const chartWidth = width - padding.left - padding.right;
            const chartHeight = height - padding.top - padding.bottom;
            const sales = Array.isArray(chartData.sales) ? chartData.sales : [];
            const purchases = Array.isArray(chartData.purchases) ? chartData.purchases : [];
            const labels = Array.isArray(chartData.labels) ? chartData.labels : [];
            const bucket = chartData.bucket || 'daily';
            const pointsCount = Math.max(labels.length, 1);
            const maxValue = Math.max(...sales, ...purchases, 0);
            const safeMax = maxValue > 0 ? maxValue : 1;
            const salesColor = '#7c3aed';
            const purchasesColor = '#f97316';
            const salesFill = 'rgba(124, 58, 237, 0.12)';
            const purchasesFill = 'rgba(249, 115, 22, 0.10)';
            const maxVisibleLabels = bucket === 'monthly' ? 10 : 8;
            const labelStep = pointsCount <= maxVisibleLabels ? 1 : Math.ceil(pointsCount / maxVisibleLabels);

            const formatCompact = new Intl.NumberFormat(undefined, {
                notation: 'compact',
                maximumFractionDigits: 1,
            });

            const buildAxisLabel = (label, index) => {
                const isEdge = index === 0 || index === pointsCount - 1;
                const shouldShow = isEdge || index % labelStep === 0;

                if (!shouldShow) {
                    return '';
                }

                if (bucket !== 'daily' || pointsCount <= 12) {
                    return label;
                }

                const parts = String(label).split(' ');
                return isEdge ? label : (parts[0] || label);
            };

            const xForIndex = (index) => {
                if (pointsCount === 1) {
                    return padding.left + chartWidth / 2;
                }

                return padding.left + (chartWidth / (pointsCount - 1)) * index;
            };

            const yForValue = (value) => padding.top + chartHeight - ((value / safeMax) * chartHeight);

            const makeLine = (values) => values
                .map((value, index) => `${index === 0 ? 'M' : 'L'} ${xForIndex(index).toFixed(2)} ${yForValue(value).toFixed(2)}`)
                .join(' ');

            const makeArea = (values) => {
                const line = makeLine(values);

                return `${line} L ${xForIndex(values.length - 1).toFixed(2)} ${(padding.top + chartHeight).toFixed(2)} L ${xForIndex(0).toFixed(2)} ${(padding.top + chartHeight).toFixed(2)} Z`;
            };

            const gridLines = 4;
            let markup = '';

            for (let i = 0; i <= gridLines; i++) {
                const y = padding.top + (chartHeight / gridLines) * i;
                const value = safeMax - ((safeMax / gridLines) * i);
                markup += `<line x1="${padding.left}" y1="${y}" x2="${width - padding.right}" y2="${y}" stroke="#dce5ef" stroke-width="1" />`;
                markup += `<text x="${padding.left - 12}" y="${y + 4}" text-anchor="end" font-size="11" fill="#667085">${formatCompact.format(value)}</text>`;
            }

            labels.forEach((label, index) => {
                const axisLabel = buildAxisLabel(label, index);

                if (!axisLabel) {
                    return;
                }

                markup += `<text x="${xForIndex(index)}" y="${height - 14}" text-anchor="middle" font-size="11" fill="#667085">${axisLabel}</text>`;
            });

            markup += `
                <path d="${makeArea(purchases)}" fill="${purchasesFill}"></path>
                <path d="${makeArea(sales)}" fill="${salesFill}"></path>
                <path d="${makeLine(purchases)}" fill="none" stroke="${purchasesColor}" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"></path>
                <path d="${makeLine(sales)}" fill="none" stroke="${salesColor}" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"></path>
                <g>
                    <circle cx="${padding.left + 10}" cy="14" r="5" fill="${salesColor}"></circle>
                    <text x="${padding.left + 22}" y="18" font-size="12" fill="#102033">Sales</text>
                    <circle cx="${padding.left + 92}" cy="14" r="5" fill="${purchasesColor}"></circle>
                    <text x="${padding.left + 104}" y="18" font-size="12" fill="#102033">Purchases</text>
                </g>
            `;

            svg.innerHTML = markup;
        })();
    </script>
</body>
</html>
