<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KIM Rx</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <style>
        * { box-sizing: border-box; }

        :root {
            --desk-bg: #f4f7fb;
            --desk-panel: #ffffff;
            --desk-soft: #f7f9fc;
            --desk-line: #dfe6ee;
            --desk-text: #172033;
            --desk-muted: #667085;
            --desk-green: #16754a;
            --desk-green-soft: #e9f8ef;
            --desk-blue: #2458a6;
            --desk-blue-soft: #edf4ff;
            --desk-amber: #9a6100;
            --desk-amber-soft: #fff5df;
            --desk-violet: #6d3fb5;
            --desk-violet-soft: #f4efff;
            --desk-shadow: 0 10px 28px rgba(21, 36, 58, 0.07);
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: Arial, sans-serif;
            color: var(--desk-text);
            background: var(--desk-bg);
        }

        .layout { display: flex; min-height: 100vh; }

        .content {
            flex: 1;
            width: calc(100% - 260px);
            margin-left: 260px;
            padding: 22px;
            transition: margin-left 0.3s ease, width 0.3s ease;
        }

        .content.expanded {
            width: calc(100% - 80px);
            margin-left: 80px;
        }

        .desk-topbar,
        .desk-panel,
        .stat-card {
            border: 1px solid var(--desk-line);
            border-radius: 8px;
            background: var(--desk-panel);
            box-shadow: var(--desk-shadow);
        }

        .desk-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            padding: 18px 20px;
            margin-bottom: 16px;
        }

        .desk-eyebrow {
            margin: 0 0 5px;
            color: var(--desk-green);
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .desk-topbar h1 {
            margin: 0;
            font-size: 25px;
            line-height: 1.15;
        }

        .desk-topbar p {
            margin: 6px 0 0;
            color: var(--desk-muted);
            font-size: 13px;
        }

        .desk-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 9px;
            flex-wrap: wrap;
        }

        .btn {
            min-height: 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 9px 13px;
            border: 1px solid var(--desk-line);
            border-radius: 7px;
            color: var(--desk-text);
            background: var(--desk-panel);
            font-size: 13px;
            font-weight: 800;
            text-decoration: none;
        }

        .btn-primary {
            border-color: var(--desk-green);
            color: #ffffff;
            background: var(--desk-green);
        }

        .signed-in-chip {
            min-height: 38px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 10px;
            border: 1px solid var(--desk-line);
            border-radius: 7px;
            color: var(--desk-muted);
            background: var(--desk-soft);
            font-size: 12px;
        }

        .signed-in-chip strong { color: var(--desk-text); }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 16px;
        }

        .stat-card {
            min-height: 116px;
            padding: 15px;
            border-top-width: 4px;
        }

        .stat-card.tone-amber { border-top-color: var(--desk-amber); background: linear-gradient(180deg, var(--desk-amber-soft), var(--desk-panel) 52%); }
        .stat-card.tone-green { border-top-color: var(--desk-green); background: linear-gradient(180deg, var(--desk-green-soft), var(--desk-panel) 52%); }
        .stat-card.tone-blue { border-top-color: var(--desk-blue); background: linear-gradient(180deg, var(--desk-blue-soft), var(--desk-panel) 52%); }
        .stat-card.tone-violet { border-top-color: var(--desk-violet); background: linear-gradient(180deg, var(--desk-violet-soft), var(--desk-panel) 52%); }

        .stat-label {
            color: var(--desk-muted);
            font-size: 12px;
            font-weight: 800;
        }

        .stat-value {
            margin-top: 13px;
            font-size: 27px;
            font-weight: 900;
            overflow-wrap: anywhere;
        }

        .workspace-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.55fr) minmax(280px, 0.65fr);
            gap: 16px;
        }

        .desk-panel { padding: 17px; }

        .panel-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 14px;
        }

        .panel-head h2 {
            margin: 0;
            font-size: 18px;
        }

        .panel-head p {
            margin: 5px 0 0;
            color: var(--desk-muted);
            font-size: 12px;
        }

        .table-wrap {
            max-width: 100%;
            overflow-x: auto;
            border: 1px solid var(--desk-line);
            border-radius: 7px;
        }

        table { width: 100%; border-collapse: collapse; }

        th,
        td {
            padding: 11px 12px;
            border-bottom: 1px solid var(--desk-line);
            text-align: left;
            vertical-align: middle;
            font-size: 12.5px;
            white-space: nowrap;
        }

        th {
            color: var(--desk-muted);
            background: var(--desk-soft);
            font-size: 11px;
            text-transform: uppercase;
        }

        tr:last-child td { border-bottom: none; }

        .reference-link {
            color: var(--desk-blue);
            font-weight: 800;
            text-decoration: none;
        }

        .status {
            display: inline-flex;
            padding: 5px 8px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 800;
        }

        .status-approved { color: var(--desk-green); background: var(--desk-green-soft); }
        .status-pending { color: var(--desk-amber); background: var(--desk-amber-soft); }
        .status-proforma { color: var(--desk-blue); background: var(--desk-blue-soft); }

        .empty-state {
            padding: 28px 14px;
            color: var(--desk-muted);
            text-align: center;
        }

        .side-stack { display: grid; gap: 16px; align-content: start; }

        .quick-links { display: grid; gap: 8px; }

        .quick-link {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            min-height: 44px;
            padding: 10px 12px;
            border: 1px solid var(--desk-line);
            border-radius: 7px;
            color: var(--desk-text);
            background: var(--desk-soft);
            font-size: 13px;
            font-weight: 800;
            text-decoration: none;
        }

        .quick-link span { color: var(--desk-muted); font-size: 15px; }

        .type-list { display: grid; gap: 10px; }

        .type-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 11px 12px;
            border: 1px solid var(--desk-line);
            border-radius: 7px;
            background: var(--desk-soft);
            font-size: 13px;
        }

        .type-row strong { font-size: 18px; }

        html[data-theme="dark"] {
            --desk-bg: #070c12;
            --desk-panel: #111923;
            --desk-soft: #17212d;
            --desk-line: #2b3948;
            --desk-text: #e8eef5;
            --desk-muted: #a8b4c2;
            --desk-green-soft: #10271f;
            --desk-blue-soft: #132338;
            --desk-amber-soft: #2b2110;
            --desk-violet-soft: #241a36;
            --desk-shadow: 0 12px 30px rgba(0, 0, 0, 0.28);
        }

        html[data-theme="dark"] .stat-card.tone-amber,
        html[data-theme="dark"] .stat-card.tone-green,
        html[data-theme="dark"] .stat-card.tone-blue,
        html[data-theme="dark"] .stat-card.tone-violet {
            background: var(--desk-panel);
        }

        @media (max-width: 1100px) {
            .stats-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .workspace-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 900px) {
            .content,
            .content.expanded {
                width: 100%;
                margin-left: 0;
                padding: 72px 12px 18px;
            }

            .desk-topbar { align-items: flex-start; flex-direction: column; }
            .desk-actions { justify-content: flex-start; }
        }

        @media (max-width: 560px) {
            .stats-grid { grid-template-columns: 1fr; }
            .desk-actions, .btn { width: 100%; }
            .signed-in-chip { width: 100%; justify-content: center; }
            .desk-topbar h1 { font-size: 22px; }
        }
    </style>
