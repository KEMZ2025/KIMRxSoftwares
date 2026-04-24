@extends('accounting.layout')

@section('title', 'General Ledger')

@section('content')
    <div class="topbar">
        <div>
            <p class="eyebrow">Transaction History</p>
            <h1>General Ledger</h1>
            <p>Live transaction history with running balances by account.</p>
        </div>
        <div class="range-chip">{{ $from->format('d M Y') }} to {{ $to->format('d M Y') }}</div>
    </div>

    <div class="panel">
        @include('accounting._tabs')

        <div class="filters">
            <div>
                <h2 style="margin:0 0 6px;">Ledger Filters</h2>
                <p class="panel-subtitle" style="margin:0;">Choose a period window and optionally one account for a tighter running balance view.</p>
            </div>
            <form method="GET" class="filter-form">
                <input type="date" name="from" value="{{ $from->toDateString() }}">
                <input type="date" name="to" value="{{ $to->toDateString() }}">
                <select name="account">
                    <option value="">All Accounts</option>
                    @foreach ($ledger['accounts'] as $account)
                        <option value="{{ $account['code'] }}" @selected($accountCode === $account['code'])>
                            {{ $account['code'] }} - {{ $account['name'] }}
                        </option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-primary">Apply Filters</button>
                <a href="{{ route('accounting.general-ledger') }}" class="btn btn-light">Reset</a>
            </form>
        </div>
    </div>

    <div class="cards-grid" style="margin-bottom:20px;">
        @if ($ledger['account'])
            <div class="summary-card tone-blue">
                <div class="label">Selected Account</div>
                <div class="value">{{ $ledger['account']['code'] }}</div>
                <div class="meta">{{ $ledger['account']['name'] }}</div>
            </div>
            <div class="summary-card tone-teal">
                <div class="label">Opening Balance</div>
                <div class="value">{{ \App\Support\Accounting\ChartOfAccounts::formatBalance((float) $ledger['openingBalance'], $ledger['account']['normal_balance']) }}</div>
            </div>
            <div class="summary-card tone-amber">
                <div class="label">Period Debits</div>
                <div class="value">{{ number_format((float) $ledger['periodDebit'], 2) }}</div>
            </div>
            <div class="summary-card tone-violet">
                <div class="label">Period Credits</div>
                <div class="value">{{ number_format((float) $ledger['periodCredit'], 2) }}</div>
            </div>
            <div class="summary-card tone-emerald">
                <div class="label">Closing Balance</div>
                <div class="value">{{ \App\Support\Accounting\ChartOfAccounts::formatBalance((float) $ledger['closingBalance'], $ledger['account']['normal_balance']) }}</div>
            </div>
        @else
            <div class="summary-card tone-blue">
                <div class="label">Ledger Rows</div>
                <div class="value">{{ $ledger['rows']->count() }}</div>
            </div>
            <div class="summary-card tone-teal">
                <div class="label">Accounts Touched</div>
                <div class="value">{{ $ledger['rows']->pluck('account_code')->unique()->count() }}</div>
            </div>
            <div class="summary-card tone-amber">
                <div class="label">Period Debits</div>
                <div class="value">{{ number_format((float) $ledger['periodDebit'], 2) }}</div>
            </div>
            <div class="summary-card tone-violet">
                <div class="label">Period Credits</div>
                <div class="value">{{ number_format((float) $ledger['periodCredit'], 2) }}</div>
            </div>
        @endif
    </div>

    <div class="panel">
        <h2>Ledger Rows</h2>
        <p class="panel-subtitle">Each line below keeps the account-side running balance visible for accountant review.</p>

        @if ($ledger['rows']->isEmpty())
            <div class="empty-state">No ledger rows matched that filter window.</div>
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Account</th>
                            <th>Reference</th>
                            <th>Description</th>
                            <th class="amount">Debit</th>
                            <th class="amount">Credit</th>
                            <th class="amount">Running Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($ledger['rows'] as $row)
                            <tr>
                                <td>{{ $row['display_date'] }}</td>
                                <td>
                                    <strong>{{ $row['account_code'] }}</strong><br>
                                    <span style="color:#667085;">{{ $row['account_name'] }}</span>
                                </td>
                                <td>
                                    @if ($row['source_route'])
                                        <a href="{{ $row['source_route'] }}">{{ $row['reference_number'] }}</a>
                                    @else
                                        {{ $row['reference_number'] }}
                                    @endif
                                </td>
                                <td>{{ $row['description'] }}</td>
                                <td class="amount">{{ number_format((float) $row['debit'], 2) }}</td>
                                <td class="amount">{{ number_format((float) $row['credit'], 2) }}</td>
                                <td class="amount">{{ $row['running_balance_display'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
