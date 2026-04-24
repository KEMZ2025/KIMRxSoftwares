<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customers - KIM Rx</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; display: flex; background: #f5f7fb; }
        .content { flex: 1; padding: 20px; }
        .topbar, .panel { background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
        .panel-head { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 16px; }
        .summary-grid { display: grid; grid-template-columns: repeat(4, minmax(180px, 1fr)); gap: 16px; }
        .summary-card { background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 10px; padding: 14px; }
        .summary-card h4 { margin: 0 0 8px; font-size: 13px; color: #666; }
        .summary-card p { margin: 0; font-weight: bold; }
        .actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .btn { padding: 8px 12px; border-radius: 6px; color: white; text-decoration: none; border: none; cursor: pointer; display: inline-block; }
        .btn-add { background: #1f7a4f; }
        .btn-view { background: #3949ab; }
        .btn-edit { background: #ff9800; }
        .btn-delete { background: #b42318; }
        .btn-secondary { background: #0f766e; }
        .alert-success { background: #e7f6ec; color: #1f7a4f; padding: 12px; border-radius: 8px; margin-bottom: 15px; }
        .search-form { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 15px; }
        .search-form input { flex: 1; min-width: 240px; padding: 10px; border: 1px solid #ddd; border-radius: 8px; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 1250px; }
        table th, table td { padding: 10px; border-bottom: 1px solid #ddd; text-align: left; vertical-align: top; }
        table th { background: #f8f8f8; font-size: 13px; }
        .muted { color: #666; font-size: 13px; }
        .badge { display: inline-block; padding: 6px 10px; border-radius: 999px; font-size: 12px; font-weight: bold; }
        .badge-clear { background: #e7f6ec; color: #1f7a4f; }
        .badge-due { background: #fff4db; color: #9a6700; }
        .danger-text { color: #b42318; font-weight: bold; }
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
            <h3>Customers</h3>
            <p>Client: {{ $clientName }} | Branch: {{ $branchName }}</p>
        </div>

        <div class="panel">
            <div class="panel-head">
                <div>
                    <h2 style="margin:0;">Customer Accounts</h2>
                    <p class="muted" style="margin:6px 0 0;">Add customers, set credit limits, and jump into receivables or collections.</p>
                </div>

                <div class="actions">
                    <a href="{{ route('customers.receivables') }}" class="btn btn-secondary">Receivables</a>
                    <a href="{{ route('customers.collections.index') }}" class="btn btn-secondary">Collections</a>
                    <a href="{{ route('customers.create') }}" class="btn btn-add">Add Customer</a>
                </div>
            </div>

            @if(session('success'))
                <div class="alert-success">{{ session('success') }}</div>
            @endif

            <div class="summary-grid" style="margin-bottom:16px;">
                <div class="summary-card">
                    <h4>Active Customers</h4>
                    <p>{{ $customerCount }}</p>
                </div>
                <div class="summary-card">
                    <h4>Total Credit Limit</h4>
                    <p>{{ number_format((float) $totalCreditLimit, 2) }}</p>
                </div>
                <div class="summary-card">
                    <h4>Total Outstanding</h4>
                    <p>{{ number_format((float) $totalOutstanding, 2) }}</p>
                </div>
                <div class="summary-card">
                    <h4>Remaining Credit</h4>
                    <p>{{ number_format((float) $totalRemainingCredit, 2) }}</p>
                </div>
            </div>

            <form method="GET" action="{{ route('customers.index') }}" class="search-form">
                <input
                    type="text"
                    name="search"
                    placeholder="Search by customer, contact person, phone, email, or address..."
                    value="{{ request('search') }}"
                >
                <button type="submit" class="btn btn-view">Search</button>
            </form>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Customer</th>
                            <th>Contacts</th>
                            <th>Credit Limit</th>
                            <th>Outstanding</th>
                            <th>Remaining Credit</th>
                            <th>Account Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($customers as $customer)
                            <tr>
                                <td>{{ $loop->iteration + ($customers->currentPage() - 1) * $customers->perPage() }}</td>
                                <td>
                                    <strong>{{ $customer->name }}</strong><br>
                                    <span class="muted">{{ $customer->contact_person ?: 'No contact person' }}</span>
                                </td>
                                <td>
                                    <div>{{ $customer->phone ?: 'No phone' }}</div>
                                    <div class="muted">{{ $customer->email ?: 'No email' }}</div>
                                </td>
                                <td>{{ number_format((float) $customer->credit_limit, 2) }}</td>
                                <td class="{{ (float) $customer->outstanding_balance > 0 ? 'danger-text' : '' }}">
                                    {{ number_format((float) $customer->outstanding_balance, 2) }}
                                </td>
                                <td>{{ number_format((float) $customer->remaining_credit, 2) }}</td>
                                <td>
                                    @if((float) $customer->outstanding_balance > 0)
                                        <span class="badge badge-due">Has Outstanding Invoices</span>
                                    @else
                                        <span class="badge badge-clear">Up to Date</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="actions">
                                        <a href="{{ route('customers.show', $customer->id) }}" class="btn btn-view">Statement</a>
                                        <a href="{{ route('customers.edit', $customer->id) }}" class="btn btn-edit">Edit</a>
                                        <form method="POST" action="{{ route('customers.destroy', $customer->id) }}" style="display:inline;" onsubmit="return confirm('Remove this customer? Linked history will only be deactivated, not lost.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-delete">Deactivate</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8">No customers found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div style="margin-top: 15px;">
                {{ $customers->withQueryString()->links() }}
            </div>
        </div>
    </div>
</body>
</html>
