<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Trail - KIM Rx</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; background: #f4f6fb; color: #1f2937; }
        .layout { display: flex; min-height: 100vh; }
        .content { flex: 1; padding: 24px; margin-left: 260px; transition: margin-left 0.3s ease; }
        .content.expanded { margin-left: 80px; }
        .topbar, .panel { background: #fff; border-radius: 18px; padding: 22px; box-shadow: 0 10px 28px rgba(0, 0, 0, 0.06); margin-bottom: 22px; }
        .panel-head { display:flex; justify-content:space-between; align-items:flex-start; gap:14px; flex-wrap:wrap; margin-bottom:18px; }
        .summary-grid { display:grid; grid-template-columns: repeat(4, minmax(180px, 1fr)); gap:16px; margin-bottom:18px; }
        .summary-card { background:#f8fafc; border:1px solid #e5e7eb; border-radius:14px; padding:14px; }
        .summary-card h4 { margin:0 0 8px; font-size:13px; color:#64748b; }
        .summary-card p { margin:0; font-size:26px; font-weight:700; }
        .filters { display:grid; grid-template-columns: repeat(6, minmax(140px, 1fr)); gap:12px; margin-bottom:16px; }
        .filters label { display:flex; flex-direction:column; gap:6px; font-size:13px; color:#475569; }
        .filters input, .filters select { width:100%; padding:11px 12px; border:1px solid #dbe3ef; border-radius:12px; font-size:14px; }
        .filters-actions { display:flex; gap:10px; flex-wrap:wrap; margin-top:8px; }
        .btn { display:inline-flex; align-items:center; justify-content:center; gap:8px; padding:10px 14px; border-radius:10px; border:none; color:#fff; text-decoration:none; cursor:pointer; font-size:14px; font-weight:700; }
        .btn-primary { background:#1f7a4f; }
        .btn-secondary { background:#2563eb; }
        .btn-ghost { background:#64748b; }
        .table-wrap { overflow-x:auto; }
        table { width:100%; border-collapse:collapse; min-width:1280px; }
        th, td { padding:13px 12px; border-bottom:1px solid #e5e7eb; text-align:left; vertical-align:top; }
        th { background:#f8fafc; font-size:12px; color:#475569; text-transform:uppercase; letter-spacing:0.04em; }
        .pill { display:inline-flex; align-items:center; padding:6px 10px; border-radius:999px; font-size:12px; font-weight:700; background:#e2e8f0; color:#1e293b; }
        .muted { color:#64748b; font-size:13px; }
        .entry-meta { display:flex; gap:8px; flex-wrap:wrap; margin-top:8px; }
        details.audit-details { margin-top:10px; }
        details.audit-details summary { cursor:pointer; color:#2563eb; font-weight:700; }
        .json-grid { display:grid; grid-template-columns: repeat(3, minmax(200px, 1fr)); gap:12px; margin-top:12px; }
        .json-card { background:#0f172a; color:#e2e8f0; border-radius:14px; padding:12px; }
        .json-card h5 { margin:0 0 8px; font-size:12px; text-transform:uppercase; letter-spacing:0.05em; color:#93c5fd; }
        .json-card pre { margin:0; font-size:12px; white-space:pre-wrap; word-break:break-word; font-family:Consolas, monospace; }
        @media (max-width: 1100px) {
            .filters { grid-template-columns: repeat(2, minmax(180px, 1fr)); }
            .json-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 900px) {
            .layout { display:block; }
            .content, .content.expanded { margin-left:0; padding:16px; }
            .summary-grid { grid-template-columns:1fr; }
            .filters { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="layout">
    @include('layouts.sidebar')

    <main class="content" id="mainContent">
        <section class="topbar">
            <h3 style="margin:0 0 8px;">Audit Trail</h3>
            <p style="margin:0; color:#64748b;">Client: {{ $clientName }} | Branch: {{ $branchName }}</p>
        </section>

        <section class="panel">
            <div class="panel-head">
                <div>
                    <h2 style="margin:0 0 6px;">Sensitive Activity Log</h2>
                    <p class="muted" style="margin:0;">Review who changed money, stock, settings, cash drawer activity, and client access controls.</p>
                </div>
            </div>

            <div class="summary-grid">
                <div class="summary-card"><h4>Matching Entries</h4><p>{{ number_format($totalEntries) }}</p></div>
                <div class="summary-card"><h4>Today</h4><p>{{ number_format($todayEntries) }}</p></div>
                <div class="summary-card"><h4>Modules</h4><p>{{ number_format($moduleCount) }}</p></div>
                <div class="summary-card"><h4>Actors</h4><p>{{ number_format($actorCount) }}</p></div>
            </div>

            <form method="GET" action="{{ route('admin.audit.index') }}">
                <div class="filters">
                    <label>
                        Search
                        <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Search action, reason, invoice, user...">
                    </label>

                    <label>
                        From Date
                        <input type="date" name="from_date" value="{{ $filters['from_date'] }}">
                    </label>

                    <label>
                        To Date
                        <input type="date" name="to_date" value="{{ $filters['to_date'] }}">
                    </label>

                    <label>
                        Module
                        <select name="module">
                            <option value="">All Modules</option>
                            @foreach ($moduleOptions as $moduleOption)
                                <option value="{{ $moduleOption }}" @selected($filters['module'] === $moduleOption)>{{ $moduleOption }}</option>
                            @endforeach
                        </select>
                    </label>

                    @if ($isSuperAdmin)
                        <label>
                            Client
                            <select name="client_id">
                                <option value="">All Clients</option>
                                @foreach ($clientOptions as $clientOption)
                                    <option value="{{ $clientOption->id }}" @selected((int) $filters['client_id'] === (int) $clientOption->id)>{{ $clientOption->name }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endif

                    <label>
                        Branch
                        <select name="branch_id">
                            <option value="">All Branches</option>
                            @foreach ($branchOptions as $branchOption)
                                <option value="{{ $branchOption->id }}" @selected((int) $filters['branch_id'] === (int) $branchOption->id)>{{ $branchOption->name }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label>
                        Actor
                        <select name="actor_id">
                            <option value="">All Users</option>
                            @foreach ($actorOptions as $actorOption)
                                <option value="{{ $actorOption->id }}" @selected((int) $filters['actor_id'] === (int) $actorOption->id)>{{ $actorOption->name }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <div class="filters-actions">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="{{ route('admin.audit.index') }}" class="btn btn-ghost">Clear</a>
                </div>
            </form>

            <div class="table-wrap" style="margin-top:18px;">
                <table>
                    <thead>
                    <tr>
                        <th>When</th>
                        <th>Module</th>
                        <th>Action</th>
                        <th>Actor</th>
                        <th>Client / Branch</th>
                        <th>Summary</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($entries as $entry)
                        @php
                            $beforeJson = $entry->old_values ? json_encode($entry->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : null;
                            $afterJson = $entry->new_values ? json_encode($entry->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : null;
                            $contextJson = $entry->context ? json_encode($entry->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : null;
                        @endphp
                        <tr>
                            <td>
                                <strong>{{ optional($entry->created_at)->format('d M Y') }}</strong><br>
                                <span class="muted">{{ optional($entry->created_at)->format('H:i:s') }}</span>
                            </td>
                            <td><span class="pill">{{ $entry->module }}</span></td>
                            <td>
                                <strong>{{ $entry->action }}</strong><br>
                                <span class="muted">{{ $entry->event_key }}</span>
                            </td>
                            <td>
                                <strong>{{ $entry->user?->name ?? 'System' }}</strong><br>
                                @if ($entry->ip_address)
                                    <span class="muted">{{ $entry->ip_address }}</span>
                                @endif
                            </td>
                            <td>
                                <strong>{{ $entry->client?->name ?? 'N/A' }}</strong><br>
                                <span class="muted">{{ $entry->branch?->name ?? 'Client-wide / Global' }}</span>
                            </td>
                            <td>
                                <strong>{{ $entry->summary }}</strong>

                                <div class="entry-meta">
                                    @if ($entry->subject_label)
                                        <span class="muted">Subject: {{ $entry->subject_label }}</span>
                                    @endif
                                    @if ($entry->reason)
                                        <span class="muted">Reason: {{ $entry->reason }}</span>
                                    @endif
                                </div>

                                @if ($beforeJson || $afterJson || $contextJson)
                                    <details class="audit-details">
                                        <summary>View Details</summary>
                                        <div class="json-grid">
                                            @if ($beforeJson)
                                                <div class="json-card">
                                                    <h5>Before</h5>
                                                    <pre>{{ $beforeJson }}</pre>
                                                </div>
                                            @endif
                                            @if ($afterJson)
                                                <div class="json-card">
                                                    <h5>After</h5>
                                                    <pre>{{ $afterJson }}</pre>
                                                </div>
                                            @endif
                                            @if ($contextJson)
                                                <div class="json-card">
                                                    <h5>Context</h5>
                                                    <pre>{{ $contextJson }}</pre>
                                                </div>
                                            @endif
                                        </div>
                                    </details>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="muted">No audit entries match the current filters.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <div style="margin-top:16px;">
                {{ $entries->withQueryString()->links() }}
            </div>
        </section>
    </main>
</div>
</body>
</html>
