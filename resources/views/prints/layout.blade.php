<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $pageTitle ?? 'Print Document' }} - KIM Rx</title>
    <style>
        @page {
            size: A4;
            margin: 14mm;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            color: #172033;
            background: #eef2f7;
        }
        .page {
            max-width: 210mm;
            margin: 0 auto;
            background: #fff;
            min-height: 100vh;
            padding: 18px 22px 26px;
        }
        .toolbar {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            padding: 16px 22px 0;
            max-width: 210mm;
            margin: 0 auto;
        }
        .btn {
            border: none;
            border-radius: 999px;
            padding: 10px 16px;
            cursor: pointer;
            font-weight: 700;
        }
        .btn-print { background: #155eef; color: #fff; }
        .btn-close { background: #e2e8f0; color: #172033; }
        .doc-header {
            display: flex;
            justify-content: space-between;
            gap: 18px;
            padding-bottom: 18px;
            border-bottom: 2px solid #d9e2f1;
        }
        .brand {
            display: flex;
            gap: 14px;
            align-items: flex-start;
        }
        .brand-logo {
            width: 74px;
            height: 74px;
            object-fit: contain;
            border-radius: 14px;
            border: 1px solid #dbe5f1;
            padding: 6px;
            background: #fff;
        }
        .brand h1 {
            margin: 0 0 6px;
            font-size: 30px;
        }
        .brand p {
            margin: 2px 0;
            color: #475467;
            font-size: 13px;
        }
        .doc-meta {
            text-align: right;
        }
        .doc-meta h2 {
            margin: 0 0 6px;
            font-size: 24px;
        }
        .doc-meta p {
            margin: 3px 0;
            color: #475467;
            font-size: 13px;
        }
        .chip {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 999px;
            background: #eef4ff;
            color: #155eef;
            font-size: 12px;
            font-weight: 700;
            margin-top: 8px;
        }
        .section {
            margin-top: 22px;
        }
        .section h3 {
            margin: 0 0 10px;
            font-size: 18px;
        }
        .section p {
            margin: 0;
            color: #475467;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            margin-top: 16px;
        }
        .summary-card {
            border: 1px solid #e4e7ec;
            border-radius: 14px;
            padding: 12px;
            background: #f8fafc;
        }
        .summary-card .label {
            color: #667085;
            font-size: 12px;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.04em;
        }
        .summary-card .value {
            margin-top: 10px;
            font-size: 22px;
            font-weight: 800;
            word-break: break-word;
        }
        .table-wrap {
            width: 100%;
            overflow: hidden;
            border: 1px solid #e4e7ec;
            border-radius: 14px;
            margin-top: 14px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 10px 12px;
            border-bottom: 1px solid #eaecf0;
            text-align: left;
            vertical-align: top;
            font-size: 13px;
        }
        th {
            background: #f8fafc;
            color: #475467;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.04em;
        }
        .amount {
            text-align: right;
            font-weight: 700;
        }
        .footer-note {
            margin-top: 24px;
            padding-top: 14px;
            border-top: 1px dashed #d0d5dd;
            color: #475467;
            font-size: 12px;
        }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .page {
                max-width: none;
                margin: 0;
                min-height: auto;
                padding: 0;
            }
        }
        @media (max-width: 900px) {
            .doc-header,
            .brand {
                flex-direction: column;
            }
            .doc-meta {
                text-align: left;
            }
            .summary-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
    </style>
    @stack('styles')
</head>
<body>
    @unless($isPdfDownload ?? false)
        <div class="toolbar">
            <button class="btn btn-print" onclick="prepareAndPrint()">Print</button>
            <button class="btn btn-close" onclick="window.close()">Close</button>
        </div>
    @endunless

    <div class="page">
        <header class="doc-header">
            <div class="brand">
                @if(($branding['show_logo'] ?? false) && !empty($branding['logo_url']))
                    <img src="{{ $branding['logo_url'] }}" alt="Logo" class="brand-logo" data-print-blocking="true" fetchpriority="high" loading="eager">
                @endif

                <div>
                    <h1>{{ $branding['company_name'] ?? 'KIM Rx' }}</h1>
                    @if(!empty($branding['receipt_header']))
                        <p>{{ $branding['receipt_header'] }}</p>
                    @endif
                    <p>{{ $branding['company_address'] ?: 'Address not set' }}</p>
                    @if(!empty($branding['company_phone']) || !empty($branding['company_email']))
                        <p>{{ collect([$branding['company_phone'] ?? null, $branding['company_email'] ?? null])->filter()->implode(' | ') }}</p>
                    @endif
                    @if(($branding['show_branch_contacts'] ?? false) && !empty($branding['branch_name']))
                        <p><strong>Branch:</strong> {{ $branding['branch_name'] }}@if(!empty($branding['branch_code'])) ({{ $branding['branch_code'] }}) @endif</p>
                        @if(!empty($branding['branch_phone']) || !empty($branding['branch_email']))
                            <p>{{ collect([$branding['branch_phone'] ?? null, $branding['branch_email'] ?? null])->filter()->implode(' | ') }}</p>
                        @endif
                    @endif
                    @if(!empty($branding['tax_number']))
                        <p><strong>{{ $branding['tax_label'] ?? 'TIN' }}:</strong> {{ $branding['tax_number'] }}</p>
                    @endif
                </div>
            </div>

            <div class="doc-meta">
                <h2>{{ $pageTitle ?? 'Print Document' }}</h2>
                @if(!empty($rangeLabel))
                    <p>{{ $rangeLabel }}</p>
                @endif
                @if(!empty($metaLines ?? []))
                    @foreach($metaLines as $line)
                        <p>{{ $line }}</p>
                    @endforeach
                @endif
                @if(!empty($pageBadge))
                    <div class="chip">{{ $pageBadge }}</div>
                @endif
            </div>
        </header>

        @yield('content')

        @if(($showDefaultFooter ?? true) && !empty($branding['report_footer']))
            <div class="footer-note">{{ $branding['report_footer'] }}</div>
        @endif
    </div>

    @unless($isPdfDownload ?? false)
        <script>
            (function () {
                function onReady(callback) {
                    if (document.readyState === 'loading') {
                        document.addEventListener('DOMContentLoaded', callback, { once: true });
                        return;
                    }

                    callback();
                }

                function runBeforePrintHook() {
                    if (typeof window.beforeDocumentPrint === 'function') {
                        window.beforeDocumentPrint();
                    }
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

                window.prepareAndPrint = function () {
                    runBeforePrintHook();
                    waitForPrintAssets(function () {
                        window.print();
                    });
                };

                @if($autoPrint ?? false)
                    onReady(function () {
                        window.prepareAndPrint();
                    });
                @endif
            })();
        </script>
    @endunless
</body>
</html>
