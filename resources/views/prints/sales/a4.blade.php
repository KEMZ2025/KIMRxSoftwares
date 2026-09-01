@extends('prints.layout')

@php
    $pageTitle = $documentTitle;
    $pageBadge = null;
    $showDefaultFooter = false;

    $isReceipt = $sale->status === 'approved';
    $isProforma = $sale->status === 'proforma';
    $primaryNumberLabel = $isReceipt ? 'Receipt#' : ($isProforma ? 'Proforma#' : 'Invoice#');
    $primaryNumberValue = $isReceipt
        ? ($sale->receipt_number ?: 'Not generated yet')
        : $sale->invoice_number;
    $documentDateValue = ($isReceipt && $sale->approved_at)
        ? $sale->approved_at->format('D M d Y, h:i A')
        : (optional($sale->sale_date)->format('D M d Y') ?? 'N/A');
    $printedAtFallback = now()->format('D M d Y, h:i:s A');
    $changeAmount = max(0, (float) $sale->amount_received - (float) $sale->total_amount);
    $balanceDue = (float) $sale->balance_due;
    $receiptSettlementLabel = $changeAmount > 0 ? 'Change' : ($balanceDue > 0.009 ? 'Amount Due' : 'Change');
    $receiptSettlementAmount = $changeAmount > 0 ? $changeAmount : ($balanceDue > 0.009 ? $balanceDue : 0);
    $footerText = $documentFooter ?: ($branding['report_footer'] ?? null);
    $customerName = $sale->customer?->name ?? 'Cash Customer';

    $contactPhone = $sale->customer?->phone
        ?: $sale->customer?->alt_phone
        ?: $sale->customer?->contact_person;
    $contactAddress = $sale->customer?->address;

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

    $cleanPaymentMethodLabel = trim((string) ($paymentMethodLabel ?? ''));
    $cleanPaymentMethodLower = strtolower($cleanPaymentMethodLabel);
    $paymentTypeLower = strtolower((string) ($sale->payment_type ?? ''));
    $shouldShowPaymentMethod = $cleanPaymentMethodLabel !== ''
        && ! in_array($cleanPaymentMethodLower, ['pending approval', 'not captured', 'n/a'], true);

    if ($isReceipt) {
        $compactPaymentSummary = $sale->payment_type ? ucfirst($sale->payment_type) : 'Not captured';

        if ($paymentTypeLower === 'credit' && (float) $sale->balance_due <= 0 && $shouldShowPaymentMethod) {
            $compactPaymentSummary = $cleanPaymentMethodLabel;
        } elseif ($shouldShowPaymentMethod && ! in_array($paymentTypeLower, ['credit', 'insurance'], true)) {
            $compactPaymentSummary .= ' - ' . $cleanPaymentMethodLabel;
        }
    } else {
        $invoicePaymentLines = collect(preg_split('/\R/', (string) ($branding['invoice_payment_details'] ?? '')))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->values()
            ->all();
        $compactPaymentSummary = implode(' | ', $invoicePaymentLines ?: ['Payment details will be provided by pharmacy.']);
    }

    $formatQty = function ($quantity) {
        $value = (float) $quantity;

        return abs($value - round($value)) < 0.0001
            ? number_format($value, 0)
            : rtrim(rtrim(number_format($value, 2), '0'), '.');
    };
    $formatMoney = fn ($amount) => number_format((float) $amount, 0);
@endphp

