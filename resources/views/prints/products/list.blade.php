<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Product List</title>
    <style>
        @page { size: A4 landscape; margin: 9mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #172033; font-family: Helvetica, Arial, sans-serif; font-size: 8px; }
        .product-page { page-break-after: always; }
        .product-page:last-child { page-break-after: auto; }
        .header { border-bottom: 1.5px solid #18864b; margin-bottom: 5px; padding-bottom: 4px; }
        .header h1 { margin: 0; font-size: 14px; }
        .header h2 { margin: 2px 0 0; font-size: 11px; }
        .meta { margin-top: 2px; color: #475467; font-size: 7px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        td { width: 33.333%; border: 1px solid #aeb7c4; padding: 3px 4px; vertical-align: top; }
        .product-name { font-size: 8px; font-weight: bold; line-height: 1.15; }
        .number { display: inline-block; min-width: 22px; color: #17643b; }
        .product-meta { margin-top: 1px; color: #526173; font-size: 7px; line-height: 1.12; }
        .empty { border-color: transparent; }
        .page-number { margin-top: 4px; color: #667085; text-align: right; font-size: 7px; }
    </style>
</head>
<body>
    @php
        $productsPerPage = 72;
        $productPages = $products->values()->chunk($productsPerPage);
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
                <tbody>
                    @foreach($pageProducts->values()->chunk(3) as $rowIndex => $rowProducts)
                        <tr>
                            @foreach($rowProducts->values() as $columnIndex => $product)
                                @php
                                    $productNumber = ($pageIndex * $productsPerPage) + ($rowIndex * 3) + $columnIndex + 1;
                                    $unit = $product->unit?->short_name ?: $product->unit?->name;
                                    $details = collect([
                                        $product->strength,
                                        $product->category?->name,
                                        $unit,
                                        $product->is_active ? 'Active' : 'Inactive',
                                    ])->filter(fn ($value) => filled($value))->implode(' | ');
                                @endphp
                                <td>
                                    <div class="product-name"><span class="number">{{ $productNumber }}.</span>{{ $product->name }}</div>
                                    <div class="product-meta">{{ $details }}</div>
                                </td>
                            @endforeach

                            @for($emptyCell = $rowProducts->count(); $emptyCell < 3; $emptyCell++)
                                <td class="empty"></td>
                            @endfor
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
