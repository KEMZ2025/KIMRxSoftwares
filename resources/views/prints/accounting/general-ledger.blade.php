@extends('prints.layout')

@php
    $pageTitle = 'General Ledger';
    $pageBadge = 'Accounting';
    $rangeLabel = $from->format('d M Y') . ' to ' . $to->format('d M Y');
@endphp

@section('content')
    <div class="section">
        <div class="summary-grid">
            <div class="summary-card">
                <div class="label">Period Debits</div>
                <div class="value">{{ number_format((float) $ledger['periodDebit'], 2) }}</div>
            </div>
            <div class="summary-card">
                <div class="label">Period Credits</div>
                <div class="value">{{ number_format((float) $ledger['periodCredit'], 2) }}</div>
            </div>
            @if($ledger['account'])
                <div class="summary-card">
                    <div class="label">Selected Account</div>
                    <div class="value" style="font-size:20px;">{{ $ledger['account']['code'] }}</div>
                </div>
                <div class="summary-card">
                    <div class="label">Closing Balance</div>
                    <div class="value" style="font-size:20px;">{{ $ledger['closingBalance'] }}</div>
                </div>
            @endif
        </div>
    </div>

    <div class="section">
        <div class="table-wrap">
            <table>
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
                    @forelse($ledger['rows'] as $row)
                        <tr>
                            <td>{{ $row['display_date'] }}</td>
                            <td>{{ $row['account_code'] }} - {{ $row['account_name'] }}</td>
                            <td>{{ $row['reference_number'] }}</td>
                            <td>{{ $row['description'] }}</td>
                            <td class="amount">{{ number_format((float) $row['debit'], 2) }}</td>
                            <td class="amount">{{ number_format((float) $row['credit'], 2) }}</td>
                            <td class="amount">{{ $row['running_balance_display'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7">No ledger rows matched this filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
