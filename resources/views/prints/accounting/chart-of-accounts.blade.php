@extends('prints.layout')

@php
    $pageTitle = 'Chart Of Accounts';
    $pageBadge = 'Accounting';
    $rangeLabel = 'As of ' . $asOfDate->format('d M Y');
@endphp

@section('content')
    @foreach($groupedAccounts as $categoryKey => $accounts)
        @continue(($selectedCategory ?? '') !== '' && ($selectedCategory ?? '') !== $categoryKey)

        @php
            $category = $categories[$categoryKey] ?? [
                'label' => ucwords(str_replace('_', ' ', (string) $categoryKey)),
                'description' => null,
            ];
        @endphp

        <div class="section">
            <h3>{{ $category['label'] }}</h3>
            @if(!empty($category['description']))
                <p>{{ $category['description'] }}</p>
            @endif

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
                        @forelse($accounts as $account)
                            @php
                                $statementAmount = \App\Support\Accounting\ChartOfAccounts::statementAmount(
                                    $account,
                                    (float) ($account['balance'] ?? 0.0)
                                );
                            @endphp
                            <tr>
                                <td>{{ $account['code'] }}</td>
                                <td>{{ $account['name'] }}</td>
                                <td>{{ ucfirst($account['normal_balance']) }}</td>
                                <td class="amount">{{ number_format((float) $statementAmount, 2) }}</td>
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