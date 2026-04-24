@extends('prints.layout')

@php
    $pageTitle = 'Chart Of Accounts';
    $pageBadge = 'Accounting';
    $rangeLabel = 'As of ' . $asOfDate->format('d M Y');
@endphp

@section('content')
    @foreach($groupedAccounts as $group)
        <div class="section">
            <h3>{{ $group['definition']['label'] }}</h3>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Account</th>
                            <th>Normal Balance</th>
                            <th class="amount">Statement Amount</th>
                            <th class="amount">Balance Display</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($group['accounts'] as $account)
                            <tr>
                                <td>{{ $account['code'] }}</td>
                                <td>{{ $account['name'] }}</td>
                                <td>{{ ucfirst($account['normal_balance']) }}</td>
                                <td class="amount">{{ number_format((float) $account['statement_amount'], 2) }}</td>
                                <td class="amount">{{ $account['balance_display'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5">No accounts in this category.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach
@endsection
