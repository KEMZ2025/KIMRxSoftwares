@extends('accounting.layout')

@section('title', 'KIM Rx')

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
        @if ($errors->any())
            <div class="empty-state" style="margin-bottom:14px; border-color:#fda29b; color:#b42318;">
                {{ $errors->first() }}
            </div>
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
                <select name="status">
                    <option value="active" @selected($status === 'active')>Active Expenses</option>
                    <option value="voided" @selected($status === 'voided')>Voided Expenses</option>
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
            <div class="label">{{ $status === 'active' ? 'Active Expenses' : 'Voided Expenses' }}</div>
            <div class="value">{{ $expenses->count() }}</div>
        </div>
        <div class="summary-card tone-amber">
            <div class="label">{{ $status === 'active' ? 'Total Posted' : 'Total Voided' }}</div>
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
                            <th>Votes</th>
                            <th>Reference</th>
                            <th>Entered By</th>
                            <th class="amount">Amount</th>
                            <th>Status / Reason</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($expenses as $expense)
                            <tr>
                                <td>{{ optional($expense->expense_date)->format('d M Y') }}</td>
                                <td>
                                    <strong>{{ $expense->account_name }}</strong>
                                    <div style="margin-top:3px; color:#667085; font-size:12px;">{{ $expense->account_code }}</div>
                                </td>
                                <td>{{ $expense->description }}</td>
                                <td>{{ $expense->payee_name ?: 'N/A' }}</td>
                                <td>{{ $expense->payment_method ?: 'Cheque' }}</td>
                                <td>{{ $expense->source_of_funds ?: 'Not recorded' }}</td>
                                <td>{{ $expense->reference_number ?: 'N/A' }}</td>
                                <td>{{ $expense->enteredByUser?->name ?? 'N/A' }}</td>
                                <td class="amount">{{ number_format((float) $expense->amount, 2) }}</td>
                                <td>
                                    @if ($expense->is_active)
                                        <span class="badge badge-emerald">Active</span>
                                    @else
                                        <span class="badge badge-amber">Voided</span>
                                        <div style="margin-top:6px;"><strong>{{ $expense->void_reason }}</strong></div>
                                        <div style="margin-top:4px; color:#667085; font-size:12px;">
                                            {{ $expense->voidedByUser?->name ?? 'N/A' }}
                                            @if ($expense->voided_at)
                                                on {{ $expense->voided_at->format('d M Y H:i') }}
                                            @endif
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    <div style="display:flex; gap:6px; align-items:flex-start; flex-wrap:wrap;">
                                        <a href="{{ route('accounting.expenses.show', $expense) }}" class="btn btn-light">View</a>
                                        @if ($status === 'active' && auth()->user()?->hasPermission('accounting.expenses.manage'))
                                            <a href="{{ route('accounting.expenses.edit', $expense) }}" class="btn btn-primary">Edit</a>
                                        <details>
                                            <summary class="btn" style="background:#fee4e2; color:#b42318; list-style:none;">Void</summary>
                                            <form method="POST" action="{{ route('accounting.expenses.void', $expense) }}" style="display:grid; gap:8px; min-width:220px; margin-top:8px;">
                                                @csrf
                                                @method('PATCH')
                                                <label for="void_reason_{{ $expense->id }}"><strong>Reason</strong></label>
                                                <textarea id="void_reason_{{ $expense->id }}" name="void_reason" rows="3" minlength="5" maxlength="500" required placeholder="Explain why this expense is being voided" style="width:100%; padding:8px; border:1px solid #d0d5dd; border-radius:6px;"></textarea>
                                                <button type="submit" class="btn" style="background:#b42318; color:#fff;" onclick="return confirm('Void this expense and remove it from active accounting totals?');">Confirm Void</button>
                                            </form>
                                        </details>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
