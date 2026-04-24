@extends('prints.layout')

@php
    $pageTitle = 'Balance Sheet';
    $pageBadge = abs($statement['difference']) < 0.005 ? 'Balanced' : 'Review Needed';
    $rangeLabel = 'As of ' . $asOf->format('d M Y');
@endphp

@section('content')
    <div class="section">
        <div class="summary-grid">
            <div class="summary-card">
                <div class="label">Total Assets</div>
                <div class="value">{{ number_format((float) $statement['totalAssets'], 2) }}</div>
            </div>
            <div class="summary-card">
                <div class="label">Total Liabilities</div>
                <div class="value">{{ number_format((float) $statement['totalLiabilities'], 2) }}</div>
            </div>
            <div class="summary-card">
                <div class="label">Total Equity</div>
                <div class="value">{{ number_format((float) $statement['totalEquity'], 2) }}</div>
            </div>
            <div class="summary-card">
                <div class="label">Difference</div>
                <div class="value">{{ number_format((float) abs($statement['difference']), 2) }}</div>
            </div>
        </div>
    </div>

    @foreach($statement['assetSections'] as $section)
        <div class="section">
            <h3>{{ $section['label'] }}</h3>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Account</th>
                            <th>Balance</th>
                            <th class="amount">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($section['rows'] as $row)
                            <tr>
                                <td>{{ $row['code'] }}</td>
                                <td>{{ $row['name'] }}</td>
                                <td>{{ $row['balance_display'] }}</td>
                                <td class="amount">{{ number_format((float) $row['statement_amount'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4">No balances in this section.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach

    @foreach($statement['liabilitySections'] as $section)
        <div class="section">
            <h3>{{ $section['label'] }}</h3>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Account</th>
                            <th>Balance</th>
                            <th class="amount">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($section['rows'] as $row)
                            <tr>
                                <td>{{ $row['code'] }}</td>
                                <td>{{ $row['name'] }}</td>
                                <td>{{ $row['balance_display'] }}</td>
                                <td class="amount">{{ number_format((float) $row['statement_amount'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4">No balances in this section.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach

    <div class="section">
        <h3>{{ $statement['equitySection']['label'] }}</h3>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Account</th>
                        <th>Balance</th>
                        <th class="amount">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($statement['equitySection']['rows'] as $row)
                        <tr>
                            <td>{{ $row['code'] }}</td>
                            <td>{{ $row['name'] }}</td>
                            <td>{{ $row['balance_display'] }}</td>
                            <td class="amount">{{ number_format((float) $row['statement_amount'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
