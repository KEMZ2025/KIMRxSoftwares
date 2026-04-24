@extends('prints.layout')

@php
    $pageTitle = 'Profit & Loss';
    $pageBadge = 'Accounting';
    $rangeLabel = $from->format('d M Y') . ' to ' . $to->format('d M Y');
@endphp

@section('content')
    <div class="section">
        <div class="summary-grid">
            <div class="summary-card">
                <div class="label">Sales Revenue</div>
                <div class="value">{{ number_format((float) $statement['salesRevenue'], 2) }}</div>
            </div>
            <div class="summary-card">
                <div class="label">Gross Profit</div>
                <div class="value">{{ number_format((float) $statement['grossProfit'], 2) }}</div>
            </div>
            <div class="summary-card">
                <div class="label">Total Expenses</div>
                <div class="value">{{ number_format((float) $statement['totalExpenses'], 2) }}</div>
            </div>
            <div class="summary-card">
                <div class="label">Net Profit / Loss</div>
                <div class="value">{{ number_format((float) $statement['netProfit'], 2) }}</div>
            </div>
        </div>
    </div>

    @foreach($statement['sections'] as $section)
        <div class="section">
            <h3>{{ $section['label'] }}</h3>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Account</th>
                            <th class="amount">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($section['rows'] as $row)
                            <tr>
                                <td>{{ $row['code'] }}</td>
                                <td>{{ $row['name'] }}</td>
                                <td class="amount">{{ number_format((float) $row['statement_amount'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3">No values posted in this section.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="2">{{ $section['label'] }} Total</th>
                            <th class="amount">{{ number_format((float) $section['total'], 2) }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    @endforeach
@endsection
