@if(($tenantWorkspaceActive ?? false)
    && strcasecmp(trim((string) ($authUser?->client?->name ?? '')), 'VIP PHARMACY') === 0
    && now('Africa/Kampala')->toDateString() === '2026-10-08')
    <style id="vip-manager-birthday-style">
        #mainContent::before {
            content: "Happy Birthday to our wonderful Manager! Thank you for your leadership, care and dedication to VIP Pharmacy. Wishing you joy, good health and a beautiful year ahead. With love from the VIP Pharmacy team.";
            display: block;
            box-sizing: border-box;
            width: 100%;
            margin: 0 0 18px;
            padding: 16px 20px;
            border: 1px solid #c9dfd5;
            border-left: 4px solid #128351;
            border-radius: 8px;
            background: #f0faf4;
            color: #17533b;
            font-size: 15px;
            font-weight: 600;
            line-height: 1.6;
            white-space: normal;
            overflow-wrap: anywhere;
        }
        html[data-theme="dark"] #mainContent::before {
            background: #19382b;
            border-color: #397959;
            color: #e0f5e8;
        }
        @media print { #mainContent::before { display: none; } }
    </style>
    <script>
        (() => {
            const expiresAt = Date.parse('2026-10-09T00:00:00+03:00');
            const expireBirthday = () => {
                if (Date.now() >= expiresAt) {
                    document.getElementById('vip-manager-birthday-style')?.remove();
                }
            };
            window.setTimeout(expireBirthday, Math.max(0, expiresAt - Date.now()));
            document.addEventListener('visibilitychange', expireBirthday);
            window.addEventListener('focus', expireBirthday);
        })();
    </script>
@endif
