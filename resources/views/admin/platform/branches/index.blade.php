<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Branches - KIM Rx</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; background: #f4f6fb; color: #1f2937; }
        .layout { display: flex; min-height: 100vh; }
        .content { flex: 1; padding: 24px; margin-left: 260px; transition: margin-left 0.3s ease; }
        .content.expanded { margin-left: 80px; }
        .topbar, .panel { background: #fff; border-radius: 18px; padding: 22px; box-shadow: 0 10px 28px rgba(0, 0, 0, 0.06); margin-bottom: 22px; }
        .panel-head { display:flex; justify-content:space-between; align-items:flex-start; gap:14px; flex-wrap:wrap; margin-bottom:18px; }
        .btn { display:inline-flex; align-items:center; justify-content:center; gap:8px; padding:10px 14px; border-radius:10px; border:none; color:#fff; text-decoration:none; cursor:pointer; font-size:14px; font-weight:700; }
        .btn-primary { background:#1f7a4f; }
        .btn-secondary { background:#2563eb; }
        .btn-edit { background:#7c3aed; }
        .muted { color:#64748b; font-size:13px; }
        .alert-success { background:#e7f6ec; color:#166534; padding:12px 14px; border-radius:12px; margin-bottom:16px; font-weight:600; }
        .table-wrap { overflow-x:auto; }
        table { width:100%; border-collapse:collapse; min-width:980px; }
        th, td { padding:13px 12px; border-bottom:1px solid #e5e7eb; text-align:left; vertical-align:top; }
        th { background:#f8fafc; font-size:12px; color:#475569; text-transform:uppercase; letter-spacing:0.04em; }
        .pill { display:inline-flex; align-items:center; padding:6px 10px; border-radius:999px; font-size:12px; font-weight:700; }
        .pill-active { background:#dcfce7; color:#166534; }
        .pill-inactive { background:#fee2e2; color:#b91c1c; }
        .pill-main { background:#ede9fe; color:#6d28d9; }
        .actions { display:flex; gap:8px; flex-wrap:wrap; }
        @media (max-width: 900px) {
            .layout { display:block; }
            .content, .content.expanded { margin-left:0; padding:16px; }
        }
    </style>
</head>
<body>
<div class="layout">
    @include('layouts.sidebar')

    <main class="content" id="mainContent">
        <section class="topbar">
            <h3 style="margin:0 0 8px;">Branches</h3>
            <p style="margin:0; color:#64748b;">Client: {{ $managedClient->name }}</p>
        </section>

        <section class="panel">
            <div class="panel-head">
                <div>
                    <h2 style="margin:0 0 6px;">{{ $managedClient->name }} Branches</h2>
                    <p class="muted" style="margin:0;">Add more branches for the same client and choose which one is the main branch.</p>
                </div>
                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <a href="{{ route('admin.platform.clients.edit', $managedClient) }}" class="btn btn-secondary">Edit Client</a>
                    <a href="{{ route('admin.platform.branches.create', $managedClient) }}" class="btn btn-primary">Add Branch</a>
                </div>
            </div>

            @if(session('success'))
                <div class="alert-success">{{ session('success') }}</div>
            @endif

            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>Branch</th>
                        <th>Contact</th>
                        <th>Mode</th>
                        <th>Main</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($branches as $branch)
                        <tr>
                            <td>{{ $loop->iteration + ($branches->currentPage() - 1) * $branches->perPage() }}</td>
                            <td>
                                <strong>{{ $branch->name }}</strong><br>
                                <span class="muted">{{ $branch->code ?: 'No code' }}</span>
                            </td>
                            <td>{{ $branch->email ?: 'No email' }}{{ $branch->phone ? ' | ' . $branch->phone : '' }}</td>
                            <td>
                                <strong>{{ $branch->effectiveBusinessModeLabel() }}</strong><br>
                                <span class="muted">{{ $branch->business_mode === 'inherit' ? 'Inherited from client' : 'Branch override' }}</span>
                            </td>
                            <td>
                                @if($branch->is_main)
                                    <span class="pill pill-main">Main</span>
                                @else
                                    <span class="muted">No</span>
                                @endif
                            </td>
                            <td>
                                <span class="pill {{ $branch->is_active ? 'pill-active' : 'pill-inactive' }}">
                                    {{ $branch->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td>
                                <div class="actions">
                                    <a href="{{ route('admin.platform.branches.edit', [$managedClient, $branch]) }}" class="btn btn-edit">Edit</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="muted">No branches found.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <div style="margin-top:16px;">
                {{ $branches->withQueryString()->links() }}
            </div>
        </section>
    </main>
</div>
</body>
</html>
