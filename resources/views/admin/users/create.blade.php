<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add User - KIM Rx</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; background: #f4f6fb; color: #1f2937; }
        .layout { display: flex; min-height: 100vh; }
        .content { flex: 1; padding: 24px; margin-left: 260px; transition: margin-left 0.3s ease; }
        .content.expanded { margin-left: 80px; }
        .topbar, .panel { background: #fff; border-radius: 18px; padding: 22px; box-shadow: 0 10px 28px rgba(0, 0, 0, 0.06); margin-bottom: 22px; }
        .panel-head { display:flex; justify-content:space-between; align-items:flex-start; gap:14px; flex-wrap:wrap; margin-bottom:18px; }
        .btn { display:inline-flex; align-items:center; justify-content:center; padding:10px 14px; border-radius:10px; border:none; color:#fff; text-decoration:none; cursor:pointer; font-weight:700; }
        .btn-back { background:#3949ab; }
        .btn-save { background:#1f7a4f; }
        .alert-info, .alert-warning { padding:12px 14px; border-radius:12px; margin-bottom:18px; font-weight:600; }
        .alert-info { background:#eff6ff; color:#1d4ed8; }
        .alert-warning { background:#fff7ed; color:#9a3412; }
        .form-grid { display:grid; grid-template-columns: repeat(2, minmax(240px, 1fr)); gap:16px; }
        .field label { display:block; margin-bottom:6px; font-weight:700; }
        .field input, .field select { width:100%; padding:12px 14px; border:1px solid #dbe3ef; border-radius:12px; }
        .hint { display:block; margin-top:6px; color:#64748b; font-size:12px; }
        .error { display:block; margin-top:6px; color:#b91c1c; font-size:12px; font-weight:700; }
        .roles-panel { margin-top:22px; padding-top:22px; border-top:1px solid #e5e7eb; }
        .roles-head h3 { margin:0 0 6px; }
        .roles-head p { margin:0 0 14px; color:#64748b; }
        .roles-grid { display:grid; grid-template-columns: repeat(2, minmax(240px, 1fr)); gap:14px; }
        .role-card { display:flex; gap:12px; align-items:flex-start; padding:14px; border:1px solid #dbe3ef; border-radius:14px; background:#f8fafc; cursor:pointer; }
        .role-card input { margin-top:4px; }
        .role-card-head { display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin-bottom:6px; }
        .role-card p { margin:0 0 6px; color:#475569; font-size:13px; }
        .role-card small { color:#64748b; }
        .pill { display:inline-flex; align-items:center; padding:4px 8px; border-radius:999px; background:#ede9fe; color:#6d28d9; font-size:11px; font-weight:700; }
        @media (max-width: 900px) {
            .layout { display:block; }
            .content, .content.expanded { margin-left:0; padding:16px; }
            .form-grid, .roles-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="layout">
    @include('layouts.sidebar')

    <main class="content" id="mainContent">
        <section class="topbar">
            <h3 style="margin:0 0 8px;">Add User</h3>
            <p style="margin:0; color:#64748b;">Client: {{ $clientName }} | Branch: {{ $branchName }}</p>
        </section>

        <section class="panel">
            <div class="panel-head">
                <div>
                    <h2 style="margin:0 0 6px;">New Staff Account</h2>
                    <p style="margin:0; color:#64748b;">Create a user, pick the branch, and decide which roles control their screens.</p>
                </div>
                <a href="{{ route('admin.users.index') }}" class="btn btn-back">Back to Users</a>
            </div>

            @if($userSeatSummary['is_full'])
                <div class="alert-warning">
                    This client package is full at {{ $userSeatSummary['used'] }} / {{ $userSeatSummary['limit_label'] }} active users. You can still prepare an inactive account now, then activate it later when a seat opens.
                </div>
            @else
                <div class="alert-info">
                    User seats in use: {{ $userSeatSummary['used'] }} / {{ $userSeatSummary['limit_label'] }}. Remaining seats: {{ $userSeatSummary['remaining_label'] }}.
                </div>
            @endif

            <form method="POST" action="{{ route('admin.users.store') }}">
                @csrf

                @include('admin.users._form')

                <div style="margin-top:20px; display:flex; justify-content:flex-end;">
                    <button type="submit" class="btn btn-save">Create User</button>
                </div>
            </form>
        </section>
    </main>
</div>
</body>
</html>
