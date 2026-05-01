<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Correct Purchase Item - KIM Rx</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; display: flex; background: #f5f7fb; }
        .content { flex: 1; padding: 20px; }
        .topbar, .panel { background: white; border-radius: 12px; padding: 20px; }
        .topbar { margin-bottom: 20px; }
        .panel { margin-bottom: 20px; }
        .summary-grid, .form-grid {
            display: grid;
            gap: 16px;
        }
        .summary-grid { grid-template-columns: repeat(4, minmax(160px, 1fr)); }
        .form-grid { grid-template-columns: repeat(2, minmax(220px, 1fr)); }
        .summary-box {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 14px;
        }
        .summary-box h4 {
            margin: 0 0 8px;
            font-size: 13px;
            color: #666;
        }
        .summary-box p {
            margin: 0;
            font-weight: bold;
        }
        .form-group { display: flex; flex-direction: column; }
        .form-group label { margin-bottom: 8px; font-weight: bold; }
        .form-group input, .form-group select, .form-group textarea {
            padding: 10px;
            border: 1px solid #d0d7de;
            border-radius: 8px;
        }
        .full { grid-column: 1 / -1; }
        .readonly-value {
            min-height: 42px;
            display: flex;
            align-items: center;
            padding: 10px 12px;
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
        }
        .alert-danger, .alert-info, .alert-warning {
            border-radius: 10px;
            padding: 12px;
            margin-bottom: 16px;
        }
        .alert-danger { background: #fdecea; color: #b42318; }
        .alert-info { background: #eef4ff; color: #1d4ed8; }
        .alert-warning { background: #fff7e6; color: #9a6700; }
        .btn {
            padding: 10px 14px;
            border: none;
            border-radius: 8px;
            color: white;
            text-decoration: none;
            cursor: pointer;
            display: inline-block;
        }
        .btn-save { background: #1f7a4f; }
        .btn-back { background: #3949ab; }
        .muted { color: #666; font-size: 13px; }        .price-guide {
            margin-top: 8px;
            color: #374151;
            font-size: 13px;
        }
        .price-guide strong { color: #111827; }
        .price-warning-inline {
            display: none;
            margin-top: 8px;
            color: #b42318;
            font-size: 13px;
            font-weight: bold;
        }
        .price-warning-inline.visible { display: block; }

        @media (max-width: 900px) {
            body { flex-direction: column; }
            .summary-grid, .form-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    @include('layouts.sidebar')

    <div class="content" id="mainContent">
        <div class="topbar">
            <h3>Correct Purchase Item</h3>
            <p>Invoice: {{ $purchase->invoice_number }}</p>
        </div>

        <div class="panel">
            @if ($errors->any())
                <div class="alert-danger">
                    <ul style="margin:0; padding-left:18px;">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if($correctionSummary['sale_item_count'] > 0)
                <div class="alert-warning">
                    This correction keeps existing sale receipts on the originally sold drug. Any affected sale lines will be moved to another valid batch of that same sold drug before this purchase item is corrected. If no valid batch has enough stock, the correction will be blocked.
                </div>
            @else
                <div class="alert-info">
                    No sales are tied to this purchase item yet, so this correction only updates the purchase item, linked stock batches, and the audit log.
                </div>
            @endif

            <div class="summary-grid" style="margin-bottom:20px;">
                <div class="summary-box">
                    <h4>Linked Batches</h4>
                    <p>{{ $correctionSummary['batch_count'] }}</p>
                </div>
                <div class="summary-box">
                    <h4>Affected Sale Lines</h4>
                    <p>{{ $correctionSummary['sale_item_count'] }}</p>
                </div>
                <div class="summary-box">
                    <h4>Affected Sales</h4>
                    <p>{{ $correctionSummary['sale_count'] }}</p>
                </div>
                <div class="summary-box">
                    <h4>Other Valid Batches</h4>
                    <p>{{ $correctionSummary['alternative_batch_count'] }}</p>
                </div>
                <div class="summary-box">
                    <h4>Free Stock In Other Batches</h4>
                    <p>{{ number_format((float) $correctionSummary['alternative_free_stock'], 2) }}</p>
                </div>
                <div class="summary-box">
                    <h4>Sold Qty</h4>
                    <p>{{ number_format((float) $correctionSummary['sold_quantity'], 2) }}</p>
                </div>
                <div class="summary-box">
                    <h4>Received Qty</h4>
                    <p>{{ number_format((float) $correctionSummary['received_quantity'], 2) }}</p>
                </div>
                <div class="summary-box">
                    <h4>Available Qty</h4>
                    <p>{{ number_format((float) $correctionSummary['available_quantity'], 2) }}</p>
                </div>
                <div class="summary-box">
                    <h4>Reserved Qty</h4>
                    <p>{{ number_format((float) $correctionSummary['reserved_quantity'], 2) }}</p>
                </div>
                <div class="summary-box">
                    <h4>Current Product</h4>
                    <p>{{ $item->product?->name ?? 'N/A' }}</p>
                </div>
            </div>

            <form method="POST" action="{{ route('purchases.items.updateCorrection', [$purchase->id, $item->id]) }}" id="purchase-correction-form">
                @csrf
                @method('PUT')

                <div class="form-grid">
                    <div class="form-group">
                        <label>Ordered Quantity</label>
                        <div class="readonly-value">{{ number_format((float) $item->ordered_quantity, 2) }}</div>
                        <div class="muted" style="margin-top:8px;">Quantity stays locked in this correction flow.</div>
                    </div>

                    <div class="form-group">
                        <label>Received Quantity</label>
                        <div class="readonly-value">{{ number_format((float) $item->received_quantity, 2) }}</div>
                        <div class="muted" style="margin-top:8px;">Use stock adjustments for quantity mistakes after receiving.</div>
                    </div>

                    <div class="form-group">
                        <label for="product_id">Correct Product</label>
                        <select name="product_id" id="product_id" data-original-product-id="{{ $item->product_id }}" required>
                            @foreach($products as $product)
                                <option value="{{ $product->id }}" {{ old('product_id', $item->product_id) == $product->id ? 'selected' : '' }}>
                                    {{ $product->name }}
                                </option>
                            @endforeach
                        </select>
                        <div class="price-guide">Last Purchase Price: <strong id="last_purchase_price_guide">Loading...</strong></div>
                        <div class="muted" style="margin-top:8px;">When you change the drug, retail and wholesale prices refresh from the selected drug. Enter the actual unit cost for this corrected purchase line.</div>
                    </div>

                    <div class="form-group">
                        <label for="batch_number">Correct Batch Number</label>
                        <input type="text" name="batch_number" id="batch_number" value="{{ old('batch_number', $item->batch_number) }}" required>
                    </div>

                    <div class="form-group">
                        <label for="expiry_date">Expiry Date</label>
                        <input type="date" name="expiry_date" id="expiry_date" value="{{ old('expiry_date', optional($item->expiry_date)->format('Y-m-d')) }}">
                    </div>

                    <div class="form-group">
                        <label for="unit_cost">Unit Cost</label>
                        <input type="number" step="0.01" min="0.01" name="unit_cost" id="unit_cost" value="{{ old('unit_cost', $item->unit_cost) }}" required>
                    </div>

                    <div class="form-group">
                        <label for="retail_price">Retail Price</label>
                        <input type="number" step="0.01" min="0" name="retail_price" id="retail_price" value="{{ old('retail_price', $item->retail_price) }}" required>
                    </div>

                    <div class="form-group">
                        <label for="wholesale_price">Wholesale Price</label>
                        <input type="number" step="0.01" min="0" name="wholesale_price" id="wholesale_price" value="{{ old('wholesale_price', $item->wholesale_price) }}" required>
                    </div>

                    <div class="form-group full">
                        <div class="price-warning-inline" id="correction_price_warning"></div>
                    </div>
                    <div class="form-group full">
                        <label for="reason">Correction Reason</label>
                        <textarea name="reason" id="reason" rows="4" required>{{ old('reason') }}</textarea>
                        <div class="muted" style="margin-top:8px;">Example: Product was entered as syrup instead of tablets on receiving.</div>
                    </div>
                </div>

                <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:20px;">
                    <button type="submit" class="btn btn-save">Save Correction</button>
                    <a href="{{ route('purchases.show', $purchase->id) }}" class="btn btn-back">Back to Purchase</a>
                </div>
            </form>
        </div>
    </div>
    <script>
        const correctionProductDataUrlTemplate = "{{ route('products.purchase-data', ['product' => '__PRODUCT_ID__']) }}";
        const productSelect = document.getElementById('product_id');
        const correctionForm = document.getElementById('purchase-correction-form');
        const unitCostInput = document.getElementById('unit_cost');
        const retailPriceInput = document.getElementById('retail_price');
        const wholesalePriceInput = document.getElementById('wholesale_price');
        const lastPurchaseGuide = document.getElementById('last_purchase_price_guide');
        const priceWarning = document.getElementById('correction_price_warning');

        function correctionMoney(value) {
            return (parseFloat(value) || 0).toFixed(2);
        }

        function setCorrectionWarning(message) {
            if (!priceWarning) return;
            priceWarning.textContent = message || '';
            priceWarning.classList.toggle('visible', !!message);
        }

        function validateCorrectionPrices() {
            const unitCost = parseFloat(unitCostInput?.value) || 0;
            const retailPrice = parseFloat(retailPriceInput?.value) || 0;
            const wholesalePrice = parseFloat(wholesalePriceInput?.value) || 0;

            if (unitCost <= 0) {
                setCorrectionWarning('');
                return true;
            }

            const warnings = [];

            if (wholesalePrice < unitCost) {
                warnings.push('Wholesale price is below the unit cost.');
            }

            if (retailPrice < unitCost) {
                warnings.push('Retail price is below the unit cost.');
            }

            if (warnings.length > 0) {
                setCorrectionWarning(warnings.join(' ') + ' Increase the selling price before saving so the medicine is not sold at a loss.');
                return false;
            }

            setCorrectionWarning('');
            return true;
        }

        async function loadCorrectionProductPrices(applySelectedPrices = false) {
            const productId = productSelect?.value;

            if (!productId) {
                if (lastPurchaseGuide) lastPurchaseGuide.textContent = '0.00';
                return;
            }

            try {
                const url = correctionProductDataUrlTemplate.replace('__PRODUCT_ID__', productId);
                const response = await fetch(url, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                const data = await response.json();

                if (!response.ok) {
                    throw data;
                }

                if (lastPurchaseGuide) {
                    lastPurchaseGuide.textContent = correctionMoney(data.last_purchase_price);
                }

                if (applySelectedPrices) {
                    if (retailPriceInput) retailPriceInput.value = correctionMoney(data.retail_price);
                    if (wholesalePriceInput) wholesalePriceInput.value = correctionMoney(data.wholesale_price);
                    if (unitCostInput) {
                        unitCostInput.value = '';
                        unitCostInput.focus();
                    }
                }

                validateCorrectionPrices();
            } catch (error) {
                if (lastPurchaseGuide) {
                    lastPurchaseGuide.textContent = 'Not loaded';
                }
                setCorrectionWarning('The selected drug price guide could not load. You can still save, but confirm the unit cost, retail price, and wholesale price carefully.');
            }
        }

        productSelect?.addEventListener('change', () => loadCorrectionProductPrices(true));
        unitCostInput?.addEventListener('input', validateCorrectionPrices);
        retailPriceInput?.addEventListener('input', validateCorrectionPrices);
        wholesalePriceInput?.addEventListener('input', validateCorrectionPrices);

        correctionForm?.addEventListener('submit', function (event) {
            if (!validateCorrectionPrices()) {
                event.preventDefault();
                priceWarning?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });

        document.addEventListener('DOMContentLoaded', () => loadCorrectionProductPrices(false));
    </script>
</body>
</html>
