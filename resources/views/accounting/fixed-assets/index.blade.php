@extends('accounting.layout')

@section('title', 'Fixed Assets')

@section('content')
    <div class="topbar">
        <div>
            <p class="eyebrow">Asset Register</p>
            <h1>Fixed Assets</h1>
            <p>Track asset cost, accumulated depreciation, and current net book value.</p>
        </div>
        <div class="range-chip">As Of {{ $asOf->format('d M Y') }}</div>
    </div>

    <div class="panel">
        @include('accounting._tabs')

        @if (session('success'))
            <div class="badge badge-emerald" style="margin-bottom:14px;">{{ session('success') }}</div>
        @endif

        <div class="filters">
            <div>
                <h2 style="margin:0 0 6px;">Asset Filters</h2>
                <p class="panel-subtitle" style="margin:0;">View the register by category and accounting date.</p>
            </div>
            <form method="GET" class="filter-form">
                <input type="date" name="as_of" value="{{ $asOf->toDateString() }}">
                <select name="category">
                    <option value="">All Asset Categories</option>
                    @foreach ($fixedAssetDefinitions as $key => $definition)
                        <option value="{{ $key }}" @selected($category === $key)>{{ $definition['label'] }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-primary">Apply Filters</button>
                <a href="{{ route('accounting.fixed-assets.index') }}" class="btn btn-light">Reset</a>
                @if (auth()->user()?->hasPermission('accounting.fixed_assets.manage'))
                    <a href="{{ route('accounting.fixed-assets.create') }}" class="btn btn-primary">Add Fixed Asset</a>
                @endif
            </form>
        </div>
    </div>

    <div class="cards-grid" style="margin-bottom:20px;">
        <div class="summary-card tone-teal">
            <div class="label">Asset Cost</div>
            <div class="value">{{ number_format((float) $assets->sum(fn ($item) => (float) $item['model']->acquisition_cost), 2) }}</div>
        </div>
        <div class="summary-card tone-amber">
            <div class="label">Accumulated Depreciation</div>
            <div class="value">{{ number_format((float) $assets->sum('accumulated_depreciation'), 2) }}</div>
        </div>
        <div class="summary-card tone-emerald">
            <div class="label">Net Book Value</div>
            <div class="value">{{ number_format((float) $assets->sum('net_book_value'), 2) }}</div>
        </div>
        <div class="summary-card tone-violet">
            <div class="label">Registered Assets</div>
            <div class="value">{{ $assets->count() }}</div>
        </div>
        <div class="summary-card tone-slate">
            <div class="label">Monthly Depreciation</div>
            <div class="value">{{ number_format((float) $assets->sum('monthly_depreciation'), 2) }}</div>
        </div>
    </div>

    <div class="two-up">
        <div class="panel">
            <h2>Fixed Asset Register</h2>
            <p class="panel-subtitle">Asset cost and depreciation values are calculated from acquisition cost, salvage value, and useful life.</p>

            @if ($assets->isEmpty())
                <div class="empty-state">No fixed assets matched that filter yet.</div>
            @else
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Asset</th>
                                <th>Category</th>
                                <th>Acquired</th>
                                <th>Accounts</th>
                                <th class="amount">Cost</th>
                                <th class="amount">Monthly Dep.</th>
                                <th class="amount">Accum. Dep.</th>
                                <th class="amount">NBV</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($assets as $assetRow)
                                @php($asset = $assetRow['model'])
                                <tr>
                                    <td>
                                        <strong>{{ $asset->asset_name }}</strong><br>
                                        <span style="color:#667085;">{{ $asset->asset_code ?: 'No Asset Code' }}</span>
                                    </td>
                                    <td>{{ $assetRow['definition']['label'] }}</td>
                                    <td>{{ optional($asset->acquisition_date)->format('d M Y') }}</td>
                                    <td>
                                        <strong>{{ $assetRow['definition']['asset_account_code'] }}</strong><br>
                                        <span style="color:#667085;">/{{ $assetRow['definition']['accumulated_depreciation_account_code'] }}</span>
                                     </td>
                                     <td class="amount">{{ number_format((float) $asset->acquisition_cost, 2) }}</td>
                                     <td class="amount">{{ number_format((float) $assetRow['monthly_depreciation'], 2) }}</td>
                                     <td class="amount">{{ number_format((float) $assetRow['accumulated_depreciation'], 2) }}</td>
                                     <td class="amount">{{ number_format((float) $assetRow['net_book_value'], 2) }}</td>
                                 </tr>
                             @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="panel">
            <h2>Category Breakdown</h2>
            <p class="panel-subtitle">See which asset categories are carrying the highest cost and net book value.</p>

            @if ($categoryBreakdown->isEmpty())
                <div class="empty-state">No fixed asset categories are available yet.</div>
            @else
                <div class="mini-stat-list">
                    @foreach ($categoryBreakdown as $categoryRow)
                        <div class="mini-stat" style="align-items:flex-start;">
                            <div>
                                <div class="name">{{ $categoryRow['label'] }}</div>
                                <div style="margin-top:6px; color:#667085; font-size:13px;">
                                    Assets: {{ $categoryRow['asset_count'] }}<br>
                                    Accum. Dep.: {{ number_format((float) $categoryRow['accumulated_depreciation'], 2) }}
                                </div>
                            </div>
                            <div class="amount">{{ number_format((float) $categoryRow['net_book_value'], 2) }}</div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <div class="panel">
        <h2>Depreciation Schedule</h2>
        <p class="panel-subtitle">Monthly depreciation, months used, and remaining useful life are derived from the registered asset inputs.</p>

        @if ($assets->isEmpty())
            <div class="empty-state">No fixed assets are available for depreciation yet.</div>
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Asset</th>
                            <th class="amount">Depreciable Base</th>
                            <th class="amount">Monthly Dep.</th>
                            <th class="amount">Months Used</th>
                            <th class="amount">Months Remaining</th>
                            <th class="amount">Salvage Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($assets as $assetRow)
                            @php($asset = $assetRow['model'])
                            <tr>
                                <td>
                                    <strong>{{ $asset->asset_name }}</strong><br>
                                    <span style="color:#667085;">{{ $assetRow['definition']['label'] }}</span>
                                </td>
                                <td class="amount">{{ number_format((float) $assetRow['depreciable_base'], 2) }}</td>
                                <td class="amount">{{ number_format((float) $assetRow['monthly_depreciation'], 2) }}</td>
                                <td class="amount">{{ number_format((float) $assetRow['months_elapsed'], 0) }}</td>
                                <td class="amount">{{ number_format((float) $assetRow['months_remaining'], 0) }}</td>
                                <td class="amount">{{ number_format((float) $asset->salvage_value, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