@push('styles')
    <style>
        body {
            background: #fff;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .doc-header {
            display: none !important;
        }

        .page {
            padding: 8px 10px 12px;
        }

        .invoice-sheet {
            position: relative;
            overflow: hidden;
            border: none;
            background: #ffffff;
            padding: 8px 0 0;
        }

        .invoice-sheet::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, #1ea6af 0%, #2db8c2 100%);
        }

        .invoice-branding {
            padding-top: 4px;
            text-align: center;
        }

        .invoice-logo-wrap {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 84px;
            height: 54px;
            margin: 0 auto 2px;
        }

        .invoice-logo-wrap img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .invoice-logo-fallback {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 46px;
            height: 46px;
            border-radius: 999px;
            background: linear-gradient(135deg, #12a579 0%, #a63be5 100%);
            color: #fff;
            font-size: 16px;
            font-weight: 800;
            letter-spacing: 0;
        }

        .invoice-company-name {
            margin: 0;
            color: #20314a;
            font-size: 16px;
            font-weight: 800;
            line-height: 1.05;
            text-transform: uppercase;
        }

        .invoice-company-line,
        .invoice-company-line a {
            margin-top: 1px;
            color: #334155;
            font-size: 11.5px;
            line-height: 1.25;
            text-decoration: none;
        }

        .invoice-document-title {
            margin-top: 4px;
            color: #1f3250;
            font-size: 14px;
            font-weight: 800;
            letter-spacing: 0;
        }

        .invoice-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-top: 5px;
            padding: 3px 8px;
            border-radius: 999px;
            background: #eefaf8;
            color: #157a62;
            font-size: 9px;
            font-weight: 800;
            letter-spacing: 0;
            text-transform: uppercase;
        }

        .invoice-badge::before {
            content: '';
            width: 5px;
            height: 5px;
            border-radius: 999px;
            background: #1aa680;
        }

        .invoice-meta-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(240px, 0.85fr);
            gap: 16px;
            margin-top: 12px;
            padding: 0 0 6px;
        }

        .invoice-panel-title {
            margin: 0 0 5px;
            color: #233650;
            font-size: 12px;
            font-weight: 800;
        }

        .invoice-party-line,
        .invoice-doc-line {
            color: #334155;
            font-size: 11.5px;
            line-height: 1.32;
        }

        .invoice-party-line strong,
        .invoice-doc-line strong {
            color: #1f3250;
        }

        .invoice-doc-panel {
            justify-self: end;
            width: 100%;
            max-width: 320px;
        }

        .invoice-table-wrap {
            margin-top: 4px;
            border: 1px solid #cbd5e1;
            overflow-x: auto;
        }

        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .invoice-table th,
        .invoice-table td {
            border: 1px solid #cbd5e1;
            padding: 3px 4px;
            text-align: left;
            vertical-align: top;
            font-size: 11px;
            line-height: 1.18;
            color: #24354d;
        }

        .invoice-table th {
            background: #f7f9fb;
            color: #17263a;
            font-size: 11px;
            font-weight: 800;
        }

        .invoice-table td.amount,
        .invoice-table th.amount {
            text-align: right;
        }

        .invoice-table td.qty,
        .invoice-table th.qty {
            text-align: center;
            width: 48px;
        }

        .invoice-table td.no,
        .invoice-table th.no {
            width: 32px;
            text-align: center;
        }

        .invoice-totals {
            width: min(320px, 100%);
            margin-left: auto;
            margin-top: 8px;
        }

        .invoice-total-row {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            padding: 3px 0;
            color: #22334c;
            font-size: 11.5px;
            font-weight: 700;
        }

        .invoice-total-row + .invoice-total-row {
            border-top: 1px solid #e3e8ee;
        }

        .invoice-total-row.grand {
            margin-top: 3px;
            padding-top: 5px;
            border-top: 2px solid #1ea6af;
            font-size: 13px;
            font-weight: 800;
        }


        .invoice-compact-footer {
            display: grid;
            gap: 4px;
            margin-top: 8px;
            padding-top: 6px;
            border-top: 1px solid #d6dde4;
            color: #334155;
            font-size: 10.8px;
            line-height: 1.32;
        }

        .invoice-compact-row {
            display: flex;
            flex-wrap: wrap;
            gap: 4px 18px;
        }

        .invoice-compact-row span {
            white-space: nowrap;
        }

        .invoice-compact-row strong,
        .invoice-compact-note strong {
            color: #20314a;
        }

        .invoice-compact-note {
            color: #334155;
            font-size: 10.6px;
        }

        .invoice-footnote {
            color: #526173;
            font-size: 10px;
            line-height: 1.28;
        }

        @page {
            size: A4;
            margin: 7mm;
        }

        .invoice-table tr {
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .invoice-meta {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
            gap: 3px 28px;
            margin: 9px 0 7px;
            font-size: 12px;
            line-height: 1.25;
        }

        .invoice-meta-line {
            display: flex;
            gap: 4px;
            min-width: 0;
        }

        .invoice-meta-line strong {
            color: #2f3b4c;
            white-space: nowrap;
        }

        .invoice-meta-line span {
            min-width: 0;
            word-break: break-word;
        }

        .invoice-table .product {
            width: 39%;
            word-break: break-word;
        }

        .invoice-table .batch {
            width: 11%;
            word-break: break-word;
        }

        .invoice-table .expiry {
            width: 12%;
        }

        .invoice-table td.amount,
        .invoice-table th.amount {
            width: 12%;
            white-space: nowrap;
        }

        .invoice-totals {
            width: 300px;
            max-width: 100%;
            margin: 7px 0 0 auto;
            border: 1px solid #cbd5e1;
            border-bottom: none;
        }

        .invoice-total-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 110px;
            gap: 0;
            padding: 0;
            border-bottom: 1px solid #cbd5e1;
            font-size: 12px;
            line-height: 1.2;
        }

        .invoice-total-row + .invoice-total-row {
            border-top: none;
        }

        .invoice-total-row span {
            padding: 4px 6px;
        }

        .invoice-total-row span:first-child {
            text-align: right;
            font-weight: 700;
        }

        .invoice-total-row span:last-child {
            text-align: right;
            font-weight: 800;
            border-left: 1px solid #cbd5e1;
            white-space: nowrap;
        }

        .invoice-total-row.grand {
            margin-top: 0;
            padding-top: 0;
            border-top: none;
            font-size: 13px;
            font-weight: 900;
            background: #f8fafc;
        }

        @media print {
            body {
                background: #fff;
            }

            .page {
                padding: 0;
            }

            .invoice-sheet {
                border: none;
                padding: 6px 0 0;
            }
        }

        @media screen and (max-width: 900px) {
            .invoice-meta-grid {
                grid-template-columns: 1fr;
                gap: 18px;
            }

            .invoice-compact-row {
                gap: 4px 12px;
            }

            .invoice-doc-panel,
            .invoice-totals {
                max-width: none;
                width: 100%;
                justify-self: start;
            }

            .invoice-table th,
            .invoice-table td {
                padding: 8px 7px;
                font-size: 12px;
            }
        }
    </style>
