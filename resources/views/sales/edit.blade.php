<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $pageTitle ?? 'Edit Pending Sale' }} - KIM Rx</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; display: flex; background: #f5f7fb; }

        .content { flex: 1; width: 100%; max-width: 100%; padding: 20px; }
        .topbar, .panel {
            background: white;
            padding: 20px;
            border-radius: 16px;
            margin-bottom: 20px;
            box-shadow: 0 14px 34px rgba(15, 23, 42, 0.06);
            max-width: 100%;
        }

        .form-row { display: grid; grid-template-columns: repeat(3, minmax(220px, 1fr)); gap: 16px; margin-bottom: 16px; }
        .form-group { display: flex; flex-direction: column; }
        .form-group label { margin-bottom: 8px; font-weight: bold; }
        .form-group input, .form-group select, .form-group textarea { padding: 10px; border: 1px solid #ddd; border-radius: 8px; }
        .full { grid-column: 1 / -1; }

        .alert-danger, .alert-success { padding: 12px; border-radius: 8px; margin-bottom: 15px; }
        .alert-danger { background: #fdecea; color: #b42318; }
        .alert-success { background: #e7f6ec; color: #1f7a4f; }
        .row-below-cost td { background: #fff7f5; }
        .input-error { border: 2px solid #b42318 !important; }

        .items-table-wrap {
            overflow-x: auto;
            margin-top: 10px;
            max-width: 100%;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            background: #fff;
        }
        table { width: 100%; border-collapse: collapse; min-width: 1320px; }
        table th, table td { border: 1px solid #ddd; padding: 7px; text-align: left; vertical-align: middle; }
        table th { background: #f8f8f8; font-size: 12px; white-space: nowrap; }

        .mini-input, .mini-select { width: 100%; padding: 7px 8px; border: 1px solid #ccc; border-radius: 6px; font-size: 13px; }
        .info-box { background: #f1f3f6; border-radius: 6px; padding: 7px 6px; text-align: center; min-width: 72px; font-size: 12px; line-height: 1.3; }

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

        .btn { padding: 8px 12px; border-radius: 6px; color: white; text-decoration: none; border: none; cursor: pointer; display: inline-block; }
        .btn-save { background: green; }
        .btn-add { background: #1f7a4f; }
        .btn-delete { background: red; }
        .btn-back { background: #3949ab; }

        .btn-row { margin-top: 12px; display: flex; gap: 10px; flex-wrap: wrap; }
        .total-box { margin-top: 16px; font-size: 16px; font-weight: bold; line-height: 1.8; }
        .customer-warning { font-size: 12px; color: #a56a00; margin-top: 4px; }
        .insurance-panel {
            margin: 8px 0 18px;
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
        }
    </style>
</head>
<body>
    @include('layouts.sidebar')

    <div class="content" id="mainContent">
    <div class="topbar">
        <h3>{{ $pageTitle ?? 'Edit Pending Sale' }}</h3>
        <p>{{ $pageDescription ?? 'Continue from where you stopped' }}</p>
    </div>

    <div class="panel">
        @php($quickSearchColumnCount = ($showDispensingPriceGuide ?? false) ? 10 : 9)
        @if(session('success'))
            <div class="alert-success">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert-danger">
                <ul style="margin:0; padding-left:18px;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ $updateAction ?? route('sales.update', $sale->id) }}">
            @csrf
            @method('PUT')

            <div class="form-row">
                <div class="form-group">
                    <label for="invoice_number">Invoice Number</label>
                    <input type="text" name="invoice_number" id="invoice_number" value="{{ old('invoice_number', $sale->invoice_number) }}" readonly required>
                </div>

                <div class="form-group">
                    <label for="sale_date">Sale Date</label>
                    <input type="date" name="sale_date" id="sale_date" value="{{ old('sale_date', optional($sale->sale_date)->format('Y-m-d')) }}" required>
                </div>

                <div class="form-group">
                    <label for="sale_type">Sale Type</label>
                    <select name="sale_type" id="sale_type" onchange="handleSaleRequirements(); reapplyAllRows();" required>
                        @foreach(($saleTypeConfig['sale_type_options'] ?? ['retail' => 'Retail', 'wholesale' => 'Wholesale']) as $saleTypeValue => $saleTypeLabel)
                            <option value="{{ $saleTypeValue }}" {{ old('sale_type', $sale->sale_type) == $saleTypeValue ? 'selected' : '' }}>{{ $saleTypeLabel }}</option>
                        @endforeach
                    </select>
                    @if(!empty($saleTypeConfig['sale_type_hint']))
                        <div class="customer-warning">{{ $saleTypeConfig['sale_type_hint'] }}</div>
                    @endif
                </div>

                <div class="form-group">
                    <label for="payment_type">Payment Type</label>
                    <select name="payment_type" id="payment_type" onchange="handleSaleRequirements()" required>
                        <option value="cash" {{ old('payment_type', $sale->payment_type) == 'cash' ? 'selected' : '' }}>Cash</option>
                        <option value="credit" {{ old('payment_type', $sale->payment_type) == 'credit' ? 'selected' : '' }}>Credit</option>
                        @if($insuranceEnabled ?? false)
                            <option value="insurance" {{ old('payment_type', $sale->payment_type) == 'insurance' ? 'selected' : '' }}>Insurance</option>
                        @endif
                    </select>
                </div>

                <div class="form-group">
                    <label for="customer_id">Customer</label>
                    <select name="customer_id" id="customer_id">
                        <option value="">Select Customer</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}" {{ old('customer_id', $sale->customer_id) == $customer->id ? 'selected' : '' }}>
                                {{ $customer->name }}
                            </option>
                        @endforeach
                    </select>
                    <div class="customer-warning" id="customer-warning"></div>
                </div>

                @if($insuranceEnabled ?? false || $sale->payment_type === 'insurance')
                    @include('sales._insurance_billing_fields', ['sale' => $sale, 'insurers' => $insurers, 'insuranceTotal' => (float) $sale->total_amount])
                @endif

                <div class="form-group full">
                    <label for="notes">Notes</label>
                    <textarea name="notes" id="notes">{{ old('notes', $sale->notes) }}</textarea>
                </div>
            </div>

            <div class="panel" style="margin-bottom:20px;">
                <h3 style="margin-top:0;">Quick Product Batch Search</h3>
                <input type="text" id="quick-search-input" placeholder="Type product name..." style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px; margin-bottom:12px;">

                <div style="overflow-x:auto;">
                    <table style="width:100%; border-collapse:collapse; min-width:1200px;">
                        <thead>
                            <tr>
                                <th style="border:1px solid #ddd; padding:8px;">Product</th>
                                <th style="border:1px solid #ddd; padding:8px;">Batch</th>
                                <th style="border:1px solid #ddd; padding:8px;">Supplier</th>
                                <th style="border:1px solid #ddd; padding:8px;">Purchase</th>
                                <th style="border:1px solid #ddd; padding:8px;">Retail</th>
                                <th style="border:1px solid #ddd; padding:8px;">Wholesale</th>
                                <th style="border:1px solid #ddd; padding:8px;">Free Stock</th>
                                <th style="border:1px solid #ddd; padding:8px;">Expiry</th>
                                @if($showDispensingPriceGuide ?? false)
                                    <th style="border:1px solid #ddd; padding:8px;">Guide</th>
                                @endif
                                <th style="border:1px solid #ddd; padding:8px;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="quick-search-results">
                            <tr>
                                <td colspan="{{ $quickSearchColumnCount }}" style="border:1px solid #ddd; padding:8px;">Type to search products...</td>
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

            <h3>Sale Items</h3>

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
                            <th>Discount</th>
                            <th>Total</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="sale-items-body">
                        @foreach($sale->items as $item)
                            @php
                                $batch = $item->batch;
                                $available = (float) ($batch?->quantity_available ?? 0);
                                $reserved = (float) ($batch?->reserved_quantity ?? 0);
                                $editingQty = (float) $item->quantity;
                                $freeForEdit = max(0, $available - $reserved + $editingQty);
                            @endphp
                            <tr class="sale-row">
                                <td class="line-no">{{ $loop->iteration }}</td>
                                <td>
                                    <select name="product_id[]" class="mini-select product-select" onchange="loadBatches(this)" required>
                                        <option value="">Select Product</option>
                                        @foreach($products as $product)
                                            <option
                                                value="{{ $product->id }}"
                                                data-dispensing-guide="{{ e(json_encode($product->normalizedDispensingPriceGuide())) }}"
                                                {{ $item->product_id == $product->id ? 'selected' : '' }}
                                            >
                                                {{ $product->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <select name="product_batch_id[]" class="mini-select batch-select" onchange="applyBatchSelection(this)" required>
                                        <option
                                            value="{{ $item->product_batch_id }}"
                                            data-expiry="{{ $batch && $batch->expiry_date ? $batch->expiry_date->format('Y-m-d') : 'N/A' }}"
                                            data-available="{{ $available }}"
                                            data-reserved="{{ $reserved }}"
                                            data-free-stock="{{ $freeForEdit }}"
                                            data-purchase-price="{{ (float) $item->purchase_price }}"
                                            data-retail-price="{{ (float) ($batch?->retail_price ?? 0) }}"
                                            data-wholesale-price="{{ (float) ($batch?->wholesale_price ?? 0) }}"
                                        >
                                            {{ $batch?->batch_number ?? 'Selected Batch' }}
                                        </option>
                                    </select>
                                </td>
                                <td><div class="info-box expiry-box">{{ $batch && $batch->expiry_date ? $batch->expiry_date->format('Y-m-d') : 'N/A' }}</div></td>
                                <td><div class="info-box available-box">{{ number_format($available, 2, '.', '') }}</div></td>
                                <td><div class="info-box reserved-box">{{ number_format($reserved, 2, '.', '') }}</div></td>
                                <td><div class="info-box free-stock-box">{{ number_format($freeForEdit, 2, '.', '') }}</div></td>
                                <td><div class="info-box purchase-price-box">{{ number_format((float) $item->purchase_price, 2, '.', '') }}</div></td>
                                <td><input type="number" step="0.01" name="unit_price[]" class="mini-input unit-price" value="{{ number_format((float) $item->unit_price, 2, '.', '') }}" oninput="calculateTotals()" required></td>
                                <td><input type="number" step="0.01" name="quantity[]" class="mini-input quantity" value="{{ number_format((float) $item->quantity, 2, '.', '') }}" oninput="calculateTotals()" required></td>
                                <td><input type="number" step="0.01" name="discount_amount[]" class="mini-input discount-amount" value="{{ number_format((float) $item->discount_amount, 2, '.', '') }}" oninput="calculateTotals()" {{ !$canManageDiscounts ? 'readonly' : '' }}></td>
                                <td><input type="number" step="0.01" class="mini-input line-total" value="{{ number_format((float) $item->total_amount, 2, '.', '') }}" readonly></td>
                                <td><button type="button" class="btn btn-delete" onclick="removeRow(this)">Remove</button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="btn-row">
                <button type="button" class="btn btn-add" onclick="addLine()">Add 1 Line</button>
                <button type="button" class="btn btn-add" onclick="addFiveLines()">Add 5 Lines</button>
            </div>

            <div class="total-box">
                Grand Total: <span id="grand-total-text">{{ number_format((float) $sale->total_amount, 2) }}</span><br>
                Balance Due After Approval: <span id="balance-due-text">{{ number_format((float) $sale->total_amount, 2) }}</span>
            </div>

            <div id="pricing-warning-box" class="alert-danger" style="display:none;"></div>

            <div style="margin-top:18px; display:flex; gap:10px; flex-wrap:wrap;">
                <button type="submit" class="btn btn-save">{{ $updateButtonLabel ?? 'Update Pending Sale' }}</button>
                <a href="{{ route('sales.show', $sale->id) }}" class="btn btn-back">Back to Sale</a>
            </div>
        </form>
    </div>
</div>

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
        <td><input type="number" step="0.01" name="discount_amount[]" class="mini-input discount-amount" value="0" oninput="calculateTotals()" {{ !$canManageDiscounts ? 'readonly' : '' }}></td>
        <td><input type="number" step="0.01" class="mini-input line-total" value="0.00" readonly></td>
        <td><button type="button" class="btn btn-delete" onclick="removeRow(this)">Remove</button></td>
    </tr>
</template>

<script>
    const isProformaDocument = @json($isProforma ?? false);
      const lockedSaleType = @json($saleTypeConfig['locked_sale_type'] ?? null);
      const canOverrideSalePrice = @json($canOverrideSalePrice ?? false);
      const showDispensingPriceGuide = @json($showDispensingPriceGuide ?? false);
      const quickSearchColspan = @json($quickSearchColumnCount);
      const insuranceModuleEnabled = @json((bool) (($insuranceEnabled ?? false) || $sale->payment_type === 'insurance'));

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

        const purchasePrice = Number(option.dataset.purchasePrice || 0);
        const sellingPrice = currentSellingPriceForOption(option);

        return canOverrideSalePrice ? purchasePrice : sellingPrice;
    }

    function currentRowPriceFloorLabel() {
        if (canOverrideSalePrice) {
            return 'purchase price';
        }

        return document.getElementById('sale_type').value === 'wholesale'
            ? 'wholesale selling price'
            : 'retail selling price';
    }

    function handleSaleRequirements() {
        const saleTypeSelect = document.getElementById('sale_type');
        if (lockedSaleType && saleTypeSelect.value !== lockedSaleType) {
            saleTypeSelect.value = lockedSaleType;
        }

        const saleType = saleTypeSelect.value;
        const paymentType = document.getElementById('payment_type').value;
        const customerSelect = document.getElementById('customer_id');
        const warning = document.getElementById('customer-warning');

          if (saleType === 'wholesale' || paymentType === 'credit' || paymentType === 'insurance') {
              customerSelect.setAttribute('required', 'required');
              warning.textContent = 'Customer is required for wholesale, credit, or insurance sales.';
          } else {
              customerSelect.removeAttribute('required');
              warning.textContent = '';
          }

          updateInsuranceFields();
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

          updateInsuranceFinancialPreview();
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
          document.getElementById('insurance-total-preview').textContent = total.toFixed(2);
          document.getElementById('insurance-patient-copay-preview').textContent = patientCopay.toFixed(2);
          document.getElementById('insurance-balance-preview').textContent = (isInsurance ? covered : 0).toFixed(2);
          hiddenCopayInput.value = patientCopay.toFixed(2);

          if (isInsurance) {
              document.getElementById('balance-due-text').textContent = covered.toFixed(2);
          }
      }

    function renumberRows() {
        document.querySelectorAll('.sale-row').forEach((row, index) => {
            row.querySelector('.line-no').textContent = index + 1;
        });
    }

    async function loadBatches(selectElement) {
        const productId = selectElement.value;
        const row = selectElement.closest('.sale-row');
        const batchSelect = row.querySelector('.batch-select');

        updateGuideFromProductSelect(selectElement);

        batchSelect.innerHTML = '<option value="">Select Batch</option>';

        if (!productId) {
            row.querySelector('.expiry-box').textContent = 'N/A';
            row.querySelector('.available-box').textContent = '0.00';
            row.querySelector('.reserved-box').textContent = '0.00';
            row.querySelector('.free-stock-box').textContent = '0.00';
            row.querySelector('.purchase-price-box').textContent = '0.00';
            row.querySelector('.unit-price').value = 0;
            row.querySelector('.unit-price').min = '0';
            row.querySelector('.unit-price').classList.remove('input-error');
            row.classList.remove('row-below-cost');
            calculateTotals();
            return;
        }

        try {
            const response = await fetch(`/products/${productId}/sale-batches`);
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
        } catch (error) {
            console.error('Failed to load sale batches', error);
        }

        calculateTotals();
    }

    function applyBatchSelection(selectElement) {
        const row = selectElement.closest('.sale-row');
        const selected = selectElement.options[selectElement.selectedIndex];

        if (!selected || !selected.value) return;

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

    function reapplyAllRows() {
        document.querySelectorAll('.sale-row').forEach(row => {
            const batchSelect = row.querySelector('.batch-select');
            if (batchSelect && batchSelect.value) {
                applyBatchSelection(batchSelect);
            }
        });
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

        const lineSubtotal = quantity * unitPrice;
        const minimumLineTotal = quantity * purchasePrice;
        const maximumDiscount = Math.max(0, lineSubtotal - minimumLineTotal);

        discountInput.max = maximumDiscount.toFixed(2);

        if (lineSubtotal - discount + 0.0001 < minimumLineTotal) {
            row.classList.add('row-below-cost');
            discountInput.classList.add('input-error');
            discountInput.title = `Discount cannot reduce the line below the batch purchase price. Maximum discount for this row is ${maximumDiscount.toFixed(2)}.`;
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

            if (qty > freeStock) {
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

            const lineTotal = Math.max(0, (qty * unitPrice) - discount);
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
            updateInsuranceFinancialPreview();
        }

        const saveBtn = document.querySelector('.btn-save');
        const pricingWarningBox = document.getElementById('pricing-warning-box');
        if (saveBtn) {
            const hasPricingError = lowPriceCount > 0 || belowPurchaseCostCount > 0;
            saveBtn.disabled = hasPricingError;
            saveBtn.style.opacity = hasPricingError ? '0.65' : '1';
            saveBtn.style.cursor = hasPricingError ? 'not-allowed' : 'pointer';
            saveBtn.title = hasPricingError ? 'This sale would create a loss or go below the allowed selling floor.' : '';
        }

        if (pricingWarningBox) {
            if (lowPriceCount > 0 || belowPurchaseCostCount > 0) {
                const warningParts = [];

                if (lowPriceCount > 0) {
                    warningParts.push(`${lowPriceCount} sale row(s) are below the allowed ${currentRowPriceFloorLabel()} for your role`);
                }

                if (belowPurchaseCostCount > 0) {
                    warningParts.push(`${belowPurchaseCostCount} sale row(s) have discounts that reduce the net selling amount below batch purchase price`);
                }

                pricingWarningBox.style.display = 'block';
                pricingWarningBox.textContent = `${warningParts.join('. ')}. Adjust the unit price or reduce the discount before saving.`;
            } else {
                pricingWarningBox.style.display = 'none';
                pricingWarningBox.textContent = '';
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
            resultsBody.innerHTML = `<tr><td colspan="${quickSearchColspan}" style="border:1px solid #ddd; padding:8px;">Type to search products...</td></tr>`;
            return;
        }

        try {
            const response = await fetch("{{ route('sales.productSearch') }}?q=" + encodeURIComponent(q));
            const rows = await response.json();

            if (!rows.length) {
                resultsBody.innerHTML = `<tr><td colspan="${quickSearchColspan}" style="border:1px solid #ddd; padding:8px;">No matching product batches found.</td></tr>`;
                return;
            }

            resultsBody.innerHTML = rows.map(row => `
                <tr>
                    <td style="border:1px solid #ddd; padding:8px;">${row.product_name ?? ''}</td>
                    <td style="border:1px solid #ddd; padding:8px;">${row.batch_number ?? ''}</td>
                    <td style="border:1px solid #ddd; padding:8px;">${row.supplier_name ?? ''}</td>
                    <td style="border:1px solid #ddd; padding:8px;">${Number(row.purchase_price).toFixed(2)}</td>
                    <td style="border:1px solid #ddd; padding:8px;">${Number(row.retail_price).toFixed(2)}</td>
                    <td style="border:1px solid #ddd; padding:8px;">${Number(row.wholesale_price).toFixed(2)}</td>
                    <td style="border:1px solid #ddd; padding:8px;">${Number(row.free_stock).toFixed(2)}</td>
                    <td style="border:1px solid #ddd; padding:8px;">${row.expiry_date}</td>
                    ${showDispensingPriceGuide ? `<td style="border:1px solid #ddd; padding:8px;">${renderDispensingGuidePreview(row.dispensing_price_guide || [])}</td>` : ''}
                    <td style="border:1px solid #ddd; padding:8px;">
                        <button type="button" onclick="addSearchResultToSale(${row.product_id}, ${row.batch_id})">Use</button>
                    </td>
                </tr>
            `).join('');
        } catch (error) {
            resultsBody.innerHTML = `<tr><td colspan="${quickSearchColspan}" style="border:1px solid #ddd; padding:8px;">Search failed.</td></tr>`;
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
          renumberRows();
          handleSaleRequirements();
          reapplyAllRows();
          calculateTotals();

          const insuranceCoveredInput = document.getElementById('insurance_covered_amount');
          if (insuranceCoveredInput) {
              insuranceCoveredInput.addEventListener('input', updateInsuranceFinancialPreview);
          }

          const quickInput = document.getElementById('quick-search-input');
        if (quickInput) {
            quickInput.addEventListener('input', runQuickSearch);
        }

        showGuideForFirstSelectedProduct();

        const saleForm = document.querySelector('form');
        if (saleForm) {
            saleForm.addEventListener('submit', function (e) {
                const hasPricingError = Array.from(document.querySelectorAll('.sale-row')).some((row) => {
                    const pricingState = validateRowPricing(row);

                    return pricingState.belowPriceFloor || pricingState.belowPurchaseCost;
                });

                if (hasPricingError) {
                    e.preventDefault();
                    alert(`Cannot save ${isProformaDocument ? 'proforma invoice' : 'sale'}. Every row must stay at or above the allowed selling floor and never discount below batch purchase price.`);
                }
            });
        }
    });
</script>
</body>
</html>
