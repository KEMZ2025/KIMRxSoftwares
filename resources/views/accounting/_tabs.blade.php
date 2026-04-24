@php
    $accountingTabs = [
        ['show' => auth()->user()?->hasPermission('accounting.view'), 'route' => 'accounting.index', 'match' => 'accounting.index', 'label' => 'Overview'],
        ['show' => auth()->user()?->hasPermission('accounting.chart'), 'route' => 'accounting.chart', 'match' => 'accounting.chart', 'label' => 'Chart Of Accounts'],
        ['show' => auth()->user()?->hasPermission('accounting.general_ledger'), 'route' => 'accounting.general-ledger', 'match' => 'accounting.general-ledger', 'label' => 'General Ledger'],
        ['show' => auth()->user()?->hasPermission('accounting.trial_balance'), 'route' => 'accounting.trial-balance', 'match' => 'accounting.trial-balance', 'label' => 'Trial Balance'],
        ['show' => auth()->user()?->hasPermission('accounting.journals'), 'route' => 'accounting.journals', 'match' => 'accounting.journals', 'label' => 'Journals'],
        ['show' => auth()->user()?->hasPermission('accounting.vouchers'), 'route' => 'accounting.vouchers', 'match' => 'accounting.vouchers', 'label' => 'Payment Vouchers'],
        ['show' => auth()->user()?->hasPermission('accounting.profit_loss'), 'route' => 'accounting.profit-loss', 'match' => 'accounting.profit-loss', 'label' => 'Profit & Loss'],
        ['show' => auth()->user()?->hasPermission('accounting.balance_sheet'), 'route' => 'accounting.balance-sheet', 'match' => 'accounting.balance-sheet', 'label' => 'Balance Sheet'],
        ['show' => auth()->user()?->hasAnyPermission(['accounting.expenses.view', 'accounting.expenses.manage']), 'route' => 'accounting.expenses.index', 'match' => 'accounting.expenses.*', 'label' => 'Expenses'],
        ['show' => auth()->user()?->hasAnyPermission(['accounting.fixed_assets.view', 'accounting.fixed_assets.manage']), 'route' => 'accounting.fixed-assets.index', 'match' => 'accounting.fixed-assets.*', 'label' => 'Fixed Assets'],
    ];

    $accountingActions = [
        'accounting.chart' => ['print' => 'accounting.chart.print', 'download' => 'accounting.chart.download'],
        'accounting.general-ledger' => ['print' => 'accounting.general-ledger.print', 'download' => 'accounting.general-ledger.download'],
        'accounting.trial-balance' => ['print' => 'accounting.trial-balance.print', 'download' => 'accounting.trial-balance.download'],
        'accounting.journals' => ['print' => 'accounting.journals.print', 'download' => 'accounting.journals.download'],
        'accounting.vouchers' => ['print' => 'accounting.vouchers.print', 'download' => 'accounting.vouchers.download'],
        'accounting.profit-loss' => ['print' => 'accounting.profit-loss.print', 'download' => 'accounting.profit-loss.download'],
        'accounting.balance-sheet' => ['print' => 'accounting.balance-sheet.print', 'download' => 'accounting.balance-sheet.download'],
        'accounting.expenses.index' => ['print' => 'accounting.expenses.print', 'download' => 'accounting.expenses.download'],
        'accounting.fixed-assets.index' => ['print' => 'accounting.fixed-assets.print', 'download' => 'accounting.fixed-assets.download'],
    ];

    $activeActions = $accountingActions[$navRoute] ?? null;
    $suppressActions = request()->routeIs('accounting.expenses.create', 'accounting.fixed-assets.create');
@endphp

<div class="tabbar-wrap">
    <div class="tabbar">
        @foreach ($accountingTabs as $tab)
            @if ($tab['show'])
                <a href="{{ route($tab['route']) }}" class="{{ request()->routeIs($tab['match']) ? 'active' : '' }}">{{ $tab['label'] }}</a>
            @endif
        @endforeach
    </div>

    @if ($activeActions && !$suppressActions)
        <div class="tabbar-actions">
            <a href="{{ route($activeActions['print'], request()->query() + ['autoprint' => 1]) }}" class="btn btn-primary" target="_blank" rel="noopener">Print</a>
            <a href="{{ route($activeActions['download'], request()->query() + ['format' => 'pdf']) }}" class="btn btn-light">Download PDF</a>
            <a href="{{ route($activeActions['download'], request()->query() + ['format' => 'csv']) }}" class="btn btn-light">Download CSV</a>
        </div>
    @endif
</div>
