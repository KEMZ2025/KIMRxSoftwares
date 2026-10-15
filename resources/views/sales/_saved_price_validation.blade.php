@php
    $savedPriceRows = in_array($sale->status, ['pending', 'approved'], true)
        ? $sale->items->map(fn ($item) => [
            'product' => (string) $item->product_id,
            'batch' => (string) $item->product_batch_id,
            'price' => (float) $item->unit_price,
        ])->values()->all()
        : [];
@endphp
const savedInvoicePrices = @json($savedPriceRows);
const savedInvoiceSaleType = @json($sale->sale_type);
function savedInvoicePriceMatches(row) {
    if (document.getElementById('sale_type').value !== savedInvoiceSaleType) return false;
    const product = row.querySelector('.product-select')?.value;
    const batch = row.querySelector('.batch-select')?.value;
    const price = Number(row.querySelector('.unit-price')?.value);
    const matches = savedInvoicePrices.filter(item => item.product === product
        && item.batch === batch && Math.abs(item.price - price) < 0.0001).length;
    if (!matches) return false;
    let used = 0;
    for (const candidate of document.querySelectorAll('.sale-row')) {
        if (candidate.querySelector('.product-select')?.value === product
            && candidate.querySelector('.batch-select')?.value === batch
            && Math.abs(Number(candidate.querySelector('.unit-price')?.value) - price) < 0.0001) used++;
        if (candidate === row) return used <= matches;
    }
    return false;
}
