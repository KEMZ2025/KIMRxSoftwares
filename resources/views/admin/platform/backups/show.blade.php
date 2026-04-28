<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Backup - KIM Rx</title>
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
        .btn-warning { background:#b45309; }
        .btn-danger { background:#b91c1c; }
        .muted { color:#64748b; font-size:13px; }
        .alert-success, .alert-error, .alert-warning { padding:12px 14px; border-radius:12px; margin-bottom:16px; font-weight:600; }
        .alert-success { background:#e7f6ec; color:#166534; }
        .alert-error { background:#fef2f2; color:#b91c1c; }
        .alert-warning { background:#fff7ed; color:#9a3412; }
        .warning-card {
            padding: 18px;
            border-radius: 16px;
            background: linear-gradient(135deg, #fff7ed, #fef2f2);
            border: 1px solid #fdba74;
        }
        .warning-card h3 { margin: 0 0 8px; }
        .warning-card p { margin: 0; line-height: 1.6; color: #7c2d12; }
        .detail-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:16px; }
        .detail-card { background:#f8fafc; border:1px solid #e5e7eb; border-radius:14px; padding:16px; }
        .detail-card h3 { margin:0 0 12px; font-size:16px; }
        .detail-row { display:flex; justify-content:space-between; gap:16px; padding:8px 0; border-bottom:1px solid #e5e7eb; }
        .detail-row:last-child { border-bottom:none; }
        .detail-row strong { color:#0f172a; }
        .form-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:16px; }
        .field label { display:block; margin-bottom:6px; font-weight:700; }
        .field input, .field textarea { width:100%; padding:12px 14px; border:1px solid #dbe3ef; border-radius:12px; background:#fff; font:inherit; }
        .field textarea { min-height: 110px; resize: vertical; }
        .field.full { grid-column: 1 / -1; }
        .checkbox-row { display:flex; align-items:flex-start; gap:10px; margin-top:12px; color:#334155; }
        .checkbox-row input { margin-top: 3px; }
        .table-wrap { overflow-x:auto; }
        table { width:100%; border-collapse:collapse; min-width:760px; }
        th, td { padding:13px 12px; border-bottom:1px solid #e5e7eb; text-align:left; vertical-align:top; }
        th { background:#f8fafc; font-size:12px; color:#475569; text-transform:uppercase; letter-spacing:0.04em; }
        .stack { display:flex; gap:8px; flex-wrap:wrap; }
        .pill { display:inline-flex; align-items:center; padding:6px 10px; border-radius:999px; font-size:12px; font-weight:700; }
        .pill-ready { background:#dcfce7; color:#166534; }
        .pill-restored { background:#dbeafe; color:#1d4ed8; }
        .pill-missing { background:#fee2e2; color:#b91c1c; }
        @media (max-width: 900px) {
            .layout { display:block; }
            .content, .content.expanded { margin-left:0; padding:16px; }
            .summary-grid, .detail-grid, .form-grid { grid-template-columns:1fr; }
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
@endphp
<div class="layout">
    @include('layouts.sidebar')

    <main class="content" id="mainContent">
        <section class="topbar">
            <h3 style="margin:0 0 8px;">Review Platform Backup</h3>
            <p style="margin:0; color:#64748b;">Owner account: {{ $user->name }} | Active client: {{ $clientName }} | Active branch: {{ $branchName }}</p>
        </section>

        <section class="panel">
            <div class="panel-head">
                <div>
                    <h2 style="margin:0 0 6px;">{{ $backup->filename }}</h2>
                    <p class="muted" style="margin:0;">Review exactly what this archive contains before you use it for a full platform recovery.</p>
                </div>
                <div class="stack">
                    <a href="{{ route('admin.platform.backups.index') }}" class="btn btn-secondary">Back To Backups</a>
                    @if($backup->fileExists())
                        <a href="{{ route('admin.platform.backups.download', $backup) }}" class="btn btn-primary">Download Archive</a>
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
                    <p><span class="pill pill-{{ $backup->status }}">{{ $backup->displayStatus() }}</span></p>
                    <small>{{ $backup->fileExists() ? 'Archive file is present on disk' : 'Archive file is missing from disk' }}</small>
                </div>
                <div class="summary-card">
                    <h4>Archive Size</h4>
                    <p>{{ $backup->formattedSize() }}</p>
                    <small>{{ strtoupper(str_replace('_', ' ', $backup->backup_type)) }}</small>
                </div>
                <div class="summary-card">
                    <h4>Database</h4>
                    <p>{{ number_format((int) $backup->database_tables_count) }} tables</p>
                    <small>{{ number_format((int) $backup->database_rows_count) }} rows exported</small>
                </div>
                <div class="summary-card">
                    <h4>Storage Files</h4>
                    <p>{{ number_format((int) $backup->storage_files_count) }}</p>
                    <small>{{ $formatBytes((int) $backup->storage_bytes) }} captured from storage/app</small>
                </div>
                <div class="summary-card">
                    <h4>Created</h4>
                    <p style="font-size:18px;">{{ $backup->created_at?->format('d M Y H:i') ?? 'Unknown' }}</p>
                    <small>By {{ $backup->creator?->name ?? 'System' }}</small>
                </div>
                <div class="summary-card">
                    <h4>App Version</h4>
                    <p style="font-size:18px;">{{ $manifest['app_version'] ?? 'Unknown' }}</p>
                    <small>{{ $manifest['database_connection'] ?? 'Unknown connection' }} | {{ $manifest['database_name'] ?? 'Unknown database' }}</small>
                </div>
            </div>

            <div class="detail-grid">
                <div class="detail-card">
                    <h3>Backup Details</h3>
                    <div class="detail-row"><span>Filename</span><strong>{{ $backup->filename }}</strong></div>
                    <div class="detail-row"><span>Stored At</span><strong>{{ $backup->disk_path }}</strong></div>
                    <div class="detail-row"><span>Created By</span><strong>{{ $backup->creator?->name ?? 'System' }}</strong></div>
                    <div class="detail-row"><span>Restore History</span><strong>{{ $backup->restored_at ? $backup->restored_at->format('d M Y H:i') : 'Not restored yet' }}</strong></div>
                    <div class="detail-row"><span>Restored By</span><strong>{{ $backup->restorer?->name ?? 'Not restored yet' }}</strong></div>
                    <div class="detail-row"><span>Backup Note</span><strong>{{ $backup->notes ?: 'No note added.' }}</strong></div>
                </div>

                <div class="detail-card">
                    <h3>Recovery Scope</h3>
                    <div class="detail-row"><span>Database Tables</span><strong>{{ number_format((int) ($databaseManifest['tables_count'] ?? 0)) }}</strong></div>
                    <div class="detail-row"><span>Database Rows</span><strong>{{ number_format((int) ($databaseManifest['rows_count'] ?? 0)) }}</strong></div>
                    <div class="detail-row"><span>Storage Root</span><strong>{{ $fileManifest['root'] ?? 'storage-app' }}</strong></div>
                    <div class="detail-row"><span>Storage Files</span><strong>{{ number_format((int) ($fileManifest['count'] ?? 0)) }}</strong></div>
                    <div class="detail-row"><span>Storage Size</span><strong>{{ $formatBytes((int) ($fileManifest['bytes'] ?? 0)) }}</strong></div>
                </div>
            </div>
        </section>

        <section class="panel">
            <div class="panel-head">
                <div>
                    <h2 style="margin:0 0 6px;">Full Restore Guard</h2>
                    <p class="muted" style="margin:0;">Restore is intentionally guarded so you do not overwrite the live platform by mistake.</p>
                </div>
            </div>

            <div class="warning-card">
                <h3>Restore Will Replace Current Platform Data</h3>
                <p>This action restores the database and the contents of <code>storage/app</code> from this archive. Existing live data will be replaced. To keep recovery safer, the form below can create an automatic pre-restore backup first.</p>
            </div>

            @if($backup->fileExists())
                <form method="POST" action="{{ route('admin.platform.backups.restore', $backup) }}" style="margin-top:18px;">
                    @csrf
                    @method('PUT')

                    <div class="form-grid">
                        <div class="field full">
                            <label for="restore_confirmation">Type The Exact Backup Filename To Confirm Restore</label>
                            <input
                                type="text"
                                id="restore_confirmation"
                                name="restore_confirmation"
                                value="{{ old('restore_confirmation') }}"
                                placeholder="{{ $backup->filename }}"
                                autocomplete="off"
                            >
                            <div class="muted" style="margin-top:8px;">Required text: <strong>{{ $backup->filename }}</strong></div>
                        </div>
                    </div>

                    <label class="checkbox-row">
                        <input type="checkbox" name="create_safety_backup" value="1" {{ old('create_safety_backup', '1') ? 'checked' : '' }}>
                        <span>Create an automatic safety backup of the current live platform before this restore starts.</span>
                    </label>

                    <div class="stack" style="justify-content:flex-end; margin-top:18px;">
                        <button type="submit" class="btn btn-danger">Restore Full Backup</button>
                    </div>
                </form>
            @else
                <div class="alert-warning" style="margin-top:18px;">This archive cannot be restored because its zip file is missing from disk.</div>
            @endif
        </section>

        <section class="panel">
            <div class="panel-head">
                <div>
                    <h2 style="margin:0 0 6px;">Database Table Manifest</h2>
                    <p class="muted" style="margin:0;">This list shows what was captured for database recovery in this archive.</p>
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
    </main>
</div>
</body>
</html>
