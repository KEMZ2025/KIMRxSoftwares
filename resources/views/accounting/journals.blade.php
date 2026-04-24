@extends('accounting.layout')

@section('title', 'Journals')

@section('content')
    <div class="topbar">
        <div>
            <p class="eyebrow">Balanced Entries</p>
            <h1>Journals</h1>
            <p>Transaction journals generated from approved sales, purchases, collections, vouchers, and adjustments.</p>
        </div>
        <div class="range-chip">{{ $from->format('d M Y') }} to {{ $to->format('d M Y') }}</div>
    </div>

    <div class="panel">
        @include('accounting._tabs')

        <div class="filters">
            <div>
                <h2 style="margin:0 0 6px;">Journal Filters</h2>
                <p class="panel-subtitle" style="margin:0;">Search by invoice, receipt, reference, party, or entrant name.</p>
            </div>
            <form method="GET" class="filter-form">
                <input type="date" name="from" value="{{ $from->toDateString() }}">
                <input type="date" name="to" value="{{ $to->toDateString() }}">
                <input type="text" name="search" placeholder="Search journals..." value="{{ $search }}">
                <button type="submit" class="btn btn-primary">Apply Filters</button>
                <a href="{{ route('accounting.journals') }}" class="btn btn-light">Reset</a>
            </form>
        </div>
    </div>

    <div class="cards-grid" style="margin-bottom:20px;">
        <div class="summary-card tone-blue">
            <div class="label">Journal Entries</div>
            <div class="value">{{ $entries->count() }}</div>
        </div>
        <div class="summary-card tone-amber">
            <div class="label">Total Debits</div>
            <div class="value">{{ number_format((float) $entries->sum('debit_total'), 2) }}</div>
        </div>
        <div class="summary-card tone-violet">
            <div class="label">Total Credits</div>
            <div class="value">{{ number_format((float) $entries->sum('credit_total'), 2) }}</div>
        </div>
        <div class="summary-card tone-emerald">
            <div class="label">Balanced Entries</div>
            <div class="value">{{ $entries->filter(fn ($entry) => abs((float) $entry['debit_total'] - (float) $entry['credit_total']) < 0.01)->count() }}</div>
        </div>
    </div>

    @if ($entries->isEmpty())
        <div class="panel">
            <div class="empty-state">No journals matched that filter window.</div>
        </div>
    @else
        @foreach ($entries as $entry)
            <div class="journal-entry">
                <div class="journal-head">
                    <div>
                        <div class="badge badge-blue">{{ $entry['source_label'] }}</div>
                        <h3 style="margin-top:12px;">{{ $entry['reference_number'] }}</h3>
                        <p>{{ $entry['description'] }}</p>
                        @if ($entry['note'])
                            <p style="margin-top:10px;"><strong>Note:</strong> {{ $entry['note'] }}</p>
                        @endif
                    </div>
                    <div class="journal-meta">
                        <div><strong>Date:</strong> {{ $entry['display_date'] }}</div>
                        <div><strong>Party:</strong> {{ $entry['party'] }}</div>
                        <div><strong>Entered By:</strong> {{ $entry['entered_by'] }}</div>
                        <div><strong>Debit / Credit:</strong> {{ number_format((float) $entry['debit_total'], 2) }}</div>
                    </div>
                </div>

                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Account Code</th>
                                <th>Account Name</th>
                                <th class="amount">Debit</th>
                                <th class="amount">Credit</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($entry['lines'] as $line)
                                <tr>
                                    <td>{{ $line['account_code'] }}</td>
                                    <td>{{ $line['account_name'] }}</td>
                                    <td class="amount">{{ number_format((float) $line['debit'], 2) }}</td>
                                    <td class="amount">{{ number_format((float) $line['credit'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach
    @endif
@endsection
