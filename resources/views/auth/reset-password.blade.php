<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - KIM Rx Softwares</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: Arial, Helvetica, sans-serif; }
        body { min-height: 100vh; background: linear-gradient(135deg, #178a63 0%, #4b2c91 100%); display: flex; align-items: center; justify-content: center; padding: 24px; }
        .card { width: 100%; max-width: 560px; background: #fff; border-radius: 24px; padding: 34px; box-shadow: 0 18px 50px rgba(0, 0, 0, 0.18); }
        h1 { font-size: 32px; font-weight: 800; color: #111827; margin-bottom: 10px; }
        p.lead { color: #6b7280; font-size: 16px; margin-bottom: 24px; line-height: 1.6; }
        .grid { display: grid; grid-template-columns: 1fr; gap: 16px; }
        .input-group label { display: block; margin-bottom: 8px; font-size: 15px; font-weight: 700; color: #111827; }
        .input-group input { width: 100%; height: 52px; border: 1px solid #d1d5db; border-radius: 12px; padding: 0 16px; font-size: 15px; outline: none; }
        .input-group input:focus { border-color: #178a63; box-shadow: 0 0 0 4px rgba(23, 138, 99, 0.10); }
        .btn { width: 100%; height: 52px; border: none; border-radius: 12px; background: linear-gradient(90deg, #178a63, #2ca36d); color: #fff; font-size: 17px; font-weight: 700; cursor: pointer; margin-top: 6px; }
        .alert { margin-bottom: 18px; padding: 14px 16px; border-radius: 12px; font-size: 14px; }
        .alert-danger { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .field-error { margin-top: 6px; font-size: 13px; color: #dc2626; }
    </style>
</head>
<body>
    <section class="card">
        <h1>Reset Password</h1>
        <p class="lead">Set a new password for your account. This reset link is time-sensitive for your security.</p>

        @if($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('password.update') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <div class="grid">
                <div class="input-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="{{ old('email', $email) }}" required autofocus>
                    @error('email')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="input-group">
                    <label for="password">New Password</label>
                    <input type="password" id="password" name="password" required>
                    @error('password')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="input-group">
                    <label for="password_confirmation">Confirm New Password</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" required>
                </div>
            </div>

            <button type="submit" class="btn">Reset Password</button>
        </form>
    </section>
</body>
</html>
