<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receivables - KIM Rx</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; display: flex; background: #f5f7fb; }
        .content { flex: 1; padding: 20px; }
        .topbar, .panel { background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
        .summary-grid { display:grid; grid-template-columns: repeat(3, minmax(180px, 1fr)); gap:16px; margin-bottom:16px; }
        .summary-card { background:#f8fafc; border:1px solid #e5e7eb; border-radius:10px; padding:14px; }
        .summary-card h4 { margin:0 0 8px; font-size:13px; color:#666; }
        .summary-card p { margin:0; font-weight:bold; }
        .btn { padding:8px 12px; border-radius:6px; color:white; text-decoration:none; border:none; cursor:pointer; display:inline-block; }
        .btn-back { background:#3949ab; }
        .btn-secondary { background:#0f766e; }
        .btn-pay { background:#1f7a4f; }
        .search-form { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:15px; }
        .search-form input { flex:1; min-width:260px; padding:10px; border:1px solid #ddd; border-radius:8px; }
        .table-wrap { overflow-x:auto; }
        table { width:100%; border-collapse:collapse; min-width:1300px; }
        table th, table td { padding:10px; border-bottom:1px solid #ddd; text-align:left; vertical-align:top; }
        table th { background:#f8f8f8; font-size:13px; }
        .muted { color:#666; font-size:13px; }
        .badge { display:inline-block; padding:6px 10px; border-radius:999px; font-size:12px; font-weight:bold; background:#fff4db; color:#9a6700; }
        @media (max-width: 900px) {
            body { flex-direction: column; }
            .summary-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    @include('layouts.sidebar')

    <div class="content" id="mainContent">
        <div class="topbar">
            <h3>Customer Receivables</h3>
            <p>Client: {{ $clientName }} | Branch: {{ $branchName }}</p>
        </div>

        <div class="panel">
            <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; align-items:center; margin-bottom:16px;">
                <div>
                    <h2 style="margin:0;">Outstanding Customer Invoices</h2>
                    <p class="muted" style="margin:6px 0 0;">Search by customer, product, invoice number, or receipt number.</p>
                </div>
                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <a href="{{ route('customers.index') }}" class="btn btn-back">Customers</a>
                    <a href="{{ route('customers.collections.index') }}" class="btn btn-secondary">Collections</a>
                </div>
            </div>

            <div class="summary-grid">
                <div class="summary-card">
                    <h4>Outstanding Amount</h4>
                    <p>{{ number_format((float) $outstandingAmount, 2) }}</p>
                </div>
                <div class="summary-card">
                    <h4>Open Invoices</h4>
                    <p>{{ $invoiceCount }}</p>
                </div>
                <div class="summary-card">
                    <h4>Customers Owing</h4>
                    <p>{{ $customerCount }}</p>
                </div>
            </div>

            <form method="GET" action="{{ route('customers.receivables') }}" class="search-form">
                <input type="text" name="search" value="{{ $search }}" placeholder="Search by customer, invoice, receipt, or product...">
                <button type="submit" class="btn btn-back">Search</button>
            </form>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Invoice</th>
                            <th>Sale Date</th>
                            <th>Items Taken</th>
                            <th>Total</th>
                            <th>Collected</th>
                            <th>Balance Due</th>
                            <th>Last Payment</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($receivables as $sale)
                            @php
                                $itemsSummary = $sale->items->map(function ($item) {
                                    return ($item->product?->name ?? 'Unknown Product') . ' x' . number_format((float) $item->quantity, 2);
                                });
                                $lastPayment = $sale->payments->sortByDesc('payment_date')->first();
                            @endphp
                            <tr>
                                <td>
                                    <strong>{{ $sale->customer?->name ?? 'N/A' }}</strong><br>
                                    <span class="muted">{{ $sale->customer?->phone ?? 'No phone' }}</span>
                                </td>
                                <td>
                                    <strong>{{ $sale->invoice_number ?? 'N/A' }}</strong><br>
                                    <span class="muted">Receipt: {{ $sale->receipt_number ?? 'Not issued' }}</span>
                                </td>
                                <td>{{ optional($sale->sale_date)->format('d M Y H:i') }}</td>
                                <td>{{ $itemsSummary->implode(', ') }}</td>
                                <td>{{ number_format((float) $sale->total_amount, 2) }}</td>
                                <td>{{ number_format((float) $sale->amount_paid, 2) }}</td>
                                <td>
                                    <span class="badge">{{ number_format((float) $sale->balance_due, 2) }}</span>
                                </td>
                                <td>
                                    @if($lastPayment)
                                        {{ $lastPayment->payment_date?->format('d M Y H:i') }}<br>
                                        <span class="muted">{{ $lastPayment->receivedByUser?->name ?? 'System' }}</span>
                                    @else
                                        <span class="muted">No payment yet</span>
                                    @endif
                                </td>
                                <td>
                                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                        <a href="{{ route('customers.show', $sale->customer_id) }}" class="btn btn-secondary">Statement</a>
                                        <a href="{{ route('customers.collections.create', $sale->id) }}" class="btn btn-pay">Receive Payment</a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9">No outstanding customer invoices found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div style="margin-top: 15px;">
                {{ $receivables->withQueryString()->links() }}
            </div>
        </div>
    </div>
</body>
</html>
