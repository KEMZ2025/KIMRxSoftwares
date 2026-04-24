<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Customer - KIM Rx</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; display: flex; background: #f5f7fb; }
        .content { flex: 1; padding: 20px; }
        .topbar, .panel { background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
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
        @media (max-width: 900px) {
            body { flex-direction: column; }
            .form-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    @include('layouts.sidebar')

    <div class="content" id="mainContent">
        <div class="topbar">
            <h3>Add Customer</h3>
            <p>Client: {{ $clientName }} | Branch: {{ $branchName }}</p>
        </div>

        <div class="panel">
            <h2 style="margin-top:0;">New Customer Account</h2>
            <p class="helper" style="margin-top:0;">Set up the customer profile first, then credit invoices and collections will be tracked under this account.</p>

            @if ($errors->any())
                <div class="alert-danger">
                    <ul style="margin:0; padding-left:18px;">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('customers.store') }}">
                @csrf

                <div class="form-row">
                    <div class="form-group">
                        <label for="name">Customer Name *</label>
                        <input type="text" name="name" id="name" value="{{ old('name') }}" required>
                    </div>

                    <div class="form-group">
                        <label for="contact_person">Contact Person</label>
                        <input type="text" name="contact_person" id="contact_person" value="{{ old('contact_person') }}">
                    </div>

                    <div class="form-group">
                        <label for="phone">Phone</label>
                        <input type="text" name="phone" id="phone" value="{{ old('phone') }}">
                    </div>

                    <div class="form-group">
                        <label for="alt_phone">Alt Phone</label>
                        <input type="text" name="alt_phone" id="alt_phone" value="{{ old('alt_phone') }}">
                    </div>

                    <div class="form-group full">
                        <label for="email">Email</label>
                        <input type="email" name="email" id="email" value="{{ old('email') }}">
                    </div>

                    <div class="form-group full">
                        <label for="address">Address</label>
                        <textarea name="address" id="address">{{ old('address') }}</textarea>
                    </div>

                    <div class="form-group">
                        <label for="credit_limit">Credit Limit</label>
                        <input type="number" step="0.01" name="credit_limit" id="credit_limit" value="{{ old('credit_limit', 0) }}">
                        <div class="helper">Use this to control how much unpaid credit this customer can carry.</div>
                    </div>

                    <div class="form-group full">
                        <label for="notes">Notes</label>
                        <textarea name="notes" id="notes">{{ old('notes') }}</textarea>
                    </div>
                </div>

                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <button type="submit" class="btn btn-save">Save Customer</button>
                    <a href="{{ route('customers.index') }}" class="btn btn-back">Back to Customers</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
