<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Statement - KIM Rx</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; display: flex; background: #f5f7fb; }
        .content { flex: 1; padding: 20px; }
        .topbar, .panel { background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
        .panel-head { display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:16px; }
        .summary-grid { display:grid; grid-template-columns: repeat(4, minmax(180px, 1fr)); gap:16px; }
        .summary-card { background:#f8fafc; border:1px solid #e5e7eb; border-radius:10px; padding:14px; }
        .summary-card h4 { margin:0 0 8px; font-size:13px; color:#666; }
        .summary-card p { margin:0; font-weight:bold; }
        .btn { padding:8px 12px; border-radius:6px; color:white; text-decoration:none; border:none; cursor:pointer; display:inline-block; }
        .btn-back { background:#3949ab; }
        .btn-edit { background:#ff9800; }
        .btn-pay { background:#1f7a4f; }
        .btn-secondary { background:#0f766e; }
        .search-form { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:15px; }
        .search-form input { flex:1; min-width:260px; padding:10px; border:1px solid #ddd; border-radius:8px; }
        .table-wrap { overflow-x:auto; }
        table { width:100%; border-collapse:collapse; min-width:1250px; }
        table th, table td { padding:10px; border-bottom:1px solid #ddd; text-align:left; vertical-align:top; }
        table th { background:#f8f8f8; font-size:13px; }
        .muted { color:#666; font-size:13px; }
        .badge { display:inline-block; padding:6px 10px; border-radius:999px; font-size:12px; font-weight:bold; }
        .badge-due { background:#fff4db; color:#9a6700; }
        .badge-clear { background:#e7f6ec; color:#1f7a4f; }
        .badge-partial { background:#eef4ff; color:#1d4ed8; }
        .badge-reversal { background:#fdecea; color:#b42318; }
        .badge-payment { background:#e7f6ec; color:#1f7a4f; }
        .alert-success { background:#e7f6ec; color:#1f7a4f; padding:12px; border-radius:8px; margin-bottom:15px; }
        .btn-reverse { background:#b42318; }
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
            <h3>{{ $customer->name }}</h3>
            <p>Client: {{ $clientName }} | Branch: {{ $branchName }}</p>
        </div>

        <div class="panel">
            <div class="panel-head">
                <div>
                    <h2 style="margin:0;">Customer Statement</h2>
                    <p class="muted" style="margin:6px 0 0;">Track invoices, items taken, payments received, and who collected each payment.</p>
                </div>
                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <a href="{{ route('customers.index') }}" class="btn btn-back">Back to Customers</a>
                    <a href="{{ route('customers.edit', $customer->id) }}" class="btn btn-edit">Edit Customer</a>
                    <a href="{{ route('customers.receivables') }}" class="btn btn-secondary">Receivables</a>
                </div>
            </div>

            @if(session('success'))
                <div class="alert-success">{{ session('success') }}</div>
            @endif

            <div class="summary-grid">
                <div class="summary-card">
                    <h4>Credit Limit</h4>
                    <p>{{ number_format((float) $customer->credit_limit, 2) }}</p>
                </div>
                <div class="summary-card">
                    <h4>Outstanding Balance</h4>
                    <p>{{ number_format((float) $outstandingBalance, 2) }}</p>
                </div>
                <div class="summary-card">
                    <h4>Total Invoiced</h4>
                    <p>{{ number_format((float) $totalInvoiced, 2) }}</p>
                </div>
                <div class="summary-card">
                    <h4>Total Collected</h4>
                    <p>{{ number_format((float) $totalCollected, 2) }}</p>
                </div>
            </div>

            <div class="summary-grid" style="margin-top:16px;">
                <div class="summary-card">
                    <h4>Invoices</h4>
                    <p>{{ $invoiceCount }}</p>
                </div>
                <div class="summary-card">
                    <h4>Outstanding Invoices</h4>
                    <p>{{ $outstandingInvoiceCount }}</p>
                </div>
                <div class="summary-card">
                    <h4>Remaining Credit</h4>
                    <p>{{ number_format((float) $remainingCredit, 2) }}</p>
                </div>
                <div class="summary-card">
                    <h4>Contact</h4>
                    <p>{{ $customer->phone ?: 'N/A' }}</p>
                    <span class="muted">{{ $customer->email ?: 'No email' }}</span>
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-head">
                <div>
                    <h2 style="margin:0;">Invoice History</h2>
                    <p class="muted" style="margin:6px 0 0;">Search by invoice number, receipt number, or product taken.</p>
                </div>
            </div>

            <form method="GET" action="{{ route('customers.show', $customer->id) }}" class="search-form">
                <input type="text" name="search" value="{{ $search }}" placeholder="Search invoices, receipt numbers, or products...">
                <button type="submit" class="btn btn-back">Search</button>
            </form>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Invoice</th>
                            <th>Date</th>
                            <th>Items Taken</th>
                            <th>Total</th>
                            <th>Collected</th>
                            <th>Balance Due</th>
                            <th>Payment Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($sales as $sale)
                            @php
                                $itemsSummary = $sale->items->map(function ($item) {
                                    return ($item->product?->name ?? 'Unknown Product') . ' x' . number_format((float) $item->quantity, 2);
                                });
                            @endphp
                            <tr>
                                <td>
                                    <strong>{{ $sale->invoice_number ?? 'N/A' }}</strong><br>
                                    <span class="muted">Receipt: {{ $sale->receipt_number ?? 'Not issued' }}</span>
                                </td>
                                <td>{{ optional($sale->sale_date)->format('d M Y H:i') }}</td>
                                <td>
                                    @if($itemsSummary->isNotEmpty())
                                        {{ $itemsSummary->implode(', ') }}
                                    @else
                                        <span class="muted">No items linked</span>
                                    @endif
                                </td>
                                <td>{{ number_format((float) $sale->total_amount, 2) }}</td>
                                <td>{{ number_format((float) $sale->amount_paid, 2) }}</td>
                                <td>{{ number_format((float) $sale->balance_due, 2) }}</td>
                                <td>
                                    @if((float) $sale->balance_due <= 0)
                                        <span class="badge badge-clear">Cleared</span>
                                    @elseif((float) $sale->amount_paid > 0)
                                        <span class="badge badge-partial">Partly Paid</span>
                                    @else
                                        <span class="badge badge-due">Outstanding</span>
                                    @endif
                                </td>
                                <td>
                                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                        @if($sale->branch_id === $user->branch_id)
                                            <a href="{{ route('sales.show', $sale->id) }}" class="btn btn-secondary">Sale</a>
                                            @if(!$sale->isInsuranceSale() && (float) $sale->balance_due > 0)
                                                <a href="{{ route('customers.collections.create', $sale->id) }}" class="btn btn-pay">Receive Payment</a>
                                            @endif
                                        @else
                                            <span class="muted">Current branch only</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8">No invoice history found for this customer.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div style="margin-top: 15px;">
                {{ $sales->withQueryString()->links() }}
            </div>
        </div>

        <div class="panel">
            <div class="panel-head">
                <div>
                    <h2 style="margin:0;">Payment History</h2>
                    <p class="muted" style="margin:6px 0 0;">Shows who received money, when it was received, and which invoice it cleared.</p>
                </div>
                <a href="{{ route('customers.collections.index') }}" class="btn btn-secondary">All Collections</a>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Date Received</th>
                            <th>Invoice</th>
                            <th>Entry Type</th>
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
                        @forelse($payments as $payment)
                            <tr>
                                <td>{{ $payment->payment_date?->format('d M Y H:i') }}</td>
                                <td>{{ $payment->sale?->invoice_number ?? 'N/A' }}</td>
                                <td>
                                    <span class="badge {{ $payment->is_reversal ? 'badge-reversal' : 'badge-payment' }}">
                                        {{ $payment->entry_type_label }}
                                    </span><br>
                                    <span class="muted">{{ $payment->reversal_status_label }}</span>
                                </td>
                                <td>{{ number_format((float) $payment->display_amount, 2) }}</td>
                                <td>{{ ucwords(str_replace('_', ' ', $payment->payment_method)) }}</td>
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
                                    @if(
                                        !$payment->is_reversal &&
                                        $payment->branch_id === $user->branch_id &&
                                        (float) $payment->available_to_reverse > 0
                                    )
                                        <a href="{{ route('customers.collections.reverse.create', $payment->id) }}" class="btn btn-reverse">Reverse</a>
                                    @else
                                        <span class="muted">No action</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10">No payments have been recorded for this customer yet.</td>
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
