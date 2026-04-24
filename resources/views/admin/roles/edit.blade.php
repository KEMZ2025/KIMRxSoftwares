<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Role - KIM Rx</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; background: #f4f6fb; color: #1f2937; }
        .layout { display:flex; min-height:100vh; }
        .content { flex:1; padding:24px; margin-left:260px; transition: margin-left 0.3s ease; }
        .content.expanded { margin-left:80px; }
        .topbar, .panel { background:#fff; border-radius:18px; padding:22px; box-shadow:0 10px 28px rgba(0,0,0,0.06); margin-bottom:22px; }
        .panel-head { display:flex; justify-content:space-between; align-items:flex-start; gap:14px; flex-wrap:wrap; margin-bottom:18px; }
        .btn { display:inline-flex; align-items:center; justify-content:center; padding:10px 14px; border-radius:10px; border:none; color:#fff; text-decoration:none; cursor:pointer; font-weight:700; }
        .btn-back { background:#3949ab; }
        .btn-save { background:#1f7a4f; }
        .summary { display:grid; grid-template-columns: repeat(3, minmax(180px, 1fr)); gap:14px; margin-bottom:20px; }
        .summary-card { background:#f8fafc; border:1px solid #e5e7eb; border-radius:14px; padding:14px; }
        .summary-card h4 { margin:0 0 8px; font-size:12px; text-transform:uppercase; letter-spacing:0.04em; color:#64748b; }
        .summary-card p { margin:0; font-size:18px; font-weight:700; }
        .locked-note { margin-bottom:18px; padding:12px 14px; border-radius:12px; background:#eff6ff; color:#1d4ed8; font-weight:600; }
        .form-grid { display:grid; grid-template-columns: repeat(2, minmax(240px, 1fr)); gap:16px; }
        .field label { display:block; margin-bottom:6px; font-weight:700; }
        .field input { width:100%; padding:12px 14px; border:1px solid #dbe3ef; border-radius:12px; }
        .hint { display:block; margin-top:6px; color:#64748b; font-size:12px; }
        .error { display:block; margin-top:6px; color:#b91c1c; font-size:12px; font-weight:700; }
        .permissions-panel { margin-top:22px; padding-top:22px; border-top:1px solid #e5e7eb; }
        .permissions-head h3 { margin:0 0 6px; }
        .permissions-head p { margin:0 0 14px; color:#64748b; }
        .permission-group { margin-bottom:20px; }
        .permission-group h4 { margin:0 0 10px; font-size:15px; }
        .permission-grid { display:grid; grid-template-columns: repeat(2, minmax(250px, 1fr)); gap:12px; }
        .permission-card { display:flex; gap:12px; align-items:flex-start; padding:14px; border:1px solid #dbe3ef; border-radius:14px; background:#f8fafc; cursor:pointer; }
        .permission-card input { margin-top:4px; }
        .permission-card p { margin:6px 0; color:#475569; font-size:13px; }
        .permission-card small { color:#64748b; }
        @media (max-width: 900px) {
            .layout { display:block; }
            .content, .content.expanded { margin-left:0; padding:16px; }
            .summary, .form-grid, .permission-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="layout">
    @include('layouts.sidebar')

    <main class="content" id="mainContent">
        <section class="topbar">
            <h3 style="margin:0 0 8px;">Edit Role</h3>
            <p style="margin:0; color:#64748b;">Client: {{ $clientName }} | Branch: {{ $branchName }}</p>
        </section>

        <section class="panel">
            <div class="panel-head">
                <div>
                    <h2 style="margin:0 0 6px;">{{ $role->name }}</h2>
                    <p style="margin:0; color:#64748b;">Review which screens this role can use and adjust access safely.</p>
                </div>
                <a href="{{ route('admin.roles.index') }}" class="btn btn-back">Back to Roles</a>
            </div>

            <div class="summary">
                <div class="summary-card"><h4>Users Assigned</h4><p>{{ $role->users()->count() }}</p></div>
                <div class="summary-card"><h4>Permissions</h4><p>{{ $role->permissions->count() }}</p></div>
                <div class="summary-card"><h4>Type</h4><p>{{ $role->is_system_role ? 'System' : 'Custom' }}</p></div>
            </div>

            @if($role->isProtectedAdminRole())
                <div class="locked-note">The Admin role always keeps full access. Its screen permissions are locked so the system never loses an administrator.</div>
            @endif

            <form method="POST" action="{{ route('admin.roles.update', $role->id) }}">
                @csrf
                @method('PUT')

                @include('admin.roles._form')

                <div style="margin-top:20px; display:flex; justify-content:flex-end;">
                    <button type="submit" class="btn btn-save">Save Role</button>
                </div>
            </form>
        </section>
    </main>
</div>
</body>
</html>
