<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Insurance Batch - KIM Rx</title>
    <style>
        * { box-sizing: border-box; }
        body { margin:0; font-family: Arial, sans-serif; display:flex; background:#f5f7fb; }
        .content { flex:1; padding:20px; }
        .topbar, .panel { background:#fff; padding:20px; border-radius:16px; margin-bottom:20px; box-shadow:0 14px 34px rgba(15,23,42,.06); }
        .grid, .summary-grid, .form-grid { display:grid; gap:16px; }
        .grid { grid-template-columns: repeat(3, minmax(220px,1fr)); }
        .summary-grid { grid-template-columns: repeat(4, minmax(180px,1fr)); }
        .form-grid { grid-template-columns: repeat(2, minmax(220px,1fr)); }
        .info-box, .summary-card { background:#f8fafc; border:1px solid #e5e7eb; border-radius:14px; padding:14px; }
        .info-box h4, .summary-card span { margin:0 0 8px; font-size:12px; text-transform:uppercase; letter-spacing:.04em; color:#526071; display:block; }
        .info-box p, .summary-card strong { margin:0; color:#0f172a; font-weight:bold; }
        .summary-card strong { font-size:26px; }
        .form-group { display:flex; flex-direction:column; }
        .form-group label { margin-bottom:8px; font-weight:bold; }
        .form-group select, .form-group textarea { padding:10px; border:1px solid #d0d5dd; border-radius:10px; }
        .btn { padding:10px 14px; border:none; border-radius:10px; color:#fff; cursor:pointer; text-decoration:none; display:inline-block; }
        .btn-primary { background:#155eef; }
        .btn-secondary { background:#0f766e; }
        .btn-muted { background:#475467; }
        .badge { display:inline-block; padding:6px 10px; border-radius:999px; font-size:12px; font-weight:bold; }
        .badge-draft { background:#eff6ff; color:#1d4ed8; }
        .badge-submitted { background:#eef2ff; color:#4338ca; }
        .badge-reconciled { background:#ecfeff; color:#0f766e; }
        .badge-closed { background:#dcfae6; color:#087443; }
        .badge-part_paid { background:#fff7ed; color:#b54708; }
        .badge-paid { background:#dcfae6; color:#087443; }
        .badge-rejected { background:#fee4e2; color:#b42318; }
        .badge-approved { background:#ecfdf3; color:#067647; }
        .table-wrap { overflow-x:auto; }
        table { width:100%; border-collapse:collapse; min-width:1080px; }
        th, td { padding:12px; border-bottom:1px solid #eaecf0; text-align:left; }
        th { background:#f8fafc; font-size:12px; text-transform:uppercase; letter-spacing:.04em; color:#475467; }
        .muted { color:#667085; }
        @media (max-width: 900px) { body { flex-direction:column; } .grid, .summary-grid, .form-grid { grid-template-columns:1fr; } }
    </style>
</head>
<body>
@include('layouts.sidebar')
<div class="content" id="mainContent">
    <div class="topbar">
        <div style="display:flex; justify-content:space-between; gap:12px; align-items:center; flex-wrap:wrap;">
            <div>
                <h2 style="margin:0 0 8px;">Insurance Batch {{ $batch->batch_number }}</h2>
                <p class="muted" style="margin:0;">{{ $batch->insurer?->name ?? 'Unknown insurer' }} · {{ $batch->period_start?->format('d M Y') }} - {{ $batch->period_end?->format('d M Y') }}</p>
            </div>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <a href="{{ route('insurance.batches.index') }}" class="btn btn-muted">Back to Batches</a>
                <a href="{{ route('insurance.claims.index', ['insurer_id' => $batch->insurer_id]) }}" class="btn btn-secondary">Open Claims</a>
            </div>
        </div>
    </div>

    <div class="grid">
        <div class="info-box"><h4>Status</h4><p><span class="badge badge-{{ $batch->status }}">{{ $batch->status_label }}</span></p></div>
        <div class="info-box"><h4>Created By</h4><p>{{ $batch->createdByUser?->name ?? 'System' }}</p></div>
        <div class="info-box"><h4>Notes</h4><p>{{ $batch->notes ?: 'No notes recorded.' }}</p></div>
    </div>

    <div class="summary-grid" style="margin:20px 0;">
        <div class="summary-card"><span>Claims</span><strong>{{ $summary['claim_count'] }}</strong></div>
        <div class="summary-card"><span>Claimed</span><strong>{{ number_format($summary['claim_total'], 2) }}</strong></div>
        <div class="summary-card"><span>Remitted / Written Off</span><strong>{{ number_format($summary['remitted_total'], 2) }} / {{ number_format($summary['written_off_total'], 2) }}</strong></div>
        <div class="summary-card"><span>Outstanding</span><strong>{{ number_format($summary['outstanding_total'], 2) }}</strong></div>
    </div>

    @if(auth()->user()?->hasPermission('insurance.manage'))
        <div class="panel">
            <h3 style="margin:0 0 14px;">Batch Status Control</h3>
            <form method="POST" action="{{ route('insurance.batches.status.update', $batch) }}">
                @csrf
                @method('PUT')
                <div class="form-grid">
                    <div class="form-group">
                        <label for="status">Batch Status</label>
                        <select name="status" id="status" required>
                            @foreach($batchStatuses as $statusValue => $statusLabel)
                                <option value="{{ $statusValue }}" {{ $batch->status === $statusValue ? 'selected' : '' }}>{{ $statusLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="notes">Notes</label>
                        <textarea name="notes" id="notes" rows="3">{{ $batch->notes }}</textarea>
                    </div>
                </div>
                <div style="margin-top:16px;">
                    <button type="submit" class="btn btn-primary">Update Batch</button>
                </div>
            </form>
        </div>
    @endif

    <div class="panel">
        <h3 style="margin:0 0 14px;">Claims in Batch</h3>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Invoice</th>
                    <th>Sale Date</th>
                    <th>Patient</th>
                    <th>Status</th>
                    <th>Covered</th>
                    <th>Remitted</th>
                    <th>Written Off</th>
                    <th>Outstanding</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                @forelse($claimRows as $row)
                    <tr>
                        <td>{{ $row['claim']->invoice_number }}</td>
                        <td>{{ optional($row['claim']->sale_date)->format('d M Y') }}</td>
                        <td>{{ $row['claim']->customer?->name ?? 'Walk-in / N/A' }}</td>
                        <td><span class="badge badge-{{ $row['claim']->insurance_claim_status }}">{{ $row['claim']->claim_status_label }}</span></td>
                        <td>{{ number_format((float) $row['claim']->insurance_covered_amount, 2) }}</td>
                        <td>{{ number_format((float) $row['remitted'], 2) }}</td>
                        <td>{{ number_format((float) $row['written_off'], 2) }}</td>
                        <td>{{ number_format((float) $row['outstanding'], 2) }}</td>
                        <td><a href="{{ route('insurance.claims.show', $row['claim']) }}" class="btn btn-primary">Open Claim</a></td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="muted" style="padding:24px; text-align:center;">No claims assigned to this batch.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
