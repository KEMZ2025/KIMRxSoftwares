<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Insurance Claims - KIM Rx</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; display: flex; background: #f5f7fb; }
        .content { flex: 1; padding: 20px; }
        .topbar, .panel { background: #fff; padding: 20px; border-radius: 16px; margin-bottom: 20px; box-shadow: 0 14px 34px rgba(15, 23, 42, 0.06); }
        .muted { color: #667085; }
        .summary-grid, .filter-grid { display: grid; grid-template-columns: repeat(4, minmax(180px, 1fr)); gap: 16px; }
        .summary-card { background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 14px; padding: 16px; }
        .summary-card span { display: block; font-size: 12px; text-transform: uppercase; letter-spacing: .05em; color: #526071; margin-bottom: 8px; }
        .summary-card strong { font-size: 28px; color: #0f172a; }
        .form-group { display: flex; flex-direction: column; }
        .form-group label { margin-bottom: 8px; font-weight: bold; }
        .form-group input, .form-group select { padding: 10px; border: 1px solid #d0d5dd; border-radius: 10px; }
        .btn { padding: 10px 14px; border: none; border-radius: 10px; color: #fff; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-primary { background: #155eef; }
        .btn-secondary { background: #0f766e; }
        .btn-muted { background: #475467; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 1100px; }
        th, td { padding: 12px; border-bottom: 1px solid #eaecf0; text-align: left; }
        th { background: #f8fafc; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; color: #475467; }
        .badge { display: inline-block; padding: 6px 10px; border-radius: 999px; font-size: 12px; font-weight: bold; }
        .badge-draft { background: #eff6ff; color: #1d4ed8; }
        .badge-submitted { background: #eef2ff; color: #4338ca; }
        .badge-approved { background: #ecfdf3; color: #067647; }
        .badge-rejected { background: #fee4e2; color: #b42318; }
        .badge-part_paid { background: #fff7ed; color: #b54708; }
        .badge-paid { background: #dcfae6; color: #087443; }
        .empty-state { padding: 24px; text-align: center; color: #667085; }
        .pagination { margin-top: 16px; }
        @media (max-width: 900px) {
            body { flex-direction: column; }
            .summary-grid, .filter-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
@include('layouts.sidebar')

<div class="content" id="mainContent">
    <div class="topbar">
        <h2 style="margin:0 0 8px;">Insurance Claims Desk</h2>
        <p class="muted" style="margin:0;">Track insurer balances, claim status, and remittances without mixing them into normal customer collections.</p>
    </div>

    <div class="summary-grid" style="margin-bottom:20px;">
        <div class="summary-card">
            <span>Gross Claims</span>
            <strong>{{ number_format($grossClaims, 2) }}</strong>
        </div>
        <div class="summary-card">
            <span>Outstanding Claims</span>
            <strong>{{ number_format($outstandingClaims, 2) }}</strong>
        </div>
        <div class="summary-card">
            <span>Remitted Amount</span>
            <strong>{{ number_format($remittedAmount, 2) }}</strong>
        </div>
        <div class="summary-card">
            <span>Paid / Rejected</span>
            <strong>{{ $paidClaims }} / {{ $rejectedClaims }}</strong>
        </div>
    </div>

    <div class="panel">
        <form method="GET">
            <div class="filter-grid">
                <div class="form-group">
                    <label for="search">Search</label>
                    <input type="text" name="search" id="search" value="{{ $search }}" placeholder="Invoice, member, card, customer, insurer">
                </div>
                <div class="form-group">
                    <label for="status">Claim Status</label>
                    <select name="status" id="status">
                        <option value="">All Statuses</option>
                        @foreach($claimStatuses as $statusValue => $statusLabel)
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
                        <button type="submit" class="btn btn-primary">Filter Claims</button>
                        <a href="{{ route('insurance.claims.index') }}" class="btn btn-muted">Reset</a>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="panel">
        <div style="display:flex; justify-content:space-between; gap:12px; align-items:center; flex-wrap:wrap; margin-bottom:14px;">
            <div>
                <h3 style="margin:0;">Approved Insurance Invoices</h3>
                <p class="muted" style="margin:6px 0 0;">Each row is an approved sale billed partly or fully to an insurer.</p>
            </div>
            @if(auth()->user()?->hasPermission('insurance.manage'))
                <a href="{{ route('insurance.insurers.index') }}" class="btn btn-secondary">Manage Insurers</a>
            @endif
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Invoice</th>
                    <th>Sale Date</th>
                    <th>Patient</th>
                    <th>Insurer</th>
                    <th>Claim Status</th>
                    <th>Covered</th>
                    <th>Outstanding</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                @forelse($claims as $claim)
                    <tr>
                        <td>
                            <strong>{{ $claim->invoice_number }}</strong><br>
                            <span class="muted">{{ ucfirst($claim->sale_type) }}</span>
                        </td>
                        <td>{{ optional($claim->sale_date)->format('d M Y') }}</td>
                        <td>
                            {{ $claim->customer?->name ?? 'Walk-in / N/A' }}<br>
                            <span class="muted">{{ $claim->insurance_member_number ?: 'No member no.' }}</span>
                        </td>
                        <td>{{ $claim->insurer?->name ?? 'Not linked' }}</td>
                        <td>
                            <span class="badge badge-{{ $claim->insurance_claim_status }}">{{ $claim->claim_status_label }}</span>
                        </td>
                        <td>{{ number_format((float) $claim->insurance_covered_amount, 2) }}</td>
                        <td>{{ number_format((float) $claim->insurance_balance_due, 2) }}</td>
                        <td>
                            <a href="{{ route('insurance.claims.show', $claim) }}" class="btn btn-primary">Open Claim</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="empty-state">No insurance claims found for the selected filters.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination">
            {{ $claims->links() }}
        </div>
    </div>
</div>
</body>
</html>
