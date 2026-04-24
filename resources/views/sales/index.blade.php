<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales - KIM Rx</title>
    <style>
        * { box-sizing: border-box; }

        :root {
            --sidebar-start: #1f7a4f;
            --sidebar-end: #6a1b9a;
            --sidebar-card: rgba(255,255,255,0.08);
            --sidebar-card-hover: rgba(255,255,255,0.16);
            --sidebar-card-active: rgba(255,255,255,0.22);
            --page-bg: #f5f7fb;
            --panel-bg: #ffffff;
            --text-main: #222;
            --text-soft: #666;
            --success: #1f7a4f;
            --danger: #b42318;
            --warning: #a56a00;
            --info: #3949ab;
            --shadow-soft: 0 8px 24px rgba(0,0,0,0.06);
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: var(--page-bg);
        }

        .layout {
            display: flex;
            min-height: 100vh;
        }

        .content {
            flex: 1;
            margin-left: 260px;
            padding: 20px;
            transition: margin-left 0.3s ease;
        }

        .content.expanded { margin-left: 80px; }

        .topbar { background: white; padding: 15px; border-radius: 10px; margin-bottom: 20px; box-shadow: var(--shadow-soft); }
        .panel { background: white; padding: 20px; border-radius: 10px; box-shadow: var(--shadow-soft); }

        .panel-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }

        .btn {
            padding: 8px 12px;
            border-radius: 8px;
            color: white;
            text-decoration: none;
            border: none;
            cursor: pointer;
            display: inline-block;
        }

        .btn-add { background: green; }
        .btn-proforma { background: #0f766e; }
        .btn-filter { background: #0f766e; }
        .btn-reset { background: #5c6bc0; }
        .btn-open { background: #3949ab; }

        .alert-success {
            background: #e7f6ec;
            color: #1f7a4f;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .filters {
            display: grid;
            grid-template-columns: repeat(4, minmax(180px, 1fr));
            gap: 14px;
            align-items: end;
            margin-bottom: 18px;
            padding: 16px;
            border-radius: 12px;
            background: linear-gradient(135deg, #f8fbff 0%, #f3f8f5 100%);
            border: 1px solid #dfe7f3;
        }

        .field label {
            display: block;
            margin-bottom: 6px;
            font-size: 12px;
            font-weight: 700;
            color: #455a64;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .field input,
        .field select {
            width: 100%;
            padding: 11px 12px;
            border: 1px solid #cfd8dc;
            border-radius: 10px;
            font-size: 14px;
            background: #fff;
        }

        .filter-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-start;
        }

        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 1360px; }
        table th, table td { padding: 10px; border-bottom: 1px solid #ddd; text-align: left; }
        table th { background: #f8f8f8; font-size: 13px; }

        .badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .badge-retail { background: #e7f6ec; color: #1f7a4f; }
        .badge-wholesale { background: #eef2ff; color: #3949ab; }
        .badge-paid { background: #e7f6ec; color: #1f7a4f; }
        .badge-partial { background: #fff4db; color: #a56a00; }
        .badge-pending { background: #fff4db; color: #a56a00; }
        .badge-approved { background: #e7f6ec; color: #1f7a4f; }
        .badge-cancelled { background: #fdecea; color: #b42318; }
        .badge-proforma { background: #eef6ff; color: #1d4ed8; }

        @media (max-width: 900px) {
            .filters { grid-template-columns: 1fr; }

            .content,
            .content.expanded {
                margin-left: 0;
            }

            .layout {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="layout">
        @include('layouts.sidebar')

        <div class="content" id="mainContent">
            <div class="topbar">
                <h3>Welcome, {{ $user->name }}</h3>
                <p>Client: {{ $clientName }} | Branch: {{ $branchName }}</p>
            </div>

            <div class="panel">
                <div class="panel-head">
                    <div>
                        <h2 style="margin:0;">Sales</h2>
                        <p style="margin:6px 0 0; color:#666;">Retail, wholesale, and proforma sales documents</p>
                    </div>

                    <div style="display:flex; gap:10px; flex-wrap:wrap;">
                        <a href="{{ route('sales.create') }}" class="btn btn-add">New Sale</a>
                        <a href="{{ route('sales.proforma.create') }}" class="btn btn-proforma">New Proforma</a>
                    </div>
                </div>

                @if(session('success'))
                    <div class="alert-success">
                        {{ session('success') }}
                    </div>
                @endif

                <form method="GET" action="{{ route('sales.index') }}">
                    <div class="filters">
                        <div class="field">
                            <label for="date_from">Date From</label>
                            <input type="date" id="date_from" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
                        </div>

                        <div class="field">
                            <label for="date_to">Date To</label>
                            <input type="date" id="date_to" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
                        </div>

                        <div class="field">
                            <label for="served_by">Dispenser</label>
                            <select id="served_by" name="served_by">
                                <option value="">All Dispensers</option>
                                @foreach($dispensers as $dispenser)
                                    <option value="{{ $dispenser->id }}" @selected((string) ($filters['served_by'] ?? '') === (string) $dispenser->id)>
                                        {{ $dispenser->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="filter-actions">
                            <button type="submit" class="btn btn-filter">Apply Filters</button>
                            <a href="{{ route('sales.index', ['clear_filters' => 1]) }}" class="btn btn-reset">Reset</a>
                        </div>
                    </div>
                </form>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Invoice Number</th>
                                <th>Receipt</th>
                                <th>Sale Date</th>
                                <th>Customer</th>
                                <th>Dispenser</th>
                                <th>Sale Type</th>
                                <th>Status</th>
                                <th>Total Amount</th>
                                <th>Amount Paid</th>
                                <th>Balance Due</th>
                                <th>Payment Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($sales as $sale)
                                <tr>
                                    <td>{{ $loop->iteration + ($sales->currentPage() - 1) * $sales->perPage() }}</td>
                                    <td>{{ $sale->invoice_number }}</td>
                                    <td>{{ $sale->receipt_number ?? 'N/A' }}</td>
                                    <td>{{ optional($sale->sale_date)->format('d M Y') }}</td>
                                    <td>{{ $sale->customer?->name ?? 'Walk-in / N/A' }}</td>
                                    <td>{{ $sale->servedByUser?->name ?? 'N/A' }}</td>
                                    <td>
                                        @if($sale->sale_type === 'wholesale')
                                            <span class="badge badge-wholesale">Wholesale</span>
                                        @else
                                            <span class="badge badge-retail">Retail</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($sale->status === 'approved')
                                            <span class="badge badge-approved">Approved</span>
                                        @elseif($sale->status === 'proforma')
                                            <span class="badge badge-proforma">Proforma</span>
                                        @elseif($sale->status === 'cancelled')
                                            <span class="badge badge-cancelled">Cancelled</span>
                                        @else
                                            <span class="badge badge-pending">Pending</span>
                                        @endif
                                    </td>
                                    <td>{{ number_format((float) $sale->total_amount, 2) }}</td>
                                    <td>{{ number_format((float) $sale->amount_paid, 2) }}</td>
                                    <td>{{ number_format((float) $sale->balance_due, 2) }}</td>
                                    <td>
                                        @if($sale->status === 'proforma')
                                            <span class="badge badge-proforma">Proforma</span>
                                        @elseif((float) $sale->balance_due > 0)
                                            <span class="badge badge-partial">Partial</span>
                                        @else
                                            <span class="badge badge-paid">Paid</span>
                                        @endif
                                    </td>
                                    <td>
                                        <a href="{{ route('sales.show', array_merge(['sale' => $sale->id, 'return_to' => 'sales.index'], $filters, request()->filled('page') ? ['page' => request('page')] : [])) }}" class="btn btn-open">Open</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="13">No sales found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div style="margin-top:15px;">
                    {{ $sales->links() }}
                </div>
            </div>
        </div>
    </div>

</body>
</html>
