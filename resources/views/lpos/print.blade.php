@php
    $savedLogo = trim((string) ($order->client?->logo ?? ''));
    $logoUrl = $savedLogo === '' ? null : ((str_starts_with($savedLogo, 'https://') || str_starts_with($savedLogo, 'http://') || str_starts_with($savedLogo, 'data:'))
        ? $savedLogo : asset(ltrim(str_replace('\\', '/', $savedLogo), '/')));
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $order->order_number }} - Local Purchase Order</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #edf1f3; font-family: Arial, sans-serif; color: #1a2d38; font-size: 12px; }
        .toolbar { max-width: 210mm; margin: 18px auto; display: flex; justify-content: space-between; align-items: center; }
        .toolbar a, .toolbar button { border: 1px solid #becbd0; background: #fff; padding: 9px 13px; border-radius: 5px; font: inherit; cursor: pointer; text-decoration: none; color: #203d4b; }
        .paper { position: relative; width: 210mm; min-height: 297mm; padding: 16mm; margin: auto auto 24px; background: #fff; box-shadow: 0 5px 20px #23374620; }
        header { display: flex; align-items: start; justify-content: space-between; gap: 20px; border-bottom: 2px solid #14865b; padding-bottom: 16px; }
        .brand { display: flex; align-items: start; gap: 12px; }
        .brand img { max-width: 65px; max-height: 65px; object-fit: contain; }
        .brand h1 { font-size: 18px; margin: 0 0 5px; }
        .brand p, .document-head p { margin: 3px 0; }
        .document-head { text-align: right; }
        .document-head h2 { margin: 0 0 7px; font-size: 17px; }
        .status { display: inline-block; margin-top: 6px; padding: 4px 8px; border: 1px solid #77959e; font-size: 10px; font-weight: bold; text-transform: uppercase; }
        .info { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin: 22px 0; }
        .info h3 { font-size: 11px; text-transform: uppercase; color: #55707c; margin: 0 0 7px; }
        .info p { margin: 3px 0; line-height: 1.35; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 9px 7px; border-bottom: 1px solid #dbe4e7; vertical-align: top; }
        th { background: #f0f5f4; text-align: left; text-transform: uppercase; font-size: 10px; }
        .num { text-align: right; white-space: nowrap; }
        .totals { width: 44%; margin: 16px 0 0 auto; }
        .totals div { display: flex; justify-content: space-between; padding: 5px 0; }
        .totals .grand { margin-top: 5px; padding-top: 10px; border-top: 2px solid #91a9ae; font-size: 14px; font-weight: bold; }
        .notes { margin-top: 24px; white-space: pre-wrap; }
        .footer { margin-top: 45px; display: flex; justify-content: space-between; gap: 25px; }
        .signature { width: 46%; border-top: 1px solid #8b9da7; padding-top: 7px; }
        tr { break-inside: avoid; }
        @page { size: A4; margin: 0; }
        @media print { body { background: #fff; } .toolbar { display: none; } .paper { box-shadow: none; margin: 0; width: auto; min-height: 297mm; } }
        @media screen and (max-width: 800px) { .paper { width: 100%; min-height: 0; padding: 20px; } .toolbar { padding: 0 12px; } .info { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="toolbar"><a href="{{ route('lpos.show', $order) }}">Back to LPO</a><button type="button" onclick="window.print()">Print A4</button></div>
<div class="paper">
    <header>
        <div class="brand">
            @if($logoUrl)<img src="{{ $logoUrl }}" alt="">@endif
            <div>
                <h1>{{ $order->client?->name }}</h1>
                <p>{{ $order->branch?->name }}</p>
                @if($order->branch?->address)<p>{{ $order->branch->address }}</p>@endif
                @if($order->branch?->phone)<p>{{ $order->branch->phone }}</p>@endif
            </div>
        </div>
        <div class="document-head">
            <h2>LOCAL PURCHASE ORDER</h2>
            <p><strong>{{ $order->order_number }}</strong></p>
            <p>{{ $order->order_date->format('d M Y') }}</p>
            <span class="status">{{ $order->status }}</span>
        </div>
    </header>

    <div class="info">
        <div><h3>Supplier</h3><p><strong>{{ $order->supplier_name }}</strong></p><p>{{ $order->supplier_address }}</p><p>{{ $order->supplier_phone }}</p></div>
        <div><h3>Delivery</h3><p><strong>To:</strong> {{ $order->delivery_address ?: ($order->branch?->address ?: $order->branch?->name) }}</p><p><strong>Expected:</strong> {{ $order->expected_delivery_date?->format('d M Y') ?? 'To be agreed' }}</p></div>
    </div>

    <table>
        <thead><tr><th>#</th><th>Item</th><th>Unit</th><th class="num">Qty</th><th class="num">Unit Cost</th><th class="num">Amount (UGX)</th></tr></thead>
        <tbody>
            @foreach($order->items as $item)
                <tr>
                    <td>{{ $item->line_number }}</td><td>{{ $item->description }}</td><td>{{ $item->unit_name }}</td>
                    <td class="num">{{ number_format((float) $item->quantity, 2) }}</td>
                    <td class="num">{{ number_format((float) $item->unit_price, 2) }}</td>
                    <td class="num">{{ number_format((float) $item->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="totals">
        <div><span>Subtotal</span><span>{{ number_format((float) $order->subtotal, 2) }}</span></div>
        @if((float) $order->discount_amount > 0)<div><span>Discount</span><span>{{ number_format((float) $order->discount_amount, 2) }}</span></div>@endif
        @if((float) $order->tax_amount > 0)<div><span>Tax</span><span>{{ number_format((float) $order->tax_amount, 2) }}</span></div>@endif
        <div class="grand"><span>Total (UGX)</span><span>{{ number_format((float) $order->total_amount, 2) }}</span></div>
    </div>
    @if($order->notes)<div class="notes"><strong>Notes / Terms</strong><br>{{ $order->notes }}</div>@endif
    @if($order->status === 'cancelled')<div class="notes"><strong>Cancelled:</strong> {{ $order->cancellation_reason }}</div>@endif
    <div class="footer"><div class="signature">Prepared by: {{ $order->createdByUser?->name ?? 'N/A' }}</div><div class="signature">Authorized by: {{ $order->issuedByUser?->name ?? '________________' }}</div></div>
</div>
</body>
</html>
