<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Roles - KIM Rx</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; background: #f4f6fb; color: #222; }
        .layout { display: flex; min-height: 100vh; }
        .content { flex: 1; padding: 24px; margin-left: 260px; transition: margin-left 0.3s ease; }
        .content.expanded { margin-left: 80px; }
        .topbar, .panel { background: #fff; border-radius: 18px; padding: 22px; box-shadow: 0 10px 28px rgba(0, 0, 0, 0.06); margin-bottom: 22px; }
        .panel-head { display:flex; justify-content:space-between; align-items:flex-start; gap:14px; flex-wrap:wrap; margin-bottom:18px; }
        .summary-grid { display:grid; grid-template-columns: repeat(4, minmax(180px, 1fr)); gap:16px; margin-bottom:18px; }
        .summary-card { background:#f8fafc; border:1px solid #e5e7eb; border-radius:14px; padding:14px; }
        .summary-card h4 { margin:0 0 8px; font-size:13px; color:#64748b; }
        .summary-card p { margin:0; font-size:26px; font-weight:700; }
        .btn { display:inline-flex; align-items:center; justify-content:center; gap:8px; padding:10px 14px; border-radius:10px; border:none; color:#fff; text-decoration:none; cursor:pointer; font-size:14px; font-weight:700; }
        .btn-primary { background:#1f7a4f; }
        .btn-secondary { background:#2563eb; }
        .btn-edit { background:#7c3aed; }
        .alert-success { background:#e7f6ec; color:#166534; padding:12px 14px; border-radius:12px; margin-bottom:16px; font-weight:600; }
        .table-wrap { overflow-x:auto; }
        table { width:100%; border-collapse:collapse; min-width:980px; }
        th, td { padding:13px 12px; border-bottom:1px solid #e5e7eb; text-align:left; vertical-align:top; }
        th { background:#f8fafc; font-size:12px; text-transform:uppercase; letter-spacing:0.04em; color:#475569; }
        .muted { color:#64748b; font-size:13px; }
        .pill { display:inline-flex; align-items:center; padding:6px 10px; border-radius:999px; font-size:12px; font-weight:700; }
        .pill-system { background:#ede9fe; color:#6d28d9; }
        .pill-custom { background:#e0f2fe; color:#075985; }
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
            <h3 style="margin:0 0 8px;">Role Administration</h3>
            <p style="margin:0; color:#64748b;">Client: {{ $clientName }} | Branch: {{ $branchName }}</p>
        </section>

        <section class="panel">
            <div class="panel-head">
                <div>
                    <h2 style="margin:0 0 6px;">Roles & Permissions</h2>
                    <p class="muted" style="margin:0;">Decide which screens and sensitive actions each staff role can use.</p>
                </div>
                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">Users</a>
                    <a href="{{ route('admin.roles.create') }}" class="btn btn-primary">Create Role</a>
                </div>
            </div>

            @if(session('success'))
                <div class="alert-success">{{ session('success') }}</div>
            @endif

            <div class="summary-grid">
                <div class="summary-card"><h4>Total Roles</h4><p>{{ $totalRoles }}</p></div>
                <div class="summary-card"><h4>System Roles</h4><p>{{ $systemRoles }}</p></div>
                <div class="summary-card"><h4>Custom Roles</h4><p>{{ $customRoles }}</p></div>
                <div class="summary-card"><h4>Permissions</h4><p>{{ $permissionCount }}</p></div>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>Role</th>
                        <th>Description</th>
                        <th>Permissions</th>
                        <th>Users</th>
                        <th>Type</th>
                        <th>Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($roles as $role)
                        <tr>
                            <td>{{ $loop->iteration + ($roles->currentPage() - 1) * $roles->perPage() }}</td>
                            <td><strong>{{ $role->name }}</strong><br><span class="muted">{{ $role->code }}</span></td>
                            <td>{{ $role->description ?: 'No description.' }}</td>
                            <td>{{ $role->permissions->count() }}</td>
                            <td>{{ $role->users_count }}</td>
                            <td>
                                <span class="pill {{ $role->is_system_role ? 'pill-system' : 'pill-custom' }}">
                                    {{ $role->is_system_role ? 'System' : 'Custom' }}
                                </span>
                            </td>
                            <td>
                                <a href="{{ route('admin.roles.edit', $role->id) }}" class="btn btn-edit">Edit</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="muted">No roles found.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <div style="margin-top:16px;">
                {{ $roles->withQueryString()->links() }}
            </div>
        </section>
    </main>
</div>
</body>
</html>
