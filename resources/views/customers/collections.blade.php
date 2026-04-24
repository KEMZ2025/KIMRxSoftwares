<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Collections - KIM Rx</title>
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
        .btn-reverse { background:#b42318; }
        .search-form { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:15px; }
        .search-form input { flex:1; min-width:260px; padding:10px; border:1px solid #ddd; border-radius:8px; }
        .table-wrap { overflow-x:auto; }
        table { width:100%; border-collapse:collapse; min-width:1380px; }
        table th, table td { padding:10px; border-bottom:1px solid #ddd; text-align:left; vertical-align:top; }
        table th { background:#f8f8f8; font-size:13px; }
        .muted { color:#666; font-size:13px; }
        .badge { display:inline-block; padding:6px 10px; border-radius:999px; font-size:12px; font-weight:bold; }
        .badge-reversal { background:#fdecea; color:#b42318; }
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
            <h3>Collections</h3>
            <p>Client: {{ $clientName }} | Branch: {{ $branchName }}</p>
        </div>

        <div class="panel">
            <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; align-items:center; margin-bottom:16px;">
                <div>
                    <h2 style="margin:0;">Received Customer Payments</h2>
                    <p class="muted" style="margin:6px 0 0;">Every payment stays linked to one invoice, one collector, and one date received.</p>
                </div>
                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <a href="{{ route('customers.receivables') }}" class="btn btn-secondary">Receivables</a>
                    <a href="{{ route('customers.index') }}" class="btn btn-back">Customers</a>
                </div>
            </div>

            <div class="summary-grid">
                <div class="summary-card">
                    <h4>Gross Received</h4>
                    <p>{{ number_format((float) $grossReceived, 2) }}</p>
                </div>
                <div class="summary-card">
                    <h4>Payments Logged</h4>
                    <p>{{ $collectionCount }}</p>
                </div>
                <div class="summary-card">
                    <h4>Reversed Amount</h4>
                    <p>{{ number_format((float) $totalReversed, 2) }}</p>
                </div>
                <div class="summary-card">
                    <h4>Net Collected</h4>
                    <p>{{ number_format((float) $netCollected, 2) }}</p>
                </div>
            </div>

            <div class="summary-grid" style="margin-top:16px;">
                <div class="summary-card">
                    <h4>Customers Paid</h4>
                    <p>{{ $customerCount }}</p>
                </div>
                <div class="summary-card">
                    <h4>Reversals Logged</h4>
                    <p>{{ $reversalCount }}</p>
                </div>
                <div class="summary-card">
                    <h4>Search Entries</h4>
                    <p>{{ $collections->total() }}</p>
                </div>
                <div class="summary-card">
                    <h4>Ledger Entries</h4>
                    <p>{{ $collectionCount + $reversalCount }}</p>
                </div>
            </div>

            <form method="GET" action="{{ route('customers.collections.index') }}" class="search-form">
                <input type="text" name="search" value="{{ $search }}" placeholder="Search by customer, invoice number, method, or reference...">
                <button type="submit" class="btn btn-back">Search</button>
            </form>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Date Received</th>
                            <th>Type</th>
                            <th>Customer</th>
                            <th>Invoice</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Reference</th>
                            <th>Received By</th>
                            <th>Reversible Left</th>
                            <th>Notes</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($collections as $payment)
                            <tr>
                                <td>{{ $payment->payment_date?->format('d M Y H:i') }}</td>
                                <td>
                                    <span class="badge {{ $payment->is_reversal ? 'badge-reversal' : 'badge-payment' }}">
                                        {{ $payment->entry_type_label }}
                                    </span><br>
                                    <span class="muted">{{ $payment->reversal_status_label }}</span>
                                </td>
                                <td>{{ $payment->customer?->name ?? 'N/A' }}</td>
                                <td>
                                    <strong>{{ $payment->sale?->invoice_number ?? 'N/A' }}</strong><br>
                                    <span class="muted">Receipt: {{ $payment->sale?->receipt_number ?? 'Not issued' }}</span>
                                </td>
                                <td>{{ number_format((float) $payment->display_amount, 2) }}</td>
                                <td>{{ $paymentMethods[$payment->payment_method] ?? ucwords(str_replace('_', ' ', $payment->payment_method)) }}</td>
                                <td>{{ $payment->reference_number ?: 'N/A' }}</td>
                                <td>{{ $payment->receivedByUser?->name ?? 'System' }}</td>
                                <td>
                                    @if($payment->is_reversal)
                                        <span class="muted">N/A</span>
                                    @else
                                        {{ number_format((float) $payment->available_to_reverse, 2) }}
                                    @endif
                                </td>
                                <td>{{ $payment->notes ?: 'No notes' }}</td>
                                <td>
                                    @if(!$payment->is_reversal && (float) $payment->available_to_reverse > 0)
                                        <a href="{{ route('customers.collections.reverse.create', $payment->id) }}" class="btn btn-reverse">Reverse</a>
                                    @else
                                        <span class="muted">No action</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11">No customer payments found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div style="margin-top: 15px;">
                {{ $collections->withQueryString()->links() }}
            </div>
        </div>
    </div>
</body>
</html>
