@extends('prints.layout')

@php
    $pageTitle = $documentTitle;
    $pageBadge = $documentBadge;
    $showDefaultFooter = false;

    $isReceipt = $sale->status === 'approved';
    $isProforma = $sale->status === 'proforma';
    $documentTypeLabel = $isReceipt ? 'Receipt' : ($isProforma ? 'Proforma Invoice' : 'Invoice');
    $primaryNumberLabel = $isReceipt ? 'Receipt #' : ($isProforma ? 'Proforma #' : 'Invoice #');
    $primaryNumberValue = $isReceipt
        ? ($sale->receipt_number ?: 'Not generated yet')
        : $sale->invoice_number;
    $saleDateValue = optional($sale->sale_date)->format('D M d Y') ?? 'N/A';
    $issueDateValue = optional($sale->sale_date)->format('d M Y') ?? 'N/A';
    $printedAtFallback = now()->format('D M d Y, h:i:s A');
    $changeAmount = max(0, (float) $sale->amount_received - (float) $sale->total_amount);
    $receiptSettlementLabel = $changeAmount > 0 ? 'Change' : 'Amount Due';
    $receiptSettlementAmount = $changeAmount > 0 ? $changeAmount : (float) $sale->balance_due;
    $footerText = $documentFooter ?: ($branding['report_footer'] ?? null);

    $contactPhone = $sale->customer?->phone
        ?: $sale->customer?->alt_phone
        ?: $sale->customer?->contact_person;
    $contactAddress = $sale->customer?->address;

    $headerAddressLines = collect([
        ($branding['show_branch_contacts'] ?? false)
            ? ($branding['branch_address'] ?: $branding['company_address'])
            : ($branding['company_address'] ?? null),
    ])->filter()->values();

    $headerPhoneLine = collect([
        ($branding['show_branch_contacts'] ?? false) ? ($branding['branch_phone'] ?? null) : null,
        $branding['company_phone'] ?? null,
    ])->filter()->unique()->implode(' / ');

    $headerEmailLine = collect([
        ($branding['show_branch_contacts'] ?? false) ? ($branding['branch_email'] ?? null) : null,
        $branding['company_email'] ?? null,
    ])->filter()->unique()->implode(' / ');

    $documentDetails = [
        ['label' => $primaryNumberLabel, 'value' => $primaryNumberValue],
    ];

    if ($isReceipt) {
        $documentDetails[] = ['label' => 'Invoice #', 'value' => $sale->invoice_number];
    }

    $documentDetails[] = ['label' => 'Date', 'value' => $saleDateValue];
    $documentDetails[] = [
        'label' => 'Printed At',
        'value' => $printedAtFallback,
        'is_print_timestamp' => true,
    ];
    $documentDetails[] = ['label' => 'Issue Date', 'value' => $issueDateValue];
    $documentDetails[] = ['label' => 'Status', 'value' => $documentBadge . ' ' . ($sale->sale_type ? ucfirst($sale->sale_type) : 'Sale')];

    if ($isReceipt && $sale->approved_at) {
        $documentDetails[] = ['label' => 'Approved At', 'value' => $sale->approved_at->format('d M Y H:i')];
    }

    $invoicePaymentSections = [
        [
            'heading' => 'ABSA Bank',
            'lines' => ['6008659379 - VIP PHARMACY'],
        ],
        [
            'heading' => 'Mobile Money',
            'lines' => [
                'MTN: Merchant ID: 731614',
                'Airtel: Merchant ID: 6682216',
            ],
        ],
    ];

    $receiptPaymentSections = [
        [
            'heading' => 'Payment Method',
            'lines' => [$paymentMethodLabel],
        ],
        [
            'heading' => 'Payment Type',
            'lines' => [$sale->payment_type ? ucfirst($sale->payment_type) : 'Pending'],
        ],
        [
            'heading' => 'Amounts',
            'lines' => [
                'Amount Received: ' . number_format((float) $sale->amount_received, 2),
                'Amount Applied: ' . number_format((float) $sale->amount_paid, 2),
                $receiptSettlementLabel . ': ' . number_format($receiptSettlementAmount, 2),
            ],
        ],
    ];

    $paymentSections = $isReceipt ? $receiptPaymentSections : $invoicePaymentSections;
