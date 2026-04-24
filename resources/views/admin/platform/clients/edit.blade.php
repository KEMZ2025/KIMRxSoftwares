<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Client - KIM Rx</title>
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
        .btn-view { background:#0f766e; }
        .summary { display:grid; grid-template-columns: repeat(4, minmax(180px, 1fr)); gap:14px; margin-bottom:20px; }
        .summary-card { background:#f8fafc; border:1px solid #e5e7eb; border-radius:14px; padding:14px; }
        .summary-card h4 { margin:0 0 8px; font-size:12px; text-transform:uppercase; letter-spacing:0.04em; color:#64748b; }
        .summary-card p { margin:0; font-size:18px; font-weight:700; }
        .form-grid { display:grid; grid-template-columns: repeat(2, minmax(240px, 1fr)); gap:16px; }
        .field-span { grid-column: 1 / -1; }
        .field label { display:block; margin-bottom:6px; font-weight:700; }
        .field input, .field select, .field textarea { width:100%; padding:12px 14px; border:1px solid #dbe3ef; border-radius:12px; }
        .section-title { margin:22px 0 10px; padding-top:22px; border-top:1px solid #e5e7eb; }
        .section-copy { margin:0 0 14px; color:#64748b; }
        .feature-grid { display:grid; grid-template-columns: repeat(3, minmax(220px, 1fr)); gap:14px; }
        .feature-grid-tight { grid-template-columns: repeat(3, minmax(200px, 1fr)); }
        .feature-card { display:grid; gap:10px; padding:16px; border:1px solid #dbe3ef; border-radius:16px; background:#f8fafc; }
        .feature-card-compact { min-height: 138px; }
        .feature-check { display:flex; align-items:center; gap:10px; font-weight:800; color:#172033; }
        .feature-check input { width:18px; height:18px; }
        .feature-description { color:#64748b; font-size:13px; line-height:1.5; }
        .feature-grid.is-disabled { opacity:0.55; }
        .hint { display:block; margin-top:6px; color:#64748b; font-size:12px; }
        .error { display:block; margin-top:6px; color:#b91c1c; font-size:12px; font-weight:700; }
        @media (max-width: 900px) {
            .layout { display:block; }
            .content, .content.expanded { margin-left:0; padding:16px; }
            .summary, .form-grid, .feature-grid, .feature-grid-tight { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="layout">
    @include('layouts.sidebar')

    <main class="content" id="mainContent">
        <section class="topbar">
            <h3 style="margin:0 0 8px;">Edit Client</h3>
            <p style="margin:0; color:#64748b;">Update the client profile and operating mode.</p>
        </section>

        <section class="panel">
            <div class="panel-head">
                <div>
                    <h2 style="margin:0 0 6px;">{{ $managedClient->name }}</h2>
                    <p style="margin:0; color:#64748b;">You can still manage branches separately after saving client details.</p>
                </div>
                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <a href="{{ route('admin.platform.branches.index', $managedClient) }}" class="btn btn-view">Branches</a>
                    <a href="{{ route('admin.platform.clients.index') }}" class="btn btn-back">Back to Clients</a>
                </div>
            </div>

            <div class="summary">
                <div class="summary-card"><h4>Client Email</h4><p>{{ $managedClient->email ?: 'Not set' }}</p></div>
                <div class="summary-card"><h4>Mode</h4><p>{{ str_replace('_', ' ', ucfirst($managedClient->business_mode)) }}</p></div>
                <div class="summary-card"><h4>Package</h4><p>{{ $managedClient->displayClientType() }}</p></div>
                <div class="summary-card"><h4>Status</h4><p>{{ $managedClient->is_active ? 'Active' : 'Inactive' }}</p></div>
            </div>

            <form method="POST" action="{{ route('admin.platform.clients.update', $managedClient) }}">
                @csrf
                @method('PUT')

                @include('admin.platform.clients._form', ['managedClient' => $managedClient])

                <div style="margin-top:20px; display:flex; justify-content:flex-end;">
                    <button type="submit" class="btn btn-save">Save Client</button>
                </div>
            </form>
        </section>
    </main>
</div>

<script>
(() => {
    const accountingToggle = document.querySelector('input[name="accounts_enabled"]');
    const accountingGrid = document.getElementById('accountingFeatureGrid');

    if (!accountingToggle || !accountingGrid) {
        return;
    }

    function syncAccountingFeatureState() {
        const enabled = accountingToggle.checked;
        accountingGrid.classList.toggle('is-disabled', !enabled);

        accountingGrid.querySelectorAll('input[type="checkbox"]').forEach((checkbox) => {
            checkbox.disabled = !enabled;
        });
    }

    accountingToggle.addEventListener('change', syncAccountingFeatureState);
    syncAccountingFeatureState();
})();
</script>
</body>
</html>
