<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Platform Backups - KIM Rx</title>
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
        .summary-card p { margin:0; font-size:26px; font-weight:700; }
        .summary-card small { display:block; margin-top:6px; color:#64748b; font-size:12px; }
        .form-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:16px; }
        .field label { display:block; margin-bottom:6px; font-weight:700; }
        .field textarea, .field input { width:100%; padding:12px 14px; border:1px solid #dbe3ef; border-radius:12px; background:#fff; font:inherit; }
        .field textarea { min-height: 110px; resize: vertical; }
        .field.full { grid-column: 1 / -1; }
        .btn { display:inline-flex; align-items:center; justify-content:center; gap:8px; padding:10px 14px; border-radius:10px; border:none; color:#fff; text-decoration:none; cursor:pointer; font-size:14px; font-weight:700; }
        .btn-primary { background:#1f7a4f; }
        .btn-secondary { background:#2563eb; }
        .btn-warning { background:#b45309; }
        .muted { color:#64748b; font-size:13px; }
        .alert-success, .alert-error, .alert-warning { padding:12px 14px; border-radius:12px; margin-bottom:16px; font-weight:600; }
        .alert-success { background:#e7f6ec; color:#166534; }
        .alert-error { background:#fef2f2; color:#b91c1c; }
        .alert-warning { background:#fff7ed; color:#9a3412; }
        .table-wrap { overflow-x:auto; }
        table { width:100%; border-collapse:collapse; min-width:1120px; }
        th, td { padding:13px 12px; border-bottom:1px solid #e5e7eb; text-align:left; vertical-align:top; }
        th { background:#f8fafc; font-size:12px; color:#475569; text-transform:uppercase; letter-spacing:0.04em; }
        .pill { display:inline-flex; align-items:center; padding:6px 10px; border-radius:999px; font-size:12px; font-weight:700; }
        .pill-ready { background:#dcfce7; color:#166534; }
        .pill-restored { background:#dbeafe; color:#1d4ed8; }
        .pill-missing { background:#fee2e2; color:#b91c1c; }
        .stack { display:flex; gap:8px; flex-wrap:wrap; }
        .actions { display:flex; gap:8px; flex-wrap:wrap; }
        .safety-card {
            margin-top: 18px;
            padding: 18px;
            border-radius: 16px;
            background: linear-gradient(135deg, #eff6ff, #ecfeff);
            border: 1px solid #bfdbfe;
        }
        .safety-card h3 { margin: 0 0 8px; }
        .safety-card p { margin: 0; color: #334155; line-height: 1.6; }
        @media (max-width: 900px) {
            .layout { display:block; }
            .content, .content.expanded { margin-left:0; padding:16px; }
            .summary-grid, .form-grid { grid-template-columns:1fr; }
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
@endphp
<div class="layout">
    @include('layouts.sidebar')

    <main class="content" id="mainContent">
        <section class="topbar">
            <h3 style="margin:0 0 8px;">Platform Backups</h3>
            <p style="margin:0; color:#64748b;">Owner account: {{ $user->name }} | Active client: {{ $clientName }} | Active branch: {{ $branchName }}</p>
        </section>

        <section class="panel">
            <div class="panel-head">
                <div>
                    <h2 style="margin:0 0 6px;">Full Backup And Restore Control</h2>
                    <p class="muted" style="margin:0;">This first safe version creates full platform archives containing database structure, database data, and the application storage files needed for recovery.</p>
                </div>
                <div class="stack">
                    <a href="{{ route('admin.platform.index') }}" class="btn btn-secondary">Owner Workspace</a>
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
                    <h4>Total Backups</h4>
                    <p>{{ number_format($totalBackups) }}</p>
                    <small>{{ number_format($readyBackups) }} currently ready on disk</small>
                </div>
                <div class="summary-card">
                    <h4>Ready Archives</h4>
                    <p>{{ number_format($readyBackups) }}</p>
                    <small>Available to review, download, or restore</small>
                </div>
                <div class="summary-card">
                    <h4>Restore History</h4>
                    <p>{{ number_format($restoredBackups) }}</p>
                    <small>Backups that have been used for full recovery</small>
                </div>
                <div class="summary-card">
                    <h4>Missing Files</h4>
                    <p>{{ number_format($missingBackups) }}</p>
                    <small>Catalog entries whose zip file is no longer present</small>
                </div>
                <div class="summary-card">
                    <h4>Latest Backup</h4>
                    <p style="font-size:18px;">{{ $latestBackup?->created_at?->format('d M Y H:i') ?? 'None yet' }}</p>
                    <small>{{ $latestBackup?->formattedSize() ?? 'Create the first platform backup' }}</small>
                </div>
                <div class="summary-card">
                    <h4>Backup Library Size</h4>
                    <p style="font-size:18px;">{{ $formatBytes($totalBackupBytes) }}</p>
                    <small>Total archive size tracked in the platform catalog</small>
                </div>
            </div>

            <div class="safety-card">
                <h3>Safety Notes Before You Restore</h3>
                <p>Restore is platform-wide. It replaces the current database and the contents of <code>storage/app</code> except the backup archive folder itself. The restore screen will ask for the exact filename, and it can create an automatic safety backup first.</p>
            </div>

            <div class="safety-card">
                <h3>Automatic Backup Policy</h3>
                <p>
                    Automatic daily backup is
                    <strong>{{ $automationEnabled ? 'enabled' : 'disabled' }}</strong>.
                    @if($automationEnabled)
                        The scheduler targets <strong>{{ $automationTime }}</strong>, keeps the latest <strong>{{ number_format($retentionCount) }}</strong> full platform backups, and skips creating a fresh scheduled archive if a newer backup already exists inside the last <strong>{{ number_format($skipRecentMinutes) }}</strong> minute(s).
                    @else
                        Turn it on in the environment configuration when you are ready for scheduled protection.
                    @endif
                </p>
            </div>
        </section>

        <section class="panel">
            <div class="panel-head">
                <div>
                    <h2 style="margin:0 0 6px;">Create A Full Backup</h2>
                    <p class="muted" style="margin:0;">Use this before deployments, package changes, or any risky operational work. The archive is written to the platform backup folder and added to the owner catalog immediately.</p>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.platform.backups.store') }}">
                @csrf

                <div class="form-grid">
                    <div class="field full">
                        <label for="notes">Backup Note</label>
                        <textarea id="notes" name="notes" placeholder="Example: Before production package update for VIP Pharmacy.">{{ old('notes') }}</textarea>
                        <div class="muted" style="margin-top:8px;">Optional, but helpful when you later need to identify why this archive was created.</div>
                        @error('notes')
                            <div class="alert-error" style="margin-top:8px; margin-bottom:0;">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="stack" style="justify-content:flex-end; margin-top:18px;">
                    <button type="submit" class="btn btn-primary">Create Full Backup</button>
                </div>
            </form>
        </section>

        <section class="panel">
            <div class="panel-head">
                <div>
                    <h2 style="margin:0 0 6px;">Backup Catalog</h2>
                    <p class="muted" style="margin:0;">Review what each archive contains before you restore it. Missing-file entries stay visible so you can spot accidental deletions from disk.</p>
                </div>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>Filename</th>
                        <th>Created</th>
                        <th>Contents</th>
                        <th>Status</th>
                        <th>Notes</th>
                        <th>Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($backups as $backup)
                        <tr>
                            <td>{{ $loop->iteration + ($backups->currentPage() - 1) * $backups->perPage() }}</td>
                            <td>
                                <strong>{{ $backup->filename }}</strong><br>
                                <span class="muted">{{ $backup->formattedSize() }} | {{ strtoupper(str_replace('_', ' ', $backup->backup_type)) }}</span>
                            </td>
                            <td>
                                <strong>{{ $backup->created_at?->format('d M Y H:i') ?? 'Unknown' }}</strong><br>
                                <span class="muted">By {{ $backup->creator?->name ?? 'System' }}</span>
                                @if($backup->restored_at)
                                    <br><span class="muted">Restored {{ $backup->restored_at->format('d M Y H:i') }}</span>
                                @endif
                            </td>
                            <td>
                                <strong>{{ number_format((int) $backup->database_tables_count) }}</strong> tables,
                                <strong>{{ number_format((int) $backup->database_rows_count) }}</strong> rows<br>
                                <span class="muted">{{ number_format((int) $backup->storage_files_count) }} files | {{ $formatBytes((int) $backup->storage_bytes) }} storage data</span>
                            </td>
                            <td>
                                <span class="pill pill-{{ $backup->status }}">{{ $backup->displayStatus() }}</span>
                            </td>
                            <td>
                                <div>{{ $backup->notes ?: 'No note added.' }}</div>
                            </td>
                            <td>
                                <div class="actions">
                                    @if($backup->status !== \App\Models\PlatformBackup::STATUS_MISSING)
                                        <a href="{{ route('admin.platform.backups.show', $backup) }}" class="btn btn-secondary">Review</a>
                                        <a href="{{ route('admin.platform.backups.download', $backup) }}" class="btn btn-primary">Download</a>
                                    @else
                                        <span class="muted">Zip file missing from disk</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="muted">No platform backups have been created yet.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <div style="margin-top:16px;">
                {{ $backups->withQueryString()->links() }}
            </div>
        </section>
    </main>
</div>
</body>
</html>
