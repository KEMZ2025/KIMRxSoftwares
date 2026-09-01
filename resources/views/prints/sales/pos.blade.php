@php
    $isApprovedReceipt = $sale->status === 'approved';
    $isProforma = $sale->status === 'proforma';
    $printedAtFallback = now()->format('D M d Y, h:i:s A');
    $changeAmount = max(0, (float) $sale->amount_received - (float) $sale->total_amount);
    $balanceDue = (float) $sale->balance_due;
    $settlementLabel = $isApprovedReceipt
        ? ($changeAmount > 0 ? 'Change' : ($balanceDue > 0.009 ? 'Amount Due' : 'Change'))
        : 'Balance Due';
    $settlementAmount = $isApprovedReceipt
        ? ($changeAmount > 0 ? $changeAmount : ($balanceDue > 0.009 ? $balanceDue : 0))
        : $balanceDue;
    $documentNumberLabel = $isApprovedReceipt ? 'Receipt#' : ($isProforma ? 'Proforma#' : 'Invoice#');
    $documentNumberValue = $isApprovedReceipt
        ? ($sale->receipt_number ?: 'Not generated yet')
        : $sale->invoice_number;
    $documentDateValue = ($isApprovedReceipt && $sale->approved_at)
        ? $sale->approved_at->format('D M d Y, h:i A')
        : (optional($sale->sale_date)->format('D M d Y') ?? $printedAtFallback);
    $customerName = $sale->customer?->name ?? 'Walk-in Customer';
    $servedBy = $sale->servedByUser?->name ?? 'N/A';
    $headerAddress = ($branding['show_branch_contacts'] ?? false) && !empty($branding['branch_address'])
        ? $branding['branch_address']
        : ($branding['company_address'] ?? null);
    $headerPhoneLine = collect([
        ($branding['show_branch_contacts'] ?? false) ? ($branding['branch_phone'] ?? null) : null,
        $branding['company_phone'] ?? null,
    ])->filter()->unique()->implode('/');
    $headerEmailLine = collect([
        ($branding['show_branch_contacts'] ?? false) ? ($branding['branch_email'] ?? null) : null,
        $branding['company_email'] ?? null,
    ])->filter()->unique()->implode('/');
    $formatQty = function ($quantity) {
        $value = (float) $quantity;

        return abs($value - round($value)) < 0.0001
            ? number_format($value, 0)
            : rtrim(rtrim(number_format($value, 2), '0'), '.');
    };
    $formatMoney = fn ($amount) => number_format((float) $amount, 0);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KIM Rx</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <style>
        @page {
            size: 80mm auto;
            margin: 2mm;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #eef2f7;
            color: #111827;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .toolbar {
            width: 80mm;
            margin: 8px auto 0;
            display: flex;
            gap: 6px;
        }
        .btn {
            flex: 1;
            border: none;
            border-radius: 999px;
            padding: 7px 9px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 700;
        }
        .btn-print { background: #155eef; color: #fff; }
        .btn-close { background: #e5e7eb; color: #172033; }
        .receipt {
            width: 80mm;
            margin: 8px auto;
            background: #fff;
            padding: 5px 5px 8px;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.10);
        }
        .center { text-align: center; }
        .logo {
            width: 52px;
            height: 34px;
            object-fit: contain;
            margin: 0 auto 2px;
            display: block;
        }
        h1 {
            margin: 0 0 2px;
            font-size: 13px;
            line-height: 1.05;
            font-weight: 800;
            text-transform: uppercase;
        }
        .muted {
            color: #111827;
            font-size: 10.5px;
            line-height: 1.22;
            font-weight: 700;
        }
        .badge {
            display: inline-block;
            margin-top: 8px;
            padding: 4px 9px;
            border-radius: 999px;
            background: #fff;
            color: #000;
            border: 1px solid #000;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
        }
        .divider {
            border-top: 1px dashed #9ca3af;
            margin: 10px 0;
        }
        .meta-row,
        .total-row {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            font-size: 11px;
            padding: 3px 0;
            color: #111827;
            font-weight: 700;
        }
        .meta-row strong,
        .total-row strong {
            font-size: 11px;
        }
        .item {
            padding: 8px 0;
            border-bottom: 1px dotted #d0d5dd;
        }
        .item:last-child {
            border-bottom: none;
        }
        .item-name {
            font-size: 12px;
            font-weight: 700;
        }
        .item-meta {
            margin-top: 3px;
            color: #111827;
            font-size: 11px;
            font-weight: 700;
        }
        .batch-number {
            font-weight: 800;
            color: #000;
        }
        .item-line {
            margin-top: 4px;
            display: flex;
            justify-content: space-between;
            gap: 8px;
            font-size: 11px;
        }
        .vip-small-receipt .item {
            padding: 5px 0;
            border-bottom: 1px dotted #000;
        }
        .vip-small-receipt .item-name,
        .vip-small-receipt .item-line,
        .vip-small-receipt .item-line strong,
        .vip-small-receipt .batch-number {
            font-weight: 900;
            color: #000;
        }
        .vip-small-receipt .item-name {
            font-size: 12px;
            line-height: 1.2;
        }
        .vip-small-receipt .item-meta {
            margin-top: 2px;
            font-size: 10px;
            line-height: 1.2;
        }
        .vip-small-receipt .item-line {
            margin-top: 3px;
            font-size: 11px;
        }
        .totals {
            margin-top: 8px;
        }
        .total-row.grand {
            padding-top: 8px;
            border-top: 1px solid #111827;
            font-size: 13px;
        }
        .footer {
            margin-top: 5px;
            text-align: center;
            font-size: 10px;
            color: #111827;
            font-weight: 700;
            line-height: 1.25;
        }

        .doc-title {
            margin-top: 3px;
            font-size: 11px;
            font-weight: 800;
        }

        .meta-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
            gap: 2px 8px;
            margin: 6px 0;
            font-size: 10.5px;
            line-height: 1.2;
        }

        .meta-pair {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr);
            gap: 3px;
            min-width: 0;
        }

        .meta-pair strong {
            color: #020617;
            font-weight: 800;
            word-break: break-word;
        }

        .items-table,
        .totals-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 10.5px;
            line-height: 1.18;
        }

        .items-table th,
        .items-table td,
        .totals-table td {
            border: 1px solid #c7cdd6;
            padding: 3px 4px;
            vertical-align: top;
        }

        .items-table th {
            background: #f4f6f8;
            color: #111827;
            font-weight: 800;
            text-align: left;
        }

        .product-cell {
            width: 47%;
            word-break: break-word;
            font-weight: 700;
        }

        .qty-col {
            width: 13%;
            text-align: center;
            white-space: nowrap;
        }

        .money-col {
            width: 20%;
            text-align: right;
            white-space: nowrap;
            font-weight: 700;
        }

        .totals-table {
            margin-top: 5px;
        }

        .totals-table td {
            padding: 3px 5px;
        }

        .totals-table .label {
            text-align: right;
            font-weight: 700;
        }

        .totals-table .value {
            width: 28%;
            text-align: right;
            font-weight: 800;
            white-space: nowrap;
        }

        .totals-table .grand td {
            font-size: 11.5px;
            font-weight: 900;
        }

        .notes {
            margin-top: 5px;
            font-size: 10px;
            line-height: 1.25;
            color: #111827;
        }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .receipt {
                margin: 0;
                box-shadow: none;
                padding: 0;
            }
        }
