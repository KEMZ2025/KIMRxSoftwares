@extends('accounting.layout')

@section('title', 'Profit & Loss')

@section('content')
    <div class="topbar">
        <div>
            <p class="eyebrow">Financial Statement</p>
            <h1>Profit &amp; Loss</h1>
            <p>Revenue, cost of sales, expenses, and net performance for the selected period.</p>
        </div>
        <div class="range-chip">{{ $from->format('d M Y') }} to {{ $to->format('d M Y') }}</div>
    </div>

    <div class="panel">
        @include('accounting._tabs')

        <div class="filters">
            <div>
                <h2 style="margin:0 0 6px;">Statement Period</h2>
                <p class="panel-subtitle" style="margin:0;">Use a clean period window for the branch's profit and loss account.</p>
            </div>
            <form method="GET" class="filter-form">
                <input type="date" name="from" value="{{ $from->toDateString() }}">
                <input type="date" name="to" value="{{ $to->toDateString() }}">
                <button type="submit" class="btn btn-primary">Apply Period</button>
                <a href="{{ route('accounting.profit-loss') }}" class="btn btn-light">Reset</a>
            </form>
        </div>
    </div>

    <div class="cards-grid" style="margin-bottom:20px;">
        <div class="summary-card tone-emerald">
            <div class="label">Sales Revenue</div>
            <div class="value">{{ number_format((float) $statement['salesRevenue'], 2) }}</div>
        </div>
        <div class="summary-card tone-slate">
            <div class="label">Gross Profit</div>
            <div class="value">{{ number_format((float) $statement['grossProfit'], 2) }}</div>
        </div>
        <div class="summary-card tone-rose">
            <div class="label">Total Expenses</div>
            <div class="value">{{ number_format((float) $statement['totalExpenses'], 2) }}</div>
        </div>
        <div class="summary-card tone-{{ $statement['netProfit'] >= 0 ? 'emerald' : 'rose' }}">
            <div class="label">Net Profit / Loss</div>
            <div class="value">{{ number_format((float) $statement['netProfit'], 2) }}</div>
        </div>
    </div>

    <div class="two-up">
        <div class="panel">
            <h2>Statement Detail</h2>
            <p class="panel-subtitle">Each section is drawn from the live ledger for this exact period.</p>

            <div class="statement-grid">
                @foreach ($statement['sections'] as $section)
                    <div class="statement-block">
                        <div class="statement-block-header">
                            <div>
                                <h3>{{ $section['label'] }}</h3>
                                <p>{{ $section['type'] === 'revenue' ? 'Income credited into the branch books.' : 'Costs and expenses charged into this period.' }}</p>
                            </div>
                            <div class="statement-total">{{ number_format((float) $section['total'], 2) }}</div>
                        </div>

                        @if ($section['rows']->isEmpty())
                            <div class="empty-state" style="margin:16px;">No amounts were posted to this section in the selected period.</div>
                        @else
                            <div class="table-wrap">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Code</th>
                                            <th>Account</th>
                                            <th class="amount">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($section['rows'] as $row)
                                            <tr>
                                                <td><strong>{{ $row['code'] }}</strong></td>
                                                <td>{{ $row['name'] }}</td>
                                                <td class="amount">{{ number_format((float) $row['statement_amount'], 2) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot>
                                        <tr class="statement-row-total">
                                            <td colspan="2">{{ $section['label'] }} Total</td>
                                            <td class="amount">{{ number_format((float) $section['total'], 2) }}</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        <div class="panel">
            <h2>Profitability Snapshot</h2>
            <p class="panel-subtitle">A concise view of how this period moved from revenue down to final profit.</p>

            <div class="mini-stat-list">
                <div class="mini-stat">
                    <div class="name">Sales Revenue</div>
                    <div class="amount">{{ number_format((float) $statement['salesRevenue'], 2) }}</div>
                </div>
                <div class="mini-stat">
                    <div class="name">Other Income</div>
                    <div class="amount">{{ number_format((float) $statement['otherIncome'], 2) }}</div>
                </div>
                <div class="mini-stat">
                    <div class="name">Cost Of Sales</div>
                    <div class="amount">{{ number_format((float) $statement['costOfSales'], 2) }}</div>
                </div>
                <div class="mini-stat">
                    <div class="name">Gross Profit</div>
                    <div class="amount">{{ number_format((float) $statement['grossProfit'], 2) }}</div>
                </div>
                <div class="mini-stat">
                    <div class="name">Operating Expenses</div>
                    <div class="amount">{{ number_format((float) $statement['operatingExpenses'], 2) }}</div>
                </div>
                <div class="mini-stat">
                    <div class="name">Depreciation</div>
                    <div class="amount">{{ number_format((float) $statement['depreciationExpense'], 2) }}</div>
                </div>
                <div class="mini-stat">
                    <div class="name">Stock Losses</div>
                    <div class="amount">{{ number_format((float) $statement['stockLosses'], 2) }}</div>
                </div>
                <div class="mini-stat">
                    <div class="name">Net Profit / Loss</div>
                    <div class="amount">{{ number_format((float) $statement['netProfit'], 2) }}</div>
                </div>
            </div>
        </div>
    </div>
@endsection
