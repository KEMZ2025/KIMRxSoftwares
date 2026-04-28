<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Insurance Statements - KIM Rx</title>
    <style>
        * { box-sizing: border-box; }
        body { margin:0; font-family:Arial,sans-serif; display:flex; background:#f5f7fb; }
        .content { flex:1; padding:20px; }
        .topbar, .panel { background:#fff; padding:20px; border-radius:16px; margin-bottom:20px; box-shadow:0 14px 34px rgba(15,23,42,.06); }
        .grid, .filter-grid { display:grid; gap:16px; }
        .grid { grid-template-columns: repeat(4, minmax(180px,1fr)); }
        .filter-grid { grid-template-columns: repeat(4, minmax(180px,1fr)); }
        .summary-card { background:#f8fafc; border:1px solid #e5e7eb; border-radius:14px; padding:16px; }
        .summary-card span { display:block; font-size:12px; text-transform:uppercase; letter-spacing:.05em; color:#526071; margin-bottom:8px; }
        .summary-card strong { font-size:26px; color:#0f172a; }
        .form-group { display:flex; flex-direction:column; }
        .form-group label { margin-bottom:8px; font-weight:bold; }
        .form-group input, .form-group select { padding:10px; border:1px solid #d0d5dd; border-radius:10px; }
        .btn { padding:10px 14px; border:none; border-radius:10px; color:#fff; cursor:pointer; text-decoration:none; display:inline-block; }
        .btn-primary { background:#155eef; }
        .btn-secondary { background:#0f766e; }
        .btn-muted { background:#475467; }
        .table-wrap { overflow-x:auto; }
        table { width:100%; border-collapse:collapse; min-width:1080px; }
        th, td { padding:12px; border-bottom:1px solid #eaecf0; text-align:left; }
        th { background:#f8fafc; font-size:12px; text-transform:uppercase; letter-spacing:.04em; color:#475467; }
        .muted { color:#667085; }
        @media (max-width:900px) { body { flex-direction:column; } .grid, .filter-grid { grid-template-columns:1fr; } }
    </style>
</head>
<body>
@include('layouts.sidebar')
<div class="content" id="mainContent">
    <div class="topbar">
        <div style="display:flex; justify-content:space-between; gap:12px; align-items:center; flex-wrap:wrap;">
            <div>
                <h2 style="margin:0 0 8px;">Insurance Statements & Ageing</h2>
                <p class="muted" style="margin:0;">Review insurer balances, ageing buckets, and reconciliation health across submitted claims.</p>
            </div>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <a href="{{ route('insurance.claims.index') }}" class="btn btn-secondary">Claims Desk</a>
                <a href="{{ route('insurance.batches.index') }}" class="btn btn-muted">Claim Batches</a>
            </div>
        </div>
    </div>

    <div class="panel">
        <form method="GET">
            <div class="filter-grid">
                <div class="form-group">
                    <label for="insurer_id">Insurer</label>
                    <select name="insurer_id" id="insurer_id">
                        <option value="">All Insurers</option>
                        @foreach($insurers as $insurer)
                            <option value="{{ $insurer->id }}" {{ (int) $insurerId === (int) $insurer->id ? 'selected' : '' }}>{{ $insurer->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label for="from">From Date</label>
                    <input type="date" name="from" id="from" value="{{ $fromDate?->format('Y-m-d') }}">
                </div>
                <div class="form-group">
                    <label for="to">To Date</label>
                    <input type="date" name="to" id="to" value="{{ $toDate?->format('Y-m-d') }}">
                </div>
                <div class="form-group">
                    <label for="as_of">As Of</label>
                    <input type="date" name="as_of" id="as_of" value="{{ $asOfDate->format('Y-m-d') }}">
                </div>
            </div>
            <div style="margin-top:16px; display:flex; gap:10px; flex-wrap:wrap;">
                <button type="submit" class="btn btn-primary">Refresh Statement</button>
                <a href="{{ route('insurance.statements.index') }}" class="btn btn-muted">Reset</a>
            </div>
        </form>
    </div>

    <div class="grid" style="margin-bottom:20px;">
        @foreach(['0-30 Days', '31-60 Days', '61-90 Days', '91+ Days'] as $bucket)
            <div class="summary-card">
                <span>{{ $bucket }}</span>
                <strong>{{ number_format((float) ($ageingTotals[$bucket] ?? 0), 2) }}</strong>
            </div>
        @endforeach
    </div>

    <div class="panel">
        <h3 style="margin:0 0 14px;">Insurer Summary</h3>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Insurer</th>
                    <th>Claims</th>
                    <th>Covered</th>
                    <th>Remitted</th>
                    <th>Written Off</th>
                    <th>Outstanding</th>
                </tr>
                </thead>
                <tbody>
                @forelse($summaryByInsurer as $insurerName => $summary)
                    <tr>
                        <td>{{ $insurerName }}</td>
                        <td>{{ $summary['claim_count'] }}</td>
                        <td>{{ number_format((float) $summary['covered'], 2) }}</td>
                        <td>{{ number_format((float) $summary['remitted'], 2) }}</td>
                        <td>{{ number_format((float) $summary['written_off'], 2) }}</td>
                        <td>{{ number_format((float) $summary['outstanding'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted" style="padding:24px; text-align:center;">No insurer claims match the selected statement filters.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="panel">
        <h3 style="margin:0 0 14px;">Claim Ageing Detail</h3>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Invoice</th>
                    <th>Sale Date</th>
                    <th>Age</th>
                    <th>Bucket</th>
                    <th>Patient</th>
                    <th>Insurer</th>
                    <th>Batch</th>
                    <th>Status</th>
                    <th>Covered</th>
                    <th>Remitted</th>
                    <th>Written Off</th>
                    <th>Outstanding</th>
                </tr>
                </thead>
                <tbody>
                @forelse($statementRows as $row)
                    <tr>
                        <td><a href="{{ route('insurance.claims.show', $row['claim']) }}" style="color:#155eef; text-decoration:none;">{{ $row['invoice_number'] }}</a></td>
                        <td>{{ $row['sale_date']->format('d M Y') }}</td>
                        <td>{{ $row['age_days'] }} days</td>
                        <td>{{ $row['age_bucket'] }}</td>
                        <td>{{ $row['patient_name'] }}</td>
                        <td>{{ $row['insurer_name'] }}</td>
                        <td>{{ $row['batch_number'] ?: 'Unbatched' }}</td>
                        <td>{{ $row['status_label'] }}</td>
                        <td>{{ number_format((float) $row['covered'], 2) }}</td>
                        <td>{{ number_format((float) $row['remitted'], 2) }}</td>
                        <td>{{ number_format((float) $row['written_off'], 2) }}</td>
                        <td>{{ number_format((float) $row['outstanding'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="12" class="muted" style="padding:24px; text-align:center;">No statement rows found for the selected filters.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
