<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pay Supplier Invoice - KIM Rx</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; display: flex; background: #f5f7fb; }
        .content { flex: 1; padding: 20px; }
        .topbar, .panel { background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
        .summary-grid { display:grid; grid-template-columns: repeat(4, minmax(180px, 1fr)); gap:16px; margin-bottom:16px; }
        .summary-card { background:#f8fafc; border:1px solid #e5e7eb; border-radius:10px; padding:14px; }
        .summary-card h4 { margin:0 0 8px; font-size:13px; color:#666; }
        .summary-card p { margin:0; font-weight:bold; }
        .form-grid { display:grid; grid-template-columns: repeat(2, minmax(220px, 1fr)); gap:16px; }
        .form-group { display:flex; flex-direction:column; }
        .form-group label { margin-bottom:8px; font-weight:bold; }
        .form-group input, .form-group select, .form-group textarea { padding:10px; border:1px solid #ddd; border-radius:8px; }
        .full { grid-column: 1 / -1; }
        .btn { padding:8px 12px; border-radius:6px; color:white; text-decoration:none; border:none; cursor:pointer; display:inline-block; }
        .btn-save { background:#1f7a4f; }
        .btn-back { background:#3949ab; }
        .btn-secondary { background:#0f766e; }
        .alert-danger { background:#fdecea; color:#b42318; padding:12px; border-radius:8px; margin-bottom:15px; }
        .muted { color:#666; font-size:13px; }
        .table-wrap { overflow-x:auto; }
        table { width:100%; border-collapse:collapse; min-width:980px; }
        table th, table td { padding:10px; border-bottom:1px solid #ddd; text-align:left; vertical-align:top; }
        table th { background:#f8f8f8; font-size:13px; }
        @media (max-width: 900px) {
            body { flex-direction: column; }
            .summary-grid, .form-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    @include('layouts.sidebar')

    <div class="content" id="mainContent">
        <div class="topbar">
            <h3>Pay Supplier Invoice</h3>
            <p>Client: {{ $clientName }} | Branch: {{ $branchName }}</p>
        </div>

        <div class="panel">
            <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; align-items:center; margin-bottom:16px;">
                <div>
                    <h2 style="margin:0;">Invoice {{ $purchase->invoice_number ?? $purchase->id }}</h2>
                    <p class="muted" style="margin:6px 0 0;">Payment will be applied to this supplier invoice only. Nothing is spread automatically to older invoices.</p>
                </div>
                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <a href="{{ route('suppliers.show', $purchase->supplier_id) }}" class="btn btn-secondary">Supplier Statement</a>
                    <a href="{{ route('suppliers.payables') }}" class="btn btn-back">Back to Payables</a>
                </div>
            </div>

            @if ($errors->any())
                <div class="alert-danger">
                    <ul style="margin:0; padding-left:18px;">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div style="background:#eef4ff; border-radius:10px; padding:12px; margin-bottom:16px;">
                <strong>Supplier:</strong> {{ $purchase->supplier?->name ?? 'N/A' }}
                <span class="muted">| Purchase Date: {{ optional($purchase->purchase_date)->format('d M Y') ?: 'N/A' }}</span>
                <span class="muted">| Due Date: {{ optional($purchase->due_date)->format('d M Y') ?: 'N/A' }}</span>
            </div>

            <div class="summary-grid">
                <div class="summary-card">
                    <h4>Total Invoice</h4>
                    <p>{{ number_format((float) $purchase->total_amount, 2) }}</p>
                </div>
                <div class="summary-card">
                    <h4>Already Paid</h4>
                    <p>{{ number_format((float) $purchase->amount_paid, 2) }}</p>
                </div>
                <div class="summary-card">
                    <h4>Balance Due</h4>
                    <p>{{ number_format((float) $purchase->balance_due, 2) }}</p>
                </div>
                <div class="summary-card">
                    <h4>Existing Type</h4>
                    <p>{{ ucfirst($purchase->payment_type ?? 'N/A') }}</p>
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
                            <th>Ordered Quantity</th>
                            <th>Unit Cost</th>
                            <th>Line Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($purchase->items as $item)
                            <tr>
                                <td>{{ $item->product?->name ?? 'Unknown Product' }}</td>
                                <td>{{ $item->batch_number }}</td>
                                <td>{{ number_format((float) $item->ordered_quantity, 2) }}</td>
                                <td>{{ number_format((float) $item->unit_cost, 2) }}</td>
                                <td>{{ number_format((float) $item->total_cost, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">No items found on this purchase invoice.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="panel">
            <h2 style="margin-top:0;">Record Payment</h2>

            <form method="POST" action="{{ route('suppliers.payments.store', $purchase->id) }}">
                @csrf

                <div class="form-grid">
                    <div class="form-group">
                        <label for="amount">Amount Paid *</label>
                        <input type="number" step="0.01" min="0.01" name="amount" id="amount" value="{{ old('amount', number_format((float) $purchase->balance_due, 2, '.', '')) }}" required>
                    </div>

                    <div class="form-group">
                        <label for="payment_method">Payment Method *</label>
                        <select name="payment_method" id="payment_method" required>
                            <option value="">Select payment method</option>
                            @foreach($paymentMethods as $value => $label)
                                <option value="{{ $value }}" {{ old('payment_method') === $value ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="payment_date">Payment Date *</label>
                        <input type="datetime-local" name="payment_date" id="payment_date" value="{{ old('payment_date', now()->format('Y-m-d\\TH:i')) }}" required>
                    </div>

                    <div class="form-group">
                        <label for="reference_number">Reference Number</label>
                        <input type="text" name="reference_number" id="reference_number" value="{{ old('reference_number') }}">
                    </div>

                    <div class="form-group full">
                        <label for="notes">Notes</label>
                        <textarea name="notes" id="notes" rows="4">{{ old('notes') }}</textarea>
                    </div>
                </div>

                <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:16px;">
                    <button type="submit" class="btn btn-save">Save Payment</button>
                    <a href="{{ route('suppliers.show', $purchase->supplier_id) }}" class="btn btn-secondary">Supplier Statement</a>
                    <a href="{{ route('suppliers.payables') }}" class="btn btn-back">Back to Payables</a>
                </div>
            </form>
        </div>

        <div class="panel">
            <h2 style="margin-top:0;">Existing Invoice Payments</h2>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Date Paid</th>
                            <th>Entry Type</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Reference</th>
                            <th>Paid By</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($purchase->supplierPayments as $payment)
                            <tr>
                                <td>{{ $payment->payment_date?->format('d M Y H:i') }}</td>
                                <td>{{ $payment->source_label }}</td>
                                <td>{{ number_format((float) $payment->amount, 2) }}</td>
                                <td>{{ $paymentMethods[$payment->payment_method] ?? ucwords(str_replace('_', ' ', $payment->payment_method)) }}</td>
                                <td>{{ $payment->reference_number ?: 'N/A' }}</td>
                                <td>{{ $payment->paidByUser?->name ?? 'System' }}</td>
                                <td>{{ $payment->notes ?: 'No notes' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">No payments have been recorded on this invoice yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
