<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Insurance Batches - KIM Rx</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; display: flex; background: #f5f7fb; }
        .content { flex: 1; padding: 20px; }
        .topbar, .panel { background: #fff; padding: 20px; border-radius: 16px; margin-bottom: 20px; box-shadow: 0 14px 34px rgba(15, 23, 42, 0.06); }
        .summary-grid, .filter-grid { display: grid; grid-template-columns: repeat(3, minmax(180px, 1fr)); gap: 16px; }
        .summary-card { background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 14px; padding: 16px; }
        .summary-card span { display:block; font-size:12px; text-transform:uppercase; letter-spacing:.05em; color:#526071; margin-bottom:8px; }
        .summary-card strong { font-size:28px; color:#0f172a; }
        .form-group { display:flex; flex-direction:column; }
        .form-group label { margin-bottom:8px; font-weight:bold; }
        .form-group input, .form-group select { padding:10px; border:1px solid #d0d5dd; border-radius:10px; }
        .btn { padding:10px 14px; border:none; border-radius:10px; color:#fff; cursor:pointer; text-decoration:none; display:inline-block; }
        .btn-primary { background:#155eef; }
        .btn-secondary { background:#0f766e; }
        .btn-muted { background:#475467; }
        .badge { display:inline-block; padding:6px 10px; border-radius:999px; font-size:12px; font-weight:bold; }
        .badge-draft { background:#eff6ff; color:#1d4ed8; }
        .badge-submitted { background:#eef2ff; color:#4338ca; }
        .badge-reconciled { background:#ecfeff; color:#0f766e; }
        .badge-closed { background:#dcfae6; color:#087443; }
        .table-wrap { overflow-x:auto; }
        table { width:100%; border-collapse:collapse; min-width:980px; }
        th, td { padding:12px; border-bottom:1px solid #eaecf0; text-align:left; }
        th { background:#f8fafc; font-size:12px; text-transform:uppercase; letter-spacing:.04em; color:#475467; }
        .muted { color:#667085; }
        .empty-state { padding:24px; text-align:center; color:#667085; }
        @media (max-width: 900px) { body { flex-direction:column; } .summary-grid, .filter-grid { grid-template-columns:1fr; } }
    </style>
</head>
<body>
@include('layouts.sidebar')

<div class="content" id="mainContent">
    <div class="topbar">
        <div style="display:flex; justify-content:space-between; gap:12px; align-items:center; flex-wrap:wrap;">
            <div>
                <h2 style="margin:0 0 8px;">Insurance Claim Batches</h2>
                <p class="muted" style="margin:0;">Group monthly insurer claims, monitor submission progress, and close only when reconciliation is complete.</p>
            </div>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <a href="{{ route('insurance.claims.index') }}" class="btn btn-secondary">Claims Desk</a>
                <a href="{{ route('insurance.statements.index') }}" class="btn btn-muted">Statements</a>
            </div>
        </div>
    </div>

    <div class="summary-grid" style="margin-bottom:20px;">
        <div class="summary-card"><span>Draft Batches</span><strong>{{ $draftCount }}</strong></div>
        <div class="summary-card"><span>Submitted Batches</span><strong>{{ $submittedCount }}</strong></div>
        <div class="summary-card"><span>Open Batches</span><strong>{{ $openCount }}</strong></div>
    </div>

    <div class="panel">
        <form method="GET">
            <div class="filter-grid">
                <div class="form-group">
                    <label for="status">Batch Status</label>
                    <select name="status" id="status">
                        <option value="">All Statuses</option>
                        @foreach($batchStatuses as $statusValue => $statusLabel)
                            <option value="{{ $statusValue }}" {{ $status === $statusValue ? 'selected' : '' }}>{{ $statusLabel }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label for="insurer_id">Insurer</label>
                    <select name="insurer_id" id="insurer_id">
                        <option value="">All Insurers</option>
                        @foreach($insurers as $insurer)
                            <option value="{{ $insurer->id }}" {{ (int) $insurerId === (int) $insurer->id ? 'selected' : '' }}>{{ $insurer->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group" style="justify-content:flex-end;">
                    <label>&nbsp;</label>
                    <div style="display:flex; gap:10px; flex-wrap:wrap;">
                        <button type="submit" class="btn btn-primary">Filter Batches</button>
                        <a href="{{ route('insurance.batches.index') }}" class="btn btn-muted">Reset</a>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="panel">
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Batch</th>
                    <th>Insurer</th>
                    <th>Status</th>
                    <th>Period</th>
                    <th>Claims</th>
                    <th>Claimed</th>
                    <th>Outstanding</th>
                    <th>Created By</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                @forelse($batches as $batch)
                    <tr>
                        <td>
                            <strong>{{ $batch->batch_number }}</strong><br>
                            <span class="muted">{{ $batch->title ?: 'No title' }}</span>
                        </td>
                        <td>{{ $batch->insurer?->name ?? 'Unknown insurer' }}</td>
                        <td><span class="badge badge-{{ $batch->status }}">{{ $batch->status_label }}</span></td>
                        <td>{{ $batch->period_start?->format('d M Y') }} - {{ $batch->period_end?->format('d M Y') }}</td>
                        <td>{{ $batch->claims_count }}</td>
                        <td>{{ number_format((float) $batch->total_claim_amount, 2) }}</td>
                        <td>{{ number_format((float) $batch->total_outstanding_amount, 2) }}</td>
                        <td>{{ $batch->createdByUser?->name ?? 'System' }}</td>
                        <td><a href="{{ route('insurance.batches.show', $batch) }}" class="btn btn-primary">Open Batch</a></td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="empty-state">No insurance claim batches found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:16px;">{{ $batches->links() }}</div>
    </div>
</div>
</body>
</html>
