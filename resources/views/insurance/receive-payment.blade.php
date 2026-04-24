<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Record Insurance Remittance - KIM Rx</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; display: flex; background: #f5f7fb; }
        .content { flex: 1; padding: 20px; }
        .topbar, .panel { background: #fff; padding: 20px; border-radius: 16px; margin-bottom: 20px; box-shadow: 0 14px 34px rgba(15, 23, 42, 0.06); }
        .grid { display: grid; grid-template-columns: repeat(3, minmax(220px, 1fr)); gap: 16px; }
        .form-group { display: flex; flex-direction: column; }
        .form-group label { margin-bottom: 8px; font-weight: bold; }
        .form-group input, .form-group select, .form-group textarea { padding: 10px; border: 1px solid #d0d5dd; border-radius: 10px; }
        .info-box { background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 14px; padding: 14px; }
        .info-box h4 { margin: 0 0 8px; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; color: #526071; }
        .info-box p { margin: 0; font-weight: bold; }
        .btn { padding: 10px 14px; border: none; border-radius: 10px; color: #fff; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-primary { background: #155eef; }
        .btn-muted { background: #475467; }
        .alert-danger { background: #fdecea; color: #b42318; padding: 12px; border-radius: 10px; margin-bottom: 16px; }
        @media (max-width: 900px) { body { flex-direction: column; } .grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
@include('layouts.sidebar')

<div class="content" id="mainContent">
    <div class="topbar">
        <h2 style="margin:0 0 8px;">Record Insurance Remittance</h2>
        <p style="margin:0; color:#667085;">Invoice {{ $sale->invoice_number }} | {{ $sale->insurer?->name ?? 'Insurer not linked' }}</p>
    </div>

    <div class="panel">
        @if ($errors->any())
            <div class="alert-danger">
                <ul style="margin:0; padding-left:18px;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid" style="margin-bottom:20px;">
            <div class="info-box">
                <h4>Patient</h4>
                <p>{{ $sale->customer?->name ?? 'Walk-in / N/A' }}</p>
            </div>
            <div class="info-box">
                <h4>Insurer Claim</h4>
                <p>{{ number_format((float) $sale->insurance_covered_amount, 2) }}</p>
            </div>
            <div class="info-box">
                <h4>Outstanding Balance</h4>
                <p>{{ number_format((float) $sale->insurance_balance_due, 2) }}</p>
            </div>
        </div>

        <form method="POST" action="{{ route('insurance.payments.store', $sale) }}">
            @csrf
            <div class="grid">
                <div class="form-group">
                    <label for="payment_method">Payment Method</label>
                    <select name="payment_method" id="payment_method" required>
                        @foreach($paymentMethods as $methodValue => $methodLabel)
                            <option value="{{ $methodValue }}" {{ old('payment_method') === $methodValue ? 'selected' : '' }}>{{ $methodLabel }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label for="amount">Amount Received</label>
                    <input type="number" step="0.01" min="0.01" max="{{ number_format((float) $sale->insurance_balance_due, 2, '.', '') }}" name="amount" id="amount" value="{{ old('amount', number_format((float) $sale->insurance_balance_due, 2, '.', '')) }}" required>
                </div>
                <div class="form-group">
                    <label for="payment_date">Payment Date</label>
                    <input type="datetime-local" name="payment_date" id="payment_date" value="{{ old('payment_date', now()->format('Y-m-d\\TH:i')) }}" required>
                </div>
                <div class="form-group">
                    <label for="reference_number">Reference Number</label>
                    <input type="text" name="reference_number" id="reference_number" value="{{ old('reference_number') }}">
                </div>
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label for="notes">Notes</label>
                    <textarea name="notes" id="notes" rows="4">{{ old('notes') }}</textarea>
                </div>
            </div>
            <div style="margin-top:16px; display:flex; gap:10px; flex-wrap:wrap;">
                <button type="submit" class="btn btn-primary">Record Remittance</button>
                <a href="{{ route('insurance.claims.show', $sale) }}" class="btn btn-muted">Back to Claim</a>
            </div>
        </form>
    </div>
</div>
</body>
</html>
