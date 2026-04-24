@extends('prints.layout')

@php
    $pageTitle = 'Fixed Assets';
    $pageBadge = 'Accounting';
    $rangeLabel = 'As of ' . $asOf->format('d M Y');
@endphp

@section('content')
    <div class="section">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Asset</th>
                        <th>Category</th>
                        <th>Acquired</th>
                        <th class="amount">Cost</th>
                        <th class="amount">Salvage</th>
                        <th class="amount">Monthly Depreciation</th>
                        <th class="amount">Accumulated Depreciation</th>
                        <th class="amount">Net Book Value</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($assets as $asset)
                        <tr>
                            <td>{{ $asset['model']->asset_name }}</td>
                            <td>{{ $asset['definition']['label'] }}</td>
                            <td>{{ optional($asset['model']->acquisition_date)->format('d M Y') }}</td>
                            <td class="amount">{{ number_format((float) $asset['model']->acquisition_cost, 2) }}</td>
                            <td class="amount">{{ number_format((float) $asset['model']->salvage_value, 2) }}</td>
                            <td class="amount">{{ number_format((float) $asset['monthly_depreciation'], 2) }}</td>
                            <td class="amount">{{ number_format((float) $asset['accumulated_depreciation'], 2) }}</td>
                            <td class="amount">{{ number_format((float) $asset['net_book_value'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8">No fixed assets available.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
