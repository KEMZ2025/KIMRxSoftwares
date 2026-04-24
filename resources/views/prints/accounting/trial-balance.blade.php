@extends('prints.layout')

@php
    $pageTitle = 'Trial Balance';
    $pageBadge = abs($trialBalance['difference']) < 0.005 ? 'Balanced' : 'Review Needed';
    $rangeLabel = 'As of ' . $asOf->format('d M Y');
@endphp

@section('content')
    <div class="section">
        <div class="summary-grid">
            <div class="summary-card">
                <div class="label">Debit Total</div>
                <div class="value">{{ number_format((float) $trialBalance['totalDebit'], 2) }}</div>
            </div>
            <div class="summary-card">
                <div class="label">Credit Total</div>
                <div class="value">{{ number_format((float) $trialBalance['totalCredit'], 2) }}</div>
            </div>
            <div class="summary-card">
                <div class="label">Difference</div>
                <div class="value">{{ number_format((float) abs($trialBalance['difference']), 2) }}</div>
            </div>
            <div class="summary-card">
                <div class="label">Accounts</div>
                <div class="value">{{ $trialBalance['accountCount'] }}</div>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="table-wrap">
            <table>
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
                    @foreach($trialBalance['rows'] as $row)
                        <tr>
                            <td>{{ $row['code'] }}</td>
                            <td>{{ $row['name'] }}</td>
                            <td>{{ ucfirst($row['category']) }}</td>
                            <td>{{ $row['balance_display'] }}</td>
                            <td class="amount">{{ $row['debit'] > 0 ? number_format((float) $row['debit'], 2) : '-' }}</td>
                            <td class="amount">{{ $row['credit'] > 0 ? number_format((float) $row['credit'], 2) : '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="4">Totals</th>
                        <th class="amount">{{ number_format((float) $trialBalance['totalDebit'], 2) }}</th>
                        <th class="amount">{{ number_format((float) $trialBalance['totalCredit'], 2) }}</th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
@endsection
