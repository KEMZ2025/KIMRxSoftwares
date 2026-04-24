<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Supplier - KIM Rx</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; display: flex; background: #f5f7fb; }

        .content { flex: 1; padding: 20px; }
        .topbar { background: white; padding: 15px; border-radius: 10px; margin-bottom: 20px; }
        .panel { background: white; padding: 20px; border-radius: 10px; }
        .form-row { display:grid; grid-template-columns: repeat(2, minmax(220px, 1fr)); gap:16px; margin-bottom:16px; }
        .form-group { display:flex; flex-direction:column; }
        .form-group label { margin-bottom:8px; font-weight:bold; }
        .form-group input, .form-group textarea { padding:10px; border:1px solid #ddd; border-radius:8px; }
        .full { grid-column: 1 / -1; }
        .btn { padding: 8px 12px; border-radius: 6px; color: white; text-decoration: none; border: none; cursor: pointer; display: inline-block; }
        .btn-save { background: green; }
        .btn-back { background: #666; }
        .alert-danger { background:#fdecea; color:#b42318; padding:12px; border-radius:8px; margin-bottom:15px; }

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
            <h3>Welcome, {{ $user->name }}</h3>
            <p>Client: {{ $clientName }} | Branch: {{ $branchName }}</p>
        </div>

        <div class="panel">
            <h2>Add Supplier</h2>
            <p style="color:#666;">Enter supplier information below.</p>

            @if ($errors->any())
                <div class="alert-danger">
                    <ul style="margin:0; padding-left:18px;">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('suppliers.store') }}">
                @csrf

                <div class="form-row">
                    <div class="form-group">
                        <label for="name">Supplier Name *</label>
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
                        <label for="alt_phone">Alternative Phone</label>
                        <input type="text" name="alt_phone" id="alt_phone" value="{{ old('alt_phone') }}">
                    </div>

                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" name="email" id="email" value="{{ old('email') }}">
                    </div>

                    <div class="form-group full">
                        <label for="address">Address</label>
                        <textarea name="address" id="address">{{ old('address') }}</textarea>
                    </div>

                    <div class="form-group full">
                        <label for="notes">Notes</label>
                        <textarea name="notes" id="notes">{{ old('notes') }}</textarea>
                    </div>

                    <div class="form-group full" style="flex-direction:row; align-items:center; gap:10px;">
                        <input type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', '1') ? 'checked' : '' }}>
                        <label for="is_active" style="margin:0;">Active Supplier</label>
                    </div>
                </div>

                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <button type="submit" class="btn btn-save">Save Supplier</button>
                    <a href="{{ route('suppliers.index') }}" class="btn btn-back">Back</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>