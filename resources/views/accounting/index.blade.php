@extends('accounting.layout')

@section('title', 'Accounting')

@section('content')
    <div class="topbar">
        <div>
            <p class="eyebrow">Accounting Hub</p>
            <h1>Accounting</h1>
            <p>Live accountant-facing balances, journals, and vouchers from this branch.</p>
        </div>
        <div class="range-chip">{{ $from->format('d M Y') }} to {{ $to->format('d M Y') }}</div>
    </div>

    <div class="panel">
        @include('accounting._tabs')

        <div class="filters">
            <div>
                <h2 style="margin:0 0 6px;">Period Window</h2>
                <p class="panel-subtitle" style="margin:0;">Use this same window across the accounting overview cards.</p>
            </div>
            <form method="GET" class="filter-form">
                <input type="date" name="from" value="{{ $from->toDateString() }}">
                <input type="date" name="to" value="{{ $to->toDateString() }}">
                <button type="submit" class="btn btn-primary">Apply Window</button>
                <a href="{{ route('accounting.index') }}" class="btn btn-light">Reset</a>
            </form>
        </div>
    </div>

    <div class="panel">
        <h2>Account Categories</h2>
        <p class="panel-subtitle">The essential pharmacy accounting structure grouped the way accountants expect it.</p>
        <div class="category-grid">
            @foreach ($summary['categoryCards'] as $card)
                <a href="{{ route('accounting.chart', ['category' => $card['key']]) }}" class="category-card">
                    <div>
                        <div class="badge badge-blue">{{ $card['label'] }}</div>
                        <div class="category-title" style="margin-top:14px;">{{ $card['account_count'] }} Accounts</div>
                        <div class="category-meta">{{ $card['description'] }}</div>
                    </div>
                    <div class="category-link">Open {{ $card['label'] }} &rsaquo;</div>
                </a>
            @endforeach
        </div>
    </div>

    <div class="cards-grid" style="margin-bottom:20px;">
        @foreach ($summary['summaryCards'] as $card)
            <div class="summary-card tone-{{ $card['tone'] }}">
                <div class="label">{{ $card['label'] }}</div>
                <div class="value">{{ number_format((float) $card['value'], 2) }}</div>
            </div>
        @endforeach
    </div>

    <div class="two-up">
        <div class="panel">
            <h2>Recent Journals</h2>
            <p class="panel-subtitle">The latest balanced entries generated from this branch’s operational transactions.</p>

            @if ($summary['recentEntries']->isEmpty())
                <div class="empty-state">No accounting entries were generated inside this period yet.</div>
            @else
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Reference</th>
                                <th>Source</th>
                                <th>Party</th>
                                <th class="amount">Debit</th>
                                <th class="amount">Credit</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($summary['recentEntries'] as $entry)
                                <tr>
                                    <td>{{ $entry['display_date'] }}</td>
                                    <td>
                                        @if ($entry['source_route'])
                                            <a href="{{ $entry['source_route'] }}">{{ $entry['reference_number'] }}</a>
                                        @else
                                            {{ $entry['reference_number'] }}
                                        @endif
                                    </td>
                                    <td>{{ $entry['source_label'] }}</td>
                                    <td>{{ $entry['party'] }}</td>
                                    <td class="amount">{{ number_format((float) $entry['debit_total'], 2) }}</td>
                                    <td class="amount">{{ number_format((float) $entry['credit_total'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="panel">
            <h2>Accounting Shortcuts</h2>
            <p class="panel-subtitle">Move straight into the screens accountants review most often.</p>
            <div class="mini-stat-list">
                <a href="{{ route('accounting.general-ledger', ['from' => $from->toDateString(), 'to' => $to->toDateString()]) }}" class="mini-stat" style="text-decoration:none;">
                    <div class="name">General Ledger</div>
                    <div class="amount">Open</div>
                </a>
                <a href="{{ route('accounting.journals', ['from' => $from->toDateString(), 'to' => $to->toDateString()]) }}" class="mini-stat" style="text-decoration:none;">
                    <div class="name">Journals</div>
                    <div class="amount">Open</div>
                </a>
                <a href="{{ route('accounting.trial-balance', ['as_of' => $to->toDateString()]) }}" class="mini-stat" style="text-decoration:none;">
                    <div class="name">Trial Balance</div>
                    <div class="amount">Open</div>
                </a>
                <a href="{{ route('accounting.vouchers', ['from' => $from->toDateString(), 'to' => $to->toDateString()]) }}" class="mini-stat" style="text-decoration:none;">
                    <div class="name">Payment Vouchers</div>
                    <div class="amount">Open</div>
                </a>
                <a href="{{ route('accounting.profit-loss', ['from' => $from->toDateString(), 'to' => $to->toDateString()]) }}" class="mini-stat" style="text-decoration:none;">
                    <div class="name">Profit &amp; Loss</div>
                    <div class="amount">Open</div>
                </a>
                <a href="{{ route('accounting.balance-sheet', ['as_of' => $to->toDateString()]) }}" class="mini-stat" style="text-decoration:none;">
                    <div class="name">Balance Sheet</div>
                    <div class="amount">Open</div>
                </a>
                <a href="{{ route('accounting.fixed-assets.index', ['as_of' => $to->toDateString()]) }}" class="mini-stat" style="text-decoration:none;">
                    <div class="name">Fixed Assets</div>
                    <div class="amount">Open</div>
                </a>
            </div>
        </div>
    </div>
@endsection
