@php
    $usesTypedProductSelector = strcasecmp(trim((string) ($clientName ?? '')), 'VIP PHARMACY') === 0;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KIM Rx</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; display: flex; background: #f5f7fb; }

        .content { flex: 1; width: 100%; max-width: 100%; padding: 20px; }
        .topbar {
            background: white;
            padding: 15px;
            border-radius: 14px;
            margin-bottom: 20px;
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.06);
        }
        .panel {
            background: white;
            padding: 20px;
            border-radius: 16px;
            box-shadow: 0 14px 34px rgba(15, 23, 42, 0.06);
            max-width: 100%;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(3, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 16px;
        }

        .form-group { display: flex; flex-direction: column; }
        .form-group label { margin-bottom: 8px; font-weight: bold; }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }

        .full { grid-column: 1 / -1; }

        .alert-danger {
            background: #fdecea;
            color: #b42318;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .alert-warning {
            background: #fff4db;
            color: #9a6700;
            padding: 12px;
            border-radius: 8px;
            margin-top: 12px;
        }

        .items-table-wrap {
            overflow-x: auto;
            margin-top: 10px;
            max-width: 100%;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            background: #fff;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1320px;
        }

        table th, table td {
            border: 1px solid #ddd;
            padding: 7px;
            text-align: left;
            vertical-align: middle;
        }

        table th {
            background: #f8f8f8;
            font-size: 12px;
            white-space: nowrap;
        }

        .mini-input, .mini-select {
            width: 100%;
            padding: 7px 8px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 13px;
        }

        .info-box {
            background: #f1f3f6;
            border-radius: 6px;
            padding: 7px 6px;
            text-align: center;
            min-width: 72px;
            font-size: 12px;
            line-height: 1.3;
        }

        .sale-items-table .col-line { width: 46px; }
        .sale-items-table .col-product { width: 170px; }
        .sale-items-table .col-batch { width: 135px; }
        .sale-items-table .col-expiry { width: 96px; }
        .sale-items-table .col-stock { width: 76px; }
        .sale-items-table .col-price { width: 96px; }
        .sale-items-table .col-qty { width: 82px; }
        .sale-items-table .col-discount { width: 88px; }
        .sale-items-table .col-total { width: 102px; }
        .sale-items-table .col-action { width: 88px; }

        .sale-items-table .btn-delete {
            padding: 7px 8px;
            font-size: 12px;
            white-space: nowrap;
        }

        .row-below-cost td {
            background: #fff7f5;
        }

        .input-error {
            border: 2px solid #b42318 !important;
        }

        .btn {
            padding: 8px 12px;
            border-radius: 6px;
            color: white;
            text-decoration: none;
            border: none;
            cursor: pointer;
            display: inline-block;
        }

        .btn-save { background: green; }
        .btn-add { background: #1f7a4f; }
        .btn-delete { background: red; }

        .btn-row {
            margin-top: 12px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .total-box {
            margin-top: 16px;
            font-size: 16px;
            font-weight: bold;
            line-height: 1.8;
        }

        .customer-warning,
        .pending-note {
            font-size: 12px;
            color: #a56a00;
            margin-top: 4px;
        }

        .customer-label-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 8px;
        }

        .customer-label-row label {
            margin: 0;
        }

        .quick-customer-trigger {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            min-height: 30px;
            padding: 5px 9px;
            border: 1px solid #15805f;
            border-radius: 6px;
            background: #ffffff;
            color: #116149;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
        }

        .quick-customer-trigger:hover {
            background: #ecfdf5;
        }

        .quick-customer-status {
            min-height: 17px;
            margin-top: 4px;
            color: #167153;
            font-size: 12px;
            font-weight: 700;
        }

        .quick-customer-modal {
            position: fixed;
            z-index: 12000;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 18px;
            background: rgba(15, 23, 42, 0.62);
        }

        .quick-customer-modal[hidden] {
            display: none;
        }

        .quick-customer-card {
            width: min(560px, 100%);
            max-height: calc(100vh - 36px);
            overflow-y: auto;
            border: 1px solid #d8e1ea;
            border-radius: 8px;
            background: #ffffff;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.28);
        }

        .quick-customer-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 16px 18px;
            border-bottom: 1px solid #e2e8f0;
        }

        .quick-customer-head h2 {
            margin: 0;
            color: #172033;
            font-size: 19px;
        }

        .quick-customer-close {
            width: 34px;
            height: 34px;
            padding: 0;
            border: 1px solid #d5dde7;
            border-radius: 50%;
            background: #f8fafc;
            color: #334155;
            font-size: 22px;
            line-height: 1;
            cursor: pointer;
        }

        .quick-customer-fields {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
            padding: 18px;
        }

        .quick-customer-field {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .quick-customer-field label {
            color: #253247;
            font-size: 13px;
            font-weight: 700;
        }

        .quick-customer-field input {
            width: 100%;
            padding: 10px;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            background: #ffffff;
            color: #172033;
        }

        .quick-customer-field.full {
            grid-column: 1 / -1;
        }

        .quick-customer-errors {
            grid-column: 1 / -1;
            padding: 10px 12px;
            border: 1px solid #fecaca;
            border-radius: 7px;
            background: #fef2f2;
            color: #b42318;
            font-size: 13px;
        }

        .quick-customer-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding: 14px 18px 18px;
            border-top: 1px solid #e2e8f0;
        }

        .quick-customer-cancel {
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            color: #334155;
        }

        .quick-customer-save {
            background: #16805f;
            color: #ffffff;
        }

        html[data-theme="dark"] .quick-customer-card {
            border-color: #334155;
            background: #111827;
        }

        html[data-theme="dark"] .quick-customer-head,
        html[data-theme="dark"] .quick-customer-actions {
            border-color: #334155;
        }

        html[data-theme="dark"] .quick-customer-head h2,
        html[data-theme="dark"] .quick-customer-field label {
            color: #e5edf6;
        }

        html[data-theme="dark"] .quick-customer-field input {
            border-color: #475569;
            background: #182234;
            color: #f8fafc;
        }

        html[data-theme="dark"] .quick-customer-trigger,
        html[data-theme="dark"] .quick-customer-close,
        html[data-theme="dark"] .quick-customer-cancel {
            border-color: #475569;
            background: #182234;
            color: #dbeafe;
        }

        html[data-theme="dark"] .quick-customer-status {
            color: #6ee7b7;
        }

        .credit-panel {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 12px;
            margin-top: 8px;
        }

        .credit-panel strong {
            display: inline-block;
            min-width: 150px;
        }
        .insurance-panel {
            margin-top: 8px;
            padding: 16px;
            border-radius: 14px;
            border: 1px solid #d7e8f3;
            background: #f8fbff;
        }
        .insurance-panel-head h4 { margin: 0 0 4px; }
        .insurance-panel-head p { margin: 0 0 14px; font-size: 12px; color: #526071; }
        .insurance-summary-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(160px, 1fr));
            gap: 12px;
            margin-top: 14px;
        }
        .insurance-summary-box {
            background: #fff;
            border: 1px solid #d7e8f3;
            border-radius: 12px;
            padding: 12px;
        }
        .insurance-summary-label {
            display: block;
            font-size: 11px;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: #526071;
            margin-bottom: 6px;
        }

        .search-wrap {
            margin: 18px 0 10px 0;
            padding: 16px;
            background: #fafafa;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
        }

        .search-wrap h3 {
            margin-top: 0;
        }

        .search-results-wrap {
            overflow-x: auto;
            margin-top: 10px;
            max-width: 100%;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            background: #fff;
        }

        .search-results-wrap table {
            min-width: 1080px;
        }

        .dispensing-guide-panel {
            margin-top: 14px;
            padding: 14px;
            border-radius: 14px;
            border: 1px solid #d8e3f3;
            background: #f8fbff;
        }

        .dispensing-guide-panel h4 {
            margin: 0 0 4px;
        }

        .dispensing-guide-copy {
            margin: 0;
            font-size: 12px;
            color: #526071;
        }

        .dispensing-guide-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 12px;
        }

        .dispensing-guide-pill {
            background: #ffffff;
            border: 1px solid #d8e3f3;
            border-radius: 999px;
            padding: 8px 12px;
            font-size: 12px;
            line-height: 1.3;
        }

        .dispensing-guide-empty,
        .guide-preview-empty {
            font-size: 12px;
            color: #667085;
        }

        .dispensing-guide-note {
            margin-top: 10px;
            font-size: 12px;
            color: #526071;
        }

        .guide-preview {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .guide-preview-pill {
            display: inline-block;
            background: #eef4ff;
            color: #1d4ed8;
            border-radius: 999px;
            padding: 4px 8px;
            font-size: 11px;
            line-height: 1.2;
        }

        .guide-preview-more {
            font-size: 11px;
            color: #667085;
        }

        @media (max-width: 900px) {
            body { flex-direction: column; }
            .form-row { grid-template-columns: 1fr; }
            .insurance-summary-grid { grid-template-columns: 1fr; }
            .quick-customer-fields { grid-template-columns: 1fr; }
            .quick-customer-field.full { grid-column: auto; }
        }
    
    /* KIM typed sale entry and FIFO batch helper controls */
    .kim-hidden-system-select {
        position: absolute !important;
        left: -9999px !important;
        width: 1px !important;
        height: 1px !important;
        opacity: 0 !important;
        pointer-events: none !important;
    }

    .kim-type-input,
    .kim-fifo-batch-display {
        width: 100%;
        min-width: 150px;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        padding: 7px 9px;
        font-size: 13px;
        background: #ffffff;
        color: #0f172a;
        box-sizing: border-box;
    }

    .kim-type-input:focus {
        border-color: #0ea5e9;
        box-shadow: 0 0 0 2px rgba(14, 165, 233, 0.16);
        outline: none;
    }

    .kim-type-wrap {
        position: relative;
        width: 100%;
    }

    .kim-type-results {
        display: none;
        position: fixed;
        z-index: 10000;
        top: 0;
        left: 0;
        width: 280px;
        max-height: 220px;
        overflow-y: auto;
        border: 1px solid #7dd3fc;
        border-radius: 8px;
        background: #ffffff;
        box-shadow: 0 14px 34px rgba(15, 23, 42, 0.18);
    }

    .kim-type-option,
    .kim-type-empty {
        padding: 8px 10px;
        font-size: 12.5px;
        line-height: 1.35;
    }

    .kim-type-option {
        cursor: pointer;
        color: #0f172a;
        border-bottom: 1px solid #e2e8f0;
    }

    .kim-type-option:last-child {
        border-bottom: 0;
    }

    .kim-type-option:hover,
    .kim-type-option.is-active {
        background: #e0f2fe;
        color: #075985;
        font-weight: 700;
    }

    .kim-type-empty {
        color: #64748b;
    }

    body.dark-mode .kim-type-results {
        background: #111827;
        border-color: #38bdf8;
        box-shadow: 0 14px 34px rgba(0, 0, 0, 0.45);
    }

    body.dark-mode .kim-type-option {
        color: #f8fafc;
        border-color: #334155;
    }

    body.dark-mode .kim-type-option:hover,
    body.dark-mode .kim-type-option.is-active {
        background: #0c4a6e;
        color: #ffffff;
    }

    body.dark-mode .kim-type-empty {
        color: #cbd5e1;
    }

    .kim-fifo-batch-display {
        background: #ecfdf5;
        border-color: #86efac;
        font-weight: 800;
        color: #064e3b;
    }

    .kim-fifo-batch-empty {
        background: #fff7ed;
        border-color: #fdba74;
        color: #9a3412;
    }</style>
</head>
<body>
        @include('layouts.sidebar')

    <div class="content" id="mainContent">
        <div class="topbar">
            <h3>{{ $pageTitle ?? 'New Sale' }}</h3>
            <p>{{ $pageDescription ?? 'Create retail or wholesale sale' }}</p>
        </div>

        <div class="panel">
            @php
                $quickSearchColumnCount = ($showDispensingPriceGuide ?? false) ? 10 : 9;
            @endphp
            @if ($errors->any())
                <div class="alert-danger">
                    <ul style="margin:0; padding-left:18px;">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form id="sale-form" method="POST" action="{{ $formAction ?? route('sales.store') }}" autocomplete="off">
                <input type="hidden" name="_sale_form" value="new">
                <input type="hidden" name="discount_mode" value="per_unit">
                @csrf

                <div class="form-row">
                    <div class="form-group">
                        <label for="invoice_number">Invoice Preview</label>
                        <input type="text" name="invoice_number" id="invoice_number" value="{{ old('invoice_number', $invoiceNumber ?? $retailInvoiceNumber) }}" readonly required>
                    </div>

                    <div class="form-group">
                        <label>Receipt Number</label>
                        <input type="text" value="{{ ($isProforma ?? false) ? 'Not generated for proforma stage' : 'Will be generated at approval stage' }}" readonly>
                        <div class="pending-note">
                            {{ ($isProforma ?? false) ? 'Receipt number is created only after this document is converted and approved.' : 'Receipt number is created after approval.' }}
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="sale_date">Sale Date</label>
                        <input type="date" name="sale_date" id="sale_date" value="{{ old('sale_date', now()->toDateString()) }}" required>
                    </div>

                    <div class="form-group">
                        <label for="sale_type">Sale Type</label>
                        <select name="sale_type" id="sale_type" onchange="handleSaleTypeChange()" required>
                            @foreach(($saleTypeConfig['sale_type_options'] ?? ['retail' => 'Retail', 'wholesale' => 'Wholesale']) as $saleTypeValue => $saleTypeLabel)
                                <option value="{{ $saleTypeValue }}" {{ old('sale_type', $defaultSaleType ?? 'retail') == $saleTypeValue ? 'selected' : '' }}>{{ $saleTypeLabel }}</option>
                            @endforeach
                        </select>
                        @if(!empty($saleTypeConfig['sale_type_hint']))
                            <div class="pending-note">{{ $saleTypeConfig['sale_type_hint'] }}</div>
                        @endif
                    </div>

                    <div class="form-group">
                        <label for="payment_type">Payment Type</label>
                        <select name="payment_type" id="payment_type" onchange="handleSaleTypeChange()" required>
                            <option value="cash" {{ old('payment_type', 'cash') == 'cash' ? 'selected' : '' }}>Cash</option>
                            <option value="credit" {{ old('payment_type') == 'credit' ? 'selected' : '' }}>Credit</option>
                            @if($insuranceEnabled ?? false)
                                <option value="insurance" {{ old('payment_type') == 'insurance' ? 'selected' : '' }}>Insurance</option>
                            @endif
                        </select>
                        <div class="pending-note">
                            {{ ($isProforma ?? false) ? 'Payment details here are for quotation planning only. No payment or stock movement happens yet.' : 'Payment will be finalized at approval stage.' }}
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="customer-label-row">
                            <label for="customer_id">Customer</label>
                            @if(auth()->user()?->hasPermission('customers.create'))
                                <button type="button" class="quick-customer-trigger" onclick="openQuickCustomerModal()">
                                    <span aria-hidden="true">+</span>
                                    <span>Add Customer</span>
                                </button>
                            @endif
                        </div>
                        <select name="customer_id" id="customer_id" onchange="showCustomerCreditInfo()" autocomplete="off">
                            <option value="">Select Customer</option>
                            @foreach($customers as $customer)
                                <option
                                    value="{{ $customer->id }}"
                                    data-credit-limit="{{ (float) $customer->credit_limit }}"
                                    data-outstanding-balance="{{ (float) $customer->outstanding_balance }}"
                                    data-remaining-credit="{{ (float) $customer->remaining_credit }}"
                                    {{ old('customer_id') == $customer->id ? 'selected' : '' }}
                                >
                                    {{ $customer->name }}
                                </option>
                            @endforeach
                        </select>
                        <div class="customer-warning" id="customer-warning"></div>
                        <div class="quick-customer-status" id="quick-customer-status" aria-live="polite"></div>
                    </div>

                    <div class="form-group">
                        <label>Amount Paid</label>
                        <input type="text" value="{{ ($isProforma ?? false) ? 'Not collected at proforma stage' : 'Will be entered at approval stage' }}" readonly>
                        <div class="pending-note">
                            {{ ($isProforma ?? false) ? 'Proforma invoices never collect money or reserve stock until converted to a real sale.' : 'Pending sales do not collect final payment here.' }}
                        </div>
                    </div>

                    <div class="form-group full" id="credit-info-wrapper" style="display:none;">
                        <label>Customer Credit Information</label>
                        <div class="credit-panel">
                            <div><strong>Credit Limit:</strong> <span id="credit-limit-text">0.00</span></div>
                            <div><strong>Outstanding Balance:</strong> <span id="outstanding-balance-text">0.00</span></div>
                            <div><strong>Remaining Credit:</strong> <span id="remaining-credit-text">0.00</span></div>
                        </div>
                    </div>

                    @if($insuranceEnabled ?? false)
                        @include('sales._insurance_billing_fields', ['sale' => new \App\Models\Sale(), 'insurers' => $insurers, 'insuranceTotal' => 0])
                    @endif

                    <div class="form-group full">
                        <label for="notes">Notes</label>
                        <textarea name="notes" id="notes">{{ old('notes') }}</textarea>
                    </div>
                </div>

                <div class="search-wrap">
                    <h3>Quick Product Batch Search</h3>
                    <input
                        type="text"
                        id="quick-search-input"
                        placeholder="Type product name..."
                        style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px; margin-bottom:12px;"
                    >

                    <div class="search-results-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Batch</th>
                                    <th>Supplier</th>
                                    <th>Purchase</th>
                                    <th>Retail</th>
                                    <th>Wholesale</th>
                                    <th>Free Stock</th>
                                    <th>Expiry</th>
                                    @if($showDispensingPriceGuide ?? false)
                                        <th>Guide</th>
                                    @endif
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="quick-search-results">
                                <tr>
                                    <td colspan="{{ $quickSearchColumnCount }}">Type to search products...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    @if($showDispensingPriceGuide ?? false)
                        <div class="dispensing-guide-panel" id="dispensing-price-guide-panel">
                            <h4>Dispensing Price Guide</h4>
                            <p class="dispensing-guide-copy" id="dispensing-price-guide-product">Quick quote guide for the selected product appears here.</p>
                            <div class="dispensing-guide-list" id="dispensing-price-guide-list">
                                <div class="dispensing-guide-empty">Select a product row to view admin-defined quick quote amounts.</div>
                            </div>
                            <div class="dispensing-guide-note" id="dispensing-price-guide-note">Display only. This guide never auto-fills unit price, quantity, totals, stock, or accounting entries.</div>
                        </div>
                    @endif
                </div>

                @include('stock_requests._launch')

                <div class="items-table-wrap">
                    <table class="sale-items-table">
                        <colgroup>
                            <col class="col-line">
                            <col class="col-product">
                            <col class="col-batch">
                            <col class="col-expiry">
                            <col class="col-stock">
                            <col class="col-stock">
                            <col class="col-stock">
                            <col class="col-price">
                            <col class="col-price">
                            <col class="col-qty">
                            <col class="col-discount">
                            <col class="col-total">
                            <col class="col-action">
                        </colgroup>
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Product *</th>
                                <th>Batch *</th>
                                <th>Expiry</th>
                                <th>Available</th>
                                <th>Reserved</th>
                                <th>Free Stock</th>
                                <th>Purchase Price</th>
                                <th>Unit Price *</th>
                                <th>Quantity *</th>
                                <th>Discount / Unit</th>
                                <th>Total</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="sale-items-body">
                            @if($recoveredSaleRows->isNotEmpty())
                                @include('sales._recovered_rows')
                            @else
                            <tr class="sale-row">
                                <td class="line-no">1</td>
                                <td>
                                    <select name="product_id[]" class="mini-select product-select" onchange="loadBatches(this)" required>
                                        <option value="">Select Product</option>
                                        @foreach($products as $product)
                                            <option
                                                value="{{ $product->id }}"
                                                data-dispensing-guide="{{ e(json_encode($product->normalizedDispensingPriceGuide())) }}"
                                            >{{ $product->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <select name="product_batch_id[]" class="mini-select batch-select" onchange="applyBatchSelection(this)" required>
                                        <option value="">Select Batch</option>
                                    </select>
                                </td>
                                <td><div class="info-box expiry-box">N/A</div></td>
                                <td><div class="info-box available-box">0.00</div></td>
                                <td><div class="info-box reserved-box">0.00</div></td>
                                <td><div class="info-box free-stock-box">0.00</div></td>
                                <td><div class="info-box purchase-price-box">0.00</div></td>
                                <td><input type="number" step="0.01" name="unit_price[]" class="mini-input unit-price" value="0" oninput="calculateTotals()" required></td>
                                <td><input type="number" step="0.01" name="quantity[]" class="mini-input quantity" value="0" oninput="calculateTotals()" required></td>
                                <td><input type="number" step="0.0001" name="discount_amount[]" class="mini-input discount-amount" value="0" oninput="calculateTotals()" {{ !$canManageDiscounts ? 'readonly' : '' }}></td>
                                <td><input type="number" step="0.01" class="mini-input line-total" value="0.00" readonly></td>
                                <td><button type="button" class="btn btn-delete" onclick="removeRow(this)">Remove</button></td>
                            </tr>
                            @endif
                        </tbody>
                    </table>
                </div>

                <div class="btn-row">
                    @if($allowAddOneLine ?? true)
                        <button type="button" class="btn btn-add" onclick="addLine()">Add 1 Line</button>
                    @endif

                    @if($allowAddFiveLines ?? true)
                        <button type="button" class="btn btn-add" onclick="addFiveLines()">Add 5 Lines</button>
                    @endif
                </div>

                <div class="total-box">
                    Grand Total: <span id="grand-total-text">0.00</span><br>
                    Balance Due After Approval: <span id="balance-due-text">0.00</span><br>
                    Save Mode: <span>{{ $saveModeLabel ?? 'Pending Sale' }}</span>
                </div>

                <div id="pricing-warning-box" class="alert-danger" style="display:none;"></div>

                <div style="margin-top:18px;">
                    <button type="submit" class="btn btn-save">{{ $saveButtonLabel ?? 'Save Pending Sale' }}</button>
                </div>
            </form>
        </div>
    </div>

    @if(auth()->user()?->hasPermission('customers.create'))
        <div
            class="quick-customer-modal"
            id="quick-customer-modal"
            hidden
            aria-hidden="true"
            onclick="handleQuickCustomerBackdrop(event)"
        >
            <section
                class="quick-customer-card"
                role="dialog"
                aria-modal="true"
                aria-labelledby="quick-customer-title"
            >
                <div class="quick-customer-head">
                    <h2 id="quick-customer-title">Add Customer</h2>
                    <button type="button" class="quick-customer-close" onclick="closeQuickCustomerModal()" aria-label="Close" title="Close">&times;</button>
                </div>

                <div class="quick-customer-fields">
                    <div class="quick-customer-field full">
                        <label for="quick-customer-name">Customer Name *</label>
                        <input type="text" id="quick-customer-name" maxlength="255" autocomplete="off">
                    </div>
                    <div class="quick-customer-field">
                        <label for="quick-customer-phone">Phone</label>
                        <input type="text" id="quick-customer-phone" maxlength="50" autocomplete="off">
                    </div>
                    <div class="quick-customer-field">
                        <label for="quick-customer-contact">Contact Person</label>
                        <input type="text" id="quick-customer-contact" maxlength="255" autocomplete="off">
                    </div>
                    <div class="quick-customer-field full">
                        <label for="quick-customer-credit-limit">Credit Limit</label>
                        <input type="number" id="quick-customer-credit-limit" min="0" step="0.01" value="0">
                    </div>
                    <div class="quick-customer-errors" id="quick-customer-errors" hidden role="alert"></div>
                </div>

                <div class="quick-customer-actions">
                    <button type="button" class="btn quick-customer-cancel" onclick="closeQuickCustomerModal()">Cancel</button>
                    <button type="button" class="btn quick-customer-save" id="quick-customer-save" onclick="saveQuickCustomer()">Save Customer</button>
                </div>
            </section>
        </div>
    @endif

    <template id="sale-row-template">
        <tr class="sale-row">
            <td class="line-no">1</td>
            <td>
                <select name="product_id[]" class="mini-select product-select" onchange="loadBatches(this)" required>
                    <option value="">Select Product</option>
                    @foreach($products as $product)
                        <option
                            value="{{ $product->id }}"
                            data-dispensing-guide="{{ e(json_encode($product->normalizedDispensingPriceGuide())) }}"
                        >{{ $product->name }}</option>
                    @endforeach
                </select>
            </td>
            <td>
                <select name="product_batch_id[]" class="mini-select batch-select" onchange="applyBatchSelection(this)" required>
                    <option value="">Select Batch</option>
                </select>
            </td>
            <td><div class="info-box expiry-box">N/A</div></td>
            <td><div class="info-box available-box">0.00</div></td>
            <td><div class="info-box reserved-box">0.00</div></td>
            <td><div class="info-box free-stock-box">0.00</div></td>
            <td><div class="info-box purchase-price-box">0.00</div></td>
            <td><input type="number" step="0.01" name="unit_price[]" class="mini-input unit-price" value="0" oninput="calculateTotals()" required></td>
            <td><input type="number" step="0.01" name="quantity[]" class="mini-input quantity" value="0" oninput="calculateTotals()" required></td>
            <td><input type="number" step="0.0001" name="discount_amount[]" class="mini-input discount-amount" value="0" oninput="calculateTotals()" {{ !$canManageDiscounts ? 'readonly' : '' }}></td>
            <td><input type="number" step="0.01" class="mini-input line-total" value="0.00" readonly></td>
            <td><button type="button" class="btn btn-delete" onclick="removeRow(this)">Remove</button></td>
        </tr>
    </template>

    <script>
        const isProformaDocument = @json($isProforma ?? false);
        const retailInvoiceNumber = @json($retailInvoiceNumber);
        const wholesaleInvoiceNumber = @json($wholesaleInvoiceNumber);
        const proformaInvoiceNumber = @json($proformaInvoiceNumber ?? null);
          const lockedSaleType = @json($saleTypeConfig['locked_sale_type'] ?? null);
          const canOverrideSalePrice = @json($canOverrideSalePrice ?? false);
          const showDispensingPriceGuide = @json($showDispensingPriceGuide ?? false);
          const quickSearchColspan = @json($quickSearchColumnCount);
          const insuranceModuleEnabled = @json((bool) ($insuranceEnabled ?? false));
          const quickCustomerStoreUrl = @json(route('customers.store'));
          const quickCustomerCsrfToken = @json(csrf_token());

    const wholesaleSaleSwitchMessage = 'You are changing this sale from Retail to Wholesale. Wholesale uses wholesale prices and may require a customer. Do you want to continue?';
    const initialSaleTypeSelect = document.getElementById('sale_type');
    const preserveInitialCustomerAfterValidation = @json(old('customer_id') !== null);
    let confirmedSaleType = initialSaleTypeSelect ? initialSaleTypeSelect.value : 'retail';
    let initialSaleFormSetup = true;
    let quickCustomerPreviousOverflow = '';

    function openQuickCustomerModal() {
        const modal = document.getElementById('quick-customer-modal');
        const errors = document.getElementById('quick-customer-errors');
        if (!modal) return;

        if (errors) {
            errors.hidden = true;
            errors.textContent = '';
        }

        quickCustomerPreviousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        window.setTimeout(() => document.getElementById('quick-customer-name')?.focus(), 0);
    }

    function closeQuickCustomerModal() {
        const modal = document.getElementById('quick-customer-modal');
        if (!modal) return;

        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = quickCustomerPreviousOverflow;
        document.querySelector('.quick-customer-trigger')?.focus();
    }

    function handleQuickCustomerBackdrop(event) {
        if (event.target?.id === 'quick-customer-modal') {
            closeQuickCustomerModal();
        }
    }

    function quickCustomerErrorMessages(payload) {
        if (payload?.errors) {
            return Object.values(payload.errors).flat().filter(Boolean);
        }

        return [payload?.message || 'Customer could not be added. Please try again.'];
    }

    async function saveQuickCustomer() {
        const nameInput = document.getElementById('quick-customer-name');
        const saveButton = document.getElementById('quick-customer-save');
        const errorBox = document.getElementById('quick-customer-errors');
        const customerSelect = document.getElementById('customer_id');
        const name = nameInput?.value.trim() || '';

        if (!saveButton || saveButton.disabled || !errorBox || !customerSelect) {
            return;
        }

        if (!name) {
            errorBox.hidden = false;
            errorBox.textContent = 'Customer name is required.';
            nameInput?.focus();
            return;
        }

        const payload = new FormData();
        payload.append('_token', quickCustomerCsrfToken);
        payload.append('name', name);
        payload.append('phone', document.getElementById('quick-customer-phone')?.value.trim() || '');
        payload.append('contact_person', document.getElementById('quick-customer-contact')?.value.trim() || '');
        payload.append('credit_limit', document.getElementById('quick-customer-credit-limit')?.value || '0');

        saveButton.disabled = true;
        saveButton.textContent = 'Saving...';
        errorBox.hidden = true;
        errorBox.textContent = '';

        try {
            const response = await fetch(quickCustomerStoreUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: payload,
            });
            const data = await response.json().catch(() => ({}));

            if (!response.ok || !data.customer) {
                const requestError = new Error(data.message || 'Customer could not be added.');
                requestError.validationMessages = quickCustomerErrorMessages(data);
                throw requestError;
            }

            const customer = data.customer;
            const option = new Option(customer.name, String(customer.id), true, true);
            option.dataset.creditLimit = String(customer.credit_limit || 0);
            option.dataset.outstandingBalance = String(customer.outstanding_balance || 0);
            option.dataset.remainingCredit = String(customer.remaining_credit || 0);
            customerSelect.add(option);
            customerSelect.value = String(customer.id);
            customerSelect.dispatchEvent(new Event('change', { bubbles: true }));

            document.getElementById('quick-customer-status').textContent = `${customer.name} added and selected.`;
            document.getElementById('quick-customer-name').value = '';
            document.getElementById('quick-customer-phone').value = '';
            document.getElementById('quick-customer-contact').value = '';
            document.getElementById('quick-customer-credit-limit').value = '0';
            closeQuickCustomerModal();
        } catch (error) {
            errorBox.hidden = false;
            errorBox.textContent = (error.validationMessages || ['Customer could not be added. Please try again.']).join(' ');
        } finally {
            saveButton.disabled = false;
            saveButton.textContent = 'Save Customer';
        }
    }

    function confirmSaleTypeSwitch() {
        const saleTypeSelect = document.getElementById('sale_type');
        if (!saleTypeSelect) {
            return true;
        }

        const previousSaleType = saleTypeSelect.dataset.confirmedSaleType || confirmedSaleType || 'retail';
        const nextSaleType = saleTypeSelect.value;

        if (previousSaleType === 'retail' && nextSaleType === 'wholesale') {
            const confirmed = window.confirm(wholesaleSaleSwitchMessage);
            if (!confirmed) {
                saleTypeSelect.value = previousSaleType;
                return false;
            }
        }

        saleTypeSelect.dataset.confirmedSaleType = nextSaleType;
        confirmedSaleType = nextSaleType;
        return true;
    }
        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function parseDispensingGuide(value) {
            if (!value) {
                return [];
            }

            try {
                const parsed = JSON.parse(value);
                return Array.isArray(parsed) ? parsed : [];
            } catch (error) {
                return [];
            }
        }

        function formatGuideQuantity(quantity) {
            const numericQuantity = Number(quantity || 0);

            if (!Number.isFinite(numericQuantity) || numericQuantity <= 0) {
                return '0';
            }

            return Number.isInteger(numericQuantity)
                ? String(numericQuantity)
                : numericQuantity.toFixed(2).replace(/0+$/, '').replace(/\.$/, '');
        }

        function renderDispensingGuidePreview(guide) {
            if (!showDispensingPriceGuide) {
                return '';
            }

            if (!Array.isArray(guide) || guide.length === 0) {
                return '<span class="guide-preview-empty">Not set</span>';
            }

            const previewLines = guide.slice(0, 2).map(line => `
                <span class="guide-preview-pill">${escapeHtml(formatGuideQuantity(line.quantity))} ${escapeHtml(line.label)} = ${Number(line.amount || 0).toFixed(2)}</span>
            `).join('');
            const more = guide.length > 2
                ? `<span class="guide-preview-more">+${guide.length - 2} more</span>`
                : '';

            return `<div class="guide-preview">${previewLines}${more}</div>`;
        }

        function updateDispensingPriceGuidePanel(productName, guide) {
            if (!showDispensingPriceGuide) {
                return;
            }

            const panel = document.getElementById('dispensing-price-guide-panel');
            const productText = document.getElementById('dispensing-price-guide-product');
            const list = document.getElementById('dispensing-price-guide-list');
            const note = document.getElementById('dispensing-price-guide-note');

            if (!panel || !productText || !list || !note) {
                return;
            }

            if (!productName) {
                panel.style.display = 'block';
                productText.textContent = 'Quick quote guide for the selected product appears here.';
                list.innerHTML = '<div class="dispensing-guide-empty">Select a product row to view admin-defined quick quote amounts.</div>';
                note.textContent = 'Display only. This guide never auto-fills unit price, quantity, totals, stock, or accounting entries.';
                return;
            }

            panel.style.display = 'block';
            productText.textContent = `Quick quote guide for ${productName}`;

            if (Array.isArray(guide) && guide.length > 0) {
                list.innerHTML = guide.map(line => `
                    <div class="dispensing-guide-pill">
                        ${escapeHtml(formatGuideQuantity(line.quantity))} ${escapeHtml(line.label)} = ${Number(line.amount || 0).toFixed(2)}
                    </div>
                `).join('');
                note.textContent = 'Display only. This guide does not auto-fill unit price, quantity, totals, stock, or accounting entries.';
                return;
            }

            list.innerHTML = '<div class="dispensing-guide-empty">No guide lines are configured for this product yet.</div>';
            note.textContent = 'Admin can add quick quote lines from the product setup screen.';
        }

        function updateGuideFromProductSelect(selectElement) {
            if (!showDispensingPriceGuide) {
                return;
            }

            const selectedOption = selectElement?.options?.[selectElement.selectedIndex];

            if (!selectedOption || !selectedOption.value) {
                updateDispensingPriceGuidePanel('', []);
                return;
            }

            updateDispensingPriceGuidePanel(
                selectedOption.textContent.trim(),
                parseDispensingGuide(selectedOption.dataset.dispensingGuide)
            );
        }

        function showGuideForFirstSelectedProduct() {
            if (!showDispensingPriceGuide) {
                return;
            }

            const firstSelected = Array.from(document.querySelectorAll('.product-select'))
                .find(select => select.value);

            if (firstSelected) {
                updateGuideFromProductSelect(firstSelected);
                return;
            }

            updateDispensingPriceGuidePanel('', []);
        }

        function currentSellingPriceForOption(option) {
            const saleType = document.getElementById('sale_type').value;

            return saleType === 'wholesale'
                ? Number(option?.dataset.wholesalePrice || 0)
                : Number(option?.dataset.retailPrice || 0);
        }

        function currentRowPurchasePrice(row, selectedOption = null) {
            const batchSelect = row.querySelector('.batch-select');
            const option = selectedOption ?? batchSelect?.options[batchSelect.selectedIndex];

            if (!option || !option.value) {
                return 0;
            }

            return Number(option.dataset.purchasePrice || 0);
        }

        function currentRowPriceFloor(row, selectedOption = null) {
            const batchSelect = row.querySelector('.batch-select');
            const option = selectedOption ?? batchSelect?.options[batchSelect.selectedIndex];

            if (!option || !option.value) {
                return 0;
            }

            return currentSellingPriceForOption(option);
        }
        function currentRowPriceFloorLabel() {
            return document.getElementById('sale_type').value === 'wholesale'
                ? 'wholesale selling price'
                : 'retail selling price';
        }
        function runScreenTask(taskName, callback) {
            try {
                callback();
            } catch (error) {
                console.error(`[sales-create] ${taskName} failed`, error);
            }
        }

        function handleSaleTypeChange() {
            const saleTypeSelect = document.getElementById('sale_type');
            const previousSaleType = confirmedSaleType;
            if (lockedSaleType && saleTypeSelect.value !== lockedSaleType) {
                saleTypeSelect.value = lockedSaleType;
            }

            if (!confirmSaleTypeSwitch()) {
                return;
            }

            const saleType = saleTypeSelect.value;
            const paymentType = document.getElementById('payment_type').value;
            const customerSelect = document.getElementById('customer_id');
            const warning = document.getElementById('customer-warning');
            const invoiceInput = document.getElementById('invoice_number');

            if (isProformaDocument) {
                invoiceInput.value = proformaInvoiceNumber || invoiceInput.value;
            } else {
                invoiceInput.value = saleType === 'wholesale' ? wholesaleInvoiceNumber : retailInvoiceNumber;
            }

              if (saleType === 'retail'
                  && paymentType === 'cash'
                  && customerSelect.value
                  && (!initialSaleFormSetup || !preserveInitialCustomerAfterValidation)) {
                  customerSelect.value = '';
                  customerSelect.dispatchEvent(new Event('change', { bubbles: true }));
              }

              if (saleType === 'wholesale' || paymentType === 'credit' || paymentType === 'insurance') {
                  customerSelect.setAttribute('required', 'required');
                  warning.textContent = 'Customer is required for wholesale, credit, or insurance sales.';
              } else {
                  customerSelect.removeAttribute('required');
                  warning.textContent = '';
              }

              if (saleType !== previousSaleType) {
                  document.querySelectorAll('.sale-row').forEach(row => {
                      const batchSelect = row.querySelector('.batch-select');
                      if (batchSelect && batchSelect.value) {
                          applyBatchSelection(batchSelect);
                      }
                  });
              }

              runScreenTask('updateInsuranceFields', () => updateInsuranceFields());
              runScreenTask('showCustomerCreditInfo', () => showCustomerCreditInfo());
              runScreenTask('calculateTotals', () => calculateTotals());
          }

          function updateInsuranceFields() {
              const panel = document.getElementById('insurance-fields-panel');
              if (!insuranceModuleEnabled || !panel) {
                  return;
              }

              const isInsurance = document.getElementById('payment_type')?.value === 'insurance';
              const insurerInput = document.getElementById('insurer_id');
              const coveredInput = document.getElementById('insurance_covered_amount');

              panel.style.display = isInsurance ? 'block' : 'none';

              if (insurerInput) {
                  if (isInsurance) {
                      insurerInput.setAttribute('required', 'required');
                  } else {
                      insurerInput.removeAttribute('required');
                  }
              }

              if (coveredInput) {
                  if (isInsurance) {
                      coveredInput.setAttribute('required', 'required');
                  } else {
                      coveredInput.removeAttribute('required');
                  }
              }

              runScreenTask('updateInsuranceFinancialPreview', () => updateInsuranceFinancialPreview());
          }

          function updateInsuranceFinancialPreview() {
              if (!insuranceModuleEnabled) {
                  return;
              }

              const isInsurance = document.getElementById('payment_type')?.value === 'insurance';
              const coveredInput = document.getElementById('insurance_covered_amount');
              const hiddenCopayInput = document.getElementById('insurance_patient_copay_amount');

              if (!coveredInput || !hiddenCopayInput) {
                  return;
              }

              const total = parseFloat((document.getElementById('grand-total-text')?.textContent || '0').replace(/,/g, '')) || 0;
              let covered = Number(coveredInput.value || 0);

              if (!Number.isFinite(covered) || covered < 0) {
                  covered = 0;
              }

              if (covered > total) {
                  covered = total;
                  coveredInput.value = total.toFixed(2);
              }

              const patientCopay = Math.max(0, total - covered);
              const insurerBalance = isInsurance ? covered : total;

              document.getElementById('insurance-total-preview').textContent = total.toFixed(2);
              document.getElementById('insurance-patient-copay-preview').textContent = patientCopay.toFixed(2);
              document.getElementById('insurance-balance-preview').textContent = (isInsurance ? covered : 0).toFixed(2);
              hiddenCopayInput.value = patientCopay.toFixed(2);

              if (isInsurance) {
                  document.getElementById('balance-due-text').textContent = insurerBalance.toFixed(2);
              }
          }

        function showCustomerCreditInfo() {
            const saleType = document.getElementById('sale_type').value;
            const paymentType = document.getElementById('payment_type').value;
            const select = document.getElementById('customer_id');
            const option = select.options[select.selectedIndex];
            const wrapper = document.getElementById('credit-info-wrapper');

            const shouldShow = saleType === 'wholesale' && paymentType === 'credit' && option && option.value;

            if (!shouldShow) {
                wrapper.style.display = 'none';
                return;
            }

            wrapper.style.display = 'block';
            document.getElementById('credit-limit-text').textContent = Number(option.dataset.creditLimit || 0).toFixed(2);
            document.getElementById('outstanding-balance-text').textContent = Number(option.dataset.outstandingBalance || 0).toFixed(2);
            document.getElementById('remaining-credit-text').textContent = Number(option.dataset.remainingCredit || 0).toFixed(2);
        }

        function renumberRows() {
            document.querySelectorAll('.sale-row').forEach((row, index) => {
                row.querySelector('.line-no').textContent = index + 1;
            });
        }
    function autoSelectFifoBatch(batchSelect) {
        if (!batchSelect || batchSelect.value) {
            return;
        }

        const fifoOption = Array.from(batchSelect.options).find((option) => option.value && !option.disabled);
        if (!fifoOption) {
            return;
        }

        batchSelect.value = fifoOption.value;

        if (typeof applyBatchSelection === 'function') {
            applyBatchSelection(batchSelect);
        } else {
            batchSelect.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }


        async function loadBatches(selectElement) {
            const productId = selectElement.value;
            const row = selectElement.closest('.sale-row');
            const batchSelect = row.querySelector('.batch-select');

            updateGuideFromProductSelect(selectElement);

            batchSelect.innerHTML = '<option value="">Select Batch</option>';
            row.querySelector('.expiry-box').textContent = 'N/A';
            row.querySelector('.available-box').textContent = '0.00';
            row.querySelector('.reserved-box').textContent = '0.00';
            row.querySelector('.free-stock-box').textContent = '0.00';
            row.querySelector('.purchase-price-box').textContent = '0.00';
            row.querySelector('.unit-price').value = 0;
            row.querySelector('.unit-price').min = '0';
            row.querySelector('.unit-price').classList.remove('input-error');
            row.classList.remove('row-below-cost');

            if (!productId) {
                calculateTotals();
                return;
            }

            try {
                const batchUrl = "{{ route('products.sale-batches', ['product' => '__PRODUCT_ID__']) }}".replace('__PRODUCT_ID__', encodeURIComponent(productId));
                const response = await fetch(batchUrl);
                const data = await response.json();

                data.batches.forEach(batch => {
                    const option = document.createElement('option');
                    option.value = batch.id;
                    option.textContent = `${batch.batch_number} | Exp: ${batch.expiry_date ?? 'N/A'} | Free: ${Number(batch.free_stock).toFixed(2)}`;
                    option.dataset.expiry = batch.expiry_date ?? 'N/A';
                    option.dataset.available = batch.quantity_available ?? 0;
                    option.dataset.reserved = batch.reserved_quantity ?? 0;
                    option.dataset.freeStock = batch.free_stock ?? 0;
                    option.dataset.purchasePrice = batch.purchase_price ?? 0;
                    option.dataset.retailPrice = batch.retail_price ?? 0;
                    option.dataset.wholesalePrice = batch.wholesale_price ?? 0;
                    batchSelect.appendChild(option);
                });
        autoSelectFifoBatch(batchSelect);
            } catch (error) {
                console.error('Failed to load sale batches', error);
            }

            calculateTotals();
        }

        function applyBatchSelection(selectElement) {
            const row = selectElement.closest('.sale-row');
            const selected = selectElement.options[selectElement.selectedIndex];

            if (!selected || !selected.value) {
                row.querySelector('.expiry-box').textContent = 'N/A';
                row.querySelector('.available-box').textContent = '0.00';
                row.querySelector('.reserved-box').textContent = '0.00';
                row.querySelector('.free-stock-box').textContent = '0.00';
                row.querySelector('.purchase-price-box').textContent = '0.00';
                row.querySelector('.unit-price').value = 0;
                calculateTotals();
                return;
            }

            row.querySelector('.expiry-box').textContent = selected.dataset.expiry || 'N/A';
            row.querySelector('.available-box').textContent = Number(selected.dataset.available || 0).toFixed(2);
            row.querySelector('.reserved-box').textContent = Number(selected.dataset.reserved || 0).toFixed(2);
            row.querySelector('.free-stock-box').textContent = Number(selected.dataset.freeStock || 0).toFixed(2);
            row.querySelector('.purchase-price-box').textContent = Number(selected.dataset.purchasePrice || 0).toFixed(2);
            row.querySelector('.unit-price').min = currentRowPriceFloor(row, selected).toFixed(2);

            const saleType = document.getElementById('sale_type').value;
            if (saleType === 'wholesale') {
                row.querySelector('.unit-price').value = Number(selected.dataset.wholesalePrice || 0).toFixed(2);
            } else {
                row.querySelector('.unit-price').value = Number(selected.dataset.retailPrice || 0).toFixed(2);
            }

            calculateTotals();
        }

        function addLine() {
            const template = document.getElementById('sale-row-template');
            const clone = template.content.cloneNode(true);
            document.getElementById('sale-items-body').appendChild(clone);
            renumberRows();
            calculateTotals();
        }

        function addFiveLines() {
            for (let i = 0; i < 5; i++) {
                addLine();
            }
        }

        function removeRow(button) {
            const tbody = document.getElementById('sale-items-body');
            const rows = tbody.querySelectorAll('.sale-row');
            if (rows.length > 1) {
                button.closest('.sale-row').remove();
                renumberRows();
                calculateTotals();
            }
        }

        function validateRowPricing(row) {
            const batchSelect = row.querySelector('.batch-select');
            const unitPriceInput = row.querySelector('.unit-price');
            const discountInput = row.querySelector('.discount-amount');
            const quantityInput = row.querySelector('.quantity');
            const minimumAllowedPrice = currentRowPriceFloor(row);
            const purchasePrice = currentRowPurchasePrice(row);
            const unitPrice = parseFloat(unitPriceInput.value) || 0;
            const quantity = parseFloat(quantityInput.value) || 0;
            const discount = parseFloat(discountInput.value) || 0;
            const validationState = {
                belowPriceFloor: false,
                belowPurchaseCost: false,
            };

            row.classList.remove('row-below-cost');
            unitPriceInput.classList.remove('input-error');
            discountInput.classList.remove('input-error');
            unitPriceInput.title = '';
            discountInput.title = '';

            if (!batchSelect?.value || unitPrice <= 0 || quantity <= 0) {
                return validationState;
            }

            if (minimumAllowedPrice > 0 && unitPrice + 0.0001 < minimumAllowedPrice) {
                row.classList.add('row-below-cost');
                unitPriceInput.classList.add('input-error');
                unitPriceInput.title = `Unit price cannot be below the ${currentRowPriceFloorLabel()} (${minimumAllowedPrice.toFixed(2)})`;
                validationState.belowPriceFloor = true;
            }

            const maximumDiscount = Math.max(0, unitPrice - purchasePrice);

            discountInput.max = maximumDiscount.toFixed(2);

            if (unitPrice - discount + 0.0001 < purchasePrice) {
                row.classList.add('row-below-cost');
                discountInput.classList.add('input-error');
                discountInput.title = `Discount cannot reduce the unit price below the batch purchase price. Maximum discount per unit is ${maximumDiscount.toFixed(2)}.`;
                validationState.belowPurchaseCost = true;
            }

            return validationState;
        }

        function calculateTotals() {
            let grandTotal = 0;
            let lowPriceCount = 0;
            let belowPurchaseCostCount = 0;

            document.querySelectorAll('.sale-row').forEach(row => {
                const qtyInput = row.querySelector('.quantity');
                let qty = parseFloat(qtyInput.value) || 0;
                const freeStock = parseFloat(row.querySelector('.free-stock-box').textContent) || 0;
                const unitPrice = parseFloat(row.querySelector('.unit-price').value) || 0;
                const discount = parseFloat(row.querySelector('.discount-amount').value) || 0;

                if (qty > freeStock && row.dataset.recoveredRow !== 'true') {
                    qty = freeStock;
                    qtyInput.value = freeStock > 0
                        ? freeStock.toFixed(2).replace(/\.00$/, '')
                        : '0';
                    qtyInput.style.border = '2px solid orange';
                    qtyInput.title = `Auto-adjusted to available stock (${freeStock})`;
                } else {
                    qtyInput.style.border = '';
                    qtyInput.title = '';
                }

                const lineTotal = Math.max(0, qty * (unitPrice - discount));
                row.querySelector('.line-total').value = lineTotal.toFixed(2);
                grandTotal += lineTotal;

                const pricingState = validateRowPricing(row);
                if (pricingState.belowPriceFloor) {
                    lowPriceCount++;
                }
                if (pricingState.belowPurchaseCost) {
                    belowPurchaseCostCount++;
                }
            });

              document.getElementById('grand-total-text').textContent = grandTotal.toFixed(2);
              if (document.getElementById('payment_type')?.value === 'insurance' && insuranceModuleEnabled) {
                  runScreenTask('updateInsuranceFinancialPreview', () => updateInsuranceFinancialPreview());
              } else {
                  document.getElementById('balance-due-text').textContent = grandTotal.toFixed(2);
              }

            const saveBtn = document.querySelector('.btn-save');
            const pricingWarningBox = document.getElementById('pricing-warning-box');

            if (lowPriceCount > 0 || belowPurchaseCostCount > 0) {
                const warningParts = [];

                if (lowPriceCount > 0) {
                    warningParts.push(`${lowPriceCount} sale row(s) are below the normal ${currentRowPriceFloorLabel()}`);
                }

                if (belowPurchaseCostCount > 0) {
                    warningParts.push(`${belowPurchaseCostCount} sale row(s) have discounts that reduce the net selling amount below batch purchase price`);
                }

                pricingWarningBox.style.display = 'block';
                pricingWarningBox.textContent = `${warningParts.join('. ')}. Adjust the unit price or reduce the discount before saving.`;
                if (saveBtn) {
                    saveBtn.disabled = true;
                    saveBtn.style.opacity = '0.65';
                    saveBtn.style.cursor = 'not-allowed';
                    saveBtn.title = 'This sale would create a loss or go below the normal selling price.';
                }
            } else {
                pricingWarningBox.style.display = 'none';
                pricingWarningBox.textContent = '';
                if (saveBtn) {
                    saveBtn.disabled = false;
                    saveBtn.style.opacity = '1';
                    saveBtn.style.cursor = 'pointer';
                    saveBtn.title = '';
                }
            }

            let warningBox = document.getElementById('stock-warning-box');
            if (!warningBox) {
                warningBox = document.createElement('div');
                warningBox.id = 'stock-warning-box';
                warningBox.style.marginTop = '12px';
                warningBox.style.padding = '10px';
                warningBox.style.borderRadius = '8px';
                warningBox.style.background = '#fff4e5';
                warningBox.style.color = '#9a6700';
                warningBox.style.display = 'none';

                const totalBox = document.querySelector('.total-box');
                if (totalBox) {
                    totalBox.insertAdjacentElement('afterend', warningBox);
                }
            }

            const adjustedRow = Array.from(document.querySelectorAll('.sale-row')).some(row => {
                return (row.querySelector('.quantity')?.title || '').includes('Auto-adjusted');
            });

            if (adjustedRow) {
                warningBox.style.display = 'block';
                warningBox.textContent = 'Quantity was automatically adjusted to available batch stock.';
            } else {
                warningBox.style.display = 'none';
                warningBox.textContent = '';
            }
        }

        async function runQuickSearch() {
            const input = document.getElementById('quick-search-input');
            const resultsBody = document.getElementById('quick-search-results');
            if (!input || !resultsBody) return;

            const q = input.value.trim();

            if (q.length === 0) {
                resultsBody.innerHTML = `<tr><td colspan="${quickSearchColspan}">Type to search products...</td></tr>`;
                return;
            }

            try {
                const response = await fetch("{{ route('sales.productSearch') }}?q=" + encodeURIComponent(q));
                const rows = await response.json();

                if (!rows.length) {
                    resultsBody.innerHTML = `<tr><td colspan="${quickSearchColspan}">No matching product batches found.</td></tr>`;
                    return;
                }

                resultsBody.innerHTML = rows.map(row => `
                    <tr>
                        <td>${row.product_name ?? ''}</td>
                        <td>${row.batch_number ?? ''}</td>
                        <td>${row.supplier_name ?? ''}</td>
                        <td>${Number(row.purchase_price).toFixed(2)}</td>
                        <td>${Number(row.retail_price).toFixed(2)}</td>
                        <td>${Number(row.wholesale_price).toFixed(2)}</td>
                        <td>${Number(row.free_stock).toFixed(2)}</td>
                        <td>${row.expiry_date}</td>
                        ${showDispensingPriceGuide ? `<td>${renderDispensingGuidePreview(row.dispensing_price_guide || [])}</td>` : ''}
                        <td>
                            <button type="button" onclick="addSearchResultToSale(${row.product_id}, ${row.batch_id})">Use</button>
                        </td>
                    </tr>
                `).join('');
            } catch (error) {
                resultsBody.innerHTML = `<tr><td colspan="${quickSearchColspan}">Search failed.</td></tr>`;
            }
        }

        async function addSearchResultToSale(productId, batchId) {
            addLine();

            const rows = document.querySelectorAll('.sale-row');
            const row = rows[rows.length - 1];

            const productSelect = row.querySelector('.product-select');
            const batchSelect = row.querySelector('.batch-select');

            productSelect.value = String(productId);
            await loadBatches(productSelect);
            batchSelect.value = String(batchId);
            applyBatchSelection(batchSelect);
        }

        document.addEventListener('DOMContentLoaded', function () {
            document.addEventListener('keydown', function (event) {
                const modal = document.getElementById('quick-customer-modal');
                if (!modal || modal.hidden) return;

                if (event.key === 'Escape') {
                    event.preventDefault();
                    closeQuickCustomerModal();
                } else if (event.key === 'Enter' && event.target?.tagName !== 'TEXTAREA') {
                    event.preventDefault();
                    saveQuickCustomer();
                }
            });

            runScreenTask('renumberRows', () => renumberRows());
            runScreenTask('handleSaleTypeChange', () => handleSaleTypeChange());
            initialSaleFormSetup = false;
            runScreenTask('calculateTotals', () => calculateTotals());

            runScreenTask('insuranceCoveredInputBinding', () => {
                const insuranceCoveredInput = document.getElementById('insurance_covered_amount');
                if (insuranceCoveredInput) {
                    insuranceCoveredInput.addEventListener('input', () => {
                        runScreenTask('updateInsuranceFinancialPreview', () => updateInsuranceFinancialPreview());
                    });
                }
            });

              const quickInput = document.getElementById('quick-search-input');
            if (quickInput) {
                quickInput.addEventListener('input', runQuickSearch);
            }

            runScreenTask('showGuideForFirstSelectedProduct', () => showGuideForFirstSelectedProduct());

            const saleForm = document.getElementById('sale-form');
            if (saleForm) {
                saleForm.addEventListener('submit', function (e) {
                    let hasError = false;

                    document.querySelectorAll('.sale-row').forEach(row => {
                        const qty = parseFloat(row.querySelector('.quantity')?.value) || 0;
                        const freeStock = parseFloat(row.querySelector('.free-stock-box')?.textContent) || 0;

                        if (qty <= 0 || qty > freeStock) {
                            hasError = true;
                        }

                        const pricingState = validateRowPricing(row);

                        if (pricingState.belowPriceFloor || pricingState.belowPurchaseCost) {
                            hasError = true;
                        }
                    });

                    if (hasError) {
                        e.preventDefault();
                        alert(`Cannot save ${isProformaDocument ? 'proforma invoice' : 'sale'}. Review stock limits and make sure every row stays at or above the normal selling price and never discounts below batch purchase price.`);
                    }
                });
            }
        });
    </script>
@include('stock_requests._modal')
</body>
</html>
@unless($usesTypedProductSelector)
<!-- KIM Rx searchable sale product selector -->
<style>
    .product-search-wrap {
        position: relative;
        margin-bottom: 5px;
    }

    .product-search-input {
        box-sizing: border-box;
        width: 100%;
        min-width: 150px;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        background: #ffffff;
        color: #0f172a;
        font-size: 12px;
        line-height: 1.25;
        padding: 7px 8px;
    }

    .product-search-input:focus {
        border-color: #159a78;
        box-shadow: 0 0 0 2px rgba(21, 154, 120, 0.16);
        outline: none;
    }

    .product-search-results {
        display: none;
        position: absolute;
        z-index: 2000;
        top: calc(100% + 3px);
        left: 0;
        width: max(100%, 300px);
        max-height: 230px;
        overflow-y: auto;
        border: 1px solid #159a78;
        border-radius: 7px;
        background: #ffffff;
        box-shadow: 0 12px 28px rgba(15, 23, 42, 0.2);
    }

    .product-search-option,
    .product-search-empty {
        padding: 8px 10px;
        font-size: 12px;
        line-height: 1.35;
    }

    .product-search-option {
        cursor: pointer;
        color: #0f172a;
        border-bottom: 1px solid #e2e8f0;
    }

    .product-search-option:last-child {
        border-bottom: 0;
    }

    .product-search-option:hover,
    .product-search-option.is-active {
        background: #e9fbf4;
        color: #075f4b;
        font-weight: 700;
    }

    .product-search-empty {
        color: #64748b;
    }
</style>
<script>
(function () {
    if (window.__kimRxSaleProductSearchReady) {
        return;
    }

    window.__kimRxSaleProductSearchReady = true;

    const MAX_RESULTS = 14;

    function textOf(option) {
        return (option && option.textContent ? option.textContent : '').replace(/\s+/g, ' ').trim();
    }

    function validOptions(select) {
        return Array.from(select.options || []).filter(function (option) {
            return option.value && textOf(option);
        });
    }

    function closeAll(exceptPanel) {
        document.querySelectorAll('.product-search-results').forEach(function (panel) {
            if (panel !== exceptPanel) {
                panel.style.display = 'none';
            }
        });
    }

    function syncInput(select, input) {
        const selected = select.options[select.selectedIndex];
        input.value = selected && selected.value ? textOf(selected) : '';
    }

    function chooseOption(select, input, panel, option) {
        select.value = option.value;
        syncInput(select, input);
        panel.style.display = 'none';
        select.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function renderResults(select, input, panel) {
        const query = input.value.trim().toLowerCase();
        const tokens = query.split(/\s+/).filter(Boolean);
        const options = validOptions(select);
        const matches = options.filter(function (option) {
            if (tokens.length === 0) {
                return true;
            }
            const haystack = textOf(option).toLowerCase();
            return tokens.every(function (token) {
                return haystack.includes(token);
            });
        }).slice(0, MAX_RESULTS);

        panel.innerHTML = '';

        if (matches.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'product-search-empty';
            empty.textContent = 'No matching medicine found';
            panel.appendChild(empty);
            panel.style.display = 'block';
            closeAll(panel);
            return;
        }

        matches.forEach(function (option) {
            const item = document.createElement('div');
            item.className = 'product-search-option';
            item.textContent = textOf(option);
            item.addEventListener('mousedown', function (event) {
                event.preventDefault();
                chooseOption(select, input, panel, option);
            });
            panel.appendChild(item);
        });

        panel.style.display = 'block';
        closeAll(panel);
    }

    function enhanceSelect(select) {
        if (!select || select.dataset.kimRxSearchEnhanced === '1') {
            return;
        }

        select.dataset.kimRxSearchEnhanced = '1';

        const wrap = document.createElement('div');
        wrap.className = 'product-search-wrap';

        const input = document.createElement('input');
        input.type = 'text';
        input.className = 'product-search-input';
        input.placeholder = 'Type medicine name to search';
        input.autocomplete = 'off';

        const panel = document.createElement('div');
        panel.className = 'product-search-results';

        wrap.appendChild(input);
        wrap.appendChild(panel);
        select.parentNode.insertBefore(wrap, select);

        syncInput(select, input);

        input.addEventListener('focus', function () {
            renderResults(select, input, panel);
        });

        input.addEventListener('input', function () {
            renderResults(select, input, panel);
        });

        input.addEventListener('keydown', function (event) {
            const items = Array.from(panel.querySelectorAll('.product-search-option'));
            const active = panel.querySelector('.product-search-option.is-active');
            let index = active ? items.indexOf(active) : -1;

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                index = Math.min(index + 1, items.length - 1);
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                index = Math.max(index - 1, 0);
            } else if (event.key === 'Enter' && items.length > 0) {
                event.preventDefault();
                (active || items[0]).dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
                return;
            } else if (event.key === 'Escape') {
                panel.style.display = 'none';
                return;
            } else {
                return;
            }

            items.forEach(function (item) {
                item.classList.remove('is-active');
            });

            if (items[index]) {
                items[index].classList.add('is-active');
                items[index].scrollIntoView({ block: 'nearest' });
            }
        });

        select.addEventListener('change', function () {
            syncInput(select, input);
        });
    }

    function enhanceAll(root) {
        (root || document).querySelectorAll('select.product-select').forEach(enhanceSelect);
    }

    document.addEventListener('click', function (event) {
        if (!event.target.closest('.product-search-wrap')) {
            closeAll(null);
        }
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            enhanceAll(document);
        });
    } else {
        enhanceAll(document);
    }

    if (document.body) {
        new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                mutation.addedNodes.forEach(function (node) {
                    if (node.nodeType === 1) {
                        enhanceAll(node);
                    }
                });
            });
        }).observe(document.body, { childList: true, subtree: true });
    }
})();
</script>
@endunless
@if($usesTypedProductSelector)
<script>
// KIM typed dispensing and FIFO batch behavior
(function () {
    if (window.__kimTypedDispensingFifoReady) {
        return;
    }
    window.__kimTypedDispensingFifoReady = true;

    function normalise(value) {
        return String(value || '').toLowerCase().replace(/\s+/g, ' ').trim();
    }

    function realOptions(select) {
        return Array.from(select ? select.options : []).filter(function (option) {
            return option.value;
        });
    }

    function labelFor(option) {
        return option ? String(option.textContent || '').replace(/\s+/g, ' ').trim() : '';
    }

    function matchOption(select, typed, allowLoose) {
        var needle = normalise(typed);
        if (!needle) {
            return null;
        }

        var options = realOptions(select);
        var exact = options.find(function (option) {
            return normalise(labelFor(option)) === needle;
        });
        if (exact) {
            return exact;
        }

        if (!allowLoose || needle.length < 2) {
            return null;
        }

        return options.find(function (option) {
            return normalise(labelFor(option)).indexOf(needle) !== -1;
        }) || null;
    }

    function matchingOptions(select, typed) {
        var needle = normalise(typed);
        if (!needle) {
            return [];
        }

        var tokens = needle.split(' ').filter(Boolean);
        return realOptions(select).filter(function (option) {
            var haystack = normalise(labelFor(option));
            return tokens.every(function (token) {
                return haystack.indexOf(token) !== -1;
            });
        }).slice(0, 12);
    }

    function hidePanel(panel) {
        if (!panel) {
            return;
        }

        panel.style.display = 'none';
        panel.innerHTML = '';
        panel._kimAnchorInput = null;
    }

    function closeTypePanels(except) {
        document.querySelectorAll('.kim-type-results').forEach(function (panel) {
            if (panel !== except) {
                hidePanel(panel);
            }
        });
    }

    function positionPanel(input, panel) {
        var rect = input.getBoundingClientRect();
        var viewportWidth = document.documentElement.clientWidth || window.innerWidth || 320;
        var viewportHeight = document.documentElement.clientHeight || window.innerHeight || 480;
        var width = Math.min(Math.max(rect.width, 280), Math.max(240, viewportWidth - 24));
        var left = Math.min(Math.max(12, rect.left), Math.max(12, viewportWidth - width - 12));
        var top = rect.bottom + 4;

        if (top > viewportHeight - 80) {
            top = Math.max(12, rect.top - 224);
        }

        panel.style.width = width + 'px';
        panel.style.left = left + 'px';
        panel.style.top = top + 'px';
    }

    function showPanel(input, panel) {
        panel._kimAnchorInput = input;
        positionPanel(input, panel);
        panel.style.display = 'block';
        closeTypePanels(panel);
    }

    function repositionOpenPanels() {
        document.querySelectorAll('.kim-type-results').forEach(function (panel) {
            if (panel.style.display !== 'block') {
                return;
            }

            var input = panel._kimAnchorInput;
            if (!input || !document.contains(input) || !input.value.trim()) {
                hidePanel(panel);
                return;
            }

            positionPanel(input, panel);
        });
    }

    var repositionQueued = false;
    function queuePanelReposition() {
        if (repositionQueued) {
            return;
        }

        repositionQueued = true;
        window.requestAnimationFrame(function () {
            repositionQueued = false;
            repositionOpenPanels();
        });
    }

    function chooseOption(select, input, panel, option) {
        if (!option) {
            return false;
        }

        if (select.value !== option.value) {
            select.value = option.value;
            triggerNativeChange(select);
        }

        input.value = labelFor(option);
        input.classList.remove('input-error');
        hidePanel(panel);
        return true;
    }

    function renderResults(select, input, panel) {
        var query = input.value.trim();
        var matches = matchingOptions(select, query);

        if (!query) {
            hidePanel(panel);
            return;
        }

        panel.innerHTML = '';

        if (!matches.length) {
            var empty = document.createElement('div');
            empty.className = 'kim-type-empty';
            empty.textContent = input.classList.contains('kim-customer-type-input')
                ? 'No matching customer found'
                : 'No matching medicine found';
            panel.appendChild(empty);
            showPanel(input, panel);
            return;
        }

        matches.forEach(function (option) {
            var item = document.createElement('div');
            item.className = 'kim-type-option';
            item.textContent = labelFor(option);
            item.addEventListener('mousedown', function (event) {
                event.preventDefault();
                chooseOption(select, input, panel, option);
            });
            panel.appendChild(item);
        });

        showPanel(input, panel);
    }

    function moveActiveResult(panel, direction) {
        var items = Array.from(panel.querySelectorAll('.kim-type-option'));
        if (!items.length) {
            return;
        }

        var current = panel.querySelector('.kim-type-option.is-active');
        var index = current ? items.indexOf(current) : -1;
        index = Math.max(0, Math.min(items.length - 1, index + direction));

        items.forEach(function (item) {
            item.classList.remove('is-active');
        });

        items[index].classList.add('is-active');
        items[index].scrollIntoView({ block: 'nearest' });
    }

    function hideSelect(select) {
        if (!select.dataset.kimWasRequired) {
            select.dataset.kimWasRequired = select.hasAttribute('required') ? '1' : '0';
        }

        if (!select.classList.contains('kim-hidden-system-select')) {
            select.classList.add('kim-hidden-system-select');
            select.tabIndex = -1;
            select.setAttribute('aria-hidden', 'true');
        }
    }

    function currentLabel(select) {
        var option = select.options[select.selectedIndex];
        return option && option.value ? labelFor(option) : '';
    }

    function triggerNativeChange(select) {
        select.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function ensureTypedInput(select, placeholder, className, listPrefix) {
        if (!select || select.dataset.kimTypedReady === '1') {
            return select ? select._kimTypedInput : null;
        }

        var wrap = document.createElement('div');
        wrap.className = 'kim-type-wrap';

        var input = document.createElement('input');
        input.type = 'text';
        input.className = 'kim-type-input ' + className;
        input.placeholder = placeholder;
        input.autocomplete = 'off';
        input.value = currentLabel(select);
        input.required = select.hasAttribute('required');

        var panel = document.createElement('div');
        panel.className = 'kim-type-results';

        wrap.appendChild(input);
        select.parentNode.insertBefore(wrap, select);
        document.body.appendChild(panel);
        hideSelect(select);
        select.required = false;
        select.dataset.kimTypedReady = '1';
        select._kimTypedInput = input;
        input._kimResultsPanel = panel;

        var resolve = function (allowLoose) {
            var option = matchOption(select, input.value, allowLoose);
            if (!option) {
                if (allowLoose) {
                    if (select.value) {
                        select.value = '';
                        triggerNativeChange(select);
                    }

                    input.classList.toggle('input-error', input.value.trim().length > 0 || select.dataset.kimWasRequired === '1');
                    hidePanel(panel);
                }

                return false;
            }

            return chooseOption(select, input, panel, option);
        };
        select._kimResolveTypedInput = resolve;

        input.addEventListener('input', function () {
            input.classList.remove('input-error');

            if (!input.value.trim()) {
                hidePanel(panel);
            } else {
                renderResults(select, input, panel);
            }

            if (!resolve(false) && !input.value.trim() && select.value) {
                select.value = '';
                triggerNativeChange(select);
            }
        });

        input.addEventListener('change', function () {
            resolve(true);
        });

        input.addEventListener('blur', function () {
            resolve(true);
        });

        input.addEventListener('keydown', function (event) {
            if (!input.value.trim()) {
                hidePanel(panel);
                return;
            }

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                if (panel.style.display !== 'block') {
                    renderResults(select, input, panel);
                }
                moveActiveResult(panel, 1);
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                if (panel.style.display !== 'block') {
                    renderResults(select, input, panel);
                }
                moveActiveResult(panel, -1);
            } else if (event.key === 'Enter' && panel.style.display === 'block') {
                var active = panel.querySelector('.kim-type-option.is-active') || panel.querySelector('.kim-type-option');
                if (active) {
                    event.preventDefault();
                    active.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
                }
            } else if (event.key === 'Escape') {
                hidePanel(panel);
            }
        });

        select.addEventListener('change', function () {
            input.value = currentLabel(select);
            input.classList.remove('input-error');
            hidePanel(panel);
        });

        return input;
    }

    function setupCustomerTyping() {
        var select = document.getElementById('customer_id');
        if (!select) {
            return;
        }
        ensureTypedInput(select, 'Type customer name', 'kim-customer-type-input', 'kim-customer-list');
    }

    function setupProductTyping(select) {
        if (!select) {
            return;
        }
        ensureTypedInput(select, 'Type medicine name', 'kim-product-type-input', 'kim-product-list');
    }

    function numberFrom(value) {
        var parsed = parseFloat(String(value || '').replace(/,/g, ''));
        return Number.isFinite(parsed) ? parsed : 0;
    }

    function optionFreeStock(option) {
        if (!option) {
            return 0;
        }
        if (option.dataset && option.dataset.freeStock !== undefined) {
            return numberFrom(option.dataset.freeStock);
        }
        var match = String(option.textContent || '').match(/Free:\s*([0-9,.]+)/i);
        return match ? numberFrom(match[1]) : 0;
    }

    function fifoOption(select) {
        var options = realOptions(select);
        return options.find(function (option) {
            return optionFreeStock(option) > 0;
        }) || options[0] || null;
    }

    function ensureBatchDisplay(select) {
        if (!select) {
            return null;
        }

        // FIFO selects the first eligible batch, but dispensers must be able to choose another.
        select.classList.remove('kim-hidden-system-select');
        select.removeAttribute('aria-hidden');
        select.removeAttribute('tabindex');
        return select;
    }

    function updateBatchDisplay(select) {
        if (!ensureBatchDisplay(select)) {
            return;
        }
        var option = select.options[select.selectedIndex];
        select.classList.toggle('kim-fifo-batch-empty', !option || !option.value);
    }

    window.kimAutoSelectFifoBatch = function (select, force) {
        if (!select) {
            return;
        }

        ensureBatchDisplay(select);
        var preferred = fifoOption(select);
        if (preferred && (force || !select.value)) {
            var changed = select.value !== preferred.value;
            select.value = preferred.value;
            if (changed) {
                triggerNativeChange(select);
            }
        }
        updateBatchDisplay(select);
    };

    function setupBatch(select) {
        if (!select) {
            return;
        }
        ensureBatchDisplay(select);
        window.kimAutoSelectFifoBatch(select, !select.value);
    }

    function refreshTypedSaleUi(root) {
        var scope = root || document;
        setupCustomerTyping();
        scope.querySelectorAll('select.product-select').forEach(setupProductTyping);
        scope.querySelectorAll('select.batch-select').forEach(function (select) {
            setupBatch(select);
        });
    }

    function bindTypedSaleValidation() {
        var form = document.getElementById('sale-form');
        if (!form || form.dataset.kimTypedSaleValidationReady === '1') {
            return;
        }

        form.dataset.kimTypedSaleValidationReady = '1';
        form.addEventListener('submit', function (event) {
            var firstInvalid = null;

            document.querySelectorAll('select.product-select').forEach(function (select) {
                var input = select._kimTypedInput;
                if (!input) {
                    return;
                }

                if (!select.value || normalise(input.value) !== normalise(currentLabel(select))) {
                    select._kimResolveTypedInput(true);
                }

                if (select.dataset.kimWasRequired === '1' && !select.value) {
                    input.classList.add('input-error');
                    firstInvalid = firstInvalid || input;
                }
            });

            document.querySelectorAll('select.batch-select').forEach(function (select) {
                if (select.dataset.kimWasRequired === '1' && !select.value) {
                    select.classList.add('kim-fifo-batch-empty');
                    firstInvalid = firstInvalid || select;
                }
            });

            if (firstInvalid) {
                event.preventDefault();
                alert('Please type and choose a medicine with an available batch before saving.');
                firstInvalid.focus();
            }
        });
    }

    var originalLoadBatches = window.loadBatches;
    if (typeof originalLoadBatches === 'function' && !originalLoadBatches.__kimWrapped) {
        window.loadBatches = async function (productSelect) {
            var result = await originalLoadBatches.apply(this, arguments);
            setTimeout(function () {
                var row = productSelect.closest('tr');
                var batchSelect = row ? row.querySelector('select.batch-select') : null;
                if (batchSelect) {
                    window.kimAutoSelectFifoBatch(batchSelect, false);
                }
                refreshTypedSaleUi(row || document);
            }, 0);
            return result;
        };
        window.loadBatches.__kimWrapped = true;
    }

    var originalAddLine = window.addLine;
    if (typeof originalAddLine === 'function' && !originalAddLine.__kimWrapped) {
        window.addLine = function () {
            var result = originalAddLine.apply(this, arguments);
            setTimeout(function () {
                refreshTypedSaleUi(document);
            }, 0);
            return result;
        };
        window.addLine.__kimWrapped = true;
    }

    var originalAddSearchResultToSale = window.addSearchResultToSale;
    if (typeof originalAddSearchResultToSale === 'function' && !originalAddSearchResultToSale.__kimWrapped) {
        window.addSearchResultToSale = async function () {
            var result = await originalAddSearchResultToSale.apply(this, arguments);
            setTimeout(function () {
                refreshTypedSaleUi(document);
                document.querySelectorAll('select.batch-select').forEach(function (select) {
                    window.kimAutoSelectFifoBatch(select, false);
                });
            }, 0);
            return result;
        };
        window.addSearchResultToSale.__kimWrapped = true;
    }

    document.addEventListener('click', function (event) {
        if (!event.target.closest('.kim-type-wrap') && !event.target.closest('.kim-type-results')) {
            closeTypePanels(null);
        }
    });

    window.addEventListener('scroll', queuePanelReposition, true);
    window.addEventListener('resize', queuePanelReposition);

    window.KimRxRefreshTypedSaleUi = refreshTypedSaleUi;

    document.addEventListener('DOMContentLoaded', function () {
        refreshTypedSaleUi(document);
        bindTypedSaleValidation();
    });

    refreshTypedSaleUi(document);
    bindTypedSaleValidation();
})();
</script>
<script>
(function () {
    try {
        for (var index = window.sessionStorage.length - 1; index >= 0; index -= 1) {
            var key = window.sessionStorage.key(index);
            if (key && key.indexOf('kimrx-tab-draft:') === 0 && key.indexOf(':vip-sale-create:') !== -1) {
                window.sessionStorage.removeItem(key);
            }
        }
    } catch (error) {
        // Storage may be unavailable in private mode; the sale form still starts clean.
    }
})();

window.addEventListener('pageshow', function (event) {
    if (event.persisted) {
        window.location.reload();
    }
});
</script>
@endif
