<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proforma Invoices - KIM Rx</title>
    <style>
        * { box-sizing: border-box; }

        :root {
            --page-bg: #f5f7fb;
            --panel-bg: #ffffff;
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
            width: 100%;
            max-width: 100%;
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

        .panel-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
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

        .btn-primary { background: #0f766e; }
        .btn-secondary { background: #1f7a4f; }
        .btn-filter { background: #3949ab; }
        .btn-reset { background: #5c6bc0; }
        .btn-open { background: #1d4ed8; }

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
            background: linear-gradient(135deg, #f8fbff 0%, #f4fbf8 100%);
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
        }

        .table-wrap {
            overflow-x: auto;
            max-width: 100%;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            background: #fff;
        }
        table { width: 100%; border-collapse: collapse; min-width: 900px; }
        table th, table td { padding: 8px 7px; border-bottom: 1px solid #ddd; text-align: left; vertical-align: middle; font-size: 13px; }
        table th { background: #f8f8f8; font-size: 12px; white-space: nowrap; }

        .proforma-table .col-no { width: 44px; }
        .proforma-table .col-invoice { width: 120px; }
        .proforma-table .col-date { width: 88px; }
        .proforma-table .col-customer { width: 132px; }
        .proforma-table .col-dispenser { width: 118px; }
        .proforma-table .col-pricing { width: 84px; }
        .proforma-table .col-payment { width: 84px; }
        .proforma-table .col-total { width: 94px; }
        .proforma-table .col-impact { width: 124px; }
        .proforma-table .col-action { width: 70px; }

        .proforma-table .btn-open {
            padding: 7px 9px;
            font-size: 12px;
            white-space: nowrap;
        }

        .badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: bold;
        }

        .badge-proforma { background: #eef6ff; color: #1d4ed8; }
        .badge-retail { background: #e7f6ec; color: #1f7a4f; }
        .badge-wholesale { background: #eef2ff; color: #3949ab; }
        .muted { color: #667085; }

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
                        <h2 style="margin:0;">Proforma Invoices</h2>
                        <p style="margin:6px 0 0; color:#666;">Quoted invoices that do not reserve or deduct stock until converted to a real sale.</p>
                    </div>

                    <div class="panel-actions">
                        <a href="{{ route('sales.create') }}" class="btn btn-secondary">New Sale</a>
                        <a href="{{ route('sales.proforma.create') }}" class="btn btn-primary">New Proforma</a>
                    </div>
                </div>

                @if(session('success'))
                    <div class="alert-success">{{ session('success') }}</div>
                @endif

                <form method="GET" action="{{ route('sales.proforma') }}">
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
                            <a href="{{ route('sales.proforma', ['clear_filters' => 1]) }}" class="btn btn-reset">Reset</a>
                        </div>
                    </div>
                </form>

                <div class="table-wrap">
                    <table class="proforma-table">
                        <colgroup>
                            <col class="col-no">
                            <col class="col-invoice">
                            <col class="col-date">
                            <col class="col-customer">
                            <col class="col-dispenser">
                            <col class="col-pricing">
                            <col class="col-payment">
                            <col class="col-total">
                            <col class="col-impact">
                            <col class="col-action">
                        </colgroup>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Invoice Number</th>
                                <th>Sale Date</th>
                                <th>Customer</th>
                                <th>Dispenser</th>
                                <th>Pricing Type</th>
                                <th>Payment Type</th>
                                <th>Total Amount</th>
                                <th>Stock Impact</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($sales as $sale)
                                <tr>
                                    <td>{{ $loop->iteration + ($sales->currentPage() - 1) * $sales->perPage() }}</td>
                                    <td>{{ $sale->invoice_number }}</td>
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
                                    <td>{{ ucfirst($sale->payment_type) }}</td>
                                    <td>{{ number_format((float) $sale->total_amount, 2) }}</td>
                                    <td>
                                        <span class="badge badge-proforma">No stock change</span>
                                        <div class="muted" style="margin-top:4px;">Reserved stays untouched</div>
                                    </td>
                                    <td>
                                        <a href="{{ route('sales.show', array_merge(['sale' => $sale->id, 'return_to' => 'sales.proforma'], $filters, request()->filled('page') ? ['page' => request('page')] : [])) }}" class="btn btn-open">Open</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10">No proforma invoices found.</td>
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
