<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Exports - KIM Rx</title>
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
        .field textarea, .field input, .field select { width:100%; padding:12px 14px; border:1px solid #dbe3ef; border-radius:12px; background:#fff; font:inherit; }
        .field textarea { min-height: 110px; resize: vertical; }
        .field.full { grid-column: 1 / -1; }
        .btn { display:inline-flex; align-items:center; justify-content:center; gap:8px; padding:10px 14px; border-radius:10px; border:none; color:#fff; text-decoration:none; cursor:pointer; font-size:14px; font-weight:700; }
        .btn-primary { background:#1f7a4f; }
        .btn-secondary { background:#2563eb; }
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
            <h3 style="margin:0 0 8px;">Client Exports</h3>
            <p style="margin:0; color:#64748b;">Owner account: {{ $user->name }} | Active client: {{ $clientName }} | Active branch: {{ $branchName }}</p>
        </section>

        <section class="panel">
            <div class="panel-head">
                <div>
                    <h2 style="margin:0 0 6px;">Per-Client Export Control</h2>
                    <p class="muted" style="margin:0;">This first safe version creates an archive for one selected client, including that tenant's database rows and managed branding files such as the client logo.</p>
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
                    <h4>Total Exports</h4>
                    <p>{{ number_format($totalExports) }}</p>
                    <small>{{ number_format($readyExports) }} currently ready on disk</small>
                </div>
                <div class="summary-card">
                    <h4>Ready Archives</h4>
                    <p>{{ number_format($readyExports) }}</p>
                    <small>Available to review or download</small>
                </div>
                <div class="summary-card">
                    <h4>Missing Files</h4>
                    <p>{{ number_format($missingExports) }}</p>
                    <small>Catalog entries whose zip file is no longer present</small>
                </div>
                <div class="summary-card">
                    <h4>Clients Covered</h4>
                    <p>{{ number_format($coveredClients) }}</p>
                    <small>Distinct tenants with at least one export archive</small>
                </div>
                <div class="summary-card">
                    <h4>Latest Export</h4>
                    <p style="font-size:18px;">{{ $latestExport?->created_at?->format('d M Y H:i') ?? 'None yet' }}</p>
                    <small>{{ $latestExport?->formattedSize() ?? 'Create the first client export' }}</small>
                </div>
                <div class="summary-card">
                    <h4>Export Library Size</h4>
                    <p style="font-size:18px;">{{ $formatBytes($totalExportBytes) }}</p>
                    <small>Total archive size tracked in the client export catalog</small>
                </div>
            </div>

            <div class="safety-card">
                <h3>What This Export Is For</h3>
                <p>Use a client export when a tenant wants their own archive, when you want a tenant-specific migration package, or when you need a safer handover copy without touching the full platform backup. This screen does not perform client-only restore yet.</p>
            </div>
        </section>

        <section class="panel">
            <div class="panel-head">
                <div>
                    <h2 style="margin:0 0 6px;">Create A Client Export</h2>
                    <p class="muted" style="margin:0;">Select one tenant and create an archive of that client's data scope only. Add a note so you can identify why the export was created later.</p>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.platform.client-exports.store') }}">
                @csrf

                <div class="form-grid">
                    <div class="field">
                        <label for="client_id">Client</label>
                        <select id="client_id" name="client_id" required>
                            <option value="">Select a client</option>
                            @foreach($clients as $client)
                                <option value="{{ $client->id }}" {{ (string) old('client_id') === (string) $client->id ? 'selected' : '' }}>
                                    {{ $client->name }}
                                    @if($client->is_platform_sandbox)
                                        - Platform Sandbox
                                    @endif
                                    @if(!$client->is_active)
                                        - Inactive
                                    @endif
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field full">
                        <label for="notes">Export Note</label>
                        <textarea id="notes" name="notes" placeholder="Example: Archive copy before ABC Pharmacy migration.">{{ old('notes') }}</textarea>
                        <div class="muted" style="margin-top:8px;">Optional, but helpful when you later need to identify why this client export was created.</div>
                    </div>
                </div>

                <div class="stack" style="justify-content:flex-end; margin-top:18px;">
                    <button type="submit" class="btn btn-primary">Create Client Export</button>
                </div>
            </form>
        </section>

        <section class="panel">
            <div class="panel-head">
                <div>
                    <h2 style="margin:0 0 6px;">Client Export Catalog</h2>
                    <p class="muted" style="margin:0;">Review what each tenant archive contains before you share or download it. Missing-file entries stay visible so you can spot accidental deletions from disk.</p>
                </div>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>Client</th>
                        <th>Filename</th>
                        <th>Created</th>
                        <th>Contents</th>
                        <th>Status</th>
                        <th>Notes</th>
                        <th>Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($exports as $clientExport)
                        @php
                            $clientSnapshot = $clientExport->manifest_json['client'] ?? [];
                            $exportClientName = $clientExport->client?->name ?? ($clientSnapshot['name'] ?? 'Unknown client');
                        @endphp
                        <tr>
                            <td>{{ $loop->iteration + ($exports->currentPage() - 1) * $exports->perPage() }}</td>
                            <td>
                                <strong>{{ $exportClientName }}</strong><br>
                                <span class="muted">Client ID {{ $clientSnapshot['id'] ?? $clientExport->client_id ?? 'N/A' }}</span>
                            </td>
                            <td>
                                <strong>{{ $clientExport->filename }}</strong><br>
                                <span class="muted">{{ $clientExport->formattedSize() }} | {{ strtoupper(str_replace('_', ' ', $clientExport->export_type)) }}</span>
                            </td>
                            <td>
                                <strong>{{ $clientExport->created_at?->format('d M Y H:i') ?? 'Unknown' }}</strong><br>
                                <span class="muted">By {{ $clientExport->creator?->name ?? 'System' }}</span>
                            </td>
                            <td>
                                <strong>{{ number_format((int) $clientExport->database_tables_count) }}</strong> tables,
                                <strong>{{ number_format((int) $clientExport->database_rows_count) }}</strong> rows<br>
                                <span class="muted">{{ number_format((int) $clientExport->storage_files_count) }} managed files | {{ $formatBytes((int) $clientExport->storage_bytes) }}</span>
                            </td>
                            <td>
                                <span class="pill pill-{{ $clientExport->status }}">{{ $clientExport->displayStatus() }}</span>
                            </td>
                            <td>{{ $clientExport->notes ?: 'No note added.' }}</td>
                            <td>
                                <div class="actions">
                                    @if($clientExport->status !== \App\Models\ClientExport::STATUS_MISSING)
                                        <a href="{{ route('admin.platform.client-exports.show', $clientExport) }}" class="btn btn-secondary">Review</a>
                                        <a href="{{ route('admin.platform.client-exports.download', $clientExport) }}" class="btn btn-primary">Download</a>
                                    @else
                                        <span class="muted">Zip file missing from disk</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="muted">No client exports have been created yet.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <div style="margin-top:16px;">
                {{ $exports->withQueryString()->links() }}
            </div>
        </section>
    </main>
</div>
</body>
</html>
