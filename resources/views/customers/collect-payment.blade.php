<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receive Payment - KIM Rx</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; display: flex; background: #f5f7fb; }
        .content { flex: 1; padding: 20px; }
        .topbar, .panel { background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
        .summary-grid { display:grid; grid-template-columns: repeat(4, minmax(180px, 1fr)); gap:16px; margin-bottom:16px; }
        .summary-card { background:#f8fafc; border:1px solid #e5e7eb; border-radius:10px; padding:14px; }
        .summary-card h4 { margin:0 0 8px; font-size:13px; color:#666; }
        .summary-card p { margin:0; font-weight:bold; }
        .form-row { display:grid; grid-template-columns: repeat(2, minmax(220px, 1fr)); gap:16px; margin-bottom:16px; }
        .form-group { display:flex; flex-direction:column; }
        .form-group label { margin-bottom:8px; font-weight:bold; }
        .form-group input, .form-group select, .form-group textarea { padding:10px; border:1px solid #ddd; border-radius:8px; }
        .full { grid-column: 1 / -1; }
        .btn { padding:8px 12px; border-radius:6px; color:white; text-decoration:none; border:none; cursor:pointer; display:inline-block; }
        .btn-save { background:#1f7a4f; }
        .btn-back { background:#3949ab; }
        .btn-secondary { background:#0f766e; }
        .btn-reverse { background:#b42318; }
        .alert-danger { background:#fdecea; color:#b42318; padding:12px; border-radius:8px; margin-bottom:15px; }
        .alert-success { background:#e7f6ec; color:#1f7a4f; padding:12px; border-radius:8px; margin-bottom:15px; }
        .table-wrap { overflow-x:auto; }
        table { width:100%; border-collapse:collapse; min-width:1100px; }
        table th, table td { padding:10px; border-bottom:1px solid #ddd; text-align:left; vertical-align:top; }
        table th { background:#f8f8f8; font-size:13px; }
        .muted { color:#666; font-size:13px; }
        .alert-info { background:#eef4ff; color:#1d4ed8; padding:12px; border-radius:8px; margin-bottom:15px; }
        .badge { display:inline-block; padding:6px 10px; border-radius:999px; font-size:12px; font-weight:bold; }
        .badge-reversal { background:#fdecea; color:#b42318; }
        .badge-payment { background:#e7f6ec; color:#1f7a4f; }
        @media (max-width: 900px) {
            body { flex-direction: column; }
            .summary-grid, .form-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    @include('layouts.sidebar')

    <div class="content" id="mainContent">
        <div class="topbar">
            <h3>Receive Customer Payment</h3>
            <p>Client: {{ $clientName }} | Branch: {{ $branchName }}</p>
        </div>

        <div class="panel">
            <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; align-items:center; margin-bottom:16px;">
                <div>
                    <h2 style="margin:0;">Invoice {{ $sale->invoice_number ?? 'N/A' }}</h2>
                    <p class="muted" style="margin:6px 0 0;">Payment will be applied to this invoice only. No automatic spreading to older invoices happens here.</p>
                </div>
                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <a href="{{ route('customers.show', $sale->customer_id) }}" class="btn btn-secondary">Customer Statement</a>
                    <a href="{{ route('customers.receivables') }}" class="btn btn-back">Back to Receivables</a>
                </div>
            </div>

            @if(session('success'))
                <div class="alert-success">{{ session('success') }}</div>
            @endif

            <div class="alert-info">
                Customer: <strong>{{ $sale->customer?->name ?? 'N/A' }}</strong>
                | Sale Date: <strong>{{ optional($sale->sale_date)->format('d M Y H:i') }}</strong>
                | Receipt: <strong>{{ $sale->receipt_number ?? 'Not issued' }}</strong>
            </div>

            <div class="summary-grid">
                <div class="summary-card">
                    <h4>Total Invoice</h4>
                    <p>{{ number_format((float) $sale->total_amount, 2) }}</p>
                </div>
                <div class="summary-card">
                    <h4>Already Collected</h4>
                    <p>{{ number_format((float) $sale->amount_paid, 2) }}</p>
                </div>
                <div class="summary-card">
                    <h4>Balance Due</h4>
                    <p>{{ number_format((float) $sale->balance_due, 2) }}</p>
                </div>
                <div class="summary-card">
                    <h4>Existing Method</h4>
                    <p>{{ $sale->payment_method ? ucwords(str_replace('_', ' ', $sale->payment_method)) : 'Not set yet' }}</p>
                </div>
            </div>
        </div>

        <div class="panel">
            <h2 style="margin-top:0;">Invoice Items</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Batch</th>
                            <th>Quantity</th>
                            <th>Unit Price</th>
                            <th>Line Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($sale->items as $item)
                            <tr>
                                <td>{{ $item->product?->name ?? 'N/A' }}</td>
                                <td>{{ $item->batch?->batch_number ?? 'N/A' }}</td>
                                <td>{{ number_format((float) $item->quantity, 2) }}</td>
                                <td>{{ number_format((float) $item->unit_price, 2) }}</td>
                                <td>{{ number_format((float) $item->total_amount, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="panel">
            <h2 style="margin-top:0;">Record Payment</h2>

            @if ($errors->any())
                <div class="alert-danger">
                    <ul style="margin:0; padding-left:18px;">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('customers.collections.store', $sale->id) }}">
                @csrf

                <div class="form-row">
                    <div class="form-group">
                        <label for="amount">Amount Received *</label>
                        <input type="number" step="0.01" min="0.01" max="{{ number_format((float) $sale->balance_due, 2, '.', '') }}" name="amount" id="amount" value="{{ old('amount', number_format((float) $sale->balance_due, 2, '.', '')) }}" required>
                        <span class="muted" style="margin-top:6px;">Cannot exceed the remaining invoice balance.</span>
                    </div>

                    <div class="form-group">
                        <label for="payment_method">Payment Method *</label>
                        <select name="payment_method" id="payment_method" required>
                            <option value="">Select payment method</option>
                            @foreach($paymentMethods as $value => $label)
                                <option value="{{ $value }}" {{ old('payment_method') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="payment_date">Date Received *</label>
                        <input type="datetime-local" name="payment_date" id="payment_date" value="{{ old('payment_date', now()->format('Y-m-d\\TH:i')) }}" required>
                    </div>

                    <div class="form-group">
                        <label for="reference_number">Reference Number</label>
                        <input type="text" name="reference_number" id="reference_number" value="{{ old('reference_number') }}">
                    </div>

                    <div class="form-group full">
                        <label for="notes">Notes</label>
                        <textarea name="notes" id="notes">{{ old('notes') }}</textarea>
                    </div>
                </div>

                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <button type="submit" class="btn btn-save">Receive Payment</button>
                    <a href="{{ route('customers.show', $sale->customer_id) }}" class="btn btn-back">Cancel</a>
                </div>
            </form>
        </div>

        @if($sale->payments->isNotEmpty())
            <div class="panel">
                <h2 style="margin-top:0;">Previous Payments On This Invoice</h2>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Date Received</th>
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
                            @foreach($sale->payments->sortByDesc('payment_date') as $payment)
                                <tr>
                                    <td>{{ $payment->payment_date?->format('d M Y H:i') }}</td>
                                    <td>
                                        <span class="badge {{ $payment->is_reversal ? 'badge-reversal' : 'badge-payment' }}">
                                            {{ $payment->entry_type_label }}
                                        </span><br>
                                        <span class="muted">{{ $payment->reversal_status_label }}</span>
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
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</body>
</html>
