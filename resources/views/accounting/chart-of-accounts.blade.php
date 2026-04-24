@extends('accounting.layout')

@section('title', 'Chart Of Accounts')

@section('content')
    <div class="topbar">
        <div>
            <p class="eyebrow">Accounting Structure</p>
            <h1>Chart Of Accounts</h1>
            <p>Grouped pharmacy accounts with live balances as of {{ $asOfDate->format('d M Y') }}.</p>
        </div>
        <div class="range-chip">As Of {{ $asOfDate->format('d M Y') }}</div>
    </div>

    <div class="panel">
        @include('accounting._tabs')

        <div class="tabbar" style="margin-bottom:0;">
            <a href="{{ route('accounting.chart') }}" class="{{ $selectedCategory === '' ? 'active' : '' }}">All Categories</a>
            @foreach ($categories as $key => $category)
                <a href="{{ route('accounting.chart', ['category' => $key]) }}" class="{{ $selectedCategory === $key ? 'active' : '' }}">{{ $category['label'] }}</a>
            @endforeach
        </div>
    </div>

    @foreach ($groupedAccounts as $categoryKey => $accounts)
        @continue($selectedCategory !== '' && $selectedCategory !== $categoryKey)

        <div class="panel">
            <h2>{{ $categories[$categoryKey]['label'] }}</h2>
            <p class="panel-subtitle">{{ $categories[$categoryKey]['description'] }}</p>

            <div class="account-card-grid">
                @foreach ($accounts as $account)
                    <div class="account-card">
                        <div class="account-code">{{ $account['code'] }}</div>
                        <div class="account-name">{{ $account['name'] }}</div>
                        <div class="account-balance">{{ $account['balance_display'] }}</div>
                        <div class="account-note">Normal balance: {{ ucfirst($account['normal_balance']) }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
@endsection
