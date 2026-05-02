<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - KIM Rx Softwares</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, Helvetica, sans-serif;
        }

        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #178a63 0%, #4b2c91 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .login-wrapper {
            width: 100%;
            max-width: 1080px;
            min-height: 640px;
            background: #ffffff;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 18px 50px rgba(0, 0, 0, 0.18);
            display: grid;
            grid-template-columns: 1.15fr 0.85fr;
        }

        .login-left {
            background: #f8fafc;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px;
        }

        .poster-box {
            width: 100%;
            height: 100%;
            border-radius: 18px;
            .poster-box {
            background: #f4f6f9;
}
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .poster-box img {
    max-width: 85%;
    max-height: 80%;
    object-fit: contain;
}

        .login-right {
            background: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 36px;
        }

        .form-box {
            width: 100%;
            max-width: 360px;
        }

        .brand-title {
            font-size: 38px;
            font-weight: 800;
            color: #111827;
            text-align: center;
            margin-bottom: 10px;
            line-height: 1.0;
        }

        .brand-subtitle {
            text-align: center;
            color: #6b7280;
            font-size: 17px;
            margin-bottom: 34px;
        }

        .input-group {
            margin-bottom: 18px;
        }

        .input-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 15px;
            font-weight: 700;
            color: #111827;
        }

        .input-group input {
            width: 100%;
            height: 52px;
            border: 1px solid #d1d5db;
            border-radius: 12px;
            padding: 0 16px;
            font-size: 15px;
            outline: none;
            transition: 0.2s ease;
            background: #fff;
        }

        .input-group input:focus {
            border-color: #178a63;
            box-shadow: 0 0 0 4px rgba(23, 138, 99, 0.10);
        }

        .remember-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 8px 0 18px;
            color: #374151;
            font-size: 15px;
        }

        .remember-row input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: #178a63;
        }

        .login-btn {
            width: 100%;
            height: 54px;
            border: none;
            border-radius: 12px;
            background: linear-gradient(90deg, #178a63, #2ca36d);
            color: #ffffff;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s ease;
        }

        .login-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 20px rgba(23, 138, 99, 0.22);
        }

        .links-row {
            text-align: center;
            margin-top: 18px;
            margin-bottom: 20px;
        }

        .links-row a {
            color: #4b2c91;
            text-decoration: none;
            font-size: 15px;
            font-weight: 600;
        }

        .links-row a:hover {
            text-decoration: underline;
        }

        .version-text {
            text-align: center;
            margin-top: 30px;
            color: #9ca3af;
            font-size: 13px;
        }

        .alert {
            margin-bottom: 18px;
            padding: 14px 16px;
            border-radius: 12px;
            font-size: 14px;
        }

        .alert-danger {
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
            border-radius: 10px;
        }

        .alert-success {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .field-error {
            margin-top: 6px;
            font-size: 13px;
            color: #dc2626;
        }

        @media (max-width: 900px) {
            .login-wrapper {
                grid-template-columns: 1fr;
                max-width: 520px;
            }

            .login-left {
                min-height: 280px;
                padding: 20px;
            }

            .poster-box {
                height: 280px;
            }

            .login-right {
                padding: 28px 22px 34px;
            }

            .brand-title {
                font-size: 30px;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 14px;
            }

            .login-wrapper {
                border-radius: 18px;
            }

            .brand-title {
                font-size: 26px;
            }

            .brand-subtitle {
                font-size: 15px;
            }

            .copyright {
                text-align: center;
                 font-size: 12px;
                 color: #9ca3af;
                margin-top: 5px;
                line-height: 1.5;
                }

            .version-text {
                text-align: center;
                 margin-top: 12px;
                 font-size: 13px;
                 color: #9ca3af;
            }

      }
    </style>
</head>
<body>

    <div class="login-wrapper">
        <!-- Left promotional panel -->
        <div class="login-left">
            <div class="poster-box">
                <img src="{{ asset('images/kemz-innova-login.png') }}" alt="KEMZ INNOVA SYSTEMS LTD - KIM Rx Software">
            </div>
        </div>

        <!-- Right login form -->
        <div class="login-right">
            <div class="form-box">
                <h1 class="brand-title">KIM Rx<br>Softwares</h1>
                <p class="brand-subtitle">Sign in to continue</p>

                @if(session('success'))
                    <div class="alert alert-success">
                        {{ session('success') }}
                    </div>
                @endif

                @if(session('error'))
                    <div class="alert alert-danger">
                        {{ session('error') }}
                    </div>
                @endif

                @if($errors->any())
                    <div class="alert alert-danger">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('login.submit') }}">
                    @csrf

                    <div class="input-group">
                        <label for="email">Email</label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="{{ old('email') }}"
                            placeholder="Enter your email"
                            required
                            autofocus
                        >
                        @error('email')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="input-group">
                        <label for="password">Password</label>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Enter your password"
                            required
                        >
                        @error('password')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="remember-row">
                        <input
                            type="checkbox"
                            id="remember"
                            name="remember"
                            {{ old('remember') ? 'checked' : '' }}
                        >
                        <label for="remember" style="margin:0; font-weight:500; cursor:pointer;">Remember me</label>
                    </div>

                    <button type="submit" class="login-btn">Login</button>

                    <div class="links-row">
                        @if (Route::has('password.request'))
                            <a href="{{ route('password.request') }}">Forgot Password?</a>
                        @else
                            <a href="#">Forgot Password?</a>
                        @endif
                    </div>

                    <div class="footer-bottom">

            <div class="copyright">
    &copy; {{ date('Y') }} KEMZ INNOVA SYSTEMS LTD. All Rights Reserved.
</div>

    <div class="version-text">
        Version 1.0.0
    </div>

</div>
                </form>
            </div>
        </div>
    </div>

</body>
</html>
