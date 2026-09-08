@if(session('show_login_welcome') && $authUser)
    @php
        $welcomeFacts = [
            'Antibiotics treat bacterial infections, not viral infections such as colds and flu.',
            'WHO encourages everyone involved with medicines to Know, Check and Ask for safer medication use.',
            'Regularly checking medicine expiry dates is an important part of medication safety.',
            'Clean hands are one of the most important protections against spreading harmful germs in health care.',
            'Patients are safer when they keep an up-to-date list of their medicines and share it with health workers.',
            'Medicines should be stored exactly as indicated so their quality is protected.',
        ];
        $welcomeFact = $welcomeFacts[array_rand($welcomeFacts)];
        $normalizedWelcomeRole = mb_strtolower(trim((string) $sessionRoleLabel));
        $welcomeRoleMessage = match (true) {
            $isSuperAdmin => 'Your platform workspace is ready.',
            str_contains($normalizedWelcomeRole, 'dispenser') => 'Your dispensing workspace is ready.',
            str_contains($normalizedWelcomeRole, 'account') => 'Your accounting workspace is ready.',
            str_contains($normalizedWelcomeRole, 'stock') => 'Your inventory workspace is ready.',
            str_contains($normalizedWelcomeRole, 'cashier') => 'Your cashier workspace is ready.',
            str_contains($normalizedWelcomeRole, 'admin') => 'Your pharmacy management workspace is ready.',
            default => 'Your pharmacy workspace is ready.',
        };
        $savedWelcomeLogo = trim((string) ($authUser->client?->logo ?? ''));
        $welcomeLogoUrl = $savedWelcomeLogo === ''
            ? asset('favicon.png')
            : ((str_starts_with($savedWelcomeLogo, 'http://') || str_starts_with($savedWelcomeLogo, 'https://') || str_starts_with($savedWelcomeLogo, 'data:'))
                ? $savedWelcomeLogo
                : asset(ltrim(str_replace('\\', '/', $savedWelcomeLogo), '/')));
    @endphp

    <style>
        .login-welcome {
            position: fixed;
            inset: 0;
            z-index: 20000;
            display: grid;
            place-items: center;
            overflow: hidden;
            padding: 24px;
            background: #f6faf8;
            color: #14251d;
            opacity: 1;
            transition: opacity 280ms ease, visibility 280ms ease;
        }
        .login-welcome::before {
            content: '';
            position: absolute;
            inset: 0 0 auto;
            height: 8px;
            background: #079455;
        }
        .login-welcome.is-closing {
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
        }
        .login-welcome-inner {
            position: relative;
            width: min(680px, 100%);
            text-align: center;
            animation: login-welcome-enter 620ms cubic-bezier(.2,.8,.2,1) both;
        }
        .login-welcome-logo-wrap {
            width: 152px;
            height: 152px;
            margin: 0 auto 22px;
            display: grid;
            place-items: center;
            border: 1px solid #d4e7dd;
            border-radius: 50%;
            background: #fff;
            box-shadow: 0 18px 40px rgba(7, 148, 85, 0.14);
            animation: login-welcome-logo 760ms cubic-bezier(.2,.8,.2,1) both;
        }
        .login-welcome-logo {
            display: block;
            width: 118px;
            height: 118px;
            object-fit: contain;
        }
        .login-welcome-kicker {
            margin: 0 0 8px;
            color: #067647;
            font-size: 13px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0;
        }
        .login-welcome h1 {
            margin: 0;
            color: #101828;
            font-size: 46px;
            line-height: 1.1;
            letter-spacing: 0;
        }
        .login-welcome-role {
            margin: 12px 0 0;
            color: #475467;
            font-size: 17px;
            line-height: 1.5;
        }
        .login-welcome-identity {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 12px;
            color: #344054;
            font-size: 14px;
            font-weight: 700;
        }
        .login-welcome-identity span + span::before {
            content: '|';
            margin-right: 8px;
            color: #98a2b3;
        }
        .login-welcome-fact {
            margin: 30px auto 24px;
            padding: 18px 20px;
            border-left: 4px solid #a832d7;
            border-radius: 6px;
            background: #fff;
            box-shadow: 0 10px 28px rgba(16, 24, 40, 0.08);
            text-align: left;
        }
        .login-welcome-fact strong {
            display: block;
            margin-bottom: 6px;
            color: #7f1d9f;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0;
        }
        .login-welcome-fact p {
            margin: 0;
            color: #344054;
            font-size: 15px;
            line-height: 1.5;
        }
        .login-welcome-actions {
            display: flex;
            justify-content: center;
        }
        .login-welcome-continue {
            min-height: 44px;
            padding: 0 22px;
            border: 0;
            border-radius: 6px;
            background: #079455;
            color: #fff;
            font: inherit;
            font-weight: 800;
            cursor: pointer;
        }
        .login-welcome-continue:hover { background: #067647; }
        .login-welcome-continue:focus-visible { outline: 3px solid rgba(168, 50, 215, 0.28); outline-offset: 3px; }
        .login-welcome-progress {
            position: absolute;
            right: 0;
            bottom: 0;
            left: 0;
            height: 4px;
            background: #d4e7dd;
        }
        .login-welcome-progress::after {
            content: '';
            display: block;
            width: 100%;
            height: 100%;
            background: #a832d7;
            transform-origin: left;
            animation: login-welcome-progress 6s linear both;
        }
        @keyframes login-welcome-enter {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes login-welcome-logo {
            from { opacity: 0; transform: scale(.78) rotate(-5deg); }
            to { opacity: 1; transform: scale(1) rotate(0); }
        }
        @keyframes login-welcome-progress {
            from { transform: scaleX(1); }
            to { transform: scaleX(0); }
        }
        @media (max-width: 600px) {
            .login-welcome { padding: 18px; }
            .login-welcome-logo-wrap { width: 124px; height: 124px; margin-bottom: 18px; }
            .login-welcome-logo { width: 96px; height: 96px; }
            .login-welcome h1 { font-size: 34px; }
            .login-welcome-fact { margin-top: 24px; }
            .login-welcome-identity { align-items: flex-start; flex-direction: column; gap: 4px; }
            .login-welcome-identity span + span::before { content: none; }
        }
        @media (prefers-reduced-motion: reduce) {
            .login-welcome,
            .login-welcome-inner,
            .login-welcome-logo-wrap,
            .login-welcome-progress::after { animation: none; transition: none; }
        }
    </style>

    <div class="login-welcome" id="loginWelcome" role="dialog" aria-modal="true" aria-labelledby="loginWelcomeTitle">
        <div class="login-welcome-inner">
            <div class="login-welcome-logo-wrap">
                <img class="login-welcome-logo" src="{{ $welcomeLogoUrl }}" alt="{{ $displayClientName }} logo">
            </div>
            <p class="login-welcome-kicker">{{ $displayClientName }}</p>
            <h1 id="loginWelcomeTitle">Welcome, {{ $sessionShortName }}</h1>
            <p class="login-welcome-role">{{ $welcomeRoleMessage }}</p>
            <div class="login-welcome-identity">
                <span>{{ $sessionRoleLabel }}</span>
                <span>{{ $displayBranchName }}</span>
            </div>
            <div class="login-welcome-fact">
                <strong>Did you know?</strong>
                <p>{{ $welcomeFact }}</p>
            </div>
            <div class="login-welcome-actions">
                <button type="button" class="login-welcome-continue" id="loginWelcomeContinue">Continue to workspace</button>
            </div>
        </div>
        <div class="login-welcome-progress" aria-hidden="true"></div>
    </div>

    <script>
        (() => {
            const welcome = document.getElementById('loginWelcome');
            const continueButton = document.getElementById('loginWelcomeContinue');

            if (!welcome || !continueButton) {
                return;
            }

            let closed = false;
            const closeWelcome = () => {
                if (closed) {
                    return;
                }

                closed = true;
                welcome.classList.add('is-closing');
                window.setTimeout(() => welcome.remove(), 300);
            };

            continueButton.addEventListener('click', closeWelcome);
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    closeWelcome();
                }
            });

            window.setTimeout(closeWelcome, 6000);
            window.setTimeout(() => continueButton.focus(), 80);
        })();
    </script>
@endif
