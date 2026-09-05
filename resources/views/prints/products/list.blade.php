<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Product List</title>
    <style>
        @page { size: A4 landscape; margin: 9mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #172033; font-family: DejaVu Sans, sans-serif; font-size: 9px; }
        .product-page { page-break-after: always; }
        .product-page:last-child { page-break-after: auto; }
        .header { border-bottom: 2px solid #18864b; margin-bottom: 8px; padding-bottom: 6px; }
        .header h1 { margin: 0; font-size: 17px; }
        .header h2 { margin: 3px 0 0; font-size: 13px; }
        .meta { margin-top: 3px; color: #475467; font-size: 8px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #8d99aa; padding: 4px 5px; text-align: left; vertical-align: top; overflow-wrap: break-word; }
        th { background: #e8f3ed; color: #172033; font-size: 8px; }
        .number { width: 5%; text-align: center; }
        .product { width: 28%; }
        .strength { width: 13%; }
        .category { width: 17%; }
        .unit { width: 12%; }
        .barcode { width: 15%; }
        .status { width: 10%; }
        .page-number { margin-top: 5px; color: #667085; text-align: right; font-size: 8px; }
    </style>
</head>
<body>
    @php
        $productPages = $products->chunk(42);
        $pageCount = $productPages->count();
    @endphp

    @forelse($productPages as $pageIndex => $pageProducts)
        <section class="product-page">
            <header class="header">
                <h1>{{ $branding['company_name'] ?? 'KIM Rx' }}</h1>
                <h2>Product List</h2>
                <div class="meta">
                    {{ number_format($products->count()) }} products | Generated {{ $generatedAt->format('d M Y, h:i A') }}
                </div>
            </header>

            <table>
                <thead>
                    <tr>
                        <th class="number">No.</th>
                        <th class="product">Product Name</th>
                        <th class="strength">Strength</th>
                        <th class="category">Category</th>
                        <th class="unit">Unit</th>
                        <th class="barcode">Barcode</th>
                        <th class="status">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($pageProducts as $product)
                        <tr>
                            <td class="number">{{ ($pageIndex * 42) + $loop->iteration }}</td>
                            <td>{{ $product->name }}</td>
                            <td>{{ $product->strength ?: '-' }}</td>
                            <td>{{ $product->category?->name ?: '-' }}</td>
                            <td>{{ $product->unit?->short_name ?: ($product->unit?->name ?: '-') }}</td>
                            <td>{{ $product->barcode ?: '-' }}</td>
                            <td>{{ $product->is_active ? 'Active' : 'Inactive' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="page-number">Page {{ $pageIndex + 1 }} of {{ $pageCount }}</div>
        </section>
    @empty
        <section class="product-page">
            <header class="header">
                <h1>{{ $branding['company_name'] ?? 'KIM Rx' }}</h1>
                <h2>Product List</h2>
            </header>
            <p>No products found.</p>
        </section>
    @endforelse
</body>
</html>
