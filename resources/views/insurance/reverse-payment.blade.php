<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reverse Insurance Remittance - KIM Rx</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; display: flex; background: #f5f7fb; }
        .content { flex: 1; padding: 20px; }
        .topbar, .panel { background: #fff; padding: 20px; border-radius: 16px; margin-bottom: 20px; box-shadow: 0 14px 34px rgba(15, 23, 42, 0.06); }
        .grid { display: grid; grid-template-columns: repeat(3, minmax(220px, 1fr)); gap: 16px; }
        .form-group { display: flex; flex-direction: column; }
        .form-group label { margin-bottom: 8px; font-weight: bold; }
        .form-group input, .form-group textarea { padding: 10px; border: 1px solid #d0d5dd; border-radius: 10px; }
        .info-box { background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 14px; padding: 14px; }
        .info-box h4 { margin: 0 0 8px; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; color: #526071; }
        .info-box p { margin: 0; font-weight: bold; }
        .btn { padding: 10px 14px; border: none; border-radius: 10px; color: #fff; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-danger { background: #b42318; }
        .btn-muted { background: #475467; }
        .alert-danger { background: #fdecea; color: #b42318; padding: 12px; border-radius: 10px; margin-bottom: 16px; }
        @media (max-width: 900px) { body { flex-direction: column; } .grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
@include('layouts.sidebar')

<div class="content" id="mainContent">
    <div class="topbar">
        <h2 style="margin:0 0 8px;">Reverse Insurance Remittance</h2>
        <p style="margin:0; color:#667085;">Reverse a recorded insurer payment for invoice {{ $payment->sale?->invoice_number ?? 'N/A' }}.</p>
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
                <h4>Insurer</h4>
                <p>{{ $payment->insurer?->name ?? 'N/A' }}</p>
            </div>
            <div class="info-box">
                <h4>Original Amount</h4>
                <p>{{ number_format((float) $payment->amount, 2) }}</p>
            </div>
            <div class="info-box">
                <h4>Available To Reverse</h4>
                <p>{{ number_format((float) $payment->available_to_reverse, 2) }}</p>
            </div>
        </div>

        <form method="POST" action="{{ route('insurance.payments.reverse.store', $payment) }}">
            @csrf
            <div class="grid">
                <div class="form-group">
                    <label for="amount">Amount To Reverse</label>
                    <input type="number" step="0.01" min="0.01" max="{{ number_format((float) $payment->available_to_reverse, 2, '.', '') }}" name="amount" id="amount" value="{{ old('amount', number_format((float) $payment->available_to_reverse, 2, '.', '')) }}" required>
                </div>
                <div class="form-group">
                    <label for="payment_date">Reversal Date</label>
                    <input type="datetime-local" name="payment_date" id="payment_date" value="{{ old('payment_date', now()->format('Y-m-d\\TH:i')) }}" required>
                </div>
                <div class="form-group">
                    <label for="reference_number">Reference Number</label>
                    <input type="text" name="reference_number" id="reference_number" value="{{ old('reference_number') }}">
                </div>
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label for="notes">Reason for Reversal</label>
                    <textarea name="notes" id="notes" rows="4" required>{{ old('notes') }}</textarea>
                </div>
            </div>
            <div style="margin-top:16px; display:flex; gap:10px; flex-wrap:wrap;">
                <button type="submit" class="btn btn-danger">Reverse Remittance</button>
                <a href="{{ route('insurance.claims.show', $payment->sale_id) }}" class="btn btn-muted">Back to Claim</a>
            </div>
        </form>
    </div>
</div>
</body>
</html>
