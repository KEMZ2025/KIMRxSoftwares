<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KIM Rx</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
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
        .topbar { display: flex; justify-content: space-between; align-items: flex-start; gap: 18px; }
        .topbar h1 { margin: 0 0 6px; font-size: 30px; }
        .topbar p, .panel-subtitle { margin: 0; color: #667085; }
        .range-chip { padding: 10px 14px; border-radius: 999px; background: #eef4ff; color: #1e40af; font-weight: 700; white-space: nowrap; }
        .topbar-actions, .action-group { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; justify-content: flex-end; }
        .filters, .custom-form { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
        .filters { justify-content: space-between; }
        .period-links, .report-nav { display: flex; flex-wrap: wrap; gap: 10px; }
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
        .period-links a.active { background: #155eef; color: #fff; border-color: #155eef; }
        .custom-form input, .custom-form select {
            padding: 10px 12px;
            border-radius: 10px;
            border: 1px solid #d0d5dd;
            background: #fff;
            min-width: 180px;
        }
        .custom-form label { display: grid; gap: 6px; color: #344054; font-size: 13px; font-weight: 700; }
        .btn {
            border: none;
            border-radius: 10px;
            padding: 10px 16px;
            cursor: pointer;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .btn-primary { background: #1d4ed8; color: #fff; }
        .btn-light { background: #eef2ff; color: #1e3a8a; }
        .btn-soft { background: #f8fafc; color: #344054; border: 1px solid #d0d5dd; }
        .report-nav { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; }
        .report-card {
            border: 1px solid #dbe3ef;
            border-radius: 14px;
            background: linear-gradient(180deg, #ffffff, #f8fafc);
            color: #172033;
            padding: 14px;
            text-decoration: none;
            min-height: 108px;
        }
        .report-card strong { display: block; margin-bottom: 8px; font-size: 16px; }
        .report-card span { color: #667085; font-size: 13px; line-height: 1.35; }
        .report-card.active { border-color: #1d4ed8; box-shadow: 0 0 0 3px rgba(29, 78, 216, 0.12); }
        .directory-panel { padding: 26px 30px; }
        .directory-title { display: flex; justify-content: space-between; align-items: flex-end; gap: 16px; margin-bottom: 20px; }
        .directory-title h2 { margin: 0; font-size: 24px; }
        .directory-title p { margin: 6px 0 0; color: #667085; }
        .reports-directory-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 34px; }
        .report-column-title {
            color: #0f766e;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .08em;
            font-size: 12px;
            margin: 0 0 8px;
        }
        .report-list { border-top: 1px solid #e5e7eb; }
        .report-row {
            min-height: 48px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid #e5e7eb;
            text-decoration: none;
            color: #006fbf;
            padding: 0 8px;
            font-size: 15px;
            font-weight: 700;
            transition: background .15s ease, color .15s ease, padding-left .15s ease;
        }
        .report-row:hover { background: #eef7ff; color: #0b4f9f; padding-left: 14px; }
        .report-icon {
            width: 10px;
            height: 10px;
            border-radius: 3px;
            background: linear-gradient(135deg, #ef2b2d, #12a8e0);
            box-shadow: 0 0 0 3px #eef7ff;
            flex: 0 0 auto;
        }
        .selected-report-panel { display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; }
        .selected-report-panel strong { color:#101828; }
        .selected-report-panel span { color:#667085; font-size:13px; }
        .cards-grid, .insight-grid, .mini-stat-list {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
        }
        .two-up { display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap:20px; }
        .summary-card, .mini-stat {
            border-radius: 16px;
            padding: 18px;
            background: linear-gradient(180deg, #ffffff, #f8fafc);
            border: 1px solid #e5e7eb;
        }
        .summary-card .label, .mini-stat .name, .eyebrow {
            color: #1d4ed8;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            font-weight: 800;
        }
        .summary-card .value, .mini-stat .amount {
            margin-top: 10px;
            font-size: 26px;
            font-weight: 900;
            line-height: 1.1;
            word-break: break-word;
        }
        .summary-card .meta { margin-top: 10px; font-size: 13px; color: #475467; }
        .tone-emerald { border-top: 4px solid #12b76a; }
        .tone-teal { border-top: 4px solid #0f766e; }
        .tone-rose { border-top: 4px solid #e11d48; }
        .tone-blue { border-top: 4px solid #2563eb; }
        .tone-amber { border-top: 4px solid #f59e0b; }
        .tone-violet { border-top: 4px solid #7c3aed; }
        .tone-slate { border-top: 4px solid #475467; }
        .channel-grid { display:grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap:20px; }
        .channel-card { padding: 22px; border-radius: 20px; border: 1px solid #dbe3ef; background: linear-gradient(180deg, #ffffff, #f8fafc); }
        .channel-card .label { color:#16a34a; font-size:13px; font-weight:800; text-transform:uppercase; letter-spacing:0.06em; }
        .channel-card .value { margin-top:12px; font-size:34px; font-weight:900; line-height:1.05; }
        .channel-card .subline, .channel-card .footer { margin-top:10px; color:#344054; font-size:14px; }
        .table-wrap { width: 100%; overflow-x: auto; border-radius: 16px; border: 1px solid #eaecf0; }
        .data-table, .profit-table { width: 100%; border-collapse: collapse; min-width: 860px; background: #fff; }
        .data-table th, .data-table td, .profit-table th, .profit-table td { padding: 11px 12px; border-bottom: 1px solid #eaecf0; text-align: left; vertical-align: top; }
        .data-table th, .profit-table th { background: #f8fafc; color: #344054; font-size: 12px; text-transform: uppercase; letter-spacing: 0.04em; }
        .profit-table .amount, .text-right { text-align: right; }
        .empty-state { padding: 18px; border-radius: 14px; background: #f8fafc; color: #667085; border: 1px dashed #d0d5dd; }
        .text-muted { color: #667085; }
        .audit-note { margin-top: 16px; padding: 14px 16px; border-radius: 14px; background: #fff7ed; border: 1px solid #fed7aa; color: #9a3412; }
        .method-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:14px; }
        .method-card { border-radius: 16px; padding: 18px; color: #fff; background: linear-gradient(135deg, #334155, #64748b); }
        .method-label { font-size: 13px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.06em; }
        .method-value { margin-top: 10px; font-size: 26px; font-weight: 900; }
        .tone-cash { background: linear-gradient(135deg, #047857, #34d399); }
        .tone-mtn { background: linear-gradient(135deg, #a16207, #eab308); }
        .tone-airtel { background: linear-gradient(135deg, #b42318, #ef4444); }
        .tone-bank { background: linear-gradient(135deg, #1d4ed8, #60a5fa); }
        .tone-cheque { background: linear-gradient(135deg, #4f46e5, #8b5cf6); }
        @media (max-width: 1280px) {
            .report-nav, .cards-grid, .insight-grid, .reports-directory-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .channel-grid, .two-up { grid-template-columns: 1fr; }
        }
        @media (max-width: 760px) {
            body { flex-direction: column; }
            .topbar { flex-direction: column; }
            .report-nav, .cards-grid, .insight-grid, .mini-stat-list, .reports-directory-grid { grid-template-columns: 1fr; }
            .custom-form input, .custom-form select, .custom-form .btn { width: 100%; }
        }
    </style>
</head>
<body>
    @include('layouts.sidebar')

    @php
        $formatMoney = fn ($value) => number_format((float) $value, 2);
        $formatCount = fn ($value) => number_format((float) $value, 0);
        $activeReport = $activeReport ?? 'overview';
        $activeReportMeta = $activeReportMeta ?? ['label' => 'Overview', 'description' => 'Reports'];
        $periodLinkFilters = request()->except(['period', 'date_from', 'date_to']);
        $profitResetFilters = request()->except(['profit_dispenser_id', 'profit_customer_id', 'profit_sale_type']);
        $isReportDirectory = ! request()->filled('report');
        $directoryGroups = [
            [
                'label' => 'Sales & Profit',
                'reports' => [
                    ['label' => 'Business Summary', 'report' => 'overview'],
                    ['label' => 'Sales Transactions', 'report' => 'sales'],
                    ['label' => 'Medicine Sales Ranking', 'report' => 'top_products'],
                    ['label' => 'Product Profit Review', 'report' => 'profit_detail'],
                    ['label' => 'Profit Summary', 'report' => 'profit_loss'],
                    ['label' => 'Collections By Payment Method', 'report' => 'money_methods'],
                ],
            ],
            [
                'label' => 'Stock & Purchases',
                'reports' => [
                    ['label' => 'Stock Watchlist', 'report' => 'stock_risk'],
                    ['label' => 'Purchase Transactions', 'report' => 'purchases'],
                    ['label' => 'Stock Movement Adjustments', 'report' => 'adjustments'],
                    ['label' => 'Damaged Stock Review', 'report' => 'damaged'],
                ],
            ],
            [
                'label' => 'Customers & Suppliers',
                'reports' => [
                    ['label' => 'Customer Sales Performance', 'report' => 'customers'],
                    ['label' => 'Staff Sales Performance', 'report' => 'staff'],
                    ['label' => 'Customer Balances', 'report' => 'receivables'],
                    ['label' => 'Supplier Balances', 'report' => 'payables'],
                ],
            ],
        ];
    @endphp

    <div class="content" id="mainContent">
        <div class="topbar">
            <div>
                <h1>{{ $isReportDirectory ? 'Reports' : $activeReportMeta['label'] }}</h1>
                <p>
                    @if($isReportDirectory)
                        Select one report to open it on its own screen | {{ $clientName }} | {{ $branchName }}
                    @else
                        {{ $activeReportMeta['description'] }} | {{ $clientName }} | {{ $branchName }} | {{ $rangeLabel }}
                    @endif
                </p>
            </div>
            @if(! $isReportDirectory)
                <div class="topbar-actions">
                    <div class="range-chip">{{ $rangeLabel }}</div>
                    <div class="action-group">
                        <a href="{{ route('reports.print', request()->query() + ['report' => $activeReport, 'autoprint' => 1]) }}" class="btn btn-primary" target="_blank" rel="noopener">Print</a>
                        <a href="{{ route('reports.download', request()->query() + ['report' => $activeReport, 'format' => 'pdf']) }}" class="btn btn-light">PDF</a>
                        <a href="{{ route('reports.download', request()->query() + ['report' => $activeReport, 'format' => 'csv']) }}" class="btn btn-light">CSV</a>
                    </div>
                </div>
            @endif
        </div>

        @if($isReportDirectory)
            <div class="panel directory-panel">
                <div class="directory-title">
                    <div>
                        <h2>All Reports</h2>
                        <p>Open one report at a time. Each report keeps its own filters, print, PDF, and CSV options.</p>
                    </div>
                    <div class="range-chip">{{ $rangeLabel }}</div>
                </div>
                <div class="reports-directory-grid">
                    @foreach($directoryGroups as $group)
                        <div>
                            <div class="report-column-title">{{ $group['label'] }}</div>
                            <div class="report-list">
                                @foreach($group['reports'] as $report)
                                    @php
                                        $href = isset($report['route'])
                                            ? route($report['route'])
                                            : route('reports.index', request()->query() + ['report' => $report['report']]);
                                    @endphp
                                    <a href="{{ $href }}" class="report-row">
                                        <span class="report-icon" aria-hidden="true"></span>
                                        <span>{{ $report['label'] }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            <div class="panel selected-report-panel">
                <div>
                    <strong>{{ $activeReportMeta['label'] }}</strong><br>
                    <span>{{ $activeReportMeta['description'] }}</span>
                </div>
                <a href="{{ route('reports.index') }}" class="btn btn-soft">Back To All Reports</a>
            </div>

        <div class="panel">
            <div class="filters">
                <div class="period-links">
                    <a href="{{ route('reports.index', $periodLinkFilters + ['period' => 'today']) }}" class="{{ $filters['period'] === 'today' ? 'active' : '' }}">Today</a>
                    <a href="{{ route('reports.index', $periodLinkFilters + ['period' => 'this_week']) }}" class="{{ $filters['period'] === 'this_week' ? 'active' : '' }}">This Week</a>
                    <a href="{{ route('reports.index', $periodLinkFilters + ['period' => 'this_month']) }}" class="{{ $filters['period'] === 'this_month' ? 'active' : '' }}">This Month</a>
                </div>
                <form method="GET" action="{{ route('reports.index') }}" class="custom-form">
                    <input type="hidden" name="report" value="{{ $activeReport }}">
                    <input type="hidden" name="period" value="custom">
                    <input type="date" name="date_from" value="{{ $filters['date_from'] }}" required>
                    <input type="date" name="date_to" value="{{ $filters['date_to'] }}" required>
                    @if($activeReport === 'adjustments')
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
                    @endif
                    <button type="submit" class="btn btn-primary">Apply Range</button>
                    <a href="{{ route('reports.index', ['report' => $activeReport, 'period' => 'today']) }}" class="btn btn-soft">Reset</a>
                </form>
            </div>
        </div>

        @switch($activeReport)
            @case('profit_detail')
                <div class="panel">
                    <h2>Profit By Dispenser And Customer</h2>
                    <p class="panel-subtitle">Use this when an owner or accountant wants to see product cost, selling price, and profit by dispenser or wholesale customer.</p>
                    <form method="GET" action="{{ route('reports.index') }}" class="custom-form" style="margin:16px 0;">
                        @foreach(request()->except(['profit_dispenser_id', 'profit_customer_id', 'profit_sale_type']) as $key => $value)
                            @if(is_array($value))
                                @foreach($value as $item)
                                    <input type="hidden" name="{{ $key }}[]" value="{{ $item }}">
                                @endforeach
                            @else
                                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                            @endif
                        @endforeach
                        <input type="hidden" name="report" value="profit_detail">
                        <label>Sale Type
                            <select name="profit_sale_type">
                                @foreach($profitSaleTypeOptions as $value => $label)
                                    <option value="{{ $value }}" @selected($filters['profit_sale_type'] === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Dispenser
                            <select name="profit_dispenser_id">
                                <option value="0">All Dispensers</option>
                                @foreach($profitDispenserOptions as $dispenser)
                                    <option value="{{ $dispenser->id }}" @selected((int) $filters['profit_dispenser_id'] === (int) $dispenser->id)>{{ $dispenser->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Customer
                            <select name="profit_customer_id">
                                <option value="0">All Customers</option>
                                @foreach($profitCustomerOptions as $customer)
                                    <option value="{{ $customer->id }}" @selected((int) $filters['profit_customer_id'] === (int) $customer->id)>{{ $customer->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <button type="submit" class="btn btn-primary">Apply Profit Filter</button>
                        <a href="{{ route('reports.index', $profitResetFilters + ['report' => 'profit_detail']) }}" class="btn btn-soft">Clear Profit Filter</a>
                    </form>
                    <div class="mini-stat-list" style="margin-bottom:16px;">
                        <div class="mini-stat"><div class="name">Net Sales</div><div class="amount">UGX {{ $formatMoney($profitDetailTotals['revenue']) }}</div></div>
                        <div class="mini-stat"><div class="name">Cost Amount</div><div class="amount">UGX {{ $formatMoney($profitDetailTotals['cost']) }}</div></div>
                        <div class="mini-stat"><div class="name">Gross Profit</div><div class="amount">UGX {{ $formatMoney($profitDetailTotals['gross_profit']) }}</div></div>
                        <div class="mini-stat"><div class="name">Margin</div><div class="amount">{{ number_format((float) $profitDetailTotals['margin'], 1) }}%</div></div>
                    </div>
                    @if($profitDetailRows->isEmpty())
                        <div class="empty-state">No profit details were found for these filters.</div>
                    @else
                        <div class="table-wrap">
                            <table class="data-table">
                                <thead><tr><th>Date</th><th>Invoice</th><th>Type</th><th>Dispenser</th><th>Customer</th><th>Product</th><th>Batch</th><th class="text-right">Qty</th><th class="text-right">Cost</th><th class="text-right">Selling</th><th class="text-right">Sales</th><th class="text-right">Profit</th><th class="text-right">Margin</th></tr></thead>
                                <tbody>
                                    @foreach($profitDetailRows as $row)
                                        <tr>
                                            <td>{{ $row['sale_date'] ? \Carbon\Carbon::parse($row['sale_date'])->format('d M Y') : 'N/A' }}</td>
                                            <td>{{ $row['invoice_number'] }}</td>
                                            <td>{{ $row['sale_type_label'] }}</td>
                                            <td>{{ $row['dispenser_name'] }}</td>
                                            <td>{{ $row['customer_name'] }}</td>
                                            <td>{{ $row['product_name'] }}</td>
                                            <td>{{ $row['batch_number'] }}</td>
                                            <td class="text-right">{{ number_format((float) $row['quantity'], 2) }}</td>
                                            <td class="text-right">UGX {{ $formatMoney($row['purchase_price']) }}</td>
                                            <td class="text-right">UGX {{ $formatMoney($row['unit_price']) }}</td>
                                            <td class="text-right">UGX {{ $formatMoney($row['total_amount']) }}</td>
                                            <td class="text-right">UGX {{ $formatMoney($row['gross_profit']) }}</td>
                                            <td class="text-right">{{ number_format((float) $row['margin'], 1) }}%</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
                @break

            @case('profit_loss')
                <div class="panel">
                    <h2>Profit &amp; Loss Snapshot</h2>
                    <p class="panel-subtitle">Sales value, cost of goods sold, stock losses, operating expenses, and final net profit for the selected range.</p>
                    <div class="table-wrap">
                        <table class="profit-table">
                            <thead><tr><th>Line</th><th class="amount">Amount</th></tr></thead>
                            <tbody>
                                @foreach($profitLossRows as $row)
                                    <tr class="{{ !empty($row['strong']) ? 'strong' : '' }}">
                                        <td>{{ $row['label'] }}</td>
                                        <td class="amount">{{ $row['amount'] < 0 ? '-' : '' }}{{ $formatMoney(abs((float) $row['amount'])) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                @break

            @case('money_methods')
                <div class="panel">
                    <h2>Money Received By Method</h2>
                    <p class="panel-subtitle">POS receipts and customer collections combined in the selected window.</p>
                    <div class="method-grid">
                        @foreach($moneyByMethod as $method)
                            <div class="method-card tone-{{ $method['tone'] }}">
                                <div class="method-label">{{ $method['label'] }}</div>
                                <div class="method-value">UGX {{ $formatMoney($method['amount']) }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
                @break

            @case('staff')
                <div class="panel">
                    <h2>Staff Performance</h2>
                    @if($staffPerformance->isEmpty())
                        <div class="empty-state">No approved sales were recorded in the selected range.</div>
                    @else
                        <div class="table-wrap">
                            <table class="data-table">
                                <thead><tr><th>Staff</th><th class="text-right">Invoices</th><th class="text-right">Units Sold</th><th class="text-right">Revenue</th><th class="text-right">Gross Profit</th></tr></thead>
                                <tbody>
                                    @foreach($staffPerformance as $row)
                                        <tr><td>{{ $row['staff_name'] }}</td><td class="text-right">{{ $formatCount($row['invoice_count']) }}</td><td class="text-right">{{ number_format((float) $row['units_sold'], 2) }}</td><td class="text-right">{{ $formatMoney($row['revenue']) }}</td><td class="text-right">{{ $formatMoney($row['gross_profit']) }}</td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
                @break

            @case('customers')
                <div class="panel">
                    <h2>Customer Performance</h2>
                    @foreach($customerPerformanceGroups as $group)
                        <h3>{{ $group['label'] }}</h3>
                        @if($group['rows']->isEmpty())
                            <div class="empty-state">No {{ strtolower($group['label']) }} were recorded in this range.</div>
                        @else
                            <div class="table-wrap" style="margin-bottom:18px;">
                                <table class="data-table">
                                    <thead><tr><th>Customer</th><th class="text-right">Invoices</th><th class="text-right">Revenue</th><th class="text-right">Gross Profit</th><th class="text-right">Paid</th><th class="text-right">Balance</th><th class="text-right">Collection Rate</th></tr></thead>
                                    <tbody>
                                        @foreach($group['rows'] as $row)
                                            <tr><td>{{ $row['customer_name'] }}</td><td class="text-right">{{ $formatCount($row['invoice_count']) }}</td><td class="text-right">{{ $formatMoney($row['revenue']) }}</td><td class="text-right">{{ $formatMoney($row['gross_profit']) }}</td><td class="text-right">{{ $formatMoney($row['amount_paid']) }}</td><td class="text-right">{{ $formatMoney($row['balance_due']) }}</td><td class="text-right">{{ number_format((float) $row['collection_rate'], 1) }}%</td></tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    @endforeach
                </div>
                @break

            @case('sales')
                <div class="panel">
                    <h2>Range Sales Detail</h2>
                    @if($selectedSalesReport->isEmpty())
                        <div class="empty-state">No approved sales were recorded in this period.</div>
                    @else
                        <div class="table-wrap">
                            <table class="data-table">
                                <thead><tr><th>Invoice</th><th>Channel</th><th>Customer</th><th>Date</th><th>Served By</th><th>Method</th><th class="text-right">Total</th><th class="text-right">Gross Profit</th><th class="text-right">Paid</th><th class="text-right">Balance</th></tr></thead>
                                <tbody>
                                    @foreach($selectedSalesReport as $sale)
                                        <tr><td>{{ $sale->invoice_number }}</td><td>{{ $sale->sale_type_label ?? 'Retail' }}</td><td>{{ $sale->customer?->name ?? 'Walk-in Customer' }}</td><td>{{ optional($sale->sale_date)->format('d M Y') }}</td><td>{{ $sale->servedByUser?->name ?? 'System' }}</td><td>{{ $sale->payment_method }}</td><td class="text-right">{{ $formatMoney($sale->total_amount) }}</td><td class="text-right">{{ $formatMoney($sale->gross_profit ?? 0) }}</td><td class="text-right">{{ $formatMoney($sale->amount_paid) }}</td><td class="text-right">{{ $formatMoney($sale->balance_due) }}</td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
                @break

            @case('purchases')
                <div class="panel">
                    <h2>Range Purchase Detail</h2>
                    @if($selectedPurchaseReport->isEmpty())
                        <div class="empty-state">No purchases were recorded in this period.</div>
                    @else
                        <div class="table-wrap">
                            <table class="data-table">
                                <thead><tr><th>Invoice</th><th>Supplier</th><th>Date</th><th>Entered By</th><th>Medicines Bought</th><th>Status</th><th class="text-right">Total</th><th class="text-right">Paid</th><th class="text-right">Balance</th></tr></thead>
                                <tbody>
                                    @foreach($selectedPurchaseReport as $purchase)
                                        <tr><td>{{ $purchase->invoice_number }}</td><td>{{ $purchase->supplier?->name ?? 'Unknown Supplier' }}</td><td>{{ optional($purchase->purchase_date)->format('d M Y') }}</td><td>{{ $purchase->createdByUser?->name ?? 'System' }}</td><td>{{ $purchase->medicine_summary ?? 'No medicine lines recorded' }}</td><td>{{ ucfirst((string) $purchase->payment_status) }}</td><td class="text-right">{{ $formatMoney($purchase->total_amount) }}</td><td class="text-right">{{ $formatMoney($purchase->amount_paid) }}</td><td class="text-right">{{ $formatMoney($purchase->balance_due) }}</td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
                @break

            @case('adjustments')
                <div class="panel">
                    <h2>Stock Adjustments</h2>
                    <div class="mini-stat-list" style="margin-bottom:16px;">
                        @foreach($adjustmentSummaryCards as $card)
                            <div class="mini-stat"><div class="name">{{ $card['label'] }}</div><div class="amount">{{ $card['kind'] === 'money' ? $formatMoney($card['value']) : $formatCount($card['value']) }}</div></div>
                        @endforeach
                    </div>
                    @if($selectedAdjustmentReport->isEmpty())
                        <div class="empty-state">No stock adjustments matched the current filter in this selected period.</div>
                    @else
                        <div class="table-wrap">
                            <table class="data-table">
                                <thead><tr><th>Date</th><th>Direction</th><th>Reason</th><th>Medicine</th><th>Batch</th><th class="text-right">Qty</th><th class="text-right">Unit Cost</th><th class="text-right">Inventory Impact</th><th class="text-right">Loss Posted</th><th>Books Effect</th></tr></thead>
                                <tbody>
                                    @foreach($selectedAdjustmentReport as $adjustment)
                                        <tr><td>{{ optional($adjustment->adjustment_date)->format('d M Y H:i') }}</td><td>{{ $adjustment->direction_label }}</td><td>{{ $adjustment->reason_label }}</td><td>{{ $adjustment->product?->name ?? 'Unknown Product' }}</td><td>{{ $adjustment->batch?->batch_number ?? 'N/A' }}</td><td class="text-right">{{ number_format((float) $adjustment->quantity, 2) }}</td><td class="text-right">{{ $formatMoney($adjustment->unit_cost ?? 0) }}</td><td class="text-right">{{ ($adjustment->inventory_impact ?? 0) < 0 ? '-' : '' }}{{ $formatMoney(abs((float) ($adjustment->inventory_impact ?? 0))) }}</td><td class="text-right">{{ $formatMoney($adjustment->loss_amount ?? 0) }}</td><td>{{ $adjustment->books_effect ?? 'Inventory books updated.' }}</td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
                @break

            @case('stock_risk')
                <div class="two-up">
                    <div class="panel">
                        <h2>Out Of Stock Medicines</h2>
                        @if($outOfStockProducts->isEmpty())
                            <div class="empty-state">No active products are completely out of free stock right now.</div>
                        @else
                            <div class="table-wrap">
                                <table class="data-table">
                                    <thead><tr><th>Medicine</th><th class="text-right">Batches</th><th class="text-right">Available</th><th class="text-right">Reserved</th><th class="text-right">Free Stock</th></tr></thead>
                                    <tbody>@foreach($outOfStockProducts as $row)<tr><td>{{ $row['product_name'] }}</td><td class="text-right">{{ $formatCount($row['batch_count']) }}</td><td class="text-right">{{ number_format((float) $row['available_stock'], 2) }}</td><td class="text-right">{{ number_format((float) $row['reserved_stock'], 2) }}</td><td class="text-right">{{ number_format((float) $row['free_stock'], 2) }}</td></tr>@endforeach</tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                    <div class="panel">
                        <h2>Likely Money To Lose</h2>
                        @if($criticalMedicines->isEmpty())
                            <div class="empty-state">No active expiry-risk batches are currently holding free stock.</div>
                        @else
                            <div class="table-wrap">
                                <table class="data-table">
                                    <thead><tr><th>Medicine</th><th>Batch</th><th>Risk Window</th><th class="text-right">Free Stock</th><th class="text-right">Unit Cost</th><th class="text-right">Likely Loss</th></tr></thead>
                                    <tbody>@foreach($criticalMedicines as $row)<tr><td>{{ $row['product_name'] }}</td><td>{{ $row['batch_number'] }}</td><td>{{ $row['risk_label'] }}</td><td class="text-right">{{ number_format((float) $row['free_stock'], 2) }}</td><td class="text-right">{{ $formatMoney($row['purchase_price']) }}</td><td class="text-right">{{ $formatMoney($row['loss_value']) }}</td></tr>@endforeach</tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
                @break

            @case('damaged')
                <div class="panel">
                    <h2>Damaged Goods Report</h2>
                    @if($damagedGoods->isEmpty())
                        <div class="empty-state">No damaged-goods adjustments were recorded in this period.</div>
                    @else
                        <div class="table-wrap"><table class="data-table"><thead><tr><th>Date</th><th>Product</th><th>Batch</th><th class="text-right">Qty</th><th class="text-right">Unit Cost</th><th class="text-right">Loss Value</th><th>Adjusted By</th></tr></thead><tbody>@foreach($damagedGoods as $adjustment)@php $unitCost = (float) ($adjustment->batch?->purchase_price ?? 0); $lossValue = (float) $adjustment->quantity * $unitCost; @endphp<tr><td>{{ optional($adjustment->adjustment_date)->format('d M Y H:i') }}</td><td>{{ $adjustment->product?->name ?? 'Unknown Product' }}</td><td>{{ $adjustment->batch?->batch_number ?? 'N/A' }}</td><td class="text-right">{{ number_format((float) $adjustment->quantity, 2) }}</td><td class="text-right">{{ $formatMoney($unitCost) }}</td><td class="text-right">{{ $formatMoney($lossValue) }}</td><td>{{ $adjustment->adjustedByUser?->name ?? 'System' }}</td></tr>@endforeach</tbody></table></div>
                    @endif
                </div>
                @break

            @case('top_products')
                <div class="panel">
                    <h2>Top Selling Products</h2>
                    @if($topSellingProducts->isEmpty())
                        <div class="empty-state">No approved sale lines were recorded in this period.</div>
                    @else
                        <div class="table-wrap"><table class="data-table"><thead><tr><th>Product</th><th class="text-right">Qty Sold</th><th class="text-right">Revenue</th><th class="text-right">Gross Margin</th></tr></thead><tbody>@foreach($topSellingProducts as $row)<tr><td>{{ $row->name }}</td><td class="text-right">{{ number_format((float) $row->total_quantity, 2) }}</td><td class="text-right">{{ $formatMoney($row->total_revenue) }}</td><td class="text-right">{{ $formatMoney((float) $row->total_revenue - (float) $row->total_cost) }}</td></tr>@endforeach</tbody></table></div>
                    @endif
                </div>
                @break

            @case('receivables')
                <div class="panel">
                    <h2>Current Outstanding Receivables</h2>
                    @if($receivables->isEmpty())
                        <div class="empty-state">No unpaid customer balances are outstanding right now.</div>
                    @else
                        <div class="table-wrap"><table class="data-table"><thead><tr><th>Invoice</th><th>Customer</th><th>Date</th><th class="text-right">Total</th><th class="text-right">Paid</th><th class="text-right">Balance</th></tr></thead><tbody>@foreach($receivables as $sale)<tr><td>{{ $sale->invoice_number }}</td><td>{{ $sale->customer?->name ?? 'Walk-in Customer' }}</td><td>{{ optional($sale->sale_date)->format('d M Y') }}</td><td class="text-right">{{ $formatMoney($sale->total_amount) }}</td><td class="text-right">{{ $formatMoney($sale->amount_paid) }}</td><td class="text-right">{{ $formatMoney($sale->balance_due) }}</td></tr>@endforeach</tbody></table></div>
                    @endif
                </div>
                @break

            @case('payables')
                <div class="panel">
                    <h2>Current Outstanding Payables</h2>
                    @if($payables->isEmpty())
                        <div class="empty-state">No unpaid supplier balances are outstanding right now.</div>
                    @else
                        <div class="table-wrap"><table class="data-table"><thead><tr><th>Invoice</th><th>Supplier</th><th>Date</th><th>Entered By</th><th class="text-right">Total</th><th class="text-right">Paid</th><th class="text-right">Balance</th></tr></thead><tbody>@foreach($payables as $purchase)<tr><td>{{ $purchase->invoice_number }}</td><td>{{ $purchase->supplier?->name ?? 'Unknown Supplier' }}</td><td>{{ optional($purchase->purchase_date)->format('d M Y') }}</td><td>{{ $purchase->createdByUser?->name ?? 'System' }}</td><td class="text-right">{{ $formatMoney($purchase->total_amount) }}</td><td class="text-right">{{ $formatMoney($purchase->amount_paid) }}</td><td class="text-right">{{ $formatMoney($purchase->balance_due) }}</td></tr>@endforeach</tbody></table></div>
                    @endif
                </div>
                @break

            @default
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
                <div class="cards-grid" style="margin-top:20px;">
                    @foreach($headlineCards as $card)
                        <div class="summary-card tone-{{ $card['tone'] }}">
                            <div class="label">{{ $card['label'] }}</div>
                            <div class="value">{{ $card['kind'] === 'money' ? $formatMoney($card['value']) : $formatCount($card['value']) }}</div>
                            @if(!empty($card['meta']))<div class="meta">{{ $card['meta']['label'] }}: {{ $card['meta']['kind'] === 'money' ? $formatMoney($card['meta']['value']) : $formatCount($card['meta']['value']) }}</div>@endif
                        </div>
                    @endforeach
                </div>
                <div class="insight-grid" style="margin-top:20px;">
                    @foreach($inventoryRiskCards as $card)
                        <div class="summary-card tone-{{ $card['tone'] }}">
                            <div class="label">{{ $card['label'] }}</div>
                            <div class="value">{{ $card['kind'] === 'money' ? $formatMoney($card['value']) : $formatCount($card['value']) }}</div>
                            <div class="meta">{{ $card['subtitle'] }}</div>
                        </div>
                    @endforeach
                </div>
        @endswitch
        @endif
    </div>
</body>
</html>