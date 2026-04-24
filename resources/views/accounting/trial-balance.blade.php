@extends('accounting.layout')

@section('title', 'Trial Balance')

@section('content')
    <div class="topbar">
        <div>
            <p class="eyebrow">Statement Check</p>
            <h1>Trial Balance</h1>
            <p>Debit and credit balances across all live accounts as of the selected date.</p>
        </div>
        <div class="range-chip">As Of {{ $asOf->format('d M Y') }}</div>
    </div>

    <div class="panel">
        @include('accounting._tabs')

        <div class="filters">
            <div>
                <h2 style="margin:0 0 6px;">Statement Date</h2>
                <p class="panel-subtitle" style="margin:0;">Use one date to review the branch's trial balance position.</p>
            </div>
            <form method="GET" class="filter-form">
                <input type="date" name="as_of" value="{{ $asOf->toDateString() }}">
                <button type="submit" class="btn btn-primary">Apply Date</button>
                <a href="{{ route('accounting.trial-balance') }}" class="btn btn-light">Reset</a>
            </form>
        </div>
    </div>

    <div class="cards-grid" style="margin-bottom:20px;">
        <div class="summary-card tone-blue">
            <div class="label">Debit Total</div>
            <div class="value">{{ number_format((float) $trialBalance['totalDebit'], 2) }}</div>
        </div>
        <div class="summary-card tone-violet">
            <div class="label">Credit Total</div>
            <div class="value">{{ number_format((float) $trialBalance['totalCredit'], 2) }}</div>
        </div>
        <div class="summary-card tone-{{ abs($trialBalance['difference']) < 0.005 ? 'emerald' : 'rose' }}">
            <div class="label">Difference</div>
            <div class="value">{{ number_format((float) abs($trialBalance['difference']), 2) }}</div>
            <div class="meta">
                <span class="status-pill {{ abs($trialBalance['difference']) < 0.005 ? 'ok' : 'warn' }}">
                    {{ abs($trialBalance['difference']) < 0.005 ? 'Balanced' : 'Review Needed' }}
                </span>
            </div>
        </div>
        <div class="summary-card tone-teal">
            <div class="label">Accounts With Balances</div>
            <div class="value">{{ $trialBalance['accountCount'] }}</div>
        </div>
    </div>

    <div class="panel">
        <h2>Trial Balance Detail</h2>
        <p class="panel-subtitle">Only accounts carrying a balance are shown here, with each amount placed on its correct side.</p>

        @if ($trialBalance['rows']->isEmpty())
            <div class="empty-state">No account balances were generated for that date yet.</div>
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Account</th>
                            <th>Category</th>
                            <th>Balance</th>
                            <th class="amount">Debit</th>
                            <th class="amount">Credit</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($trialBalance['rows'] as $row)
                            <tr>
                                <td><strong>{{ $row['code'] }}</strong></td>
                                <td>{{ $row['name'] }}</td>
                                <td>{{ ucfirst($row['category']) }}</td>
                                <td>{{ $row['balance_display'] }}</td>
                                <td class="amount">{{ $row['debit'] > 0 ? number_format((float) $row['debit'], 2) : '-' }}</td>
                                <td class="amount">{{ $row['credit'] > 0 ? number_format((float) $row['credit'], 2) : '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="statement-row-grand">
                            <td colspan="4">Trial Balance Totals</td>
                            <td class="amount">{{ number_format((float) $trialBalance['totalDebit'], 2) }}</td>
                            <td class="amount">{{ number_format((float) $trialBalance['totalCredit'], 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </div>
@endsection