</style>
</head>
<body>
    <div class="toolbar">
        <button class="btn btn-print" onclick="prepareAndPrint()">Print</button>
        <button class="btn btn-close" onclick="window.close()">Close</button>
    </div>

    <div class="receipt">
        <div class="center">
            @if(($branding['show_logo'] ?? false) && !empty($branding['logo_url']))
                <img src="{{ $branding['logo_url'] }}" alt="Logo" class="logo" data-print-blocking="true" fetchpriority="high" loading="eager">
            @endif

            <h1>{{ $branding['company_name'] ?? 'KIM Rx' }}</h1>

            @if(!empty($headerAddress))
                <div class="muted">{{ $headerAddress }}</div>
            @endif

            @if($headerPhoneLine !== '')
                <div class="muted">Tel: {{ $headerPhoneLine }}</div>
            @endif

            @if($headerEmailLine !== '')
                <div class="muted">Email: {{ $headerEmailLine }}</div>
            @endif

            @if(!empty($branding['tax_number']))
                <div class="muted">{{ $branding['tax_label'] ?? 'TIN' }}: {{ $branding['tax_number'] }}</div>
            @endif

            <div class="doc-title">{{ $documentTitle }}</div>
        </div>

        <div class="meta-grid">
            <div class="meta-pair"><span>{{ $documentNumberLabel }}</span><strong>{{ $documentNumberValue }}</strong></div>
            <div class="meta-pair"><span>Date:</span><strong>{{ $documentDateValue }}</strong></div>
            <div class="meta-pair"><span>Name:</span><strong>{{ $customerName }}</strong></div>
            <div class="meta-pair"><span>By:</span><strong>{{ $servedBy }}</strong></div>
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th class="product-cell">Brand Name</th>
                    <th class="qty-col">Qty</th>
                    <th class="money-col">Rate</th>
                    <th class="money-col">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($displayItems as $item)
                    <tr>
                        <td class="product-cell">{{ $item['product_name'] }}</td>
                        <td class="qty-col">{{ $formatQty($item['quantity']) }}</td>
                        <td class="money-col">{{ $formatMoney($item['unit_price']) }}</td>
                        <td class="money-col">{{ $formatMoney($item['line_total']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="totals-table">
            <tbody>
            @if((float) $sale->tax_amount > 0)
                <tr>
                    <td class="label">Tax Amount</td>
                    <td class="value">{{ $formatMoney($sale->tax_amount) }}</td>
                </tr>
            @endif
            <tr class="grand">
                <td class="label">Total Amount</td>
                <td class="value">{{ $formatMoney($sale->total_amount) }}</td>
            </tr>
            @if($isApprovedReceipt)
                <tr>
                    <td class="label">Amount Received</td>
                    <td class="value">{{ $formatMoney($sale->amount_received) }}</td>
                </tr>
                @if(abs((float) $settlementAmount) >= 0.01)
                    <tr>
                        <td class="label">{{ $settlementLabel }}</td>
                        <td class="value">{{ $formatMoney($settlementAmount) }}</td>
                    </tr>
                @endif
            @else
                <tr>
                    <td class="label">Amount Applied</td>
                    <td class="value">{{ $formatMoney($sale->amount_paid) }}</td>
                </tr>
                <tr>
                    <td class="label">Balance Due</td>
                    <td class="value">{{ $formatMoney($sale->balance_due) }}</td>
                </tr>
            @endif
            </tbody>
        </table>

        @if(!empty($sale->notes))
            <div class="notes"><strong>Notes:</strong> {{ $sale->notes }}</div>
        @endif

        @if(!empty($documentFooter))
            <div class="footer">
                <div>{{ $documentFooter }}</div>
            </div>
        @endif
    </div>

    <script>
        function formatPrintTimestamp(date) {
            return new Intl.DateTimeFormat(undefined, {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
            }).format(date);
        }

        function syncPrintTimestamps() {
            var value = formatPrintTimestamp(new Date());

            document.querySelectorAll('.js-print-timestamp').forEach(function (node) {
                node.textContent = value;
            });
        }

        function waitForPrintAssets(callback) {
            var assets = Array.prototype.slice.call(document.querySelectorAll('[data-print-blocking="true"]'));

            if (assets.length === 0) {
                callback();
                return;
            }

            var pending = 0;
            var finished = false;

            function complete() {
                if (finished) {
                    return;
                }

                finished = true;
                callback();
            }

            function settle() {
                pending -= 1;

                if (pending <= 0) {
                    complete();
                }
            }

            assets.forEach(function (asset) {
                if (asset.complete && asset.naturalWidth > 0) {
                    return;
                }

                pending += 1;
                asset.addEventListener('load', settle, { once: true });
                asset.addEventListener('error', settle, { once: true });
            });

            if (pending === 0) {
                complete();
                return;
            }

            window.setTimeout(complete, 450);
        }

        function prepareAndPrint() {
            syncPrintTimestamps();
            waitForPrintAssets(function () {
                window.print();
            });
        }

        syncPrintTimestamps();
        window.addEventListener('beforeprint', syncPrintTimestamps);
    </script>

    @if($autoPrint ?? false)
        <script>
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', prepareAndPrint, { once: true });
            } else {
                prepareAndPrint();
            }
        </script>
    @endif
</body>
</html>