@endphp

@push('styles')
    <style>
        body {
            background: #eff4f7;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .doc-header {
            display: none;
        }

        .page {
            padding: 10px 10px 14px;
        }

        .invoice-sheet {
            position: relative;
            overflow: hidden;
            border: 1px solid #d9e1e6;
            background: #ffffff;
            padding: 14px 14px 12px;
        }

        .invoice-sheet::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, #1ea6af 0%, #2db8c2 100%);
        }

        .invoice-branding {
            padding-top: 2px;
            text-align: center;
        }

        .invoice-logo-wrap {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 86px;
            height: 48px;
            margin: 0 auto 4px;
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
            letter-spacing: 0.08em;
        }

        .invoice-company-name {
            margin: 0;
            color: #20314a;
            font-size: 18px;
            font-weight: 800;
            line-height: 1.05;
            text-transform: uppercase;
        }

        .invoice-company-line,
        .invoice-company-line a {
            margin-top: 2px;
            color: #334155;
            font-size: 11.5px;
            line-height: 1.3;
            text-decoration: none;
        }

        .invoice-document-title {
            margin-top: 6px;
            color: #1f3250;
            font-size: 15px;
            font-weight: 800;
            letter-spacing: 0.02em;
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
            letter-spacing: 0.04em;
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
            border: 1px solid #d6dde4;
        }

        .invoice-table {
            width: 100%;
            border-collapse: collapse;
        }

        .invoice-table th,
        .invoice-table td {
            border: 1px solid #d6dde4;
            padding: 4px 5px;
            text-align: left;
            vertical-align: top;
            font-size: 10.5px;
            line-height: 1.22;
            color: #24354d;
        }

        .invoice-table th {
            background: #f7f9fb;
            color: #17263a;
            font-size: 10px;
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

        .invoice-bottom-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.15fr) minmax(0, 1fr);
            gap: 10px;
            margin-top: 10px;
        }

        .invoice-card {
            border: 1px solid #d6dde4;
            padding: 8px 10px;
            background: #fbfdfe;
        }

        .invoice-card p {
            margin: 0;
            color: #334155;
            font-size: 11px;
            line-height: 1.32;
        }

        .invoice-card p + p {
            margin-top: 6px;
        }

        .invoice-payment-section + .invoice-payment-section {
            margin-top: 10px;
        }

        .invoice-payment-heading {
            color: #20314a;
            font-size: 11.5px;
            font-weight: 800;
        }

        .invoice-notes {
            margin-top: 6px;
            padding-top: 6px;
            border-top: 1px solid #e3e8ee;
        }

        .invoice-team {
            display: grid;
            gap: 3px;
            margin-top: 6px;
        }

        .invoice-team-line {
            color: #334155;
            font-size: 11.5px;
            line-height: 1.32;
        }

        .invoice-team-line strong {
            color: #20314a;
        }

        .invoice-footnote {
            margin-top: 6px;
            padding-top: 6px;
            border-top: 1px dashed #cad5df;
            color: #526173;
            font-size: 10.5px;
            line-height: 1.3;
        }

        @page {
            size: A4;
            margin: 8mm;
        }

        .invoice-table tr {
            page-break-inside: avoid;
            break-inside: avoid;
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

        @media (max-width: 900px) {
            .invoice-meta-grid,
            .invoice-bottom-grid {
                grid-template-columns: 1fr;
                gap: 18px;
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

            @foreach($headerAddressLines as $line)
                <div class="invoice-company-line">{{ $line }}</div>
            @endforeach

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
            <div class="invoice-badge">{{ $documentBadge }} {{ $sale->sale_type ? ucfirst($sale->sale_type) : 'Sale' }}</div>
        </div>

        <div class="invoice-meta-grid">
            <div>
                <h3 class="invoice-panel-title">{{ $isReceipt ? 'Customer Details' : 'Invoice To' }}</h3>
                <div class="invoice-party-line"><strong>{{ $sale->customer?->name ?? 'Walk-in Customer' }}</strong></div>

                @if(!empty($contactPhone))
                    <div class="invoice-party-line"><strong>Contact:</strong> {{ $contactPhone }}</div>
                @endif

                @if(!empty($contactAddress))
                    <div class="invoice-party-line"><strong>Address:</strong> {{ $contactAddress }}</div>
                @endif

                @if(!empty($sale->customer?->email))
                    <div class="invoice-party-line"><strong>Email:</strong> {{ $sale->customer->email }}</div>
                @endif
            </div>

            <div class="invoice-doc-panel">
                <h3 class="invoice-panel-title">{{ $documentTypeLabel }} Details</h3>

                @foreach($documentDetails as $row)
                    <div class="invoice-doc-line">
                        <strong>{{ $row['label'] }}:</strong>
                        @if($row['is_print_timestamp'] ?? false)
                            <span class="js-print-timestamp" data-fallback="{{ $row['value'] }}">{{ $row['value'] }}</span>
                        @else
                            {{ $row['value'] }}
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        <div class="invoice-table-wrap">
            <table class="invoice-table">
                <thead>
                    <tr>
                        <th class="no">No.</th>
                        <th>Brand Name</th>
                        <th>Batch</th>
                        <th>Expiry</th>
                        <th class="qty">Qty</th>
                        <th class="amount">Unit Price</th>
                        <th class="amount">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($displayItems as $index => $item)
                        <tr>
                            <td class="no">{{ $index + 1 }}</td>
                            <td>{{ $item['product_name'] }}</td>
                            <td>{{ $item['batch_number'] }}</td>
                            <td>{{ $item['expiry_date'] }}</td>
                            <td class="qty">{{ number_format($item['quantity'], 2) }}</td>
                            <td class="amount">{{ number_format($item['unit_price'], 2) }}</td>
                            <td class="amount">{{ number_format($item['line_total'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="invoice-totals">
            <div class="invoice-total-row">
                <span>Sub Total</span>
                <span>{{ number_format((float) $sale->subtotal, 2) }}</span>
            </div>
            <div class="invoice-total-row">
                <span>Tax Amount</span>
                <span>{{ number_format((float) $sale->tax_amount, 2) }}</span>
            </div>
            <div class="invoice-total-row">
                <span>{{ $isReceipt ? 'Amount Received' : 'Amount Applied' }}</span>
                <span>{{ number_format((float) ($isReceipt ? $sale->amount_received : $sale->amount_paid), 2) }}</span>
            </div>
            <div class="invoice-total-row">
                <span>{{ $isReceipt ? $receiptSettlementLabel : 'Balance Due' }}</span>
                <span>{{ number_format($isReceipt ? $receiptSettlementAmount : (float) $sale->balance_due, 2) }}</span>
            </div>
            <div class="invoice-total-row grand">
                <span>Total Amount</span>
                <span>{{ number_format((float) $sale->total_amount, 2) }}</span>
            </div>
        </div>

        <div class="invoice-bottom-grid">
            <div class="invoice-card">
                <h3 class="invoice-panel-title">Payment Details</h3>

                @foreach($paymentSections as $section)
                    <div class="invoice-payment-section">
                        <div class="invoice-payment-heading">{{ $section['heading'] }}:</div>
                        @foreach($section['lines'] as $line)
                            <p>{{ $line }}</p>
                        @endforeach
                    </div>
                @endforeach
            </div>

            <div class="invoice-card">
                <h3 class="invoice-panel-title">Additional Details</h3>

                <div class="invoice-team">
                    <div class="invoice-team-line"><strong>Dispensed By:</strong> {{ $sale->servedByUser?->name ?? 'N/A' }}</div>
                    <div class="invoice-team-line">
                        <strong>{{ $isReceipt ? 'Approved By' : 'Approval Status' }}:</strong>
                        {{ $isReceipt ? ($sale->approvedByUser?->name ?? 'N/A') : 'Pending Approval' }}
                    </div>
                </div>

                @if(!empty($sale->notes))
                    <div class="invoice-notes">
                        <p><strong>Notes:</strong> {{ $sale->notes }}</p>
                    </div>
                @endif

                <div class="invoice-footnote">
                    {{ $footerText ?: 'This document is computer generated and valid without a signature.' }}
                </div>
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
