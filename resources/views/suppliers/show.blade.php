<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Statement - KIM Rx</title>
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
        .btn-view { background:#2563eb; }
        .search-form { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:15px; }
        .search-form input { flex:1; min-width:260px; padding:10px; border:1px solid #ddd; border-radius:8px; }
        .table-wrap { overflow-x:auto; }
        table { width:100%; border-collapse:collapse; min-width:1320px; }
        table th, table td { padding:10px; border-bottom:1px solid #ddd; text-align:left; vertical-align:top; }
        table th { background:#f8f8f8; font-size:13px; }
        .muted { color:#666; font-size:13px; }
        .badge { display:inline-block; padding:6px 10px; border-radius:999px; font-size:12px; font-weight:bold; }
        .badge-due { background:#fff4db; color:#9a6700; }
        .badge-clear { background:#e7f6ec; color:#1f7a4f; }
        .badge-partial { background:#eef4ff; color:#1d4ed8; }
        .badge-entry { background:#eef4ff; color:#1d4ed8; }
        .badge-payment { background:#e7f6ec; color:#1f7a4f; }
        .alert-success { background:#e7f6ec; color:#1f7a4f; padding:12px; border-radius:8px; margin-bottom:15px; }
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
            <h3>{{ $supplier->name }}</h3>
            <p>Client: {{ $clientName }} | Branch: {{ $branchName }}</p>
        </div>

        <div class="panel">
            <div class="panel-head">
                <div>
                    <h2 style="margin:0;">Supplier Statement</h2>
                    <p class="muted" style="margin:6px 0 0;">Track supplied invoices, outstanding balances, and which staff member paid each supplier invoice.</p>
                </div>
                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <a href="{{ route('suppliers.index') }}" class="btn btn-back">Back to Suppliers</a>
                    <a href="{{ route('suppliers.edit', $supplier->id) }}" class="btn btn-edit">Edit Supplier</a>
                    <a href="{{ route('suppliers.payables') }}" class="btn btn-secondary">Payables</a>
                    <a href="{{ route('suppliers.payments.index') }}" class="btn btn-secondary">Payments</a>
                </div>
            </div>

            @if(session('success'))
                <div class="alert-success">{{ session('success') }}</div>
            @endif

            <div class="summary-grid">
                <div class="summary-card">
                    <h4>Outstanding Balance</h4>
                    <p>{{ number_format((float) $outstandingBalance, 2) }}</p>
                </div>
                <div class="summary-card">
                    <h4>Total Invoiced</h4>
                    <p>{{ number_format((float) $totalInvoiced, 2) }}</p>
                </div>
                <div class="summary-card">
                    <h4>Total Paid</h4>
                    <p>{{ number_format((float) $totalPaid, 2) }}</p>
                </div>
                <div class="summary-card">
                    <h4>Payment Records</h4>
                    <p>{{ $paymentCount }}</p>
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
                    <h4>Contact</h4>
                    <p>{{ $supplier->phone ?: 'N/A' }}</p>
                    <span class="muted">{{ $supplier->email ?: 'No email' }}</span>
                </div>
                <div class="summary-card">
                    <h4>Contact Person</h4>
                    <p>{{ $supplier->contact_person ?: 'N/A' }}</p>
                    <span class="muted">{{ $supplier->address ?: 'No address' }}</span>
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-head">
                <div>
                    <h2 style="margin:0;">Invoice History</h2>
                    <p class="muted" style="margin:6px 0 0;">Search by invoice number or the products supplied on those invoices.</p>
                </div>
            </div>

            <form method="GET" action="{{ route('suppliers.show', $supplier->id) }}" class="search-form">
                <input type="text" name="search" value="{{ $search }}" placeholder="Search invoices, products, payment references, or methods...">
                <button type="submit" class="btn btn-back">Search</button>
            </form>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Invoice</th>
                            <th>Date</th>
                            <th>Items Supplied</th>
                            <th>Total</th>
                            <th>Paid</th>
                            <th>Balance Due</th>
                            <th>Status</th>
                            <th>Due Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($purchases as $purchase)
                            @php
                                $itemsSummary = $purchase->items->map(function ($item) {
                                    return ($item->product?->name ?? 'Unknown Product') . ' x' . number_format((float) $item->ordered_quantity, 2);
                                });
                            @endphp
                            <tr>
                                <td><strong>{{ $purchase->invoice_number ?? 'N/A' }}</strong></td>
                                <td>{{ optional($purchase->purchase_date)->format('d M Y') }}</td>
                                <td>
                                    @if($itemsSummary->isNotEmpty())
                                        {{ $itemsSummary->implode(', ') }}
                                    @else
                                        <span class="muted">No items linked</span>
                                    @endif
                                </td>
                                <td>{{ number_format((float) $purchase->total_amount, 2) }}</td>
                                <td>{{ number_format((float) $purchase->amount_paid, 2) }}</td>
                                <td>{{ number_format((float) $purchase->balance_due, 2) }}</td>
                                <td>
                                    @if((float) $purchase->balance_due <= 0)
                                        <span class="badge badge-clear">Cleared</span>
                                    @elseif((float) $purchase->amount_paid > 0)
                                        <span class="badge badge-partial">Partly Paid</span>
                                    @else
                                        <span class="badge badge-due">Outstanding</span>
                                    @endif
                                </td>
                                <td>{{ optional($purchase->due_date)->format('d M Y') ?: 'N/A' }}</td>
                                <td>
                                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                        <a href="{{ route('purchases.show', $purchase->id) }}" class="btn btn-view">Purchase</a>
                                        @if((float) $purchase->balance_due > 0)
                                            <a href="{{ route('suppliers.payments.create', $purchase->id) }}" class="btn btn-pay">Pay Supplier</a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9">No invoice history found for this supplier in the current branch.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div style="margin-top: 15px;">
                {{ $purchases->withQueryString()->links() }}
            </div>
        </div>

        <div class="panel">
            <div class="panel-head">
                <div>
                    <h2 style="margin:0;">Payment History</h2>
                    <p class="muted" style="margin:6px 0 0;">Shows every supplier payment entry, including amounts captured at invoice entry and later manual payments.</p>
                </div>
                <a href="{{ route('suppliers.payments.index') }}" class="btn btn-secondary">All Supplier Payments</a>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Date Paid</th>
                            <th>Invoice</th>
                            <th>Entry Type</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Reference</th>
                            <th>Paid By</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($payments as $payment)
                            <tr>
                                <td>{{ $payment->payment_date?->format('d M Y H:i') }}</td>
                                <td>{{ $payment->purchase?->invoice_number ?? 'N/A' }}</td>
                                <td>
                                    <span class="badge {{ $payment->source === 'invoice_entry' ? 'badge-entry' : 'badge-payment' }}">
                                        {{ $payment->source_label }}
                                    </span>
                                </td>
                                <td>{{ number_format((float) $payment->amount, 2) }}</td>
                                <td>{{ \App\Http\Controllers\SupplierAccountController::paymentMethodOptions()[$payment->payment_method] ?? ucwords(str_replace('_', ' ', $payment->payment_method)) }}</td>
                                <td>{{ $payment->reference_number ?: 'N/A' }}</td>
                                <td>{{ $payment->paidByUser?->name ?? 'System' }}</td>
                                <td>{{ $payment->notes ?: 'No notes' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8">No supplier payments have been recorded for this supplier yet.</td>
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
