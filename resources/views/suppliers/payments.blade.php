<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Payments - KIM Rx</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; display: flex; background: #f5f7fb; }
        .content { flex: 1; padding: 20px; }
        .topbar, .panel { background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
        .summary-grid { display:grid; grid-template-columns: repeat(4, minmax(180px, 1fr)); gap:16px; margin-bottom:16px; }
        .summary-card { background:#f8fafc; border:1px solid #e5e7eb; border-radius:10px; padding:14px; }
        .summary-card h4 { margin:0 0 8px; font-size:13px; color:#666; }
        .summary-card p { margin:0; font-weight:bold; }
        .btn { padding:8px 12px; border-radius:6px; color:white; text-decoration:none; border:none; cursor:pointer; display:inline-block; }
        .btn-back { background:#3949ab; }
        .btn-secondary { background:#0f766e; }
        .btn-view { background:#2563eb; }
        .search-form { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:15px; }
        .search-form input { flex:1; min-width:260px; padding:10px; border:1px solid #ddd; border-radius:8px; }
        .table-wrap { overflow-x:auto; }
        table { width:100%; border-collapse:collapse; min-width:1380px; }
        table th, table td { padding:10px; border-bottom:1px solid #ddd; text-align:left; vertical-align:top; }
        table th { background:#f8f8f8; font-size:13px; }
        .muted { color:#666; font-size:13px; }
        .badge { display:inline-block; padding:6px 10px; border-radius:999px; font-size:12px; font-weight:bold; }
        .badge-entry { background:#eef4ff; color:#1d4ed8; }
        .badge-payment { background:#e7f6ec; color:#1f7a4f; }
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
            <h3>Supplier Payments</h3>
            <p>Client: {{ $clientName }} | Branch: {{ $branchName }}</p>
        </div>

        <div class="panel">
            <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; align-items:center; margin-bottom:16px;">
                <div>
                    <h2 style="margin:0;">Supplier Payment Ledger</h2>
                    <p class="muted" style="margin:6px 0 0;">Every payment remains linked to one supplier invoice, one payment date, and the staff member who recorded it.</p>
                </div>
                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <a href="{{ route('suppliers.payables') }}" class="btn btn-secondary">Payables</a>
                    <a href="{{ route('suppliers.index') }}" class="btn btn-back">Suppliers</a>
                </div>
            </div>

            <div class="summary-grid">
                <div class="summary-card">
                    <h4>Total Paid</h4>
                    <p>{{ number_format((float) $totalPaid, 2) }}</p>
                </div>
                <div class="summary-card">
                    <h4>Payment Records</h4>
                    <p>{{ $paymentCount }}</p>
                </div>
                <div class="summary-card">
                    <h4>Suppliers Paid</h4>
                    <p>{{ $supplierCount }}</p>
                </div>
                <div class="summary-card">
                    <h4>Invoices Paid On</h4>
                    <p>{{ $invoiceCount }}</p>
                </div>
            </div>

            <div class="summary-grid" style="margin-top:16px;">
                <div class="summary-card">
                    <h4>Manual Payments</h4>
                    <p>{{ $manualCount }}</p>
                </div>
                <div class="summary-card">
                    <h4>Invoice Entry Records</h4>
                    <p>{{ $invoiceEntryCount }}</p>
                </div>
                <div class="summary-card">
                    <h4>Search Results</h4>
                    <p>{{ $payments->total() }}</p>
                </div>
                <div class="summary-card">
                    <h4>Current Open Balance</h4>
                    <p>{{ number_format((float) $openBalance, 2) }}</p>
                </div>
            </div>

            <form method="GET" action="{{ route('suppliers.payments.index') }}" class="search-form">
                <input type="text" name="search" value="{{ $search }}" placeholder="Search by supplier, invoice number, method, or reference...">
                <button type="submit" class="btn btn-back">Search</button>
            </form>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Date Paid</th>
                            <th>Supplier</th>
                            <th>Invoice</th>
                            <th>Entry Type</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Reference</th>
                            <th>Paid By</th>
                            <th>Notes</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($payments as $payment)
                            <tr>
                                <td>{{ $payment->payment_date?->format('d M Y H:i') }}</td>
                                <td>{{ $payment->supplier?->name ?? 'N/A' }}</td>
                                <td>{{ $payment->purchase?->invoice_number ?? 'N/A' }}</td>
                                <td>
                                    <span class="badge {{ $payment->source === 'invoice_entry' ? 'badge-entry' : 'badge-payment' }}">
                                        {{ $payment->source_label }}
                                    </span>
                                </td>
                                <td>{{ number_format((float) $payment->amount, 2) }}</td>
                                <td>{{ $paymentMethods[$payment->payment_method] ?? ucwords(str_replace('_', ' ', $payment->payment_method)) }}</td>
                                <td>{{ $payment->reference_number ?: 'N/A' }}</td>
                                <td>{{ $payment->paidByUser?->name ?? 'System' }}</td>
                                <td>{{ $payment->notes ?: 'No notes' }}</td>
                                <td>
                                    @if($payment->supplier_id)
                                        <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                            <a href="{{ route('suppliers.show', $payment->supplier_id) }}" class="btn btn-secondary">Statement</a>
                                            @if($payment->purchase_id)
                                                <a href="{{ route('purchases.show', $payment->purchase_id) }}" class="btn btn-view">Purchase</a>
                                            @endif
                                        </div>
                                    @else
                                        <span class="muted">No action</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10">No supplier payments found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div style="margin-top: 15px;">
                {{ $payments->withQueryString()->links() }}
            </div>
        </div>
    </div>
</body>
</html>
