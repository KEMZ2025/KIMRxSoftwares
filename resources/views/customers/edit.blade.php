<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Customer - KIM Rx</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; display: flex; background: #f5f7fb; }
        .content { flex: 1; padding: 20px; }
        .topbar, .panel { background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
        .summary-grid { display: grid; grid-template-columns: repeat(3, minmax(180px, 1fr)); gap: 16px; margin-bottom: 16px; }
        .summary-card { background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 10px; padding: 14px; }
        .summary-card h4 { margin: 0 0 8px; font-size: 13px; color: #666; }
        .summary-card p { margin: 0; font-weight: bold; }
        .form-row { display: grid; grid-template-columns: repeat(2, minmax(220px, 1fr)); gap: 16px; margin-bottom: 16px; }
        .form-group { display: flex; flex-direction: column; }
        .form-group label { margin-bottom: 8px; font-weight: bold; }
        .form-group input, .form-group textarea { padding: 10px; border: 1px solid #ddd; border-radius: 8px; }
        .full { grid-column: 1 / -1; }
        .alert-danger { background: #fdecea; color: #b42318; padding: 12px; border-radius: 8px; margin-bottom: 15px; }
        .helper { color: #666; font-size: 13px; margin-top: 6px; }
        .btn { padding: 8px 12px; border-radius: 6px; color: white; text-decoration: none; border: none; cursor: pointer; display: inline-block; }
        .btn-save { background: #1f7a4f; }
        .btn-back { background: #3949ab; }
        .btn-secondary { background: #0f766e; }
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
            <h3>Edit Customer</h3>
            <p>Client: {{ $clientName }} | Branch: {{ $branchName }}</p>
        </div>

        <div class="panel">
            <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; align-items:center; margin-bottom:16px;">
                <div>
                    <h2 style="margin:0;">{{ $customer->name }}</h2>
                    <p class="helper" style="margin:6px 0 0;">Keep profile and credit details current before issuing new credit invoices.</p>
                </div>
                <a href="{{ route('customers.show', $customer->id) }}" class="btn btn-secondary">View Statement</a>
            </div>

            <div class="summary-grid">
                <div class="summary-card">
                    <h4>Credit Limit</h4>
                    <p>{{ number_format((float) $customer->credit_limit, 2) }}</p>
                </div>
                <div class="summary-card">
                    <h4>Outstanding Balance</h4>
                    <p>{{ number_format((float) $customer->outstanding_balance, 2) }}</p>
                </div>
                <div class="summary-card">
                    <h4>Remaining Credit</h4>
                    <p>{{ number_format((float) $customer->remaining_credit, 2) }}</p>
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

            <form method="POST" action="{{ route('customers.update', $customer->id) }}">
                @csrf
                @method('PUT')

                <div class="form-row">
                    <div class="form-group">
                        <label for="name">Customer Name *</label>
                        <input type="text" name="name" id="name" value="{{ old('name', $customer->name) }}" required>
                    </div>

                    <div class="form-group">
                        <label for="contact_person">Contact Person</label>
                        <input type="text" name="contact_person" id="contact_person" value="{{ old('contact_person', $customer->contact_person) }}">
                    </div>

                    <div class="form-group">
                        <label for="phone">Phone</label>
                        <input type="text" name="phone" id="phone" value="{{ old('phone', $customer->phone) }}">
                    </div>

                    <div class="form-group">
                        <label for="alt_phone">Alt Phone</label>
                        <input type="text" name="alt_phone" id="alt_phone" value="{{ old('alt_phone', $customer->alt_phone) }}">
                    </div>

                    <div class="form-group full">
                        <label for="email">Email</label>
                        <input type="email" name="email" id="email" value="{{ old('email', $customer->email) }}">
                    </div>

                    <div class="form-group full">
                        <label for="address">Address</label>
                        <textarea name="address" id="address">{{ old('address', $customer->address) }}</textarea>
                    </div>

                    <div class="form-group">
                        <label for="credit_limit">Credit Limit</label>
                        <input type="number" step="0.01" name="credit_limit" id="credit_limit" value="{{ old('credit_limit', $customer->credit_limit) }}">
                        <div class="helper">This does not write off existing debt. It only changes the customer’s allowed credit ceiling.</div>
                    </div>

                    <div class="form-group full">
                        <label for="notes">Notes</label>
                        <textarea name="notes" id="notes">{{ old('notes', $customer->notes) }}</textarea>
                    </div>
                </div>

                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <button type="submit" class="btn btn-save">Update Customer</button>
                    <a href="{{ route('customers.index') }}" class="btn btn-back">Back to Customers</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
