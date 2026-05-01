@php
    $isApprovedReceipt = $sale->status === 'approved';
    $printedAtFallback = now()->format('d M Y H:i:s');
    $changeAmount = max(0, (float) $sale->amount_received - (float) $sale->total_amount);
    $settlementLabel = $isApprovedReceipt ? ($changeAmount > 0 ? 'Change' : 'Amount Due') : 'Balance Due';
    $settlementAmount = $isApprovedReceipt ? ($changeAmount > 0 ? $changeAmount : (float) $sale->balance_due) : (float) $sale->balance_due;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $documentTitle }} - {{ $sale->invoice_number }}</title>
    <style>
        @page {
            size: 80mm auto;
            margin: 4mm;
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
            margin: 10px auto 0;
            display: flex;
            gap: 8px;
        }
        .btn {
            flex: 1;
            border: none;
            border-radius: 999px;
            padding: 8px 10px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 700;
        }
        .btn-print { background: #155eef; color: #fff; }
        .btn-close { background: #e5e7eb; color: #172033; }
        .receipt {
            width: 80mm;
            margin: 10px auto;
            background: #fff;
            padding: 10px 8px 14px;
            box-shadow: 0 14px 30px rgba(15, 23, 42, 0.10);
        }
        .center { text-align: center; }
        .logo {
            width: 68px;
            height: 68px;
            object-fit: contain;
            margin: 0 auto 6px;
            display: block;
        }
        h1 {
            margin: 0 0 4px;
            font-size: 17px;
        }
        .muted {
            color: #111827;
            font-size: 11px;
            line-height: 1.35;
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
        .totals {
            margin-top: 8px;
        }
        .total-row.grand {
            padding-top: 8px;
            border-top: 1px solid #111827;
            font-size: 13px;
        }
        .footer {
            margin-top: 10px;
            text-align: center;
            font-size: 11px;
            color: #111827;
            font-weight: 700;
            line-height: 1.45;
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
            <h1>{{ $branding['company_name'] }}</h1>
            @if(!empty($branding['branch_name']))
                <div class="muted">{{ $branding['branch_name'] }}@if(!empty($branding['branch_code'])) ({{ $branding['branch_code'] }}) @endif</div>
            @endif
            @if(($branding['show_branch_contacts'] ?? false) && (!empty($branding['branch_phone']) || !empty($branding['branch_email'])))
                <div class="muted">{{ collect([$branding['branch_phone'] ?? null, $branding['branch_email'] ?? null])->filter()->implode(' | ') }}</div>
            @endif
            @if(!empty($branding['company_address']))
                <div class="muted">{{ $branding['company_address'] }}</div>
            @endif
            @if(!empty($branding['tax_number']))
                <div class="muted">{{ $branding['tax_label'] }}: {{ $branding['tax_number'] }}</div>
            @endif
            <div class="badge">{{ $documentBadge }}</div>
        </div>

        <div class="divider"></div>

        <div class="meta-row"><span>{{ $documentTitle }}:</span><strong>{{ $sale->invoice_number }}</strong></div>
        <div class="meta-row"><span>Receipt:</span><strong>{{ $sale->receipt_number ?? 'Not generated yet' }}</strong></div>
        <div class="meta-row"><span>Date:</span><strong>{{ optional($sale->sale_date)->format('d M Y') }}</strong></div>
        <div class="meta-row"><span>Printed At:</span><strong><span class="js-print-timestamp" data-fallback="{{ $printedAtFallback }}">{{ $printedAtFallback }}</span></strong></div>
        <div class="meta-row"><span>Customer:</span><strong>{{ $sale->customer?->name ?? 'Walk-in Customer' }}</strong></div>
        <div class="meta-row"><span>Payment Type:</span><strong>{{ $sale->payment_type ? ucfirst($sale->payment_type) : 'Pending' }}</strong></div>
        <div class="meta-row"><span>Payment Method:</span><strong>{{ $paymentMethodLabel }}</strong></div>
        <div class="meta-row"><span>Dispensed By:</span><strong>{{ $sale->servedByUser?->name ?? 'N/A' }}</strong></div>
        @if($sale->status === 'approved')
            <div class="meta-row"><span>Approved By:</span><strong>{{ $sale->approvedByUser?->name ?? 'N/A' }}</strong></div>
        @endif

        <div class="divider"></div>

        @foreach($displayItems as $item)
            <div class="item">
                <div class="item-name">{{ $item['product_name'] }}</div>
                <div class="item-meta">Batch: <span class="batch-number">{{ $item['batch_number'] }}</span> | Exp: {{ $item['expiry_date'] }}</div>
                <div class="item-line">
                    <span>{{ number_format($item['quantity'], 2) }} x {{ number_format($item['unit_price'], 2) }}</span>
                    <strong>{{ number_format($item['line_total'], 2) }}</strong>
                </div>
            </div>
        @endforeach

        <div class="divider"></div>

        <div class="totals">
            @if((float) $sale->tax_amount > 0)
                <div class="total-row"><span>Tax</span><strong>{{ number_format((float) $sale->tax_amount, 2) }}</strong></div>
            @endif
            <div class="total-row grand"><span>Total</span><strong>{{ number_format((float) $sale->total_amount, 2) }}</strong></div>
            <div class="total-row"><span>Amount Received</span><strong>{{ number_format((float) $sale->amount_received, 2) }}</strong></div>
            <div class="total-row"><span>Amount Applied</span><strong>{{ number_format((float) $sale->amount_paid, 2) }}</strong></div>
            <div class="total-row"><span>{{ $settlementLabel }}</span><strong>{{ number_format($settlementAmount, 2) }}</strong></div>
        </div>

        @if(!empty($sale->notes))
            <div class="divider"></div>
            <div class="muted"><strong>Notes:</strong> {{ $sale->notes }}</div>
        @endif

        <div class="footer">
            @if(!empty($documentFooter))
                <div>{{ $documentFooter }}</div>
            @endif
            <div>Printed at <span class="js-print-timestamp" data-fallback="{{ $printedAtFallback }}">{{ $printedAtFallback }}</span></div>
        </div>
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