</head>
<body>
    <div class="layout">
        @include('layouts.sidebar')

        <main class="content" id="mainContent">
            <section class="desk-topbar">
                <div>
                    <p class="desk-eyebrow">My Dispensing Desk</p>
                    <h1>{{ $user->name }}</h1>
                    <p>{{ $branchName }} | {{ $todayLabel }}</p>
                </div>

                <div class="desk-actions">
                    <span class="signed-in-chip">Signed in as <strong>{{ $user->name }}</strong></span>
                    @if ($user->hasPermission('sales.view'))
                        <a class="btn" href="{{ route('sales.index', ['served_by' => $user->id]) }}">My Sales</a>
                    @endif
                    @if ($user->hasPermission('sales.create'))
                        <a class="btn btn-primary" href="{{ route('sales.create') }}">New Sale</a>
                    @endif
                </div>
            </section>

            <section class="stats-grid" aria-label="My dispensing summary">
                @foreach ($personalStats as $stat)
                    <article class="stat-card tone-{{ $stat['tone'] }}">
                        <div class="stat-label">{{ $stat['label'] }}</div>
                        <div class="stat-value">
                            @if ($stat['format'] === 'money')
                                {{ number_format((float) $stat['value'], 2) }}
                            @elseif ($stat['format'] === 'quantity')
                                {{ number_format((float) $stat['value'], 2) }}
                            @else
                                {{ number_format((int) $stat['value']) }}
                            @endif
                        </div>
                    </article>
                @endforeach
            </section>

            <section class="workspace-grid">
                <article class="desk-panel">
                    <div class="panel-head">
                        <div>
                            <h2>My Recent Sales</h2>
                            <p>Only sales entered using your account are shown here.</p>
                        </div>
                    </div>

                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Invoice / Receipt</th>
                                    <th>Customer</th>
                                    <th>Type</th>
                                    <th>Items</th>
                                    <th>Status</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($recentSales as $sale)
                                    <tr>
                                        <td>{{ $sale->created_at?->format('d M, H:i') ?? 'N/A' }}</td>
                                        <td>
                                            <a
                                                class="reference-link"
                                                href="{{ route('sales.show', ['sale' => $sale->id, 'return_to' => 'sales.index', 'served_by' => $user->id]) }}"
                                            >
                                                {{ $sale->receipt_number ?: $sale->invoice_number }}
                                            </a>
                                        </td>
                                        <td>{{ $sale->customer?->name ?? 'Walk-in Customer' }}</td>
                                        <td>{{ ucfirst((string) $sale->sale_type) }}</td>
                                        <td>{{ number_format((int) $sale->items_count) }}</td>
                                        <td>
                                            <span class="status status-{{ $sale->status }}">{{ ucfirst((string) $sale->status) }}</span>
                                        </td>
                                        <td>{{ number_format((float) $sale->total_amount, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="empty-state">You have not entered any sales yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </article>

                <aside class="side-stack">
                    <section class="desk-panel">
                        <div class="panel-head">
                            <div>
                                <h2>Continue Work</h2>
                                <p>Open your common dispensing screens.</p>
                            </div>
                        </div>

                        <div class="quick-links">
                            @if ($user->hasPermission('sales.create'))
                                <a class="quick-link" href="{{ route('sales.create') }}">Start New Sale <span>&rsaquo;</span></a>
                            @endif
                            @if ($user->hasPermission('sales.view_pending'))
                                <a class="quick-link" href="{{ route('sales.pending', ['served_by' => $user->id]) }}">My Pending Sales <span>&rsaquo;</span></a>
                            @endif
                            @if ($user->hasPermission('sales.view_approved'))
                                <a class="quick-link" href="{{ route('sales.approved', ['served_by' => $user->id]) }}">My Approved Sales <span>&rsaquo;</span></a>
                            @endif
                            @if ($user->hasPermission('products.view'))
                                <a class="quick-link" href="{{ route('products.index') }}">Find a Medicine <span>&rsaquo;</span></a>
                            @endif
                        </div>
                    </section>

                    <section class="desk-panel">
                        <div class="panel-head">
                            <div>
                                <h2>Today by Sale Type</h2>
                                <p>Your approved sales for {{ $todayLabel }}.</p>
                            </div>
                        </div>

                        <div class="type-list">
                            @foreach ($saleTypes as $saleType)
                                <div class="type-row">
                                    <span>{{ $saleType['label'] }}</span>
                                    <strong>{{ number_format((int) $saleType['count']) }}</strong>
                                </div>
                            @endforeach
                        </div>
                    </section>
                </aside>
            </section>
        </main>
    </div>
</body>
</html>
