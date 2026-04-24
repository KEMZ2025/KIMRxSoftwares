@extends('accounting.layout')

@section('title', 'Balance Sheet')

@section('content')
    <div class="topbar">
        <div>
            <p class="eyebrow">Financial Position</p>
            <h1>Balance Sheet</h1>
            <p>Assets, liabilities, equity, and current earnings as of the selected date.</p>
        </div>
        <div class="range-chip">As Of {{ $asOf->format('d M Y') }}</div>
    </div>

    <div class="panel">
        @include('accounting._tabs')

        <div class="filters">
            <div>
                <h2 style="margin:0 0 6px;">Statement Date</h2>
                <p class="panel-subtitle" style="margin:0;">Review the branch's financial position on one closing date.</p>
            </div>
            <form method="GET" class="filter-form">
                <input type="date" name="as_of" value="{{ $asOf->toDateString() }}">
                <button type="submit" class="btn btn-primary">Apply Date</button>
                <a href="{{ route('accounting.balance-sheet') }}" class="btn btn-light">Reset</a>
            </form>
        </div>
    </div>

    <div class="cards-grid" style="margin-bottom:20px;">
        <div class="summary-card tone-blue">
            <div class="label">Total Assets</div>
            <div class="value">{{ number_format((float) $statement['totalAssets'], 2) }}</div>
        </div>
        <div class="summary-card tone-amber">
            <div class="label">Total Liabilities</div>
            <div class="value">{{ number_format((float) $statement['totalLiabilities'], 2) }}</div>
        </div>
        <div class="summary-card tone-violet">
            <div class="label">Total Equity</div>
            <div class="value">{{ number_format((float) $statement['totalEquity'], 2) }}</div>
        </div>
        <div class="summary-card tone-{{ abs($statement['difference']) < 0.005 ? 'emerald' : 'rose' }}">
            <div class="label">Balance Difference</div>
            <div class="value">{{ number_format((float) abs($statement['difference']), 2) }}</div>
            <div class="meta">
                <span class="status-pill {{ abs($statement['difference']) < 0.005 ? 'ok' : 'warn' }}">
                    {{ abs($statement['difference']) < 0.005 ? 'Balanced' : 'Review Needed' }}
                </span>
            </div>
        </div>
    </div>

    <div class="balance-grid">
        <div class="panel">
            <h2>Assets</h2>
            <p class="panel-subtitle">Current and long-term resources controlled by this branch.</p>

            @foreach ($statement['assetSections'] as $section)
                <div class="statement-block">
                    <div class="statement-block-header">
                        <div>
                            <h3>{{ $section['label'] }}</h3>
                            <p>Live balances pulled from the chart of accounts as of this date.</p>
                        </div>
                        <div class="statement-total">{{ number_format((float) $section['total'], 2) }}</div>
                    </div>
                    <div style="padding:0 18px 18px;">
                        @if ($section['rows']->isEmpty())
                            <div class="empty-state" style="margin-top:16px;">No balances are posted in this section yet.</div>
                        @else
                            <div class="balance-list" style="margin-top:8px;">
                                @foreach ($section['rows'] as $row)
                                    <div class="balance-item">
                                        <div>
                                            <strong>{{ $row['name'] }}</strong>
                                            <div class="meta">{{ $row['code'] }} | {{ $row['balance_display'] }}</div>
                                        </div>
                                        <div class="amount">{{ number_format((float) $row['statement_amount'], 2) }}</div>
                                    </div>
                                @endforeach
                            </div>
                            <div class="balance-total">
                                <span>{{ $section['label'] }} Total</span>
                                <span>{{ number_format((float) $section['total'], 2) }}</span>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach

            <div class="balance-total" style="margin-top:18px;">
                <span>Total Assets</span>
                <span>{{ number_format((float) $statement['totalAssets'], 2) }}</span>
            </div>
        </div>

        <div class="panel">
            <h2>Liabilities &amp; Equity</h2>
            <p class="panel-subtitle">Amounts owed and the branch's accumulated financial interest.</p>

            @foreach ($statement['liabilitySections'] as $section)
                <div class="statement-block">
                    <div class="statement-block-header">
                        <div>
                            <h3>{{ $section['label'] }}</h3>
                            <p>Supplier and other obligations still outstanding on this date.</p>
                        </div>
                        <div class="statement-total">{{ number_format((float) $section['total'], 2) }}</div>
                    </div>
                    <div style="padding:0 18px 18px;">
                        @if ($section['rows']->isEmpty())
                            <div class="empty-state" style="margin-top:16px;">No liabilities are posted in this section yet.</div>
                        @else
                            <div class="balance-list" style="margin-top:8px;">
                                @foreach ($section['rows'] as $row)
                                    <div class="balance-item">
                                        <div>
                                            <strong>{{ $row['name'] }}</strong>
                                            <div class="meta">{{ $row['code'] }} | {{ $row['balance_display'] }}</div>
                                        </div>
                                        <div class="amount">{{ number_format((float) $row['statement_amount'], 2) }}</div>
                                    </div>
                                @endforeach
                            </div>
                            <div class="balance-total">
                                <span>{{ $section['label'] }} Total</span>
                                <span>{{ number_format((float) $section['total'], 2) }}</span>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach

            <div class="statement-block" style="margin-top:18px;">
                <div class="statement-block-header">
                    <div>
                        <h3>{{ $statement['equitySection']['label'] }}</h3>
                        <p>Posted equity accounts plus current earnings derived from live profit to date.</p>
                    </div>
                    <div class="statement-total">{{ number_format((float) $statement['equitySection']['total'], 2) }}</div>
                </div>
                <div style="padding:0 18px 18px;">
                    @if ($statement['equitySection']['rows']->isEmpty())
                        <div class="empty-state" style="margin-top:16px;">No equity balances are posted yet.</div>
                    @else
                        <div class="balance-list" style="margin-top:8px;">
                            @foreach ($statement['equitySection']['rows'] as $row)
                                <div class="balance-item">
                                    <div>
                                        <strong>{{ $row['name'] }}</strong>
                                        <div class="meta">{{ $row['code'] }} | {{ $row['balance_display'] }}</div>
                                    </div>
                                    <div class="amount">{{ number_format((float) $row['statement_amount'], 2) }}</div>
                                </div>
                            @endforeach
                        </div>
                        <div class="balance-total">
                            <span>Equity Total</span>
                            <span>{{ number_format((float) $statement['equitySection']['total'], 2) }}</span>
                        </div>
                    @endif
                </div>
            </div>

            <div class="balance-total" style="margin-top:18px;">
                <span>Total Liabilities &amp; Equity</span>
                <span>{{ number_format((float) ($statement['totalLiabilities'] + $statement['totalEquity']), 2) }}</span>
            </div>
        </div>
    </div>
@endsection
