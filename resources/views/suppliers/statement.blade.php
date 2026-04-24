<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Statement - KIM Rx</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; display: flex; background: #f5f7fb; }
        .content { flex: 1; padding: 20px; }
        .topbar, .panel { background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
        .summary-grid { display:grid; grid-template-columns: repeat(4, minmax(180px, 1fr)); gap:16px; margin-bottom:16px; }
        .summary-card { background:#f8fafc; border:1px solid #e5e7eb; border-radius:10px; padding:14px; }
        .summary-card h4 { margin:0 0 8px; font-size:13px; color:#666; }
        .summary-card p { margin:0; font-weight:bold; }
        .search-form { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:15px; }
        .search-form input { flex:1; min-width:260px; padding:10px; border:1px solid #ddd; border-radius:8px; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 1280px; }
        table th, table td { padding: 10px; border-bottom: 1px solid #ddd; text-align: left; vertical-align: top; }
        table th { background: #f8f8f8; font-size: 13px; }
        .amount-positive { color: #b42318; font-weight: bold; }
        .amount-zero { color: #1f7a4f; font-weight: bold; }
        .btn { padding: 8px 12px; border-radius: 6px; color: white; text-decoration: none; border: none; cursor: pointer; display: inline-block; }
        .btn-back { background:#3949ab; }
        .btn-secondary { background:#0f766e; }
        .btn-view { background:#2563eb; }
        .muted { color:#666; font-size:13px; }
        @media (max-width: 900px) {
            body { flex-direction: column; }
            .summary-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    @include('layouts.sidebar')

    <div class="content" id="mainContent">
        <div class="topbar">
            <h3>Supplier Statement</h3>
            <p>Client: {{ $clientName }} | Branch: {{ $branchName }}</p>
        </div>

        <div class="panel">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:16px;">
                <div>
                    <h2 style="margin:0;">Branch Supplier Balances</h2>
                    <p class="muted" style="margin:6px 0 0;">Shows what this branch has been invoiced, paid, and still owes to each supplier.</p>
                </div>
                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <a href="{{ route('suppliers.index') }}" class="btn btn-back">Back to Suppliers</a>
                    <a href="{{ route('suppliers.payables') }}" class="btn btn-secondary">Payables</a>
                </div>
            </div>

            <div class="summary-grid">
                <div class="summary-card">
                    <h4>Suppliers</h4>
                    <p>{{ $supplierCount }}</p>
                </div>
                <div class="summary-card">
                    <h4>Suppliers With Balance</h4>
                    <p>{{ $suppliersWithBalance }}</p>
                </div>
                <div class="summary-card">
                    <h4>Total Invoiced</h4>
                    <p>{{ number_format((float) $totalInvoiced, 2) }}</p>
                </div>
                <div class="summary-card">
                    <h4>Total Outstanding</h4>
                    <p>{{ number_format((float) $totalOutstanding, 2) }}</p>
                </div>
            </div>

            <div class="summary-grid" style="margin-top:16px;">
                <div class="summary-card">
                    <h4>Total Paid</h4>
                    <p>{{ number_format((float) $totalPaid, 2) }}</p>
                </div>
                <div class="summary-card">
                    <h4>Search Results</h4>
                    <p>{{ $statements->total() }}</p>
                </div>
            </div>

            <form method="GET" action="{{ route('suppliers.statement') }}" class="search-form">
                <input type="text" name="search" value="{{ $search }}" placeholder="Search supplier, contact person, or phone...">
                <button type="submit" class="btn btn-back">Search</button>
            </form>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Supplier</th>
                            <th>Contact</th>
                            <th>Total Invoices</th>
                            <th>Outstanding Invoices</th>
                            <th>Total Invoiced</th>
                            <th>Total Paid</th>
                            <th>Total Balance</th>
                            <th>Last Purchase</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($statements as $supplier)
                            <tr>
                                <td>{{ $loop->iteration + ($statements->currentPage() - 1) * $statements->perPage() }}</td>
                                <td>{{ $supplier->name }}</td>
                                <td>
                                    {{ $supplier->contact_person ?? 'N/A' }}<br>
                                    <span class="muted">{{ $supplier->phone ?? 'No phone' }}</span>
                                </td>
                                <td>{{ $supplier->total_invoices }}</td>
                                <td>{{ $supplier->outstanding_invoices }}</td>
                                <td>{{ number_format((float) $supplier->total_purchases, 2) }}</td>
                                <td>{{ number_format((float) $supplier->total_paid, 2) }}</td>
                                <td>
                                    @if((float) $supplier->total_balance > 0)
                                        <span class="amount-positive">{{ number_format((float) $supplier->total_balance, 2) }}</span>
                                    @else
                                        <span class="amount-zero">{{ number_format((float) $supplier->total_balance, 2) }}</span>
                                    @endif
                                </td>
                                <td>{{ $supplier->last_purchase_date ? \Illuminate\Support\Carbon::parse($supplier->last_purchase_date)->format('d M Y') : 'N/A' }}</td>
                                <td>
                                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                        <a href="{{ route('suppliers.show', $supplier->id) }}" class="btn btn-view">Statement</a>
                                        @if((float) $supplier->total_balance > 0)
                                            <a href="{{ route('suppliers.payables', ['search' => $supplier->name]) }}" class="btn btn-secondary">Open Invoices</a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10">No supplier statement data found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div style="margin-top:15px;">
                {{ $statements->withQueryString()->links() }}
            </div>
        </div>
    </div>
</body>
</html>
