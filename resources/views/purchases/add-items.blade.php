<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Items to Invoice - KIM Rx</title>
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

        .alert-danger { background: #fdecea; color: #b42318; padding: 12px; border-radius: 8px; margin-bottom: 15px; }
        .items-table-wrap {
            overflow-x: auto;
            margin-top: 10px;
            max-width: 100%;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            background: #fff;
        }
        table { width: 100%; border-collapse: collapse; min-width: 1100px; }
        table th, table td { border: 1px solid #ddd; padding: 6px; text-align: left; vertical-align: top; }
        table th { background: #f8f8f8; font-size: 11.5px; white-space: nowrap; }

        .mini-input,.mini-select { width: 100%; padding: 6px 7px; border: 1px solid #ccc; border-radius: 6px; font-size: 12.5px; }
        .stock-box { background: #f1f3f6; border-radius: 6px; padding: 6px 5px; min-width: 60px; text-align: center; font-size: 11.5px; line-height: 1.25; }
        .price-note { font-size: 12px; color: #666; margin-top: 4px; }
        .price-warning { font-size: 12px; color: #b42318; margin-top: 6px; display: none; }
        .expiry-warning { font-size: 12px; color: #9a6700; margin-top: 6px; display: none; }
        .expiry-warning.expiry-error { color: #b42318; }
        .product-tools { display: flex; flex-direction: column; gap: 4px; margin-top: 6px; }
        .product-edit-link { color: #3949ab; text-decoration: none; font-size: 12px; }
        .product-edit-link:hover { text-decoration: underline; }
        .locked-price { background: #f7f8fb; color: #555; }
        .row-has-price-conflict td { background: #fff8f6; }
        .row-has-expiry-error td { background: #fff7f5; }
        .input-error { border-color: #b42318 !important; box-shadow: 0 0 0 1px rgba(180,35,24,0.08); }
        .alert-info { background: #eef4ff; color: #1d4ed8; padding: 12px; border-radius: 8px; margin-bottom: 15px; }
        .alert-warning { background: #fff4db; color: #9a6700; padding: 12px; border-radius: 8px; margin-bottom: 15px; }

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
        .btn-back { background: #3949ab; }

        .btn-row { margin-top: 12px; display: flex; gap: 10px; flex-wrap: wrap; }
        .total-box { margin-top: 16px; font-size: 16px; font-weight: bold; line-height: 1.8; }
        .muted { color: #666; font-size: 13px; }

        .purchase-items-table .col-product { width: 156px; }
        .purchase-items-table .col-batch { width: 90px; }
        .purchase-items-table .col-expiry { width: 90px; }
        .purchase-items-table .col-stock { width: 64px; }
        .purchase-items-table .col-price { width: 88px; }
        .purchase-items-table .col-qty { width: 74px; }
        .purchase-items-table .col-total { width: 96px; }
        .purchase-items-table .col-action { width: 68px; }

        .purchase-items-table .btn-delete {
            padding: 6px 7px;
            font-size: 11.5px;
            white-space: nowrap;
        }

        @media (max-width: 900px) {
            body { flex-direction: column; }
        }
    </style>
</head>
<body>
        @include('layouts.sidebar')

    <div class="content" id="mainContent">
        <div class="topbar">
            <h3>Add Items to Invoice</h3>
            <p>Invoice: {{ $purchase->invoice_number }}</p>
        </div>

        <div class="panel">
            <h2 style="margin-top:0;">Add More Items</h2>
            <p class="muted">Attach extra products to this same supplier invoice.</p>

            <div class="alert-info">
                If a product tracks expiry, an expiry date is required. If a new unit cost is above the current wholesale or retail price, the row will be highlighted and you will need to correct the selling prices before the invoice can be updated.
            </div>

            @if ($errors->any())
                <div class="alert-danger">
                    <ul style="margin:0; padding-left:18px;">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('purchases.storeAddedItems', $purchase->id) }}">
                @csrf

                <div class="items-table-wrap">
                    <table class="purchase-items-table">
                        <colgroup>
                            <col class="col-product">
                            <col class="col-batch">
                            <col class="col-expiry">
                            <col class="col-stock">
                            <col class="col-stock">
                            <col class="col-price">
                            <col class="col-price">
                            <col class="col-qty">
                            <col class="col-qty">
                            <col class="col-stock">
                            <col class="col-price">
                            <col class="col-total">
                            <col class="col-action">
                        </colgroup>
                        <thead>
                            <tr>
                                <th>Product *</th>
                                <th>Batch No *</th>
                                <th>Expiry Date</th>
                                <th>Old Stock</th>
                                <th>Last Purchase</th>
                                <th>Retail *</th>
                                <th>Wholesale *</th>
                                <th>Ordered Qty *</th>
                                <th>Received Now *</th>
                                <th>Remaining</th>
                                <th>Unit Cost *</th>
                                <th>Line Total</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="purchase-items-body">
                            <tr class="purchase-row">
                                <td>
                                    <select name="product_id[]" class="mini-select product-select" onchange="fillProductData(this)" required>
                                        <option value="">Select Product</option>
                                        @foreach($products as $product)
                                            <option value="{{ $product->id }}">{{ $product->name }}</option>
                                        @endforeach
                                    </select>
                                    <div class="product-tools">
                                        <a href="#" class="product-edit-link" target="_blank" rel="noopener" style="display:none;">Open product prices in new tab</a>
                                    </div>
                                </td>
                                <td><input type="text" name="batch_number[]" class="mini-input" required></td>
                                <td>
                                    <input type="date" name="expiry_date[]" class="mini-input expiry-date" onchange="calculateTotals()">
                                    <div class="expiry-warning"></div>
                                </td>
                                <td><div class="stock-box old-stock">0.00</div></td>
                                <td><div class="stock-box last-purchase-price">0.00</div></td>
                                <td>
                                    <input type="number" step="0.01" name="retail_price[]" class="mini-input retail-price locked-price" value="0" oninput="calculateTotals()" readonly required>
                                </td>
                                <td>
                                    <input type="number" step="0.01" name="wholesale_price[]" class="mini-input wholesale-price locked-price" value="0" oninput="calculateTotals()" readonly required>
                                </td>
                                <td><input type="number" step="0.01" name="ordered_quantity[]" class="mini-input ordered-quantity" value="0" oninput="calculateTotals()" required></td>
                                <td><input type="number" step="0.01" name="received_now_quantity[]" class="mini-input received-now-quantity" value="0" oninput="calculateTotals()" required></td>
                                <td><div class="stock-box remaining-quantity">0.00</div></td>
                                <td>
                                    <input type="number" step="0.01" name="unit_cost[]" class="mini-input unit-cost" value="" oninput="updateFromUnitCost(this)" required>
                                    <div class="price-note">Enter new cost</div>
                                    <div class="price-warning"></div>
                                </td>
                                <td><input type="number" step="0.01" min="0" class="mini-input line-total" value="0" oninput="updateFromLineTotal(this)"></td>
                                <td><button type="button" class="btn btn-delete" onclick="removeRow(this)">Delete</button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="btn-row">
                    <button type="button" class="btn btn-add" onclick="addLine()">Add 1 Line</button>
                    <button type="button" class="btn btn-add" onclick="addFiveLines()">Add 5 Lines</button>
                </div>

                <div class="total-box">
                    Extra Amount Being Added: <span id="grand-total-text">0.00</span>
                </div>

                <div id="pricing-guard" class="alert-warning" style="display:none;"></div>

                <div style="margin-top:18px; display:flex; gap:10px; flex-wrap:wrap;">
                    <button type="submit" class="btn btn-save" id="add-items-save-button">Add Items to Invoice</button>
                    <a href="{{ route('purchases.show', $purchase->id) }}" class="btn btn-back">Back to Details</a>
                </div>
            </form>
        </div>
    </div>

    <template id="purchase-row-template">
        <tr class="purchase-row">
            <td>
                <select name="product_id[]" class="mini-select product-select" onchange="fillProductData(this)" required>
                    <option value="">Select Product</option>
                    @foreach($products as $product)
                        <option value="{{ $product->id }}">{{ $product->name }}</option>
                    @endforeach
                </select>
                <div class="product-tools">
                    <a href="#" class="product-edit-link" target="_blank" rel="noopener" style="display:none;">Open product prices in new tab</a>
                </div>
            </td>
            <td><input type="text" name="batch_number[]" class="mini-input" required></td>
            <td>
                <input type="date" name="expiry_date[]" class="mini-input expiry-date" onchange="calculateTotals()">
                <div class="expiry-warning"></div>
            </td>
            <td><div class="stock-box old-stock">0.00</div></td>
            <td><div class="stock-box last-purchase-price">0.00</div></td>
            <td>
                <input type="number" step="0.01" name="retail_price[]" class="mini-input retail-price locked-price" value="0" oninput="calculateTotals()" readonly required>
            </td>
            <td>
                <input type="number" step="0.01" name="wholesale_price[]" class="mini-input wholesale-price locked-price" value="0" oninput="calculateTotals()" readonly required>
            </td>
            <td><input type="number" step="0.01" name="ordered_quantity[]" class="mini-input ordered-quantity" value="0" oninput="calculateTotals()" required></td>
            <td><input type="number" step="0.01" name="received_now_quantity[]" class="mini-input received-now-quantity" value="0" oninput="calculateTotals()" required></td>
            <td><div class="stock-box remaining-quantity">0.00</div></td>
            <td>
                <input type="number" step="0.01" name="unit_cost[]" class="mini-input unit-cost" value="" oninput="updateFromUnitCost(this)" required>
                <div class="price-note">Enter new cost</div>
                <div class="price-warning"></div>
            </td>
            <td><input type="number" step="0.01" min="0" class="mini-input line-total" value="0" oninput="updateFromLineTotal(this)"></td>
            <td><button type="button" class="btn btn-delete" onclick="removeRow(this)">Delete</button></td>
        </tr>
    </template>

    <script>
        function formatMoney(value) {
            return (parseFloat(value) || 0).toFixed(2);
        }

        function formatEntryValue(value) {
            const numericValue = parseFloat(value) || 0;
            return numericValue.toFixed(2)
                .replace(/\.00$/, '')
                .replace(/(\.\d*[1-9])0$/, '$1');
        }

        function getProductEditUrl(productId, data = null) {
            if (data && data.edit_url) {
                return data.edit_url;
            }

            return productId ? `/products/${productId}/edit` : '#';
        }

        function toggleProductEditLink(row, productId, data = null) {
            const link = row.querySelector('.product-edit-link');

            if (!link) {
                return;
            }

            if (productId) {
                link.href = getProductEditUrl(productId, data);
                link.style.display = 'inline';
            } else {
                link.href = '#';
                link.style.display = 'none';
            }
        }

        function setPriceInputEditable(input, editable) {
            if (!input) {
                return;
            }

            input.readOnly = !editable;
            input.classList.toggle('locked-price', !editable);
        }

        function syncSellingPriceLockState(row, options = {}) {
            const retailInput = row.querySelector('.retail-price');
            const wholesaleInput = row.querySelector('.wholesale-price');
            const unitCost = parseFloat(row.querySelector('.unit-cost').value) || 0;
            const productSelected = !!row.querySelector('.product-select').value;
            const retailPrice = parseFloat(retailInput.value) || 0;
            const wholesalePrice = parseFloat(wholesaleInput.value) || 0;

            if (!productSelected || unitCost <= 0) {
                setPriceInputEditable(retailInput, false);
                setPriceInputEditable(wholesaleInput, false);
                return;
            }

            const forceUnlockAll = options.forceUnlockAll === true;
            const retailNeedsEdit = forceUnlockAll || retailPrice + 0.0001 < unitCost;
            const wholesaleNeedsEdit = forceUnlockAll || wholesalePrice + 0.0001 < unitCost;

            setPriceInputEditable(retailInput, retailNeedsEdit);
            setPriceInputEditable(wholesaleInput, wholesaleNeedsEdit);
        }

        function todayDateString() {
            const today = new Date();
            const year = today.getFullYear();
            const month = String(today.getMonth() + 1).padStart(2, '0');
            const day = String(today.getDate()).padStart(2, '0');

            return `${year}-${month}-${day}`;
        }

        function parseDateAtMidnight(value) {
            return new Date(`${value}T00:00:00`);
        }

        function clearExpiryState(row) {
            const expiryInput = row.querySelector('.expiry-date');
            const warningBox = row.querySelector('.expiry-warning');

            row.classList.remove('row-has-expiry-error');
            expiryInput?.classList.remove('input-error');

            if (warningBox) {
                warningBox.style.display = 'none';
                warningBox.textContent = '';
                warningBox.classList.remove('expiry-error');
            }
        }

        function validateRowExpiry(row) {
            const expiryInput = row.querySelector('.expiry-date');
            const warningBox = row.querySelector('.expiry-warning');
            const trackExpiry = row.dataset.trackExpiry === '1';
            const alertDays = parseInt(row.dataset.expiryAlertDays || '0', 10) || 0;

            clearExpiryState(row);

            if (!expiryInput) {
                return { blocked: false, warning: false };
            }

            const today = todayDateString();
            expiryInput.min = today;

            if (!expiryInput.value) {
                if (trackExpiry) {
                    row.classList.add('row-has-expiry-error');
                    expiryInput.classList.add('input-error');
                    warningBox.classList.add('expiry-error');
                    warningBox.textContent = 'This product tracks expiry, so an expiry date is required before the invoice can be updated.';
                    warningBox.style.display = 'block';

                    return { blocked: true, warning: false };
                }

                return { blocked: false, warning: false };
            }

            const expiryDate = parseDateAtMidnight(expiryInput.value);
            const todayDate = parseDateAtMidnight(today);

            if (expiryDate < todayDate) {
                row.classList.add('row-has-expiry-error');
                expiryInput.classList.add('input-error');
                warningBox.classList.add('expiry-error');
                warningBox.textContent = 'This expiry date is already past. The invoice cannot be updated until it is corrected.';
                warningBox.style.display = 'block';

                return { blocked: true, warning: false };
            }

            if (trackExpiry && alertDays > 0) {
                const millisecondsPerDay = 1000 * 60 * 60 * 24;
                const daysUntilExpiry = Math.ceil((expiryDate - todayDate) / millisecondsPerDay);

                if (daysUntilExpiry <= alertDays) {
                    warningBox.textContent = `This batch expires in ${daysUntilExpiry} day(s), inside the product alert window of ${alertDays} day(s).`;
                    warningBox.style.display = 'block';

                    return { blocked: false, warning: true };
                }
            }

            return { blocked: false, warning: false };
        }

        function resetRow(row) {
            row.querySelector('.retail-price').value = '0.00';
            row.querySelector('.wholesale-price').value = '0.00';
            row.querySelector('.unit-cost').value = '';
            row.querySelector('.old-stock').textContent = '0.00';
            row.querySelector('.last-purchase-price').textContent = '0.00';
            row.querySelector('.remaining-quantity').textContent = '0.00';
            row.querySelector('.line-total').value = '0';
            row.querySelector('.price-warning').style.display = 'none';
            row.querySelector('.price-warning').textContent = '';
            row.classList.remove('row-has-price-conflict');
            row.querySelector('.retail-price').classList.remove('input-error');
            row.querySelector('.wholesale-price').classList.remove('input-error');
            row.dataset.trackExpiry = '0';
            row.dataset.expiryAlertDays = '0';
            toggleProductEditLink(row, null);
            clearExpiryState(row);
            syncSellingPriceLockState(row);
        }

        function addLine() {
            const template = document.getElementById('purchase-row-template');
            const clone = template.content.cloneNode(true);
            document.getElementById('purchase-items-body').appendChild(clone);
            calculateTotals();
        }

        function addFiveLines() {
            for (let i = 0; i < 5; i++) {
                addLine();
            }
        }

        function removeRow(button) {
            const tbody = document.getElementById('purchase-items-body');
            const rows = tbody.querySelectorAll('.purchase-row');
            if (rows.length > 1) {
                button.closest('.purchase-row').remove();
                calculateTotals();
            }
        }

        async function fillProductData(selectElement) {
            const productId = selectElement.value;
            const row = selectElement.closest('.purchase-row');

            if (!productId) {
                resetRow(row);
                calculateTotals();
                return;
            }

            const url = `/products/${productId}/purchase-data`;

            try {
                const response = await fetch(url);
                const data = await response.json();

                row.querySelector('.retail-price').value = Number(data.retail_price ?? 0).toFixed(2);
                row.querySelector('.wholesale-price').value = Number(data.wholesale_price ?? 0).toFixed(2);
                row.querySelector('.unit-cost').value = '';
                row.querySelector('.old-stock').textContent = Number(data.old_stock ?? 0).toFixed(2);
                row.querySelector('.last-purchase-price').textContent = Number(data.last_purchase_price ?? 0).toFixed(2);
                row.dataset.trackExpiry = data.track_expiry ? '1' : '0';
                row.dataset.expiryAlertDays = Number(data.expiry_alert_days ?? 0).toString();
                toggleProductEditLink(row, productId, data);
                syncSellingPriceLockState(row);

                calculateTotals();
            } catch (error) {
                console.error('Failed to load product purchase data', error);
            }
        }

        function updateFromUnitCost(input) {
            const row = input.closest('.purchase-row');
            calculateRow(row);
            calculateTotals();
        }

        function updateFromLineTotal(input) {
            const row = input.closest('.purchase-row');
            const orderedQty = parseFloat(row.querySelector('.ordered-quantity').value) || 0;
            const lineTotal = parseFloat(input.value) || 0;

            if (orderedQty > 0) {
                row.querySelector('.unit-cost').value = formatEntryValue(lineTotal / orderedQty);
            } else {
                row.querySelector('.unit-cost').value = '0';
            }

            calculateTotals();
        }

        function calculateRow(row) {
            const orderedQty = parseFloat(row.querySelector('.ordered-quantity').value) || 0;
            const cost = parseFloat(row.querySelector('.unit-cost').value) || 0;
            const lineTotal = orderedQty * cost;

            row.querySelector('.line-total').value = formatEntryValue(lineTotal);
        }

        function validateRowPricing(row) {
            const productName = row.querySelector('.product-select option:checked')?.text?.trim() || 'Selected product';
            const unitCost = parseFloat(row.querySelector('.unit-cost').value) || 0;
            const retailPrice = parseFloat(row.querySelector('.retail-price').value) || 0;
            const wholesalePrice = parseFloat(row.querySelector('.wholesale-price').value) || 0;
            const retailInput = row.querySelector('.retail-price');
            const wholesaleInput = row.querySelector('.wholesale-price');
            const warningBox = row.querySelector('.price-warning');

            retailInput.classList.remove('input-error');
            wholesaleInput.classList.remove('input-error');
            row.classList.remove('row-has-price-conflict');
            warningBox.style.display = 'none';
            warningBox.textContent = '';

            if (unitCost <= 0 || !row.querySelector('.product-select').value) {
                return [];
            }

            const warnings = [];

            if (wholesalePrice + 0.0001 < unitCost) {
                warnings.push(`${productName}: wholesale price is below the current unit cost.`);
                wholesaleInput.classList.add('input-error');
            }

            if (retailPrice + 0.0001 < unitCost) {
                warnings.push(`${productName}: retail price is below the current unit cost.`);
                retailInput.classList.add('input-error');
            }

            if (warnings.length > 0) {
                row.classList.add('row-has-price-conflict');
                warningBox.style.display = 'block';
                warningBox.textContent = `${warnings.join(' ')} Raise the highlighted selling price field(s) to at least ${formatMoney(unitCost)} before updating this invoice.`;
            }

            syncSellingPriceLockState(row);

            return warnings;
        }

        function updatePricingGuard(conflictCount, expiryBlockedCount, expiryWarningCount) {
            const guard = document.getElementById('pricing-guard');
            const saveButton = document.getElementById('add-items-save-button');

            if (expiryBlockedCount > 0) {
                guard.style.display = 'block';
                guard.textContent = `${expiryBlockedCount} added row(s) have expiry dates that are already past. Fix those expiry dates before updating this invoice.`;
                saveButton.disabled = true;
                saveButton.style.opacity = '0.65';
                saveButton.style.cursor = 'not-allowed';
                return;
            }

            if (conflictCount > 0) {
                guard.style.display = 'block';
                guard.textContent = `${conflictCount} added row(s) still have selling prices below the new cost. Fix the highlighted wholesale or retail price fields before saving.`;
                saveButton.disabled = true;
                saveButton.style.opacity = '0.65';
                saveButton.style.cursor = 'not-allowed';
                return;
            }

            if (expiryWarningCount > 0) {
                guard.style.display = 'block';
                guard.textContent = `${expiryWarningCount} added row(s) are inside the product expiry alert window. You can still save after reviewing them.`;
                saveButton.disabled = false;
                saveButton.style.opacity = '1';
                saveButton.style.cursor = 'pointer';
                return;
            }

            guard.style.display = 'none';
            guard.textContent = '';
            saveButton.disabled = false;
            saveButton.style.opacity = '1';
            saveButton.style.cursor = 'pointer';
        }

        function calculateTotals() {
            const rows = document.querySelectorAll('.purchase-row');
            let grandTotal = 0;
            let priceConflictCount = 0;
            let expiryBlockedCount = 0;
            let expiryWarningCount = 0;

            rows.forEach(row => {
                const orderedQty = parseFloat(row.querySelector('.ordered-quantity').value) || 0;
                let receivedNowQty = parseFloat(row.querySelector('.received-now-quantity').value) || 0;
                const cost = parseFloat(row.querySelector('.unit-cost').value) || 0;

                if (receivedNowQty > orderedQty) {
                    receivedNowQty = orderedQty;
                    row.querySelector('.received-now-quantity').value = orderedQty.toFixed(2).replace(/\.00$/, '');
                }

                const remainingQty = Math.max(0, orderedQty - receivedNowQty);
                const lineTotal = orderedQty * cost;

                row.querySelector('.remaining-quantity').textContent = remainingQty.toFixed(2);
                row.querySelector('.line-total').value = formatEntryValue(lineTotal);
                grandTotal += lineTotal;

                if (validateRowPricing(row).length > 0) {
                    priceConflictCount++;
                }

                const expiryState = validateRowExpiry(row);
                if (expiryState.blocked) {
                    expiryBlockedCount++;
                } else if (expiryState.warning) {
                    expiryWarningCount++;
                }
            });

            document.getElementById('grand-total-text').textContent = grandTotal.toFixed(2);
            updatePricingGuard(priceConflictCount, expiryBlockedCount, expiryWarningCount);
            return priceConflictCount === 0 && expiryBlockedCount === 0;
        }

        document.querySelector('form').addEventListener('submit', function (event) {
            if (!calculateTotals()) {
                event.preventDefault();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        });

        calculateTotals();
    </script>
</body>
</html>   
