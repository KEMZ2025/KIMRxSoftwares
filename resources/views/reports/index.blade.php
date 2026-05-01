<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - KIM Rx</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; display: flex; background: #f5f7fb; color: #172033; }
        .content { flex: 1; width: 100%; max-width: 100%; padding: 20px; }
        .topbar, .panel {
            background: #fff;
            border-radius: 18px;
            padding: 22px;
            box-shadow: 0 16px 34px rgba(15, 23, 42, 0.08);
            margin-bottom: 20px;
        }
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 18px;
        }
        .topbar h1 { margin: 0 0 6px; font-size: 30px; }
        .topbar p { margin: 0; color: #667085; }
        .range-chip {
            padding: 10px 14px;
            border-radius: 999px;
            background: #eef4ff;
            color: #1e40af;
            font-weight: 700;
            white-space: nowrap;
        }
        .topbar-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }
        .filters {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            justify-content: space-between;
        }
        .period-links {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .period-links a {
            text-decoration: none;
            color: #155eef;
            background: #eef4ff;
            border: 1px solid #dbe5ff;
            padding: 10px 14px;
            border-radius: 999px;
            font-weight: 700;
            font-size: 13px;
        }
        .period-links a.active {
            background: #155eef;
            color: #fff;
            border-color: #155eef;
        }
        .custom-form {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
        .custom-form input {
            padding: 10px 12px;
            border-radius: 10px;
            border: 1px solid #d0d5dd;
        }
        .custom-form select {
            padding: 10px 12px;
            border-radius: 10px;
            border: 1px solid #d0d5dd;
            background: #fff;
            min-width: 190px;
        }
        .btn {
            border: none;
            border-radius: 10px;
            padding: 10px 16px;
            cursor: pointer;
            font-weight: 700;
        }
        .btn-primary { background: #1d4ed8; color: #fff; }
        .btn-light { background: #eef2ff; color: #1e3a8a; text-decoration: none; display: inline-flex; align-items: center; }
        .action-group { display:flex; flex-wrap:wrap; gap:10px; justify-content:flex-end; }
        .action-group.compact .btn-light { background:#f8fafc; color:#344054; border:1px solid #d0d5dd; }
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
        }
        .summary-card {
            border-radius: 18px;
            padding: 18px;
            background: linear-gradient(180deg, #ffffff, #f8fafc);
            border: 1px solid #e5e7eb;
            min-height: 128px;
        }
        .summary-card .label {
            color: #667085;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .summary-card .value {
            margin-top: 14px;
            font-size: 28px;
            font-weight: 800;
            line-height: 1.1;
            word-break: break-word;
        }
        .summary-card .meta {
            margin-top: 12px;
            font-size: 13px;
            color: #475467;
        }
        .tone-emerald { border-top: 4px solid #12b76a; }
        .tone-teal { border-top: 4px solid #0f766e; }
        .tone-rose { border-top: 4px solid #e11d48; }
        .tone-blue { border-top: 4px solid #2563eb; }
        .tone-amber { border-top: 4px solid #f59e0b; }
        .tone-violet { border-top: 4px solid #7c3aed; }
        .tone-slate { border-top: 4px solid #475467; }
        .eyebrow {
            margin: 0 0 8px;
            color: #1d4ed8;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-weight: 800;
        }
        .insight-grid {
            display:grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap:16px;
        }
        .channel-grid {
            display:grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap:20px;
            margin-top:20px;
        }
        .channel-card {
            padding: 22px;
            border-radius: 20px;
            border: 1px solid #dbe3ef;
            background: linear-gradient(180deg, #ffffff, #f8fafc);
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.06);
        }
        .channel-card .label {
            color:#16a34a;
            font-size:13px;
            font-weight:800;
            text-transform:uppercase;
            letter-spacing:0.06em;
        }
        .channel-card.tone-blue .label { color:#1d4ed8; }
        .channel-card.tone-rose .label { color:#e11d48; }
        .channel-card .value {
            margin-top:12px;
            font-size:40px;
            font-weight:900;
            line-height:1.05;
        }
        .channel-card .subline,
        .channel-card .footer {
            margin-top:10px;
            color:#344054;
            font-size:16px;
        }
        .channel-card .footer {
            color:#667085;
            font-size:14px;
        }
        .hero-metric {
            display:grid;
            gap:8px;
            padding:18px;
            border-radius:18px;
            border:1px solid #dbe3ef;
            background: linear-gradient(135deg, #eff6ff, #f8fafc);
            margin-bottom:18px;
        }
        .hero-metric .name {
            font-size: 13px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #1d4ed8;
        }
        .hero-metric .value {
            font-size: 34px;
            font-weight: 900;
            line-height: 1.05;
        }
        .hero-metric .meta {
            color: #475467;
            font-size: 14px;
        }
        .chart-list {
            display:grid;
            gap:14px;
        }
        .chart-row {
            display:grid;
            gap:8px;
        }
        .chart-head {
            display:flex;
            justify-content:space-between;
            gap:12px;
            align-items:flex-end;
        }
        .chart-head .label {
            font-weight:700;
            color:#344054;
        }
        .chart-head .meta {
            color:#667085;
            font-size:13px;
            text-align:right;
        }
        .chart-track {
            width:100%;
            height:12px;
            border-radius:999px;
            background:#e5e7eb;
            overflow:hidden;
        }
        .chart-fill {
            height:100%;
            border-radius:999px;
            background: linear-gradient(90deg, #1d4ed8, #12b76a);
        }
        .chart-fill.tone-amber { background: linear-gradient(90deg, #d97706, #f59e0b); }
        .chart-fill.tone-rose { background: linear-gradient(90deg, #e11d48, #fb7185); }
        .table-note {
            margin-top: 14px;
            color: #667085;
            font-size: 13px;
        }

        .two-up {
            display: grid;
            grid-template-columns: 1.4fr 1fr;
            gap: 20px;
        }
        .three-up {
            display: grid;
            grid-template-columns: 1.1fr 1.1fr 0.9fr;
            gap: 20px;
        }
        .panel h2 {
            margin: 0 0 8px;
            font-size: 22px;
        }
        .panel .panel-subtitle {
            margin: 0 0 18px;
            color: #667085;
            font-size: 14px;
        }
        .mini-stat-list {
            display: grid;
            gap: 12px;
        }
        .mini-stat {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 18px;
            padding: 14px 16px;
            border-radius: 14px;
            background: #f8fafc;
            border: 1px solid #eaecf0;
        }
        .mini-stat .name {
            font-weight: 700;
            color: #344054;
        }
        .mini-stat .amount {
            font-weight: 800;
            font-size: 18px;
            text-align: right;
        }
        .profit-table,
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .profit-table th,
        .profit-table td,
        .data-table th,
        .data-table td {
            padding: 12px 14px;
            border-bottom: 1px solid #eaecf0;
            text-align: left;
            vertical-align: top;
        }
        .profit-table th,
        .data-table th {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #667085;
            background: #f8fafc;
        }
        .profit-table .amount,
        .data-table .amount {
            text-align: right;
            font-weight: 800;
        }
        .profit-table .positive { color: #067647; }
        .profit-table .negative { color: #b42318; }
        .profit-table .strong td {
            font-size: 16px;
            font-weight: 800;
        }
        .method-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 14px;
        }
        .method-card {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 16px;
            border-radius: 16px;
            color: #fff;
            min-height: 132px;
            gap: 18px;
        }
        .method-card .method-label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            font-weight: 700;
            opacity: 0.9;
        }
        .method-card .method-value {
            margin-top: 0;
            font-size: clamp(24px, 2.6vw, 31px);
            font-weight: 800;
            line-height: 1.15;
            overflow-wrap: anywhere;
            word-break: break-word;
        }
        .tone-cash { background: linear-gradient(135deg, #0f766e, #14b8a6); }
        .tone-mtn { background: linear-gradient(135deg, #a16207, #eab308); }
        .tone-airtel { background: linear-gradient(135deg, #b42318, #ef4444); }
        .tone-bank { background: linear-gradient(135deg, #1d4ed8, #60a5fa); }
        .tone-cheque { background: linear-gradient(135deg, #4f46e5, #8b5cf6); }
        .table-wrap {
            width: 100%;
            overflow-x: auto;
            border-radius: 16px;
            border: 1px solid #eaecf0;
        }
        .text-muted { color: #667085; }
        .text-right { text-align: right; }
        .empty-state {
            padding: 18px;
            border-radius: 14px;
            background: #f8fafc;
            color: #667085;
            border: 1px dashed #d0d5dd;
        }
        .audit-note {
            margin-top: 16px;
            padding: 14px 16px;
            border-radius: 14px;
            background: #fff7ed;
            border: 1px solid #fed7aa;
            color: #9a3412;
        }
        .audit-note strong {
            display: block;
            margin-bottom: 4px;
        }

        @media (max-width: 1280px) {
            .cards-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .insight-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .channel-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .two-up,
            .three-up { grid-template-columns: 1fr; }
        }

        @media (max-width: 760px) {
            body { flex-direction: column; }
            .topbar { flex-direction: column; }
            .topbar-actions { justify-content: flex-start; }
            .cards-grid { grid-template-columns: 1fr; }
            .insight-grid { grid-template-columns: 1fr; }
            .channel-grid { grid-template-columns: 1fr; }
            .filters { align-items: stretch; }
            .custom-form { width: 100%; }
            .custom-form input,
            .custom-form select,
            .custom-form .btn { width: 100%; }
        }
    </style>
</head>
<body>
    @include('layouts.sidebar')

    @php
        $formatMoney = fn ($value) => number_format((float) $value, 2);
        $formatCount = fn ($value) => number_format((float) $value, 0);
        $staffPerformanceScale = max((float) ($staffPerformance->max('gross_profit') ?? 0), 0.0);
        $periodLinkFilters = request()->except(['period', 'date_from', 'date_to']);
    @endphp

    <div class="content" id="mainContent">
        <div class="topbar">
            <div>
                <h1>Performance Reports</h1>
                <p>{{ $clientName }} | {{ $branchName }} | {{ $businessModeLabel }}</p>
            </div>
            <div class="topbar-actions">
                <div class="range-chip">{{ $rangeLabel }}</div>
                <div class="action-group">
                    <a href="{{ route('reports.print', request()->query() + ['autoprint' => 1]) }}" class="btn btn-primary" target="_blank" rel="noopener">Print</a>
                    <a href="{{ route('reports.download', request()->query() + ['format' => 'pdf']) }}" class="btn btn-light">Full PDF</a>
                    <a href="{{ route('reports.download', request()->query() + ['format' => 'csv']) }}" class="btn btn-light">Full CSV</a>
                </div>
                <div class="action-group compact">
                    <a href="{{ route('reports.download', request()->query() + ['section' => 'performance', 'format' => 'csv']) }}" class="btn btn-light">Performance CSV</a>
                    <a href="{{ route('reports.download', request()->query() + ['section' => 'sales', 'format' => 'csv']) }}" class="btn btn-light">Sales CSV</a>
                    <a href="{{ route('reports.download', request()->query() + ['section' => 'purchases', 'format' => 'csv']) }}" class="btn btn-light">Purchases CSV</a>
                    <a href="{{ route('reports.download', request()->query() + ['section' => 'customers', 'format' => 'csv']) }}" class="btn btn-light">Customers CSV</a>
                    <a href="{{ route('reports.download', request()->query() + ['section' => 'adjustments', 'format' => 'csv']) }}" class="btn btn-light">Adjustments CSV</a>
                    <a href="{{ route('reports.download', request()->query() + ['section' => 'stock_risk', 'format' => 'csv']) }}" class="btn btn-light">Stock Risk CSV</a>
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="filters">
                <div class="period-links">
                    <a href="{{ route('reports.index', $periodLinkFilters + ['period' => 'today']) }}" class="{{ $filters['period'] === 'today' ? 'active' : '' }}">Today</a>
                    <a href="{{ route('reports.index', $periodLinkFilters + ['period' => 'this_week']) }}" class="{{ $filters['period'] === 'this_week' ? 'active' : '' }}">This Week</a>
                    <a href="{{ route('reports.index', $periodLinkFilters + ['period' => 'this_month']) }}" class="{{ $filters['period'] === 'this_month' ? 'active' : '' }}">This Month</a>
                </div>

                <form method="GET" action="{{ route('reports.index') }}" class="custom-form">
                    <input type="hidden" name="period" value="custom">
                    <input type="date" name="date_from" value="{{ $filters['date_from'] }}" required>
                    <input type="date" name="date_to" value="{{ $filters['date_to'] }}" required>
                    <select name="adjustment_direction">
                        <option value="">All adjustment directions</option>
                        @foreach($adjustmentDirectionOptions as $key => $label)
                            <option value="{{ $key }}" @selected($filters['adjustment_direction'] === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <select name="adjustment_reason">
                        <option value="">All adjustment reasons</option>
                        @foreach($adjustmentReasonOptions as $key => $label)
                            <option value="{{ $key }}" @selected($filters['adjustment_reason'] === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-primary">Apply Range</button>
                    <a href="{{ route('reports.index', ['period' => 'today']) }}" class="btn btn-light">Reset</a>
                </form>
            </div>
        </div>

        <div class="channel-grid">
            @foreach($salesChannelCards as $card)
                <div class="channel-card tone-{{ $card['tone'] }}">
                    <div class="label">{{ $card['label'] }}</div>
                    <div class="value">UGX {{ $formatMoney($card['revenue']) }}</div>
                    <div class="subline">COGS: UGX {{ $formatMoney($card['cogs']) }} | Profit: UGX {{ $formatMoney($card['gross_profit']) }}</div>
                    <div class="footer">Discount: UGX {{ $formatMoney($card['discounts']) }} | Invoices: {{ $formatCount($card['invoice_count']) }}</div>
                </div>
            @endforeach

            <div class="channel-card tone-{{ $overallNetProfitCard['tone'] }}">
                <div class="label">{{ $overallNetProfitCard['label'] }}</div>
                <div class="value">UGX {{ $formatMoney($overallNetProfitCard['value']) }}</div>
                <div class="subline">Margin: {{ number_format((float) $overallNetProfitCard['margin'], 1) }}% | Expenses: UGX {{ $formatMoney($overallNetProfitCard['expenses']) }}</div>
                <div class="footer">Stock loss in range: UGX {{ $formatMoney($overallNetProfitCard['stock_loss']) }}</div>
            </div>
        </div>

        <div class="cards-grid">
            @foreach($headlineCards as $card)
                <div class="summary-card tone-{{ $card['tone'] }}">
                    <div class="label">{{ $card['label'] }}</div>
                    <div class="value">
                        {{ $card['kind'] === 'money' ? $formatMoney($card['value']) : $formatCount($card['value']) }}
                    </div>

                    @if(!empty($card['meta']))
                        <div class="meta">
                            {{ $card['meta']['label'] }}:
                            {{ $card['meta']['kind'] === 'money' ? $formatMoney($card['meta']['value']) : $formatCount($card['meta']['value']) }}
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="insight-grid" style="margin-top:20px;">
            @foreach($inventoryRiskCards as $card)
                <div class="summary-card tone-{{ $card['tone'] }}">
                    <div class="label">{{ $card['label'] }}</div>
                    <div class="value">
                        {{ $card['kind'] === 'money' ? $formatMoney($card['value']) : $formatCount($card['value']) }}
                    </div>
                    <div class="meta">{{ $card['subtitle'] }}</div>
                </div>
            @endforeach
        </div>

        <div class="two-up" style="margin-top:20px;">
            <div class="panel">
                <p class="eyebrow">Team Performance</p>
                <h2>Top Performer</h2>
                <p class="panel-subtitle">Shows which team member generated the highest gross profit for the pharmacy in the selected range.</p>

                @if($topPerformer)
                    <div class="hero-metric">
                        <div class="name">{{ $topPerformer['staff_name'] }}</div>
                        <div class="value">{{ $formatMoney($topPerformer['gross_profit']) }}</div>
                        <div class="meta">
                            Revenue {{ $formatMoney($topPerformer['revenue']) }} | {{ $formatCount($topPerformer['invoice_count']) }} invoices | {{ number_format((float) $topPerformer['units_sold'], 2) }} units sold
                        </div>
                    </div>

                    <div class="chart-list">
                        @foreach($staffPerformance as $row)
                            @php
                                $barWidth = $staffPerformanceScale > 0 && $row['gross_profit'] > 0
                                    ? max(6, (($row['gross_profit'] / $staffPerformanceScale) * 100))
                                    : 0;
                            @endphp
                            <div class="chart-row">
                                <div class="chart-head">
                                    <div class="label">{{ $row['staff_name'] }}</div>
                                    <div class="meta">{{ $formatMoney($row['gross_profit']) }} gross profit</div>
                                </div>
                                <div class="chart-track">
                                    <div class="chart-fill" style="width: {{ $barWidth }}%;"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="empty-state">No approved sales were recorded in the selected range, so no top performer is available yet.</div>
                @endif
            </div>

            <div class="panel">
                <p class="eyebrow">Customer Performance</p>
                <h2>Top Customers By Channel</h2>
                <p class="panel-subtitle">Shows which customers are bringing the strongest revenue and gross profit in the selected range for the active business mode.</p>

                @if($customerPerformanceGroups->every(fn ($group) => $group['rows']->isEmpty()))
                    <div class="empty-state">No customer sales were recorded in the selected range.</div>
                @else
                    @foreach($customerPerformanceGroups as $group)
                        <div style="margin-bottom: {{ !$loop->last ? '20px' : '0' }};">
                            <div class="mini-stat" style="margin-bottom:14px;">
                                <div class="name">{{ $group['label'] }}</div>
                                <div class="amount">
                                    @if($group['top_customer'])
                                        {{ $group['top_customer']['customer_name'] }} | {{ $formatMoney($group['top_customer']['gross_profit']) }} profit
                                    @else
                                        No customer activity
                                    @endif
                                </div>
                            </div>

                            @if($group['rows']->isEmpty())
                                <div class="empty-state">No {{ strtolower($group['label']) }} were recorded in this range.</div>
                            @else
                                <div class="chart-list">
                                    @foreach($group['rows'] as $row)
                                        @php
                                            $barWidth = $group['scale'] > 0 && $row['revenue'] > 0
                                                ? max(6, (($row['revenue'] / $group['scale']) * 100))
                                                : 0;
                                        @endphp
                                        <div class="chart-row">
                                            <div class="chart-head">
                                                <div class="label">{{ $row['customer_name'] }}</div>
                                                <div class="meta">
                                                    Revenue {{ $formatMoney($row['revenue']) }} | Profit {{ $formatMoney($row['gross_profit']) }} | {{ number_format((float) $row['collection_rate'], 1) }}% collected
                                                </div>
                                            </div>
                                            <div class="chart-track">
                                                <div class="chart-fill tone-amber" style="width: {{ $barWidth }}%;"></div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                @endif
            </div>
        </div>

        <div class="two-up" style="margin-top:20px;">
            <div class="panel">
                <h2>Profit &amp; Loss Snapshot</h2>
                <p class="panel-subtitle">Sales value, cost of goods sold, stock losses, operating expenses, and final net profit for the selected range.</p>

                <div class="table-wrap">
                    <table class="profit-table">
                        <thead>
                            <tr>
                                <th>Line</th>
                                <th class="amount">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($profitLossRows as $row)
                                <tr class="{{ !empty($row['strong']) ? 'strong' : '' }}">
                                    <td>{{ $row['label'] }}</td>
                                    <td class="amount {{ $row['tone'] }}">
                                        {{ $row['amount'] < 0 ? '-' : '' }}{{ $formatMoney(abs((float) $row['amount'])) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="panel">
                <h2>Money Received By Method</h2>
                <p class="panel-subtitle">POS receipts and customer collections combined in the selected window.</p>

                <div class="method-grid">
                    @foreach($moneyByMethod as $method)
                        <div class="method-card tone-{{ $method['tone'] }}">
                            <div class="method-label">{{ $method['label'] }}</div>
                            <div class="method-value">{{ $formatMoney($method['amount']) }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="three-up" style="margin-top:20px;">
            <div class="panel">
                <h2>Sales Summary</h2>
                <p class="panel-subtitle">Approved sales and customer money movement in the selected range.</p>
                <div class="mini-stat-list">
                    @foreach($salesSummary as $stat)
                        <div class="mini-stat">
                            <div class="name">{{ $stat['label'] }}</div>
                            <div class="amount">{{ $stat['kind'] === 'money' ? $formatMoney($stat['value']) : $formatCount($stat['value']) }}</div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="panel">
                <h2>Purchase Summary</h2>
                <p class="panel-subtitle">Purchases and supplier-side movement in the selected range.</p>
                <div class="mini-stat-list">
                    @foreach($purchaseSummary as $stat)
                        <div class="mini-stat">
                            <div class="name">{{ $stat['label'] }}</div>
                            <div class="amount">{{ $stat['kind'] === 'money' ? $formatMoney($stat['value']) : $formatCount($stat['value']) }}</div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="panel">
                <h2>Adjustment Breakdown</h2>
                <p class="panel-subtitle">Filtered stock movement reasons and their money impact in the selected range.</p>

                <div class="mini-stat-list" style="margin-bottom:16px;">
                    @foreach($adjustmentSummaryCards as $card)
                        <div class="mini-stat">
                            <div class="name">{{ $card['label'] }}</div>
                            <div class="amount">
                                {{ $card['kind'] === 'money' ? $formatMoney($card['value']) : $formatCount($card['value']) }}
                            </div>
                        </div>
                    @endforeach
                </div>

                @if($adjustmentBreakdown->isEmpty())
                    <div class="empty-state">No stock adjustments matched the selected filters in this period.</div>
                @else
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Reason</th>
                                    <th>Direction</th>
                                    <th class="text-right">Qty</th>
                                    <th class="text-right">Value</th>
                                    <th class="text-right">Loss Hit</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($adjustmentBreakdown as $row)
                                    <tr>
                                        <td>{{ $row['reason'] }}</td>
                                        <td>{{ $row['direction'] }}</td>
                                        <td class="text-right">{{ number_format($row['quantity'], 2) }}</td>
                                        <td class="text-right">{{ $formatMoney($row['value']) }}</td>
                                        <td class="text-right">{{ $formatMoney($row['loss_value']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <div class="two-up" style="margin-top:20px;">
            <div class="panel">
                <p class="eyebrow">Sales Report</p>
                <h2>Range Sales Detail</h2>
                <p class="panel-subtitle">Approved sales captured inside the selected range, ready for export.</p>

                @if($selectedSalesReport->isEmpty())
                    <div class="empty-state">No approved sales were recorded in this period.</div>
                @else
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Invoice</th>
                                    <th>Channel</th>
                                    <th>Customer</th>
                                    <th>Date</th>
                                    <th>Served By</th>
                                    <th>Method</th>
                                    <th class="text-right">Total</th>
                                    <th class="text-right">Gross Profit</th>
                                    <th class="text-right">Paid</th>
                                    <th class="text-right">Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($selectedSalesReport as $sale)
                                    <tr>
                                        <td>{{ $sale->invoice_number }}</td>
                                        <td>{{ $sale->sale_type_label ?? 'Retail' }}</td>
                                        <td>{{ $sale->customer?->name ?? 'Walk-in Customer' }}</td>
                                        <td>{{ optional($sale->sale_date)->format('d M Y') }}</td>
                                        <td>{{ $sale->servedByUser?->name ?? 'System' }}</td>
                                        <td>{{ $sale->payment_method }}</td>
                                        <td class="text-right">{{ $formatMoney($sale->total_amount) }}</td>
                                        <td class="text-right">{{ $formatMoney($sale->gross_profit ?? 0) }}</td>
                                        <td class="text-right">{{ $formatMoney($sale->amount_paid) }}</td>
                                        <td class="text-right">{{ $formatMoney($sale->balance_due) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div class="panel">
                <p class="eyebrow">Purchase Report</p>
                <h2>Range Purchase Detail</h2>
                <p class="panel-subtitle">Purchases posted inside the selected range, ready for export and review.</p>

                @if($selectedPurchaseReport->isEmpty())
                    <div class="empty-state">No purchases were recorded in this period.</div>
                @else
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Invoice</th>
                                    <th>Supplier</th>
                                    <th>Date</th>
                                    <th>Entered By</th>
                                    <th>Medicines Bought</th>
                                    <th>Status</th>
                                    <th class="text-right">Total</th>
                                    <th class="text-right">Paid</th>
                                    <th class="text-right">Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($selectedPurchaseReport as $purchase)
                                    <tr>
                                        <td>{{ $purchase->invoice_number }}</td>
                                        <td>{{ $purchase->supplier?->name ?? 'Unknown Supplier' }}</td>
                                        <td>{{ optional($purchase->purchase_date)->format('d M Y') }}</td>
                                        <td>{{ $purchase->createdByUser?->name ?? 'System' }}</td>
                                        <td>{{ $purchase->medicine_summary ?? 'No medicine lines recorded' }}</td>
                                        <td>{{ ucfirst((string) $purchase->payment_status) }}</td>
                                        <td class="text-right">{{ $formatMoney($purchase->total_amount) }}</td>
                                        <td class="text-right">{{ $formatMoney($purchase->amount_paid) }}</td>
                                        <td class="text-right">{{ $formatMoney($purchase->balance_due) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <div class="panel" style="margin-top:20px;">
            <p class="eyebrow">Stock Adjustments</p>
            <h2>Money Impact By Reason</h2>
            <p class="panel-subtitle">Use the direction and reason filters above to review how stock adjustments changed inventory value and which ones hit profit as losses.</p>

            @if($selectedAdjustmentReport->isEmpty())
                <div class="empty-state">No stock adjustments matched the current filter in this selected period.</div>
            @else
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Direction</th>
                                <th>Reason</th>
                                <th>Medicine</th>
                                <th>Batch</th>
                                <th class="text-right">Qty</th>
                                <th class="text-right">Unit Cost</th>
                                <th class="text-right">Inventory Impact</th>
                                <th class="text-right">Loss Posted</th>
                                <th>Books Effect</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($selectedAdjustmentReport as $adjustment)
                                <tr>
                                    <td>{{ optional($adjustment->adjustment_date)->format('d M Y H:i') }}</td>
                                    <td>{{ $adjustment->direction_label }}</td>
                                    <td>{{ $adjustment->reason_label }}</td>
                                    <td>
                                        {{ $adjustment->product?->name ?? 'Unknown Product' }}
                                        @if(!empty($adjustment->product?->strength))
                                            <div class="text-muted">{{ $adjustment->product?->strength }}</div>
                                        @endif
                                    </td>
                                    <td>{{ $adjustment->batch?->batch_number ?? 'N/A' }}</td>
                                    <td class="text-right">{{ number_format((float) $adjustment->quantity, 2) }}</td>
                                    <td class="text-right">{{ $formatMoney($adjustment->unit_cost ?? 0) }}</td>
                                    <td class="text-right">
                                        {{ ($adjustment->inventory_impact ?? 0) < 0 ? '-' : '' }}{{ $formatMoney(abs((float) ($adjustment->inventory_impact ?? 0))) }}
                                    </td>
                                    <td class="text-right">{{ $formatMoney($adjustment->loss_amount ?? 0) }}</td>
                                    <td>{{ $adjustment->books_effect ?? 'Inventory books updated.' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="audit-note">
                    <strong>Books Balance Note</strong>
                    Decrease adjustments reduce inventory value on the books. When the reason is damaged, expired, count loss, theft, sample use, or other loss, the same value also reduces profit in this report.
                </div>
            @endif
        </div>

        <div class="two-up" style="margin-top:20px;">
            <div class="panel">
                <p class="eyebrow">Stock Risk</p>
                <h2>Out Of Stock Medicines</h2>
                <p class="panel-subtitle">Current inventory position, independent of the selected date range.</p>

                @if($outOfStockProducts->isEmpty())
                    <div class="empty-state">No active products are completely out of free stock right now.</div>
                @else
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Medicine</th>
                                    <th class="text-right">Batches</th>
                                    <th class="text-right">Available</th>
                                    <th class="text-right">Reserved</th>
                                    <th class="text-right">Free Stock</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($outOfStockProducts as $row)
                                    <tr>
                                        <td>
                                            {{ $row['product_name'] }}
                                            @if(!empty($row['strength']))
                                                <div class="text-muted">{{ $row['strength'] }}@if(!empty($row['unit_name'])) | {{ $row['unit_name'] }}@endif</div>
                                            @endif
                                        </td>
                                        <td class="text-right">{{ $formatCount($row['batch_count']) }}</td>
                                        <td class="text-right">{{ number_format((float) $row['available_stock'], 2) }}</td>
                                        <td class="text-right">{{ number_format((float) $row['reserved_stock'], 2) }}</td>
                                        <td class="text-right">{{ number_format((float) $row['free_stock'], 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div class="panel">
                <p class="eyebrow">Critical Medicines</p>
                <h2>Likely Money To Lose</h2>
                <p class="panel-subtitle">Batches approaching expiry while they still hold free stock. This is the stock value currently at risk.</p>

                @if($criticalMedicines->isEmpty())
                    <div class="empty-state">No active expiry-risk batches are currently holding free stock.</div>
                @else
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Medicine</th>
                                    <th>Batch</th>
                                    <th>Risk Window</th>
                                    <th class="text-right">Free Stock</th>
                                    <th class="text-right">Unit Cost</th>
                                    <th class="text-right">Likely Loss</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($criticalMedicines as $row)
                                    <tr>
                                        <td>
                                            {{ $row['product_name'] }}
                                            @if(!empty($row['strength']))
                                                <div class="text-muted">{{ $row['strength'] }}@if(!empty($row['unit_name'])) | {{ $row['unit_name'] }}@endif</div>
                                            @endif
                                        </td>
                                        <td>{{ $row['batch_number'] }}</td>
                                        <td>{{ $row['risk_label'] }}</td>
                                        <td class="text-right">{{ number_format((float) $row['free_stock'], 2) }}</td>
                                        <td class="text-right">{{ $formatMoney($row['purchase_price']) }}</td>
                                        <td class="text-right">{{ $formatMoney($row['loss_value']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="table-note">Total stock value at expiry risk: {{ $formatMoney($criticalMedicines->sum('loss_value')) }}</div>
                @endif
            </div>
        </div>

        <div class="panel" style="margin-top:20px;">
            <h2>Damaged Goods Report</h2>
            <p class="panel-subtitle">Stock decreases recorded specifically as damaged goods in the selected range.</p>

            @if($damagedGoods->isEmpty())
                <div class="empty-state">No damaged-goods adjustments were recorded in this period.</div>
            @else
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Product</th>
                                <th>Batch</th>
                                <th class="text-right">Qty</th>
                                <th class="text-right">Unit Cost</th>
                                <th class="text-right">Loss Value</th>
                                <th>Adjusted By</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($damagedGoods as $adjustment)
                                @php
                                    $unitCost = (float) ($adjustment->batch?->purchase_price ?? 0);
                                    $lossValue = (float) $adjustment->quantity * $unitCost;
                                @endphp
                                <tr>
                                    <td>{{ optional($adjustment->adjustment_date)->format('d M Y H:i') }}</td>
                                    <td>{{ $adjustment->product?->name ?? 'Unknown Product' }}</td>
                                    <td>{{ $adjustment->batch?->batch_number ?? 'N/A' }}</td>
                                    <td class="text-right">{{ number_format((float) $adjustment->quantity, 2) }}</td>
                                    <td class="text-right">{{ $formatMoney($unitCost) }}</td>
                                    <td class="text-right">{{ $formatMoney($lossValue) }}</td>
                                    <td>{{ $adjustment->adjustedByUser?->name ?? 'System' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
        <div class="two-up" style="margin-top:20px;">
            <div class="panel">
                <h2>Top Selling Products</h2>
                <p class="panel-subtitle">Fast-moving products by quantity and revenue in the selected range.</p>

                @if($topSellingProducts->isEmpty())
                    <div class="empty-state">No approved sale lines were recorded in this period.</div>
                @else
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th class="text-right">Qty Sold</th>
                                    <th class="text-right">Revenue</th>
                                    <th class="text-right">Gross Margin</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($topSellingProducts as $row)
                                    @php
                                        $grossMargin = (float) $row->total_revenue - (float) $row->total_cost;
                                    @endphp
                                    <tr>
                                        <td>{{ $row->name }}</td>
                                        <td class="text-right">{{ number_format((float) $row->total_quantity, 2) }}</td>
                                        <td class="text-right">{{ $formatMoney($row->total_revenue) }}</td>
                                        <td class="text-right">{{ $formatMoney($grossMargin) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div class="panel">
                <h2>Current Outstanding Receivables</h2>
                <p class="panel-subtitle">Approved customer invoices that still have a balance due right now.</p>

                @if($receivables->isEmpty())
                    <div class="empty-state">No customer balances are currently outstanding.</div>
                @else
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Invoice</th>
                                    <th>Customer</th>
                                    <th>Date</th>
                                    <th class="text-right">Total</th>
                                    <th class="text-right">Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($receivables as $sale)
                                    <tr>
                                        <td>{{ $sale->invoice_number }}</td>
                                        <td>{{ $sale->customer?->name ?? 'Walk-in Customer' }}</td>
                                        <td>{{ optional($sale->sale_date)->format('d M Y') }}</td>
                                        <td class="text-right">{{ $formatMoney($sale->total_amount) }}</td>
                                        <td class="text-right">{{ $formatMoney($sale->balance_due) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <div class="panel" style="margin-top:20px;">
            <h2>Current Outstanding Payables</h2>
            <p class="panel-subtitle">Supplier invoices that the branch still owes right now.</p>

            @if($payables->isEmpty())
                <div class="empty-state">No supplier balances are currently outstanding.</div>
            @else
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Invoice</th>
                                <th>Supplier</th>
                                <th>Date</th>
                                <th class="text-right">Total</th>
                                <th class="text-right">Balance</th>
                                <th>Entered By</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($payables as $purchase)
                                <tr>
                                    <td>{{ $purchase->invoice_number }}</td>
                                    <td>{{ $purchase->supplier?->name ?? 'Unknown Supplier' }}</td>
                                    <td>{{ optional($purchase->purchase_date)->format('d M Y') }}</td>
                                    <td class="text-right">{{ $formatMoney($purchase->total_amount) }}</td>
                                    <td class="text-right">{{ $formatMoney($purchase->balance_due) }}</td>
                                    <td>{{ $purchase->createdByUser?->name ?? 'System' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</body>
</html>
