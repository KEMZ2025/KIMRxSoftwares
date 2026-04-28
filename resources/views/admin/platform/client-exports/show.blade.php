<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Client Export - KIM Rx</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; background: #f4f6fb; color: #1f2937; }
        .layout { display: flex; min-height: 100vh; }
        .content { flex: 1; padding: 24px; margin-left: 260px; transition: margin-left 0.3s ease; }
        .content.expanded { margin-left: 80px; }
        .topbar, .panel { background: #fff; border-radius: 18px; padding: 22px; box-shadow: 0 10px 28px rgba(0, 0, 0, 0.06); margin-bottom: 22px; }
        .panel-head { display:flex; justify-content:space-between; align-items:flex-start; gap:14px; flex-wrap:wrap; margin-bottom:18px; }
        .summary-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:16px; margin-bottom:18px; }
        .summary-card { background:#f8fafc; border:1px solid #e5e7eb; border-radius:14px; padding:14px; }
        .summary-card h4 { margin:0 0 8px; font-size:13px; color:#64748b; }
        .summary-card p { margin:0; font-size:22px; font-weight:700; line-height:1.25; }
        .summary-card small { display:block; margin-top:6px; color:#64748b; font-size:12px; }
        .btn { display:inline-flex; align-items:center; justify-content:center; gap:8px; padding:10px 14px; border-radius:10px; border:none; color:#fff; text-decoration:none; cursor:pointer; font-size:14px; font-weight:700; }
        .btn-primary { background:#1f7a4f; }
        .btn-secondary { background:#2563eb; }
        .muted { color:#64748b; font-size:13px; }
        .alert-success, .alert-error, .alert-warning { padding:12px 14px; border-radius:12px; margin-bottom:16px; font-weight:600; }
        .alert-success { background:#e7f6ec; color:#166534; }
        .alert-error { background:#fef2f2; color:#b91c1c; }
        .alert-warning { background:#fff7ed; color:#9a3412; }
        .info-card {
            padding: 18px;
            border-radius: 16px;
            background: linear-gradient(135deg, #eff6ff, #ecfeff);
            border: 1px solid #bfdbfe;
        }
        .info-card h3 { margin: 0 0 8px; }
        .info-card p { margin: 0; line-height: 1.6; color: #334155; }
        .detail-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:16px; }
        .detail-card { background:#f8fafc; border:1px solid #e5e7eb; border-radius:14px; padding:16px; }
        .detail-card h3 { margin:0 0 12px; font-size:16px; }
        .detail-row { display:flex; justify-content:space-between; gap:16px; padding:8px 0; border-bottom:1px solid #e5e7eb; }
        .detail-row:last-child { border-bottom:none; }
        .detail-row strong { color:#0f172a; text-align:right; }
        .table-wrap { overflow-x:auto; }
        table { width:100%; border-collapse:collapse; min-width:760px; }
        th, td { padding:13px 12px; border-bottom:1px solid #e5e7eb; text-align:left; vertical-align:top; }
        th { background:#f8fafc; font-size:12px; color:#475569; text-transform:uppercase; letter-spacing:0.04em; }
        .stack { display:flex; gap:8px; flex-wrap:wrap; }
        .pill { display:inline-flex; align-items:center; padding:6px 10px; border-radius:999px; font-size:12px; font-weight:700; }
        .pill-ready { background:#dcfce7; color:#166534; }
        .pill-missing { background:#fee2e2; color:#b91c1c; }
        @media (max-width: 900px) {
            .layout { display:block; }
            .content, .content.expanded { margin-left:0; padding:16px; }
            .summary-grid, .detail-grid { grid-template-columns:1fr; }
        }
    </style>
</head>
<body>
@php
    $formatBytes = static function (int $bytes): string {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        $units = ['KB', 'MB', 'GB', 'TB'];
        $size = $bytes / 1024;
        $unitIndex = 0;

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }

        return number_format($size, 2) . ' ' . $units[$unitIndex];
    };

    $databaseManifest = $manifest['database'] ?? [];
    $tables = $databaseManifest['tables'] ?? [];
    $fileManifest = $manifest['files'] ?? [];
    $fileItems = $fileManifest['items'] ?? [];
    $clientSnapshot = $manifest['client'] ?? [];
@endphp
<div class="layout">
    @include('layouts.sidebar')

    <main class="content" id="mainContent">
        <section class="topbar">
            <h3 style="margin:0 0 8px;">Review Client Export</h3>
            <p style="margin:0; color:#64748b;">Owner account: {{ $user->name }} | Active client: {{ $clientName }} | Active branch: {{ $branchName }}</p>
        </section>

        <section class="panel">
            <div class="panel-head">
                <div>
                    <h2 style="margin:0 0 6px;">{{ $clientExport->filename }}</h2>
                    <p class="muted" style="margin:0;">Review exactly what this tenant archive contains before you download it or share it for migration or handover work.</p>
                </div>
                <div class="stack">
                    <a href="{{ route('admin.platform.client-exports.index') }}" class="btn btn-secondary">Back To Client Exports</a>
                    @if($clientExport->fileExists())
                        <a href="{{ route('admin.platform.client-exports.download', $clientExport) }}" class="btn btn-primary">Download Archive</a>
                    @endif
                </div>
            </div>

            @if(session('success'))
                <div class="alert-success">{{ session('success') }}</div>
            @endif

            @if($errors->any())
                <div class="alert-error">{{ $errors->first() }}</div>
            @endif

            <div class="summary-grid">
                <div class="summary-card">
                    <h4>Status</h4>
                    <p><span class="pill pill-{{ $clientExport->status }}">{{ $clientExport->displayStatus() }}</span></p>
                    <small>{{ $clientExport->fileExists() ? 'Archive file is present on disk' : 'Archive file is missing from disk' }}</small>
                </div>
                <div class="summary-card">
                    <h4>Archive Size</h4>
                    <p>{{ $clientExport->formattedSize() }}</p>
                    <small>{{ strtoupper(str_replace('_', ' ', $clientExport->export_type)) }}</small>
                </div>
                <div class="summary-card">
                    <h4>Client</h4>
                    <p style="font-size:18px;">{{ $clientSnapshot['name'] ?? ($clientExport->client?->name ?? 'Unknown') }}</p>
                    <small>Client ID {{ $clientSnapshot['id'] ?? $clientExport->client_id ?? 'N/A' }}</small>
                </div>
                <div class="summary-card">
                    <h4>Database</h4>
                    <p>{{ number_format((int) $clientExport->database_tables_count) }} tables</p>
                    <small>{{ number_format((int) $clientExport->database_rows_count) }} rows exported</small>
                </div>
                <div class="summary-card">
                    <h4>Managed Files</h4>
                    <p>{{ number_format((int) $clientExport->storage_files_count) }}</p>
                    <small>{{ $formatBytes((int) $clientExport->storage_bytes) }} captured from client branding assets</small>
                </div>
                <div class="summary-card">
                    <h4>Created</h4>
                    <p style="font-size:18px;">{{ $clientExport->created_at?->format('d M Y H:i') ?? 'Unknown' }}</p>
                    <small>By {{ $clientExport->creator?->name ?? 'System' }}</small>
                </div>
            </div>

            <div class="detail-grid">
                <div class="detail-card">
                    <h3>Export Details</h3>
                    <div class="detail-row"><span>Filename</span><strong>{{ $clientExport->filename }}</strong></div>
                    <div class="detail-row"><span>Stored At</span><strong>{{ $clientExport->disk_path }}</strong></div>
                    <div class="detail-row"><span>Created By</span><strong>{{ $clientExport->creator?->name ?? 'System' }}</strong></div>
                    <div class="detail-row"><span>Export Note</span><strong>{{ $clientExport->notes ?: 'No note added.' }}</strong></div>
                    <div class="detail-row"><span>App Version</span><strong>{{ $manifest['app_version'] ?? 'Unknown' }}</strong></div>
                    <div class="detail-row"><span>Database</span><strong>{{ $manifest['database_connection'] ?? 'Unknown connection' }} | {{ $manifest['database_name'] ?? 'Unknown database' }}</strong></div>
                </div>

                <div class="detail-card">
                    <h3>Client Snapshot</h3>
                    <div class="detail-row"><span>Name</span><strong>{{ $clientSnapshot['name'] ?? 'Unknown' }}</strong></div>
                    <div class="detail-row"><span>Email</span><strong>{{ $clientSnapshot['email'] ?: 'Not set' }}</strong></div>
                    <div class="detail-row"><span>Phone</span><strong>{{ $clientSnapshot['phone'] ?: 'Not set' }}</strong></div>
                    <div class="detail-row"><span>Package</span><strong>{{ \App\Support\ClientPackagePresetCatalog::label($clientSnapshot['package_preset'] ?? null) }}</strong></div>
                    <div class="detail-row"><span>Client Type</span><strong>{{ \App\Models\Client::clientTypeOptions()[$clientSnapshot['client_type'] ?? ''] ?? 'Not set' }}</strong></div>
                    <div class="detail-row"><span>Subscription</span><strong>{{ \App\Models\Client::subscriptionStatusOptions()[$clientSnapshot['subscription_status'] ?? ''] ?? 'Not set' }}</strong></div>
                </div>
            </div>
        </section>

        <section class="panel">
            <div class="panel-head">
                <div>
                    <h2 style="margin:0 0 6px;">Export Scope</h2>
                    <p class="muted" style="margin:0;">This archive is a tenant export package. It is intended for review, handover, and future migration tooling, not direct client-only restore in this first version.</p>
                </div>
            </div>

            <div class="info-card">
                <h3>What This Archive Contains</h3>
                <p>The export captures rows directly owned by the selected client and linked child records such as user-role pivots and role-permission pivots. Managed public branding files, such as the client logo, are also included when they exist on disk.</p>
            </div>
        </section>

        <section class="panel">
            <div class="panel-head">
                <div>
                    <h2 style="margin:0 0 6px;">Database Table Manifest</h2>
                    <p class="muted" style="margin:0;">This list shows what was captured for the tenant database scope in this archive.</p>
                </div>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>Table</th>
                        <th>Rows</th>
                        <th>Schema File</th>
                        <th>Data File</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($tables as $table)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td><strong>{{ $table['name'] ?? 'Unknown' }}</strong></td>
                            <td>{{ number_format((int) ($table['rows'] ?? 0)) }}</td>
                            <td>{{ $table['schema_file'] ?? 'N/A' }}</td>
                            <td>{{ $table['data_file'] ?? 'N/A' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="muted">No database manifest entries were found in this archive.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel">
            <div class="panel-head">
                <div>
                    <h2 style="margin:0 0 6px;">Managed File Manifest</h2>
                    <p class="muted" style="margin:0;">Only client-specific managed branding assets are included in this first safe version.</p>
                </div>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>Source</th>
                        <th>Archived As</th>
                        <th>Size</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($fileItems as $item)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $item['source'] ?? 'Unknown' }}</td>
                            <td>{{ $item['target'] ?? 'Unknown' }}</td>
                            <td>{{ $formatBytes((int) ($item['bytes'] ?? 0)) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="muted">No managed branding files were present for this client in the export.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>
</body>
</html>
