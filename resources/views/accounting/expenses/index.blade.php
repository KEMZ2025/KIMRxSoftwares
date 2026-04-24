@extends('accounting.layout')

@section('title', 'Expenses')

@section('content')
    <div class="topbar">
        <div>
            <p class="eyebrow">Manual Expense Posting</p>
            <h1>Expenses</h1>
            <p>Posted operating expenses that already flow into the ledger and journals.</p>
        </div>
        <div class="range-chip">{{ $from->format('d M Y') }} to {{ $to->format('d M Y') }}</div>
    </div>

    <div class="panel">
        @include('accounting._tabs')

        @if (session('success'))
            <div class="badge badge-emerald" style="margin-bottom:14px;">{{ session('success') }}</div>
        @endif

        <div class="filters">
            <div>
                <h2 style="margin:0 0 6px;">Expense Filters</h2>
                <p class="panel-subtitle" style="margin:0;">Filter posted expenses by date and expense account.</p>
            </div>
            <form method="GET" class="filter-form">
                <input type="date" name="from" value="{{ $from->toDateString() }}">
                <input type="date" name="to" value="{{ $to->toDateString() }}">
                <select name="account">
                    <option value="">All Expense Accounts</option>
                    @foreach ($expenseAccounts as $account)
                        <option value="{{ $account['code'] }}" @selected($accountCode === $account['code'])>
                            {{ $account['code'] }} - {{ $account['name'] }}
                        </option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-primary">Apply Filters</button>
                <a href="{{ route('accounting.expenses.index') }}" class="btn btn-light">Reset</a>
                @if (auth()->user()?->hasPermission('accounting.expenses.manage'))
                    <a href="{{ route('accounting.expenses.create') }}" class="btn btn-primary">Post Expense</a>
                @endif
            </form>
        </div>
    </div>

    <div class="cards-grid" style="margin-bottom:20px;">
        <div class="summary-card tone-rose">
            <div class="label">Expense Entries</div>
            <div class="value">{{ $expenses->count() }}</div>
        </div>
        <div class="summary-card tone-amber">
            <div class="label">Total Posted</div>
            <div class="value">{{ number_format((float) $expenses->sum('amount'), 2) }}</div>
        </div>
        <div class="summary-card tone-blue">
            <div class="label">Unique Payees</div>
            <div class="value">{{ $expenses->pluck('payee_name')->filter()->unique()->count() }}</div>
        </div>
        <div class="summary-card tone-slate">
            <div class="label">Methods Used</div>
            <div class="value">{{ $expenses->pluck('payment_method')->filter()->unique()->count() }}</div>
        </div>
    </div>

    <div class="panel">
        <h2>Posted Expense List</h2>
        <p class="panel-subtitle">Each posted row below already feeds the accounting journals and general ledger.</p>

        @if ($expenses->isEmpty())
            <div class="empty-state">No manual expenses matched that filter window.</div>
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Account</th>
                            <th>Description</th>
                            <th>Payee</th>
                            <th>Method</th>
                            <th>Reference</th>
                            <th>Entered By</th>
                            <th class="amount">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($expenses as $expense)
                            <tr>
                                <td>{{ optional($expense->expense_date)->format('d M Y') }}</td>
                                <td><strong>{{ $expense->account_code }}</strong></td>
                                <td>{{ $expense->description }}</td>
                                <td>{{ $expense->payee_name ?: 'N/A' }}</td>
                                <td>{{ $expense->payment_method ?: 'Other / Unspecified' }}</td>
                                <td>{{ $expense->reference_number ?: 'N/A' }}</td>
                                <td>{{ $expense->enteredByUser?->name ?? 'N/A' }}</td>
                                <td class="amount">{{ number_format((float) $expense->amount, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
