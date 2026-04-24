<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - KIM Rx</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; background: #f4f6fb; color: #1f2937; }
        .layout { display: flex; min-height: 100vh; }
        .content { flex: 1; padding: 24px; margin-left: 260px; transition: margin-left 0.3s ease; }
        .content.expanded { margin-left: 80px; }
        .topbar, .panel { background: #fff; border-radius: 18px; padding: 22px; box-shadow: 0 10px 28px rgba(0, 0, 0, 0.06); margin-bottom: 22px; }
        .panel-head { margin-bottom: 18px; }
        .form-grid { display: grid; grid-template-columns: repeat(2, minmax(240px, 1fr)); gap: 16px; }
        .field label { display: block; margin-bottom: 8px; font-weight: 700; }
        .field input { width: 100%; padding: 12px 14px; border: 1px solid #dbe3ef; border-radius: 12px; }
        .full-span { grid-column: 1 / -1; }
        .alert-success, .alert-error { padding: 12px 14px; border-radius: 12px; margin-bottom: 16px; font-weight: 600; }
        .alert-success { background: #e7f6ec; color: #166534; }
        .alert-error { background: #fef2f2; color: #b91c1c; }
        .hint { margin-top: 8px; color: #64748b; font-size: 13px; }
        .error { display: block; margin-top: 6px; font-size: 12px; color: #b91c1c; font-weight: 700; }
        .btn { display: inline-flex; align-items: center; justify-content: center; padding: 10px 16px; border-radius: 10px; border: none; background: #1f7a4f; color: #fff; cursor: pointer; font-weight: 700; }
        @media (max-width: 900px) {
            .layout { display: block; }
            .content, .content.expanded { margin-left: 0; padding: 16px; }
            .form-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="layout">
    @include('layouts.sidebar')

    <main class="content" id="mainContent">
        <section class="topbar">
            <h3 style="margin:0 0 8px;">Change Password</h3>
            <p style="margin:0; color:#64748b;">Client: {{ $clientName }} | Branch: {{ $branchName }}</p>
        </section>

        <section class="panel">
            <div class="panel-head">
                <h2 style="margin:0 0 6px;">Update Your Password</h2>
                <p style="margin:0; color:#64748b;">Use your current password, then set a new one you want to keep using for login.</p>
            </div>

            @if(session('success'))
                <div class="alert-success">{{ session('success') }}</div>
            @endif

            @if($errors->any())
                <div class="alert-error">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('account.password.update') }}">
                @csrf
                @method('PUT')

                <div class="form-grid">
                    <div class="field full-span">
                        <label for="current_password">Current Password</label>
                        <input type="password" id="current_password" name="current_password" required>
                        @error('current_password')
                            <small class="error">{{ $message }}</small>
                        @enderror
                    </div>

                    <div class="field">
                        <label for="password">New Password</label>
                        <input type="password" id="password" name="password" required>
                        @error('password')
                            <small class="error">{{ $message }}</small>
                        @enderror
                    </div>

                    <div class="field">
                        <label for="password_confirmation">Confirm New Password</label>
                        <input type="password" id="password_confirmation" name="password_confirmation" required>
                    </div>
                </div>

                <p class="hint">Use at least 8 characters and make it different from the one you are using now.</p>

                <div style="margin-top:18px;">
                    <button type="submit" class="btn">Save New Password</button>
                </div>
            </form>
        </section>
    </main>
</div>
</body>
</html>
