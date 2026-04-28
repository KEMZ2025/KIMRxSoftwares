<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Owner Workspace - KIM Rx</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; background: #f4f6fb; color: #1f2937; }
        .layout { display: flex; min-height: 100vh; }
        .content { flex: 1; width: 100%; max-width: 100%; min-width: 0; padding: 24px; margin-left: 260px; transition: margin-left 0.3s ease; }
        .content.expanded { margin-left: 80px; }
        .topbar, .panel { width: 100%; max-width: 100%; background: #fff; border-radius: 18px; padding: 22px; box-shadow: 0 10px 28px rgba(0, 0, 0, 0.06); margin-bottom: 22px; overflow: hidden; }
        .panel-head { display:flex; justify-content:space-between; align-items:flex-start; gap:14px; flex-wrap:wrap; margin-bottom:18px; }
        .hero { display:grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap:18px; }
        .card-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap:14px; }
        .card { background:#f8fafc; border:1px solid #e5e7eb; border-radius:16px; padding:16px; }
        .card h4 { margin:0 0 8px; font-size:12px; text-transform:uppercase; letter-spacing:0.04em; color:#64748b; }
        .card p { margin:0; font-size:22px; font-weight:700; line-height:1.25; overflow-wrap:anywhere; }
        .muted { color:#64748b; font-size:13px; }
        .context-box { padding:18px; border-radius:16px; background:linear-gradient(135deg, #eff6ff, #ecfeff); border:1px solid #c7d2fe; }
        .context-box h3 { margin:0 0 8px; }
        .context-box p { margin:0; color:#334155; line-height:1.6; }
        .form-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:16px; }
        .field label { display:block; margin-bottom:6px; font-weight:700; }
        .field select, .field input, .field textarea { width:100%; padding:12px 14px; border:1px solid #dbe3ef; border-radius:12px; background:#fff; font:inherit; }
        .field textarea { min-height: 112px; resize: vertical; }
        .field.full { grid-column: 1 / -1; }
        .btn { display:inline-flex; align-items:center; justify-content:center; padding:10px 14px; border-radius:10px; border:none; color:#fff; text-decoration:none; cursor:pointer; font-weight:700; }
        .btn-primary { background:#1f7a4f; }
        .btn-secondary { background:#3949ab; }
        .alert-success, .alert-error, .alert-warning { padding:12px 14px; border-radius:12px; margin-bottom:16px; font-weight:600; }
        .alert-success { background:#e7f6ec; color:#166534; }
        .alert-error { background:#fef2f2; color:#b91c1c; }
        .alert-warning { background:#fff7ed; color:#9a3412; }
        .list-panel { display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:16px; margin-top:18px; }
        .client-card { border:1px solid #e5e7eb; border-radius:16px; padding:16px; background:#fcfdff; }
        .client-card h4 { margin:0 0 6px; }
        .client-card-header { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap; }
        .client-badges { display:flex; gap:8px; flex-wrap:wrap; }
        .pill { display:inline-flex; align-items:center; padding:6px 10px; border-radius:999px; font-size:12px; font-weight:700; }
        .pill-type-demo { background:#ede9fe; color:#6d28d9; }
        .pill-type-paying { background:#dbeafe; color:#1d4ed8; }
        .pill-type-trial { background:#fef3c7; color:#92400e; }
        .pill-type-internal { background:#e5e7eb; color:#334155; }
        .pill-subscription-active { background:#dcfce7; color:#166534; }
        .pill-subscription-grace { background:#fef3c7; color:#92400e; }
        .pill-subscription-overdue { background:#fee2e2; color:#b91c1c; }
        .pill-subscription-suspended { background:#e5e7eb; color:#334155; }
        .client-card ul { margin:10px 0 0; padding-left:18px; color:#475569; }
        .client-card li { margin-bottom:6px; }
        .client-metrics { display:grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap:10px; margin-top:14px; }
        .metric-card { padding:12px; border-radius:12px; background:#f8fafc; border:1px solid #e5e7eb; }
        .metric-card strong { display:block; font-size:16px; }
        .actions-inline { display:flex; gap:10px; flex-wrap:wrap; }
        .support-settings-panel { margin-top: 22px; }
        .summary-strip { display:grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap:14px; margin-top:22px; }
        .summary-strip .card { min-height: 128px; }
        .insight-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:16px; margin-top:18px; }
        .insight-card { border:1px solid #e5e7eb; border-radius:16px; padding:16px; background:#fcfdff; }
        .insight-card h3 { margin:0 0 8px; font-size:17px; }
        .insight-card p { margin:0; color:#475569; line-height:1.6; }
        .insight-list { list-style:none; margin:14px 0 0; padding:0; }
        .insight-list li { display:flex; justify-content:space-between; gap:14px; padding:10px 0; border-bottom:1px solid #e5e7eb; }
        .insight-list li:last-child { border-bottom:none; }
        .insight-list strong { color:#0f172a; }
        .attention-item { padding:12px 0; border-bottom:1px solid #e5e7eb; }
        .attention-item:last-child { border-bottom:none; }
        .attention-item h4 { margin:0 0 6px; font-size:15px; }
        .attention-tags { display:flex; gap:8px; flex-wrap:wrap; margin-top:8px; }
        .tag { display:inline-flex; align-items:center; padding:5px 9px; border-radius:999px; font-size:12px; font-weight:700; background:#e2e8f0; color:#334155; }
        .tag-critical { background:#fee2e2; color:#b91c1c; }
        .tag-warning { background:#fef3c7; color:#92400e; }
        .tag-info { background:#dbeafe; color:#1d4ed8; }
        .sandbox-card {
            margin-top: 18px;
            padding: 18px;
            border-radius: 16px;
            background: linear-gradient(135deg, #fef3c7, #fff7ed);
            border: 1px solid #fdba74;
        }
        .sandbox-card h3 { margin: 0 0 8px; }
        .sandbox-card p { margin: 0; color: #7c2d12; line-height: 1.6; }
        @media (max-width: 1100px) {
            .hero { grid-template-columns: 1fr; }
        }
        @media (max-width: 980px) {
            .layout { display:block; }
            .content, .content.expanded { margin-left:0; padding:16px; }
            .hero, .form-grid, .card-grid, .list-panel { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="layout">
    @include('layouts.sidebar')

    <main class="content" id="mainContent">
        <section class="topbar">
            <h3 style="margin:0 0 8px;">Owner Workspace</h3>
            <p style="margin:0; color:#64748b;">Owner account: {{ $user->name }} | Active client: {{ $clientName }} | Active branch: {{ $branchName }}</p>
        </section>

        <section class="panel">
            <div class="panel-head">
                <div>
                    <h2 style="margin:0 0 6px;">Run The Platform From One Owner Login</h2>
                    <p class="muted" style="margin:0;">This is the platform owner home. Stay here to manage the product, or switch into a client and branch only when you want to use tenant-level screens.</p>
                </div>
                <div class="actions-inline">
                    <a href="{{ route('admin.platform.clients.index') }}" class="btn btn-secondary">Client Setup</a>
                    @if($hasTenantContext)
                        <a href="{{ route('dashboard') }}" class="btn btn-secondary">Open Client Dashboard</a>
                    @endif
                </div>
            </div>

            @if(session('success'))
                <div class="alert-success">{{ session('success') }}</div>
            @endif

            @if(session('warning'))
                <div class="alert-warning">{{ session('warning') }}</div>
            @endif

            @if($errors->any())
                <div class="alert-error">{{ $errors->first() }}</div>
            @endif

            <div class="hero">
                <div class="card-grid">
                    <div class="card">
                        <h4>Platform Owner</h4>
                        <p>{{ $user->name }}</p>
                        <div class="muted" style="margin-top:8px;">{{ $user->email }}</div>
                    </div>
                    <div class="card">
                        <h4>Active Client</h4>
                        <p>{{ $clientName }}</p>
                    </div>
                    <div class="card">
                        <h4>Active Branch</h4>
                        <p>{{ $branchName }}</p>
                    </div>
                    <div class="card">
                        <h4>Platform Reach</h4>
                        <p>{{ number_format($clientCount) }} Clients</p>
                        <div class="muted" style="margin-top:8px;">{{ number_format($branchCount) }} Active branches</div>
                    </div>
                </div>

                <div class="context-box">
                    <h3>Switch Client Context</h3>
                    <p>You still own every feature, but tenant screens should only open inside the client and branch you intentionally choose. Clear the context any time to return to the owner workspace only.</p>

                    <form method="POST" action="{{ route('admin.platform.update') }}" style="margin-top:18px;">
                        @csrf
                        @method('PUT')

                        <div class="form-grid">
                            <div class="field">
                                <label for="client_id">Client</label>
                                <select id="client_id" name="client_id" required>
                                    @foreach($clients as $client)
                                        <option value="{{ $client->id }}" {{ (int) old('client_id', $selectedClient?->id) === (int) $client->id ? 'selected' : '' }}>
                                            {{ $client->name }} - {{ $client->displayClientType() }} / {{ $client->displaySubscriptionStatus() }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('client_id')<small class="alert-error" style="display:block; margin-top:8px;">{{ $message }}</small>@enderror
                            </div>

                            <div class="field">
                                <label for="branch_id">Branch</label>
                                <select id="branch_id" name="branch_id" required></select>
                                @error('branch_id')<small class="alert-error" style="display:block; margin-top:8px;">{{ $message }}</small>@enderror
                            </div>
                        </div>

                        <div style="margin-top:18px; display:flex; justify-content:flex-end;">
                            <button type="submit" class="btn btn-primary">Use This Context</button>
                        </div>
                    </form>

                    <form method="POST" action="{{ route('admin.platform.clear') }}" style="margin-top:12px;">
                        @csrf
                        @method('DELETE')

                        <div class="actions-inline" style="justify-content:flex-end;">
                            <button type="submit" class="btn btn-secondary">Return To Owner Workspace</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="sandbox-card">
                <h3>Built-In Testing Sandbox</h3>
                <p>The platform owner sandbox is a separate system client reserved for testing features, workflows, and reports without touching a paying client.</p>
                <div class="actions-inline" style="margin-top:14px;">
                    <form method="POST" action="{{ route('admin.platform.sandbox') }}">
                        @csrf
                        <button type="submit" class="btn btn-primary">Open Sandbox</button>
                    </form>
                    <div class="muted" style="align-self:center;">
                        {{ $sandboxClient->name }} | {{ $sandboxBranch->name }}
                    </div>
                </div>
            </div>

            <div class="summary-strip">
                <div class="card">
                    <h4>Paying Clients</h4>
                    <p>{{ number_format($payingClients) }}</p>
                    <div class="muted" style="margin-top:8px;">{{ number_format($subscriptionSummary[\App\Models\Client::STATUS_ACTIVE] ?? 0) }} currently in active subscription state</div>
                </div>
                <div class="card">
                    <h4>Trial And Demo</h4>
                    <p>{{ number_format($trialClients + $demoClients) }}</p>
                    <div class="muted" style="margin-top:8px;">{{ number_format($trialClients) }} trial | {{ number_format($demoClients) }} demo</div>
                </div>
                <div class="card">
                    <h4>Renewals Due Soon</h4>
                    <p>{{ number_format($renewalsDueSoon) }}</p>
                    <div class="muted" style="margin-top:8px;">{{ number_format($expiredRenewals) }} already expired</div>
                </div>
                <div class="card">
                    <h4>Attention Queue</h4>
                    <p>{{ number_format(($subscriptionSummary[\App\Models\Client::STATUS_OVERDUE] ?? 0) + ($subscriptionSummary[\App\Models\Client::STATUS_SUSPENDED] ?? 0) + $clientsAtSeatLimit) }}</p>
                    <div class="muted" style="margin-top:8px;">Overdue, suspended, or full-seat clients needing follow-up</div>
                </div>
                <div class="card">
                    <h4>Active Seats Used</h4>
                    <p>{{ number_format($activeSeatsUsed) }}</p>
                    <div class="muted" style="margin-top:8px;">
                        @if($seatCapacity > 0)
                            {{ number_format($remainingSeats) }} remaining across limited packages
                        @else
                            All current client packages are unlimited
                        @endif
                    </div>
                </div>
                <div class="card">
                    <h4>Latest Backup</h4>
                    <p style="font-size:20px;">{{ $backupSummary['latest']?->created_at?->format('d M Y') ?? 'None yet' }}</p>
                    <div class="muted" style="margin-top:8px;">
                        @if($backupSummary['latest'])
                            {{ $backupSummary['latest']->formattedSize() }} | {{ $backupSummary['ready_count'] }} ready archive(s)
                        @else
                            Create a full backup before the next risky change
                        @endif
                    </div>
                </div>
            </div>

            <div class="insight-grid">
                <div class="insight-card">
                    <h3>Package Mix</h3>
                    <p>See how the platform is distributed across your commercial presets right now.</p>
                    <ul class="insight-list">
                        @foreach($packageMix as $packageRow)
                            <li>
                                <span>{{ $packageRow['label'] }}</span>
                                <strong>{{ number_format($packageRow['count']) }}</strong>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="insight-card">
                    <h3>Subscription Health</h3>
                    <p>This gives the owner a fast picture of who is healthy, late, or blocked.</p>
                    <ul class="insight-list">
                        <li><span>Active</span><strong>{{ number_format($subscriptionSummary[\App\Models\Client::STATUS_ACTIVE] ?? 0) }}</strong></li>
                        <li><span>Grace Period</span><strong>{{ number_format($subscriptionSummary[\App\Models\Client::STATUS_GRACE] ?? 0) }}</strong></li>
                        <li><span>Overdue</span><strong>{{ number_format($subscriptionSummary[\App\Models\Client::STATUS_OVERDUE] ?? 0) }}</strong></li>
                        <li><span>Suspended</span><strong>{{ number_format($subscriptionSummary[\App\Models\Client::STATUS_SUSPENDED] ?? 0) }}</strong></li>
                        <li><span>Missing Renewal Date</span><strong>{{ number_format($clientsMissingRenewalDate) }}</strong></li>
                    </ul>
                </div>

                <div class="insight-card">
                    <h3>Seat Pressure</h3>
                    <p>This shows whether package user caps are close to blocking new staff accounts.</p>
                    <ul class="insight-list">
                        <li><span>Seat-Limited Clients</span><strong>{{ number_format($seatLimitedClients) }}</strong></li>
                        <li><span>Clients At Seat Limit</span><strong>{{ number_format($clientsAtSeatLimit) }}</strong></li>
                        <li><span>Total Used Seats</span><strong>{{ number_format($activeSeatsUsed) }}</strong></li>
                        <li><span>Total Finite Capacity</span><strong>{{ $seatCapacity > 0 ? number_format($seatCapacity) : 'Unlimited only' }}</strong></li>
                        <li><span>Remaining Finite Seats</span><strong>{{ $seatCapacity > 0 ? number_format($remainingSeats) : 'N/A' }}</strong></li>
                    </ul>
                </div>

                <div class="insight-card">
                    <h3>Backup Health</h3>
                    <p>Keep this green before deployments, imports, or any broad platform changes.</p>
                    <ul class="insight-list">
                        <li><span>Ready Backups</span><strong>{{ number_format($backupSummary['ready_count']) }}</strong></li>
                        <li><span>Restored Backups</span><strong>{{ number_format($backupSummary['restored_count']) }}</strong></li>
                        <li><span>Missing Backup Files</span><strong>{{ number_format($backupSummary['missing_count']) }}</strong></li>
                        <li>
                            <span>Latest Archive</span>
                            <strong>{{ $backupSummary['latest']?->created_at?->format('d M Y H:i') ?? 'None yet' }}</strong>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="insight-grid">
                <div class="insight-card" style="grid-column: 1 / -1;">
                    <h3>Owner Attention Board</h3>
                    <p>These are the first clients to check when renewals are close, subscriptions have slipped, or seat limits are already full.</p>
                    @forelse($attentionClients as $attentionRow)
                        @php
                            $attentionClient = $attentionRow['client'];
                        @endphp
                        <div class="attention-item">
                            <h4>{{ $attentionClient->name }}</h4>
                            <div class="muted">
                                {{ $attentionClient->displayClientType() }} | {{ $attentionClient->displaySubscriptionStatus() }} | {{ $attentionClient->subscriptionRenewalLabel() }}
                            </div>
                            <div class="attention-tags">
                                @foreach($attentionRow['alerts'] as $alert)
                                    @php
                                        $tagClass = str_contains(strtolower($alert), 'suspended') || str_contains(strtolower($alert), 'overdue') || str_contains(strtolower($alert), 'expired')
                                            ? 'tag-critical'
                                            : (str_contains(strtolower($alert), 'seat') || str_contains(strtolower($alert), 'grace') || str_contains(strtolower($alert), 'renewal')
                                                ? 'tag-warning'
                                                : 'tag-info');
                                    @endphp
                                    <span class="tag {{ $tagClass }}">{{ $alert }}</span>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <div class="muted" style="margin-top:12px;">No clients are currently in the attention queue.</div>
                    @endforelse
                </div>
            </div>

            <div class="panel support-settings-panel">
                <div class="panel-head">
                    <div>
                        <h2 style="margin:0 0 6px;">Support Contacts Shown To Clients</h2>
                        <p class="muted" style="margin:0;">These details appear on the in-system Support screen for pharmacy users whenever they need help from KIM Software Systems.</p>
                    </div>
                    <div class="muted">
                        @if($supportSettingsUpdatedAt)
                            Last updated: {{ $supportSettingsUpdatedAt->format('d M Y H:i') }}
                        @else
                            Using fallback system defaults
                        @endif
                    </div>
                </div>

                <form method="POST" action="{{ route('admin.platform.support.update') }}">
                    @csrf
                    @method('PUT')

                    <div class="form-grid">
                        <div class="field">
                            <label for="support_company_name">Company Name</label>
                            <input type="text" id="support_company_name" name="company_name" value="{{ old('company_name', $supportSettings['company_name']) }}" placeholder="KIM SOFTWARE SYSTEMS">
                        </div>

                        <div class="field">
                            <label for="support_contact_person">Contact Person</label>
                            <input type="text" id="support_contact_person" name="contact_person" value="{{ old('contact_person', $supportSettings['contact_person']) }}" placeholder="Support Desk">
                        </div>

                        <div class="field">
                            <label for="support_phone_primary">Primary Phone</label>
                            <input type="text" id="support_phone_primary" name="phone_primary" value="{{ old('phone_primary', $supportSettings['phone_primary']) }}" placeholder="+256 700 000000">
                        </div>

                        <div class="field">
                            <label for="support_phone_secondary">Secondary Phone</label>
                            <input type="text" id="support_phone_secondary" name="phone_secondary" value="{{ old('phone_secondary', $supportSettings['phone_secondary']) }}" placeholder="+256 701 000000">
                        </div>

                        <div class="field">
                            <label for="support_email">Support Email</label>
                            <input type="email" id="support_email" name="email" value="{{ old('email', $supportSettings['email']) }}" placeholder="support@example.com">
                        </div>

                        <div class="field">
                            <label for="support_whatsapp">WhatsApp</label>
                            <input type="text" id="support_whatsapp" name="whatsapp" value="{{ old('whatsapp', $supportSettings['whatsapp']) }}" placeholder="+256 700 000000">
                        </div>

                        <div class="field">
                            <label for="support_website">Website</label>
                            <input type="url" id="support_website" name="website" value="{{ old('website', $supportSettings['website']) }}" placeholder="https://example.com">
                        </div>

                        <div class="field">
                            <label for="support_hours">Support Hours</label>
                            <input type="text" id="support_hours" name="hours" value="{{ old('hours', $supportSettings['hours']) }}" placeholder="Monday - Saturday, 8:00 AM - 6:00 PM">
                        </div>

                        <div class="field full">
                            <label for="support_response_note">Support Guidance Note</label>
                            <textarea id="support_response_note" name="response_note" placeholder="Tell staff what details they should share when contacting support.">{{ old('response_note', $supportSettings['response_note']) }}</textarea>
                        </div>
                    </div>

                    <div class="actions-inline" style="justify-content:flex-end; margin-top:18px;">
                        <button type="submit" class="btn btn-primary">Save Support Contacts</button>
                    </div>
                </form>
            </div>

            <div class="list-panel">
                @foreach($clients as $client)
                    <div class="client-card">
                        <div class="client-card-header">
                            <div>
                                <h4>{{ $client->name }}</h4>
                                <div class="muted">
                                    {{ $client->email ?: 'No email set' }}{{ $client->phone ? ' | ' . $client->phone : '' }}
                                </div>
                            </div>
                            <div class="client-badges">
                                <span class="pill pill-type-{{ $client->client_type }}">{{ $client->displayClientType() }}</span>
                                <span class="pill pill-subscription-{{ $client->subscription_status }}">{{ $client->displaySubscriptionStatus() }}</span>
                            </div>
                        </div>
                        <div class="client-metrics">
                            <div class="metric-card">
                                <span class="muted">User Seats</span>
                                <strong>{{ number_format($client->active_users_count) }} / {{ $client->activeUserLimitLabel() }}</strong>
                            </div>
                            <div class="metric-card">
                                <span class="muted">Renewal</span>
                                <strong>{{ $client->subscription_ends_at ? $client->subscription_ends_at->format('d M Y') : 'Not set' }}</strong>
                            </div>
                            <div class="metric-card">
                                <span class="muted">Access</span>
                                <strong>{{ $client->is_active ? 'Enabled' : 'Suspended' }}</strong>
                            </div>
                        </div>
                        <div class="muted" style="margin-top:12px;">{{ $client->branches->count() }} active branch{{ $client->branches->count() === 1 ? '' : 'es' }}</div>
                        <ul>
                            @forelse($client->branches as $branch)
                                <li>{{ $branch->name }}{{ $branch->is_main ? ' (Main)' : '' }}</li>
                            @empty
                                <li>No active branches yet.</li>
                            @endforelse
                        </ul>
                    </div>
                @endforeach
            </div>
        </section>
    </main>
</div>

<script>
(() => {
    const branchMap = @json($branchMap);
    const clientSelect = document.getElementById('client_id');
    const branchSelect = document.getElementById('branch_id');
    const initialBranchId = @json(old('branch_id', $selectedBranch?->id));

    function renderBranches(selectedClientId, selectedBranchId) {
        const branches = branchMap[selectedClientId] || [];
        branchSelect.innerHTML = '';

        branches.forEach((branch, index) => {
            const option = document.createElement('option');
            option.value = branch.id;
            option.textContent = branch.name + (branch.is_main ? ' (Main)' : '');

            if (String(branch.id) === String(selectedBranchId) || (!selectedBranchId && index === 0)) {
                option.selected = true;
            }

            branchSelect.appendChild(option);
        });
    }

    clientSelect.addEventListener('change', () => {
        renderBranches(clientSelect.value, null);
    });

    renderBranches(clientSelect.value, initialBranchId);
})();
</script>
</body>
</html>
