<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suppliers - KIM Rx</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; display: flex; background: #f5f7fb; }
        .content { flex: 1; padding: 20px; }
        .topbar, .panel { background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
        .panel-head { display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:16px; }
        .summary-grid { display:grid; grid-template-columns: repeat(4, minmax(180px, 1fr)); gap:16px; margin-bottom:16px; }
        .summary-card { background:#f8fafc; border:1px solid #e5e7eb; border-radius:10px; padding:14px; }
        .summary-card h4 { margin:0 0 8px; font-size:13px; color:#666; }
        .summary-card p { margin:0; font-weight:bold; }
        .btn { padding:8px 12px; border-radius:6px; color:white; text-decoration:none; border:none; cursor:pointer; display:inline-block; }
        .btn-add { background:#1f7a4f; }
        .btn-edit { background:#7c3aed; }
        .btn-delete { background:#b42318; }
        .btn-back { background:#3949ab; }
        .btn-secondary { background:#0f766e; }
        .btn-view { background:#2563eb; }
        .alert-success { background:#e7f6ec; color:#1f7a4f; padding:12px; border-radius:8px; margin-bottom:15px; }
        .search-form { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:15px; }
        .search-form input { flex:1; min-width:260px; padding:10px; border:1px solid #ddd; border-radius:8px; }
        .table-wrap { overflow-x:auto; }
        table { width:100%; border-collapse:collapse; min-width:1220px; }
        table th, table td { padding:10px; border-bottom:1px solid #ddd; text-align:left; vertical-align:top; }
        table th { background:#f8f8f8; font-size:13px; }
        .status-active { color:#1f7a4f; font-weight:bold; }
        .status-inactive { color:#b42318; font-weight:bold; }
        .action-group { display:flex; gap:8px; flex-wrap:wrap; }
        .inline-form { display:inline; margin:0; }
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
            <h3>Suppliers</h3>
            <p>Client: {{ $clientName }} | Branch: {{ $branchName }}</p>
        </div>

        <div class="panel">
            <div class="panel-head">
                <div>
                    <h2 style="margin:0;">Supplier Directory</h2>
                    <p class="muted" style="margin:6px 0 0;">Manage suppliers, view statements, and move into invoice-specific supplier payments.</p>
                </div>
                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <a href="{{ route('suppliers.statement') }}" class="btn btn-back">Supplier Statement</a>
                    <a href="{{ route('suppliers.payables') }}" class="btn btn-secondary">Payables</a>
                    <a href="{{ route('suppliers.payments.index') }}" class="btn btn-secondary">Payments</a>
                    <a href="{{ route('suppliers.create') }}" class="btn btn-add">Add Supplier</a>
                </div>
            </div>

            @if(session('success'))
                <div class="alert-success">{{ session('success') }}</div>
            @endif

            <div class="summary-grid">
                <div class="summary-card">
                    <h4>Suppliers</h4>
                    <p>{{ $supplierCount }}</p>
                </div>
                <div class="summary-card">
                    <h4>Suppliers Owed</h4>
                    <p>{{ $suppliersOwed }}</p>
                </div>
                <div class="summary-card">
                    <h4>Total Outstanding</h4>
                    <p>{{ number_format((float) $totalOutstanding, 2) }}</p>
                </div>
                <div class="summary-card">
                    <h4>Total Paid</h4>
                    <p>{{ number_format((float) $totalPaid, 2) }}</p>
                </div>
            </div>

            <form method="GET" action="{{ route('suppliers.index') }}" class="search-form">
                <input
                    type="text"
                    name="search"
                    placeholder="Search by supplier, contact person, phone, email, or address..."
                    value="{{ request('search') }}"
                >
                <button type="submit" class="btn btn-back">Search</button>
            </form>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Supplier</th>
                            <th>Contact</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Address</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($suppliers as $supplier)
                            <tr>
                                <td>{{ $loop->iteration + ($suppliers->currentPage() - 1) * $suppliers->perPage() }}</td>
                                <td>
                                    <strong>{{ $supplier->name }}</strong><br>
                                    <span class="muted">{{ $supplier->notes ?: 'No notes' }}</span>
                                </td>
                                <td>{{ $supplier->contact_person ?: 'N/A' }}</td>
                                <td>
                                    {{ $supplier->phone ?: 'N/A' }}<br>
                                    <span class="muted">{{ $supplier->alt_phone ?: 'No alt phone' }}</span>
                                </td>
                                <td>{{ $supplier->email ?: 'N/A' }}</td>
                                <td>{{ $supplier->address ?: 'N/A' }}</td>
                                <td>
                                    @if($supplier->is_active)
                                        <span class="status-active">Active</span>
                                    @else
                                        <span class="status-inactive">Inactive</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="action-group">
                                        <a href="{{ route('suppliers.show', $supplier->id) }}" class="btn btn-view">View</a>
                                        <a href="{{ route('suppliers.edit', $supplier->id) }}" class="btn btn-edit">Edit</a>

                                        <form method="POST" action="{{ route('suppliers.destroy', $supplier->id) }}" class="inline-form" onsubmit="return confirm('Delete this supplier if there is no history, or deactivate it if history already exists?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-delete">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8">No suppliers found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div style="margin-top:15px;">
                {{ $suppliers->withQueryString()->links() }}
            </div>
        </div>
    </div>
</body>
</html>
