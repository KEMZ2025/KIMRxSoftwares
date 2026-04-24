<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Setup - KIM Rx</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; background: #f4f6fb; color: #1f2937; }
        .layout { display: flex; min-height: 100vh; }
        .content { flex: 1; padding: 24px; margin-left: 260px; transition: margin-left 0.3s ease; }
        .content.expanded { margin-left: 80px; }
        .topbar, .panel { background: #fff; border-radius: 18px; padding: 22px; box-shadow: 0 10px 28px rgba(0, 0, 0, 0.06); margin-bottom: 22px; }
        .panel-head { display:flex; justify-content:space-between; align-items:flex-start; gap:14px; flex-wrap:wrap; margin-bottom:18px; }
        .summary-grid { display:grid; grid-template-columns: repeat(4, minmax(180px, 1fr)); gap:16px; margin-bottom:18px; }
        .summary-card { background:#f8fafc; border:1px solid #e5e7eb; border-radius:14px; padding:14px; }
        .summary-card h4 { margin:0 0 8px; font-size:13px; color:#64748b; }
        .summary-card p { margin:0; font-size:26px; font-weight:700; }
        .summary-card small { display:block; margin-top:6px; color:#64748b; font-size:12px; }
        .btn { display:inline-flex; align-items:center; justify-content:center; gap:8px; padding:10px 14px; border-radius:10px; border:none; color:#fff; text-decoration:none; cursor:pointer; font-size:14px; font-weight:700; }
        .btn-primary { background:#1f7a4f; }
        .btn-secondary { background:#2563eb; }
        .btn-edit { background:#7c3aed; }
        .btn-ok { background:#0f766e; }
        .muted { color:#64748b; font-size:13px; }
        .alert-success { background:#e7f6ec; color:#166534; padding:12px 14px; border-radius:12px; margin-bottom:16px; font-weight:600; }
        .search-form { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:16px; }
        .search-form input, .search-form select { flex:1; min-width:180px; padding:12px 14px; border:1px solid #dbe3ef; border-radius:12px; }
        .table-wrap { overflow-x:auto; }
        table { width:100%; border-collapse:collapse; min-width:1320px; }
        th, td { padding:13px 12px; border-bottom:1px solid #e5e7eb; text-align:left; vertical-align:top; }
        th { background:#f8fafc; font-size:12px; color:#475569; text-transform:uppercase; letter-spacing:0.04em; }
        .pill { display:inline-flex; align-items:center; padding:6px 10px; border-radius:999px; font-size:12px; font-weight:700; }
        .pill-active { background:#dcfce7; color:#166534; }
        .pill-inactive { background:#fee2e2; color:#b91c1c; }
        .pill-type-demo { background:#ede9fe; color:#6d28d9; }
        .pill-type-paying { background:#dbeafe; color:#1d4ed8; }
        .pill-type-trial { background:#fef3c7; color:#92400e; }
        .pill-type-internal { background:#e5e7eb; color:#334155; }
        .pill-subscription-active { background:#dcfce7; color:#166534; }
        .pill-subscription-grace { background:#fef3c7; color:#92400e; }
        .pill-subscription-overdue { background:#fee2e2; color:#b91c1c; }
        .pill-subscription-suspended { background:#e5e7eb; color:#334155; }
        .actions { display:flex; gap:8px; flex-wrap:wrap; }
        .stack { display:flex; gap:8px; flex-wrap:wrap; }
        @media (max-width: 900px) {
            .layout { display:block; }
            .content, .content.expanded { margin-left:0; padding:16px; }
            .summary-grid { grid-template-columns:1fr; }
        }
    </style>
</head>
<body>
<div class="layout">
    @include('layouts.sidebar')

    <main class="content" id="mainContent">
        <section class="topbar">
            <h3 style="margin:0 0 8px;">Client Setup</h3>
            <p style="margin:0; color:#64748b;">Platform owner area for creating paying clients and their branches. The built-in sandbox stays separate.</p>
        </section>

        <section class="panel">
            <div class="panel-head">
                <div>
                    <h2 style="margin:0 0 6px;">Live Clients</h2>
                    <p class="muted" style="margin:0;">Create a new pharmacy client, open its branches, and prepare it for user setup. The owner sandbox is not counted here.</p>
                </div>
                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <a href="{{ route('admin.platform.index') }}" class="btn btn-secondary">Owner Workspace</a>
                    <a href="{{ route('admin.platform.clients.create') }}" class="btn btn-primary">Add Client</a>
                </div>
            </div>

            @if(session('success'))
                <div class="alert-success">{{ session('success') }}</div>
            @endif

            <div class="summary-grid">
                <div class="summary-card"><h4>Total Clients</h4><p>{{ $totalClients }}</p><small>{{ $totalBranches }} total branches</small></div>
                <div class="summary-card"><h4>Paying Clients</h4><p>{{ $payingClients }}</p><small>{{ $activeClients }} currently active</small></div>
                <div class="summary-card"><h4>Trials And Demos</h4><p>{{ $trialClients + $demoClients }}</p><small>{{ $trialClients }} trial | {{ $demoClients }} demo</small></div>
                <div class="summary-card"><h4>Need Attention</h4><p>{{ $attentionClients }}</p><small>Overdue or subscription-suspended</small></div>
                <div class="summary-card"><h4>Seat-Limited Clients</h4><p>{{ $seatLimitedClients }}</p><small>Packages with active user caps</small></div>
            </div>

            <form method="GET" action="{{ route('admin.platform.clients.index') }}" class="search-form">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search client by name, email, or phone...">
                <select name="client_type">
                    <option value="">All Client Types</option>
                    @foreach($clientTypes as $value => $label)
                        <option value="{{ $value }}" {{ request('client_type') === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="subscription_status">
                    <option value="">All Subscription Statuses</option>
                    @foreach($subscriptionStatuses as $value => $label)
                        <option value="{{ $value }}" {{ request('subscription_status') === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-secondary">Search</button>
            </form>

            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>Client</th>
                        <th>Mode</th>
                        <th>Branches</th>
                        <th>Package</th>
                        <th>Seats</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($clients as $client)
                        <tr>
                            <td>{{ $loop->iteration + ($clients->currentPage() - 1) * $clients->perPage() }}</td>
                            <td>
                                <strong>{{ $client->name }}</strong><br>
                                <span class="muted">{{ $client->email ?: 'No email' }}{{ $client->phone ? ' | ' . $client->phone : '' }}</span>
                            </td>
                            <td>{{ str_replace('_', ' ', ucfirst($client->business_mode)) }}</td>
                            <td>{{ $client->branches_count }} total | {{ $client->active_branches_count }} active</td>
                            <td>
                                <div class="stack">
                                    <span class="pill pill-type-{{ $client->client_type }}">{{ $client->displayClientType() }}</span>
                                    <span class="pill pill-subscription-{{ $client->subscription_status }}">{{ $client->displaySubscriptionStatus() }}</span>
                                </div>
                                <div class="muted" style="margin-top:8px;">
                                    {{ $client->subscription_ends_at ? 'Ends ' . $client->subscription_ends_at->format('d M Y') : 'No renewal date set' }}
                                </div>
                            </td>
                            <td>
                                <strong>{{ number_format($client->active_users_count) }}</strong>
                                <span class="muted">active</span><br>
                                <span class="muted">
                                    {{ $client->hasUnlimitedActiveUsers() ? 'Unlimited seats' : number_format((int) $client->active_user_limit) . ' seat package' }}
                                </span>
                            </td>
                            <td>
                                <div class="stack">
                                    <span class="pill {{ $client->is_active ? 'pill-active' : 'pill-inactive' }}">
                                        {{ $client->is_active ? 'Client Active' : 'Client Inactive' }}
                                    </span>
                                </div>
                                <div class="muted" style="margin-top:8px;">
                                    {{ $client->is_active ? 'Tenant access allowed' : 'Tenant access blocked' }}
                                </div>
                            </td>
                            <td>
                                <div class="actions">
                                    <a href="{{ route('admin.platform.clients.edit', $client) }}" class="btn btn-edit">Edit</a>
                                    <a href="{{ route('admin.platform.branches.index', $client) }}" class="btn btn-ok">Branches</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="muted">No clients found.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <div style="margin-top:16px;">
                {{ $clients->withQueryString()->links() }}
            </div>
        </section>
    </main>
</div>
</body>
</html>