@endpush

@section('content')
    <div class="invoice-sheet">
        <div class="invoice-branding">
            <div class="invoice-logo-wrap">
                @if(($branding['show_logo'] ?? false) && !empty($branding['logo_url']))
                    <img src="{{ $branding['logo_url'] }}" alt="Logo" data-print-blocking="true" fetchpriority="high" loading="eager">
                @else
                    <div class="invoice-logo-fallback">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($branding['company_name'] ?? 'KR', 0, 2)) }}</div>
                @endif
            </div>

            <h1 class="invoice-company-name">{{ $branding['company_name'] ?? 'KIM Rx' }}</h1>

            @if(!empty($branding['receipt_header']))
                <div class="invoice-company-line">{{ $branding['receipt_header'] }}</div>
            @endif

            @if(!empty($headerAddress))
                <div class="invoice-company-line">{{ $headerAddress }}</div>
            @endif

            @if($headerPhoneLine !== '')
                <div class="invoice-company-line">Phone: {{ $headerPhoneLine }}</div>
            @endif

            @if($headerEmailLine !== '')
                <div class="invoice-company-line">Email: {{ $headerEmailLine }}</div>
            @endif

            @if(!empty($branding['tax_number']))
                <div class="invoice-company-line">{{ $branding['tax_label'] ?? 'TIN' }}: {{ $branding['tax_number'] }}</div>
            @endif

            <div class="invoice-document-title">{{ $documentTitle }}</div>
        </div>

        <div class="invoice-meta">
            <div class="invoice-meta-line">
                <strong>{{ $isReceipt ? 'Received From:' : 'Invoice To:' }}</strong>
                <span>{{ $customerName }}</span>
            </div>
            <div class="invoice-meta-line">
                <strong>Date:</strong>
                <span>{{ $documentDateValue }}</span>
            </div>
            <div class="invoice-meta-line">
                <strong>{{ $primaryNumberLabel }}</strong>
                <span>{{ $primaryNumberValue }}</span>
            </div>
            @if($isReceipt)
                <div class="invoice-meta-line">
                    <strong>Invoice#:</strong>
                    <span>{{ $sale->invoice_number }}</span>
                </div>
            @endif
            @if(!empty($contactAddress))
                <div class="invoice-meta-line">
                    <strong>Address:</strong>
                    <span>{{ $contactAddress }}</span>
                </div>
            @endif
            @if(!empty($contactPhone))
                <div class="invoice-meta-line">
                    <strong>Contact:</strong>
                    <span>{{ $contactPhone }}</span>
                </div>
            @endif
            <div class="invoice-meta-line">
                <strong>Payment:</strong>
                <span>{{ $compactPaymentSummary }}</span>
            </div>
        </div>

        <div class="invoice-table-wrap">
            <table class="invoice-table">
                <thead>
                    <tr>
                        <th class="no">No.</th>
                        <th class="product">Brand Name</th>
                        <th class="batch">Batch</th>
                        <th class="expiry">Expiry</th>
                        <th class="qty">Qty</th>
                        <th class="amount">Unit Price</th>
                        <th class="amount">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($displayItems as $index => $item)
                        <tr>
                            <td class="no">{{ $index + 1 }}</td>
                            <td class="product">{{ $item['product_name'] }}</td>
                            <td class="batch">{{ $item['batch_number'] }}</td>
                            <td class="expiry">{{ $item['expiry_date'] }}</td>
                            <td class="qty">{{ $formatQty($item['quantity']) }}</td>
                            <td class="amount">{{ $formatMoney($item['unit_price']) }}</td>
                            <td class="amount">{{ $formatMoney($item['line_total']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="invoice-totals">
            @if((float) $sale->tax_amount > 0)
                <div class="invoice-total-row">
                    <span>Sub Total</span>
                    <span>{{ $formatMoney($sale->subtotal) }}</span>
                </div>
                <div class="invoice-total-row">
                    <span>Tax Amount</span>
                    <span>{{ $formatMoney($sale->tax_amount) }}</span>
                </div>
            @endif
            <div class="invoice-total-row grand">
                <span>Total Amount</span>
                <span>{{ $formatMoney($sale->total_amount) }}</span>
            </div>
            <div class="invoice-total-row">
                <span>{{ $isReceipt ? 'Amount Received' : 'Amount Applied' }}</span>
                <span>{{ $formatMoney($isReceipt ? $sale->amount_received : $sale->amount_paid) }}</span>
            </div>
            @if($isReceipt)
                <div class="invoice-total-row">
                    <span>{{ $receiptSettlementLabel }}</span>
                    <span>{{ $formatMoney($receiptSettlementAmount) }}</span>
                </div>
            @else
                <div class="invoice-total-row">
                    <span>Balance Due</span>
                    <span>{{ $formatMoney($sale->balance_due) }}</span>
                </div>
            @endif
        </div>

        <div class="invoice-compact-footer">
            <div class="invoice-compact-row">
                <span><strong>Payment:</strong> {{ $compactPaymentSummary }}</span>
                <span><strong>Dispensed By:</strong> {{ $sale->servedByUser?->name ?? 'N/A' }}</span>
                <span>
                    <strong>{{ $isReceipt ? 'Approved By' : 'Approval' }}:</strong>
                    {{ $isReceipt ? ($sale->approvedByUser?->name ?? 'N/A') : 'Pending Approval' }}
                </span>
            </div>

            @if(!empty($sale->notes))
                <div class="invoice-compact-note"><strong>Notes:</strong> {{ $sale->notes }}</div>
            @endif

            <div class="invoice-footnote">
                {{ $footerText ?: 'This document is computer generated and valid without a signature.' }}
            </div>
        </div>
    </div>

    @unless($isPdfDownload ?? false)
        <script>
            (function () {
                function formatPrintTimestamp(date) {
                    return new Intl.DateTimeFormat(undefined, {
                        weekday: 'short',
                        month: 'short',
                        day: '2-digit',
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

                window.beforeDocumentPrint = syncPrintTimestamps;
                syncPrintTimestamps();
                window.addEventListener('beforeprint', syncPrintTimestamps);
            })();
        </script>
    @endunless
@endsection


