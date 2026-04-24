<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users - KIM Rx</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; background: #f4f6fb; color: #222; }
        .layout { display: flex; min-height: 100vh; }
        .content { flex: 1; padding: 24px; margin-left: 260px; transition: margin-left 0.3s ease; }
        .content.expanded { margin-left: 80px; }
        .topbar, .panel { background: #fff; border-radius: 18px; padding: 22px; box-shadow: 0 10px 28px rgba(0, 0, 0, 0.06); margin-bottom: 22px; }
        .panel-head, .card-grid, .toolbar { display: flex; gap: 14px; flex-wrap: wrap; }
        .panel-head { justify-content: space-between; align-items: flex-start; margin-bottom: 18px; }
        .card-grid { display: grid; grid-template-columns: repeat(4, minmax(180px, 1fr)); gap: 16px; margin-bottom: 18px; }
        .card { background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 14px; padding: 14px; }
        .card h4 { margin: 0 0 8px; font-size: 13px; color: #64748b; }
        .card p { margin: 0; font-size: 26px; font-weight: 700; }
        .card small { display:block; margin-top:6px; color:#64748b; font-size:12px; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 10px 14px; border-radius: 10px; border: none; color: #fff; text-decoration: none; cursor: pointer; font-size: 14px; font-weight: 700; }
        .btn-primary { background: #1f7a4f; }
        .btn-secondary { background: #2563eb; }
        .btn-edit { background: #7c3aed; }
        .btn-warn { background: #b45309; }
        .btn-ok { background: #0f766e; }
        .alert-success, .alert-error, .alert-warning, .alert-info { padding: 12px 14px; border-radius: 12px; margin-bottom: 16px; font-weight: 600; }
        .alert-success { background: #e7f6ec; color: #166534; }
        .alert-error { background: #fef2f2; color: #b91c1c; }
        .alert-warning { background:#fff7ed; color:#9a3412; }
        .alert-info { background:#eff6ff; color:#1d4ed8; }
        .search-form { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 16px; }
        .search-form input { flex: 1; min-width: 260px; padding: 12px 14px; border: 1px solid #dbe3ef; border-radius: 12px; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 1080px; }
        th, td { padding: 13px 12px; border-bottom: 1px solid #e5e7eb; text-align: left; vertical-align: top; }
        th { background: #f8fafc; font-size: 12px; color: #475569; text-transform: uppercase; letter-spacing: 0.04em; }
        .status-pill, .role-pill { display: inline-flex; align-items: center; padding: 6px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; }
        .status-active { background: #dcfce7; color: #166534; }
        .status-inactive { background: #fee2e2; color: #b91c1c; }
        .role-pill { background: #ede9fe; color: #6d28d9; margin: 0 6px 6px 0; }
        .muted { color: #64748b; font-size: 13px; }
        .actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .inline-form { margin: 0; }
        @media (max-width: 900px) {
            .layout { display: block; }
            .content, .content.expanded { margin-left: 0; padding: 16px; }
            .card-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="layout">
    @include('layouts.sidebar')

    <main class="content" id="mainContent">
        <section class="topbar">
            <h3 style="margin:0 0 8px;">User Administration</h3>
            <p style="margin:0; color:#64748b;">Client: {{ $clientName }} | Branch: {{ $branchName }}</p>
        </section>

        <section class="panel">
            <div class="panel-head">
                <div>
                    <h2 style="margin:0 0 6px;">Users</h2>
                    <p class="muted" style="margin:0;">Create staff accounts, assign branches, and control screen access through roles.</p>
                </div>

                <div class="toolbar">
                    <a href="{{ route('admin.roles.index') }}" class="btn btn-secondary">Roles</a>
                    <a href="{{ route('admin.users.create') }}" class="btn btn-primary">Add User</a>
                </div>
            </div>

            @if(session('success'))
                <div class="alert-success">{{ session('success') }}</div>
            @endif

            @if($errors->any())
                <div class="alert-error">{{ $errors->first() }}</div>
            @endif

            <div class="card-grid">
                <div class="card"><h4>Total Users</h4><p>{{ $totalUsers }}</p></div>
                <div class="card"><h4>Active Users</h4><p>{{ $activeUsers }}</p></div>
                <div class="card"><h4>Inactive Users</h4><p>{{ $inactiveUsers }}</p></div>
                <div class="card"><h4>Administrators</h4><p>{{ $administrators }}</p></div>
                <div class="card">
                    <h4>User Seats</h4>
                    <p>{{ $userSeatSummary['used'] }} / {{ $userSeatSummary['limit_label'] }}</p>
                    <small>{{ $userSeatSummary['has_limit'] ? $userSeatSummary['remaining_label'] . ' remaining' : 'No active-user cap on this client package' }}</small>
                </div>
            </div>

            @if($userSeatSummary['is_full'])
                <div class="alert-warning">
                    This client package has reached its active-user seat limit. You can still create or edit inactive accounts, but activating another user is blocked until a seat opens or the package limit is raised.
                </div>
            @else
                <div class="alert-info">
                    Package seat usage: {{ $userSeatSummary['used'] }} active user{{ $userSeatSummary['used'] === 1 ? '' : 's' }} out of {{ $userSeatSummary['limit_label'] }}.
                </div>
            @endif

            <form method="GET" action="{{ route('admin.users.index') }}" class="search-form">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by name, email, role, or branch...">
                <button type="submit" class="btn btn-secondary">Search</button>
            </form>

            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>User</th>
                        <th>Branch</th>
                        <th>Roles</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($users as $managedUser)
                        <tr>
                            <td>{{ $loop->iteration + ($users->currentPage() - 1) * $users->perPage() }}</td>
                            <td>
                                <strong>{{ $managedUser->name }}</strong><br>
                                <span class="muted">{{ $managedUser->email }}</span>
                            </td>
                            <td>{{ $managedUser->branch?->name ?? 'Unassigned' }}</td>
                            <td>
                                @forelse($managedUser->roles as $role)
                                    <span class="role-pill">{{ $role->name }}</span>
                                @empty
                                    <span class="muted">No roles</span>
                                @endforelse
                            </td>
                            <td>
                                <span class="status-pill {{ $managedUser->is_active ? 'status-active' : 'status-inactive' }}">
                                    {{ $managedUser->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td>
                                <div class="actions">
                                    <a href="{{ route('admin.users.edit', $managedUser->id) }}" class="btn btn-edit">Edit</a>

                                    <form method="POST" action="{{ route('admin.users.status', $managedUser->id) }}" class="inline-form" onsubmit="return confirm('{{ $managedUser->is_active ? 'Deactivate' : 'Reactivate' }} this user account?');">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn {{ $managedUser->is_active ? 'btn-warn' : 'btn-ok' }}">
                                            {{ $managedUser->is_active ? 'Deactivate' : 'Activate' }}
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="muted">No users found.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <div style="margin-top:16px;">
                {{ $users->withQueryString()->links() }}
            </div>
        </section>
    </main>
</div>
</body>
</html>
