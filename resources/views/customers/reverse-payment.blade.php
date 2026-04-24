<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reverse Payment - KIM Rx</title>
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
        .form-group input, .form-group textarea { padding:10px; border:1px solid #ddd; border-radius:8px; }
        .full { grid-column: 1 / -1; }
        .btn { padding:8px 12px; border-radius:6px; color:white; text-decoration:none; border:none; cursor:pointer; display:inline-block; }
        .btn-save { background:#b42318; }
        .btn-back { background:#3949ab; }
        .btn-secondary { background:#0f766e; }
        .alert-danger { background:#fdecea; color:#b42318; padding:12px; border-radius:8px; margin-bottom:15px; }
        .alert-info { background:#fff4db; color:#9a6700; padding:12px; border-radius:8px; margin-bottom:15px; }
        .muted { color:#666; font-size:13px; }
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
            <h3>Reverse Invoice Payment</h3>
            <p>Client: {{ $clientName }} | Branch: {{ $branchName }}</p>
        </div>

        <div class="panel">
            <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; align-items:center; margin-bottom:16px;">
                <div>
                    <h2 style="margin:0;">Invoice {{ $payment->sale?->invoice_number ?? 'N/A' }}</h2>
                    <p class="muted" style="margin:6px 0 0;">This reversal affects this invoice only. Other invoices and payments stay untouched.</p>
                </div>
                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <a href="{{ route('sales.show', $payment->sale_id) }}" class="btn btn-secondary">Sale Details</a>
                    <a href="{{ route('customers.show', $payment->customer_id) }}" class="btn btn-back">Customer Statement</a>
                </div>
            </div>

            <div class="alert-info">
                Customer: <strong>{{ $payment->sale?->customer?->name ?? 'N/A' }}</strong>
                | Receipt: <strong>{{ $payment->sale?->receipt_number ?? 'Not issued' }}</strong>
                | Original Collector: <strong>{{ $payment->receivedByUser?->name ?? 'System' }}</strong>
            </div>

            <div class="summary-grid">
                <div class="summary-card">
                    <h4>Original Payment</h4>
                    <p>{{ number_format((float) $payment->amount, 2) }}</p>
                </div>
                <div class="summary-card">
                    <h4>Already Reversed</h4>
                    <p>{{ number_format((float) $payment->reversed_amount, 2) }}</p>
                </div>
                <div class="summary-card">
                    <h4>Available To Reverse</h4>
                    <p>{{ number_format((float) $payment->available_to_reverse, 2) }}</p>
                </div>
                <div class="summary-card">
                    <h4>Current Invoice Balance</h4>
                    <p>{{ number_format((float) $payment->sale?->balance_due, 2) }}</p>
                </div>
            </div>

            <div class="summary-grid" style="margin-top:16px;">
                <div class="summary-card">
                    <h4>Total Invoice</h4>
                    <p>{{ number_format((float) $payment->sale?->total_amount, 2) }}</p>
                </div>
                <div class="summary-card">
                    <h4>Collected On Invoice</h4>
                    <p>{{ number_format((float) $payment->sale?->amount_paid, 2) }}</p>
                </div>
                <div class="summary-card">
                    <h4>Method</h4>
                    <p>{{ ucwords(str_replace('_', ' ', $payment->payment_method)) }}</p>
                </div>
                <div class="summary-card">
                    <h4>Original Payment Date</h4>
                    <p>{{ $payment->payment_date?->format('d M Y H:i') }}</p>
                </div>
            </div>
        </div>

        <div class="panel">
            <h2 style="margin-top:0;">Record Reversal</h2>

            @if ($errors->any())
                <div class="alert-danger">
                    <ul style="margin:0; padding-left:18px;">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('customers.collections.reverse.store', $payment->id) }}">
                @csrf

                <div class="form-row">
                    <div class="form-group">
                        <label for="amount">Amount To Reverse *</label>
                        <input type="number" step="0.01" min="0.01" max="{{ number_format((float) $payment->available_to_reverse, 2, '.', '') }}" name="amount" id="amount" value="{{ old('amount', number_format((float) $payment->available_to_reverse, 2, '.', '')) }}" required>
                        <span class="muted" style="margin-top:6px;">You can reverse all or only part of this one payment.</span>
                    </div>

                    <div class="form-group">
                        <label for="payment_date">Reversal Date *</label>
                        <input type="datetime-local" name="payment_date" id="payment_date" value="{{ old('payment_date', now()->format('Y-m-d\\TH:i')) }}" required>
                    </div>

                    <div class="form-group">
                        <label for="reference_number">Reference Number</label>
                        <input type="text" name="reference_number" id="reference_number" value="{{ old('reference_number') }}">
                    </div>

                    <div class="form-group full">
                        <label for="notes">Reason For Reversal *</label>
                        <textarea name="notes" id="notes" required>{{ old('notes') }}</textarea>
                    </div>
                </div>

                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <button type="submit" class="btn btn-save">Reverse Payment</button>
                    <a href="{{ route('customers.collections.create', $payment->sale_id) }}" class="btn btn-back">Back To Invoice</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
