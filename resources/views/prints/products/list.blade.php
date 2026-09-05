@extends('prints.layout')

@php
    $centeredPrintHeader = true;
    $pageTitle = 'Product List';
    $pageBadge = number_format($products->count()) . ' products';
    $rangeLabel = 'Generated ' . $generatedAt->format('d M Y, h:i A');
@endphp

@push('styles')
    <style>
        @page { size: A4 landscape; margin: 10mm; }
        .page { max-width: none; padding: 0; }
        .section { margin-top: 14px; }
        .table-wrap { border-radius: 0; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th, td { padding: 6px 7px; font-size: 10px; }
        th { font-size: 9px; }
        .col-number { width: 5%; text-align: center; }
        .col-product { width: 28%; }
        .col-strength { width: 13%; }
        .col-category { width: 17%; }
        .col-unit { width: 12%; }
        .col-barcode { width: 15%; }
        .col-status { width: 10%; }
    </style>
@endpush

@section('content')
    <div class="section">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th class="col-number">No.</th>
                        <th class="col-product">Product Name</th>
                        <th class="col-strength">Strength</th>
                        <th class="col-category">Category</th>
                        <th class="col-unit">Unit</th>
                        <th class="col-barcode">Barcode</th>
                        <th class="col-status">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($products as $product)
                        <tr>
                            <td class="col-number">{{ $loop->iteration }}</td>
                            <td>{{ $product->name }}</td>
                            <td>{{ $product->strength ?: '-' }}</td>
                            <td>{{ $product->category?->name ?: '-' }}</td>
                            <td>{{ $product->unit?->short_name ?: ($product->unit?->name ?: '-') }}</td>
                            <td>{{ $product->barcode ?: '-' }}</td>
                            <td>{{ $product->is_active ? 'Active' : 'Inactive' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">No products found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
