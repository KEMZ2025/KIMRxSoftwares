<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - KIM Rx</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            display: flex;
            background:
                radial-gradient(circle at top right, rgba(21, 94, 239, 0.08), transparent 28%),
                linear-gradient(180deg, #f8fafc 0%, #eef2ff 100%);
            color: #172033;
        }
        .content {
            flex: 1;
            width: 100%;
            max-width: 100%;
            padding: 20px;
        }
        .topbar,
        .panel {
            background: rgba(255, 255, 255, 0.96);
            border-radius: 20px;
            padding: 22px;
            box-shadow: 0 18px 42px rgba(15, 23, 42, 0.08);
            border: 1px solid rgba(226, 232, 240, 0.9);
            margin-bottom: 20px;
            backdrop-filter: blur(10px);
        }
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 18px;
        }
        .eyebrow {
            margin: 0 0 8px;
            color: #475467;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        .topbar h1 {
            margin: 0 0 6px;
            font-size: clamp(28px, 3vw, 42px);
            line-height: 1.05;
        }
        .topbar p {
            margin: 0;
            color: #667085;
            font-size: 15px;
        }
        .chips {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: flex-end;
        }
        .chip {
            padding: 12px 16px;
            border-radius: 999px;
            background: linear-gradient(135deg, #e0ecff, #eef4ff);
            color: #1d4ed8;
            font-weight: 800;
            white-space: nowrap;
            font-size: 13px;
        }
        .chip.alt {
            background: linear-gradient(135deg, #ecfdf3, #f0fdf4);
            color: #067647;
        }
        .section-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
        }
        .panel h2 {
            margin: 0 0 8px;
            font-size: 22px;
        }
        .panel .panel-subtitle {
            margin: 0 0 18px;
            color: #667085;
            font-size: 14px;
        }
        .field-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }
        .field.full,
        .checkbox-card.full {
            grid-column: 1 / -1;
        }
        .field label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            color: #344054;
            margin-bottom: 8px;
        }
        .field input,
        .field textarea,
        .field select {
            width: 100%;
            border: 1px solid #d0d5dd;
            border-radius: 14px;
            padding: 12px 14px;
            font: inherit;
            background: #fff;
        }
        .field textarea {
            min-height: 110px;
            resize: vertical;
        }
        .hint {
            margin-top: 6px;
            color: #667085;
            font-size: 12px;
        }
        .error {
            margin-top: 6px;
            color: #b42318;
            font-size: 12px;
            font-weight: 700;
        }
        .checkbox-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }
        .checkbox-card {
            border: 1px solid #e4e7ec;
            border-radius: 18px;
            padding: 14px 16px;
            background: linear-gradient(180deg, #ffffff, #f8fafc);
        }
        .checkbox-card label {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            cursor: pointer;
        }
        .checkbox-card input[type="checkbox"] {
            margin-top: 2px;
            width: 18px;
            height: 18px;
        }
        .checkbox-card strong {
            display: block;
            margin-bottom: 4px;
        }
        .checkbox-card span {
            color: #667085;
            font-size: 13px;
            line-height: 1.45;
        }
        .logo-preview-card {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px;
            border: 1px solid #e4e7ec;
            border-radius: 18px;
            background: linear-gradient(180deg, #ffffff, #f8fafc);
        }
        .logo-preview-card img {
            width: 88px;
            height: 88px;
            object-fit: contain;
            border-radius: 16px;
            border: 1px solid #dbe5f1;
            background: #fff;
            padding: 8px;
        }
        .logo-preview-card .logo-empty {
            width: 88px;
            height: 88px;
            border-radius: 16px;
            border: 1px dashed #cbd5e1;
            background: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #94a3b8;
            font-size: 12px;
            font-weight: 700;
            text-align: center;
            padding: 10px;
        }
        .inline-check {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 10px;
            font-size: 13px;
            color: #475467;
        }
        .inline-check input {
            width: 16px;
            height: 16px;
        }
        .message {
            border-radius: 14px;
            padding: 14px 16px;
            margin-bottom: 16px;
            font-weight: 700;
        }
        .message.success {
            background: #dcfce7;
            color: #067647;
            border: 1px solid #abefc6;
        }
        .message.error {
            background: #fee4e2;
            color: #b42318;
            border: 1px solid #fecdca;
        }
        .actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 4px;
        }
        .btn {
            border: none;
            border-radius: 14px;
            padding: 12px 16px;
            cursor: pointer;
            font-weight: 800;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }
        .btn-primary {
            background: #155eef;
            color: #fff;
        }
        .btn-light {
            background: #eef4ff;
            color: #1d4ed8;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 14px;
            margin: 18px 0;
        }
        .stat-card {
            border: 1px solid #e4e7ec;
            border-radius: 18px;
            padding: 16px;
            background: linear-gradient(180deg, #ffffff, #f8fafc);
        }
        .stat-card strong {
            display: block;
            font-size: 13px;
            color: #475467;
            margin-bottom: 8px;
        }
        .stat-card span {
            display: block;
            font-size: 28px;
            font-weight: 800;
            color: #172033;
        }
        .status-banner {
            border-radius: 18px;
            padding: 16px 18px;
            margin: 18px 0;
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            color: #334155;
        }
        .status-banner.ready {
            background: #ecfdf3;
            border-color: #abefc6;
            color: #067647;
        }
        .status-banner.warn {
            background: #fffaeb;
            border-color: #fedf89;
            color: #b54708;
        }
        .check-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
            margin-top: 16px;
        }
        .check-card {
            border: 1px solid #e4e7ec;
            border-radius: 18px;
            padding: 14px 16px;
            background: linear-gradient(180deg, #ffffff, #f8fafc);
        }
        .check-card.ready {
            border-color: #abefc6;
            background: linear-gradient(180deg, #f0fdf4, #ecfdf3);
        }
        .check-card.missing {
            border-color: #fedf89;
            background: linear-gradient(180deg, #fffdf7, #fffaeb);
        }
        .check-card strong {
            display: block;
            margin-bottom: 6px;
        }
        .check-meta {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 6px;
        }
        .queue-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 6px;
        }
        .queue-actions form {
            margin: 0;
        }
        .table-wrap {
            overflow-x: auto;
            border: 1px solid #e4e7ec;
            border-radius: 18px;
            background: #fff;
        }
        .table-wrap table {
            width: 100%;
            border-collapse: collapse;
        }
        .table-wrap th,
        .table-wrap td {
            padding: 12px 14px;
            border-bottom: 1px solid #eef2f6;
            text-align: left;
            font-size: 13px;
            vertical-align: top;
        }
        .table-wrap th {
            background: #f8fafc;
            color: #475467;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        @media (max-width: 1100px) {
            .section-grid,
            .field-grid,
            .checkbox-grid,
            .summary-grid,
            .check-grid {
                grid-template-columns: 1fr;
            }
        }
        @media (max-width: 760px) {
            .topbar {
                flex-direction: column;
            }
            .chips {
                justify-content: flex-start;
            }
            .actions {
                flex-direction: column;
            }
            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    @include('layouts.sidebar', [
        'clientName' => optional($client)->name ?? 'N/A',
        'branchName' => optional($branch)->name ?? 'N/A',
    ])

    <main class="content" id="mainContent">
        @php
            $canManageSettings = auth()->user()?->hasPermission('settings.manage') ?? false;
        @endphp

        <div class="topbar">
            <div>
                <p class="eyebrow">Administration</p>
                <h1>Settings</h1>
                <p>Manage the active client identity, branch details, purchase-entry defaults, and print preferences.</p>
            </div>
            <div class="chips">
                <div class="chip">Client Mode: {{ $paymentLabels[$client?->business_mode ?? 'both'] ?? 'Retail and Wholesale' }}</div>
                <div class="chip alt">Branch Mode: {{ $branch?->effectiveBusinessModeLabel() ?? 'Retail and Wholesale' }}</div>
            </div>
        </div>

        <form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            @php
                $efrisModuleEnabled = \App\Support\ClientFeatureAccess::efrisEnabled($settings);
                $cashDrawerModuleEnabled = \App\Support\ClientFeatureAccess::cashDrawerEnabled($settings);
                $showEfrisOperations = $efrisModuleEnabled || (($efrisSummary['total'] ?? 0) > 0);
            @endphp

            @if (session('success'))
                <div class="message success">{{ session('success') }}</div>
            @endif

            @if ($errors->any())
                <div class="message error">Please correct the highlighted settings fields and save again.</div>
            @endif

            <div class="section-grid">
                <section class="panel">
                    <h2>Pharmacy Profile</h2>
                    <p class="panel-subtitle">These are the main client details that should appear on receipts, invoices, and reports.</p>

                    <div class="field-grid">
                        <div class="field full">
                            <label>Current Logo</label>
                            <div class="logo-preview-card">
                                @if($logoPreviewUrl)
                                    <img src="{{ $logoPreviewUrl }}" alt="Current pharmacy logo">
                                @else
                                    <div class="logo-empty">No Logo Yet</div>
                                @endif
                                <div>
                                    <strong style="display:block; margin-bottom:6px;">Printing Preview</strong>
                                    <div class="hint" style="margin-top:0;">
                                        This logo is used on receipts, invoices, reports, and accounting printouts when logo display is enabled.
                                    </div>
                                    @if($client?->logo)
                                        <div class="hint" style="margin-top:8px;"><strong>Saved path:</strong> {{ $client->logo }}</div>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="field">
                            <label for="client_name">Pharmacy Name</label>
                            <input id="client_name" type="text" name="client_name" value="{{ old('client_name', $client?->name) }}" @disabled(!$canManageSettings)>
                            @error('client_name') <div class="error">{{ $message }}</div> @enderror
                        </div>
                        <div class="field">
                            <label for="client_logo_file">Upload Logo</label>
                            <input id="client_logo_file" type="file" name="client_logo_file" accept=".png,.jpg,.jpeg,.webp" @disabled(!$canManageSettings)>
                            <div class="hint">Use PNG, JPG, or WEBP. A square logo with transparent background works best.</div>
                            @if($client?->logo)
                                <label class="inline-check">
                                    <input type="checkbox" name="remove_client_logo" value="1" @disabled(!$canManageSettings)>
                                    <span>Remove current logo</span>
                                </label>
                            @endif
                            @error('client_logo_file') <div class="error">{{ $message }}</div> @enderror
                        </div>
                        <div class="field full">
                            <label for="client_logo">Logo Path / URL (Advanced)</label>
                            <input id="client_logo" type="text" name="client_logo" value="{{ old('client_logo', $client?->logo) }}" @disabled(!$canManageSettings)>
                            <div class="hint">Optional advanced override if you want to point to an existing logo path or hosted image instead of uploading one.</div>
                            @error('client_logo') <div class="error">{{ $message }}</div> @enderror
                        </div>
                        <div class="field">
                            <label for="client_email">Pharmacy Email</label>
                            <input id="client_email" type="email" name="client_email" value="{{ old('client_email', $client?->email) }}" @disabled(!$canManageSettings)>
                            @error('client_email') <div class="error">{{ $message }}</div> @enderror
                        </div>
                        <div class="field">
                            <label for="client_phone">Pharmacy Phone</label>
                            <input id="client_phone" type="text" name="client_phone" value="{{ old('client_phone', $client?->phone) }}" @disabled(!$canManageSettings)>
                            @error('client_phone') <div class="error">{{ $message }}</div> @enderror
                        </div>
                        <div class="field full">
                            <label for="client_address">Pharmacy Address</label>
                            <textarea id="client_address" name="client_address" @disabled(!$canManageSettings)>{{ old('client_address', $client?->address) }}</textarea>
                            @error('client_address') <div class="error">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </section>

                <section class="panel">
                    <h2>Branch Profile</h2>
                    <p class="panel-subtitle">These are the active branch details that can appear on branch-level printouts and statements.</p>

                    <div class="field-grid">
                        <div class="field">
                            <label for="branch_name">Branch Name</label>
                            <input id="branch_name" type="text" name="branch_name" value="{{ old('branch_name', $branch?->name) }}" @disabled(!$canManageSettings)>
                            @error('branch_name') <div class="error">{{ $message }}</div> @enderror
                        </div>
                        <div class="field">
                            <label for="branch_code">Branch Code</label>
                            <input id="branch_code" type="text" name="branch_code" value="{{ old('branch_code', $branch?->code) }}" @disabled(!$canManageSettings)>
                            @error('branch_code') <div class="error">{{ $message }}</div> @enderror
                        </div>
                        <div class="field">
                            <label for="branch_email">Branch Email</label>
                            <input id="branch_email" type="email" name="branch_email" value="{{ old('branch_email', $branch?->email) }}" @disabled(!$canManageSettings)>
                            @error('branch_email') <div class="error">{{ $message }}</div> @enderror
                        </div>
                        <div class="field">
                            <label for="branch_phone">Branch Phone</label>
                            <input id="branch_phone" type="text" name="branch_phone" value="{{ old('branch_phone', $branch?->phone) }}" @disabled(!$canManageSettings)>
                            @error('branch_phone') <div class="error">{{ $message }}</div> @enderror
                        </div>
                        <div class="field full">
                            <label for="branch_address">Branch Address</label>
                            <textarea id="branch_address" name="branch_address" @disabled(!$canManageSettings)>{{ old('branch_address', $branch?->address) }}</textarea>
                            @error('branch_address') <div class="error">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </section>
            </div>

            <div class="panel">
                <h2>URA / EFRIS Readiness</h2>
                @if ($efrisModuleEnabled)
                    <p class="panel-subtitle">These compliance details feed the queued URA EFRIS sync flow. Sales stay safe because submission happens outside the approval screen, not in the middle of dispensing.</p>

                    <div class="field-grid">
                        <div class="field">
                            <label for="efris_environment">EFRIS Environment</label>
                            <select id="efris_environment" name="efris_environment" @disabled(!$canManageSettings)>
                                <option value="sandbox" @selected(old('efris_environment', $settings->efris_environment ?? 'sandbox') === 'sandbox')>Sandbox / UAT</option>
                                <option value="production" @selected(old('efris_environment', $settings->efris_environment ?? 'sandbox') === 'production')>Production</option>
                            </select>
                            @error('efris_environment') <div class="error">{{ $message }}</div> @enderror
                        </div>
                        <div class="field">
                            <label for="efris_transport_mode">Transport Mode</label>
                            <select id="efris_transport_mode" name="efris_transport_mode" @disabled(!$canManageSettings)>
                                <option value="simulate" @selected(old('efris_transport_mode', $settings->efris_transport_mode ?? 'simulate') === 'simulate')>Simulation</option>
                                <option value="http" @selected(old('efris_transport_mode', $settings->efris_transport_mode ?? 'simulate') === 'http')>Real HTTP Connector</option>
                            </select>
                            <div class="hint">Use simulation until URA UAT credentials and endpoints are confirmed for this client.</div>
                            @error('efris_transport_mode') <div class="error">{{ $message }}</div> @enderror
                        </div>
                        <div class="field">
                            <label for="efris_tin">URA TIN</label>
                            <input id="efris_tin" type="text" name="efris_tin" value="{{ old('efris_tin', $settings->efris_tin ?: $settings->tax_number) }}" @disabled(!$canManageSettings)>
                            <div class="hint">Use the TIN that should be used for EFRIS fiscal documents.</div>
                            @error('efris_tin') <div class="error">{{ $message }}</div> @enderror
                        </div>
                        <div class="field">
                            <label for="efris_legal_name">Legal Registered Name</label>
                            <input id="efris_legal_name" type="text" name="efris_legal_name" value="{{ old('efris_legal_name', $settings->efris_legal_name ?: $client?->name) }}" @disabled(!$canManageSettings)>
                            @error('efris_legal_name') <div class="error">{{ $message }}</div> @enderror
                        </div>
                        <div class="field">
                            <label for="efris_business_name">Trading / Display Name</label>
                            <input id="efris_business_name" type="text" name="efris_business_name" value="{{ old('efris_business_name', $settings->efris_business_name ?: $client?->name) }}" @disabled(!$canManageSettings)>
                            @error('efris_business_name') <div class="error">{{ $message }}</div> @enderror
                        </div>
                        <div class="field">
                            <label for="efris_branch_code">EFRIS Branch Code</label>
                            <input id="efris_branch_code" type="text" name="efris_branch_code" value="{{ old('efris_branch_code', $settings->efris_branch_code ?: $branch?->code) }}" @disabled(!$canManageSettings)>
                            <div class="hint">Use the branch identifier expected in your URA compliance profile.</div>
                            @error('efris_branch_code') <div class="error">{{ $message }}</div> @enderror
                        </div>
                        <div class="field">
                            <label for="efris_device_serial">Device / Terminal Serial</label>
                            <input id="efris_device_serial" type="text" name="efris_device_serial" value="{{ old('efris_device_serial', $settings->efris_device_serial) }}" @disabled(!$canManageSettings)>
                            <div class="hint">Keep this ready for the live integration phase if URA assigns a fiscal device or terminal identifier.</div>
                            @error('efris_device_serial') <div class="error">{{ $message }}</div> @enderror
                        </div>
                        <div class="field full">
                            <label for="efris_auth_url">EFRIS Auth URL</label>
                            <input id="efris_auth_url" type="url" name="efris_auth_url" value="{{ old('efris_auth_url', $settings->efris_auth_url) }}" @disabled(!$canManageSettings)>
                            <div class="hint">Optional. Leave blank if URA or your accredited integration path does not require a separate token endpoint.</div>
                            @error('efris_auth_url') <div class="error">{{ $message }}</div> @enderror
                        </div>
                        <div class="field">
                            <label for="efris_submission_url">EFRIS Sale Submission URL</label>
                            <input id="efris_submission_url" type="url" name="efris_submission_url" value="{{ old('efris_submission_url', $settings->efris_submission_url) }}" @disabled(!$canManageSettings)>
                            @error('efris_submission_url') <div class="error">{{ $message }}</div> @enderror
                        </div>
                        <div class="field">
                            <label for="efris_reversal_url">EFRIS Reversal URL</label>
                            <input id="efris_reversal_url" type="url" name="efris_reversal_url" value="{{ old('efris_reversal_url', $settings->efris_reversal_url) }}" @disabled(!$canManageSettings)>
                            @error('efris_reversal_url') <div class="error">{{ $message }}</div> @enderror
                        </div>
                        <div class="field">
                            <label for="efris_username">EFRIS Username</label>
                            <input id="efris_username" type="text" name="efris_username" value="{{ old('efris_username', $settings->efris_username) }}" autocomplete="off" @disabled(!$canManageSettings)>
                            @error('efris_username') <div class="error">{{ $message }}</div> @enderror
                        </div>
                        <div class="field">
                            <label for="efris_password">EFRIS Password</label>
                            <input id="efris_password" type="password" name="efris_password" value="{{ old('efris_password') }}" autocomplete="new-password" @disabled(!$canManageSettings)>
                            <div class="hint">Saved encrypted in the database. Leave blank to keep the current saved password.</div>
                            @error('efris_password') <div class="error">{{ $message }}</div> @enderror
                        </div>
                        <div class="field">
                            <label for="efris_client_id">EFRIS Client ID</label>
                            <input id="efris_client_id" type="text" name="efris_client_id" value="{{ old('efris_client_id', $settings->efris_client_id) }}" autocomplete="off" @disabled(!$canManageSettings)>
                            <div class="hint">Use this if URA or your accredited integration path issues a client/application identifier.</div>
                            @error('efris_client_id') <div class="error">{{ $message }}</div> @enderror
                        </div>
                        <div class="field">
                            <label for="efris_client_secret">EFRIS Client Secret</label>
                            <input id="efris_client_secret" type="password" name="efris_client_secret" value="{{ old('efris_client_secret') }}" autocomplete="new-password" @disabled(!$canManageSettings)>
                            <div class="hint">Saved encrypted in the database. Leave blank to keep the current saved secret.</div>
                            @error('efris_client_secret') <div class="error">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="status-banner {{ $efrisChecklist['ready'] ? 'ready' : 'warn' }}">
                        @if ($efrisChecklist['ready'])
                            <strong>EFRIS setup is ready for {{ strtoupper($efrisChecklist['transport']) }} processing.</strong>
                            This client has the minimum required details needed for the selected connector mode.
                        @else
                            <strong>EFRIS setup still needs attention.</strong>
                            Missing required items for {{ strtoupper($efrisChecklist['transport']) }} mode:
                            {{ implode(', ', $efrisChecklist['missing_required']) }}.
                        @endif
                    </div>

                    <div class="check-grid">
                        @foreach ($efrisChecklist['checks'] as $check)
                            <div class="check-card {{ $check['ready'] ? 'ready' : 'missing' }}">
                                <div class="check-meta">
                                    {{ $check['required'] ? 'Required' : 'Optional' }}
                                </div>
                                <strong>{{ $check['label'] }}</strong>
                                <div class="hint" style="margin-top:0;">
                                    {{ $check['ready'] ? 'Ready' : 'Missing' }}.
                                    {{ $check['help'] }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="panel-subtitle">The platform owner has not enabled the URA / EFRIS module for this client package yet, so these compliance settings stay locked out of the normal pharmacy setup flow.</p>
                @endif
            </div>

            <div class="section-grid">
                <section class="panel">
                    <h2>Cash Drawer Control</h2>
                    @if ($cashDrawerModuleEnabled)
                        <p class="panel-subtitle">Set the drawer alert threshold for this branch. The cash drawer workspace will count the opening balance, approved cash sales, same-day cash collections for invoices raised today, and documented draws only. Bank, mobile money, and other non-cash methods stay out of this drawer figure.</p>

                        <div class="field-grid">
                            <div class="field">
                                <label for="cash_drawer_alert_threshold">Alert Threshold Amount</label>
                                <input
                                    id="cash_drawer_alert_threshold"
                                    type="number"
                                    name="cash_drawer_alert_threshold"
                                    min="0"
                                    step="0.01"
                                    value="{{ old('cash_drawer_alert_threshold', $settings->cash_drawer_alert_threshold) }}"
                                    @disabled(!$canManageSettings)
                                >
                                <div class="hint">When the tracked drawer balance reaches or exceeds this amount, cashiers and admins will be warned to draw money out and record a reason.</div>
                                @error('cash_drawer_alert_threshold') <div class="error">{{ $message }}</div> @enderror
                            </div>
                            <div class="field">
                                <label>Daily Opening Balance</label>
                                <div class="logo-preview-card" style="align-items:flex-start;">
                                    <div class="logo-empty" style="border-style:solid; color:#067647; border-color:#abefc6; background:#ecfdf3;">Daily</div>
                                    <div>
                                        <strong style="display:block; margin-bottom:6px;">Managed In Cash Drawer Workspace</strong>
                                        <div class="hint" style="margin-top:0;">
                                            Some branches start at zero, and others start with float. Users can set today&apos;s opening balance directly on the Cash Drawer screen without changing sales or accounting entries.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @else
                        <p class="panel-subtitle">The platform owner has not enabled the Cash Drawer Control module for this client package yet, so drawer thresholds and alerts stay unavailable in pharmacy settings.</p>
                    @endif
                </section>

                <section class="panel">
                    <h2>Print Identity</h2>
                    <p class="panel-subtitle">These controls will shape how receipts, invoices, and reports present your pharmacy identity.</p>

                    <div class="field-grid">
                        <div class="field">
                            <label for="currency_symbol">Currency Symbol</label>
                            <input id="currency_symbol" type="text" name="currency_symbol" value="{{ old('currency_symbol', $settings->currency_symbol ?? 'UGX') }}" @disabled(!$canManageSettings)>
                            @error('currency_symbol') <div class="error">{{ $message }}</div> @enderror
                        </div>
                        <div class="field">
                            <label for="tax_label">Tax Label</label>
                            <input id="tax_label" type="text" name="tax_label" value="{{ old('tax_label', $settings->tax_label ?? 'TIN') }}" @disabled(!$canManageSettings)>
                            @error('tax_label') <div class="error">{{ $message }}</div> @enderror
                        </div>
                        <div class="field full">
                            <label for="tax_number">Tax Number</label>
                            <input id="tax_number" type="text" name="tax_number" value="{{ old('tax_number', $settings->tax_number) }}" @disabled(!$canManageSettings)>
                            @error('tax_number') <div class="error">{{ $message }}</div> @enderror
                        </div>
                        <div class="field full">
                            <label for="receipt_header">Receipt Header</label>
                            <input id="receipt_header" type="text" name="receipt_header" value="{{ old('receipt_header', $settings->receipt_header) }}" @disabled(!$canManageSettings)>
                            @error('receipt_header') <div class="error">{{ $message }}</div> @enderror
                        </div>
                        <div class="field full">
                            <label for="receipt_footer">Receipt Footer</label>
                            <textarea id="receipt_footer" name="receipt_footer" @disabled(!$canManageSettings)>{{ old('receipt_footer', $settings->receipt_footer) }}</textarea>
                            @error('receipt_footer') <div class="error">{{ $message }}</div> @enderror
                        </div>
                        <div class="field full">
                            <label for="invoice_footer">Invoice Footer</label>
                            <textarea id="invoice_footer" name="invoice_footer" @disabled(!$canManageSettings)>{{ old('invoice_footer', $settings->invoice_footer) }}</textarea>
                            @error('invoice_footer') <div class="error">{{ $message }}</div> @enderror
                        </div>
                        <div class="field full">
                            <label for="report_footer">Report Footer</label>
                            <textarea id="report_footer" name="report_footer" @disabled(!$canManageSettings)>{{ old('report_footer', $settings->report_footer) }}</textarea>
                            @error('report_footer') <div class="error">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </section>

                <section class="panel">
                    <h2>Print & Entry Options</h2>
                    <p class="panel-subtitle">These affect print availability and the purchase-entry line controls already active in the system.</p>

                    <div class="field-grid" style="margin-bottom:16px;">
                        <div class="field">
                            <label for="default_line_count">Default Purchase Lines</label>
                            <select id="default_line_count" name="default_line_count" @disabled(!$canManageSettings)>
                                @foreach ([1,2,3,4,5,6,7,8,9,10] as $lineCount)
                                    <option value="{{ $lineCount }}" @selected((int) old('default_line_count', $settings->default_line_count ?? 1) === $lineCount)>{{ $lineCount }}</option>
                                @endforeach
                            </select>
                            @error('default_line_count') <div class="error">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="checkbox-grid">
                        @php
                            $checkboxes = [
                                ['name' => 'allow_small_receipt', 'title' => 'Allow Small Receipt', 'text' => 'Enable compact receipt printing.'],
                                ['name' => 'allow_large_receipt', 'title' => 'Allow Large Receipt', 'text' => 'Enable A4-style receipt printing.'],
                                ['name' => 'allow_small_invoice', 'title' => 'Allow Small Invoice', 'text' => 'Enable compact invoice printing.'],
                                ['name' => 'allow_large_invoice', 'title' => 'Allow Large Invoice', 'text' => 'Enable A4-style invoice printing.'],
                                ['name' => 'allow_small_proforma', 'title' => 'Allow Small Proforma', 'text' => 'Enable compact proforma printing.'],
                                ['name' => 'allow_large_proforma', 'title' => 'Allow Large Proforma', 'text' => 'Enable A4-style proforma printing.'],
                                ['name' => 'hide_discount_line_on_print', 'title' => 'Hide Discount Line', 'text' => 'Suppress the separate discount row when printing.'],
                                ['name' => 'show_logo_on_print', 'title' => 'Show Logo On Print', 'text' => 'Display the configured logo on printouts when available.'],
                                ['name' => 'show_branch_contacts_on_print', 'title' => 'Show Branch Contacts', 'text' => 'Print the active branch phone, email, and address.'],
                                ['name' => 'allow_add_one_line', 'title' => 'Allow Add One Line', 'text' => 'Keep the quick single-line button on purchase entry.'],
                                ['name' => 'allow_add_five_lines', 'title' => 'Allow Add Five Lines', 'text' => 'Keep the quick five-line button on purchase entry.'],
                            ];
                        @endphp

                        @foreach ($checkboxes as $checkbox)
                            <div class="checkbox-card">
                                <label>
                                    <input type="checkbox" name="{{ $checkbox['name'] }}" value="1" @checked(old($checkbox['name'], $settings->{$checkbox['name']})) @disabled(!$canManageSettings)>
                                    <span>
                                        <strong>{{ $checkbox['title'] }}</strong>
                                        <span>{{ $checkbox['text'] }}</span>
                                    </span>
                                </label>
                            </div>
                        @endforeach
                    </div>
                </section>
            </div>

            <div class="panel">
                <h2>Save Settings</h2>
                <p class="panel-subtitle">These settings apply to the active client and active branch context you are working in.</p>

                <div class="actions">
                    <a href="{{ route('dashboard') }}" class="btn btn-light">Back To Dashboard</a>
                    @if ($canManageSettings)
                        <button type="submit" class="btn btn-primary">Save Settings</button>
                    @endif
                </div>
            </div>
        </form>

        @if ($showEfrisOperations)
            <div class="panel">
                <h2>EFRIS Sync Queue</h2>
                <p class="panel-subtitle">
                    This queue tracks the fiscal documents prepared for the active client. The current processor mode is
                    <strong style="display:inline;">{{ strtoupper($efrisTransport) }}</strong>.
                    Automatic processing can run every minute through the Laravel scheduler, and you can also trigger up to {{ $efrisBatchLimit }} records manually here.
                </p>

                <div class="summary-grid">
                    <div class="stat-card">
                        <strong>Ready</strong>
                        <span>{{ $efrisSummary['ready'] ?? 0 }}</span>
                    </div>
                    <div class="stat-card">
                        <strong>Accepted</strong>
                        <span>{{ $efrisSummary['accepted'] ?? 0 }}</span>
                    </div>
                    <div class="stat-card">
                        <strong>Failed</strong>
                        <span>{{ $efrisSummary['failed'] ?? 0 }}</span>
                    </div>
                    <div class="stat-card">
                        <strong>Submitted</strong>
                        <span>{{ $efrisSummary['submitted'] ?? 0 }}</span>
                    </div>
                    <div class="stat-card">
                        <strong>Reversal Queue</strong>
                        <span>{{ $efrisSummary['reversal_ready'] ?? 0 }}</span>
                    </div>
                </div>

                @if ($canManageSettings)
                    <div class="queue-actions">
                        <form method="POST" action="{{ route('settings.efris.process') }}">
                            @csrf
                            <input type="hidden" name="scope" value="ready">
                            <button type="submit" class="btn btn-primary">Process Pending EFRIS</button>
                        </form>
                        <form method="POST" action="{{ route('settings.efris.process') }}">
                            @csrf
                            <input type="hidden" name="scope" value="failed">
                            <button type="submit" class="btn btn-light">Retry Failed EFRIS</button>
                        </form>
                        <form method="POST" action="{{ route('settings.efris.process') }}">
                            @csrf
                            <input type="hidden" name="scope" value="all">
                            <button type="submit" class="btn btn-light">Process Ready And Failed</button>
                        </form>
                    </div>
                @endif

                @if ($recentEfrisDocuments->isNotEmpty())
                    <div class="table-wrap" style="margin-top:18px;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Reference</th>
                                    <th>Sale</th>
                                    <th>Status</th>
                                    <th>Next Action</th>
                                    <th>Attempts</th>
                                    <th>Last Update</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($recentEfrisDocuments as $document)
                                    <tr>
                                        <td>{{ $document->reference_number ?? 'N/A' }}</td>
                                        <td>
                                            {{ $document->sale?->invoice_number ?? 'Sale #' . $document->sale_id }}
                                            @if ($document->sale?->receipt_number)
                                                <div class="hint" style="margin-top:4px;">Receipt: {{ $document->sale->receipt_number }}</div>
                                            @endif
                                        </td>
                                        <td>{{ $document->statusLabel() }}</td>
                                        <td>{{ $document->next_action === 'complete' ? 'Complete' : str_replace('_', ' ', $document->next_action) }}</td>
                                        <td>{{ (int) $document->attempt_count }}</td>
                                        <td>{{ optional($document->updated_at)->format('d M Y H:i') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="hint" style="margin-top:16px;">No EFRIS documents have been prepared for this client yet.</div>
                @endif
            </div>
        @endif
    </main>
</body>
</html>
