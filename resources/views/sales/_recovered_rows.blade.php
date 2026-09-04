@foreach($recoveredSaleRows as $recovered)
    @php
        $batch = $recovered['batch'];
        $selectedProduct = $recovered['product'];
        $expiry = $batch?->expiry_date?->format('Y-m-d') ?? 'N/A';
    @endphp
    <tr class="sale-row" data-recovered-row="true">
        <td class="line-no">{{ $loop->iteration }}</td>
        <td>
            <select name="product_id[]" class="mini-select product-select" onchange="loadBatches(this)" required>
                <option value="">Select Product</option>
                @if($selectedProduct && !$products->contains('id', $selectedProduct->id))
                    <option value="{{ $selectedProduct->id }}" selected>{{ $selectedProduct->name }}</option>
                @endif
                @foreach($products as $product)
                    <option value="{{ $product->id }}" data-dispensing-guide="{{ e(json_encode($product->normalizedDispensingPriceGuide())) }}" @selected($selectedProduct?->id === $product->id)>{{ $product->name }}</option>
                @endforeach
            </select>
        </td>
        <td>
            <select name="product_batch_id[]" class="mini-select batch-select" onchange="applyBatchSelection(this)" required>
                <option value="">Select Batch</option>
                @if($batch)
                    <option value="{{ $batch->id }}" selected data-expiry="{{ $expiry }}"
                        data-available="{{ $batch->quantity_available }}" data-reserved="{{ $batch->reserved_quantity }}"
                        data-free-stock="{{ $recovered['free_stock'] }}" data-purchase-price="{{ $batch->purchase_price }}"
                        data-retail-price="{{ $recovered['retail_price'] }}" data-wholesale-price="{{ $recovered['wholesale_price'] }}">{{ $batch->batch_number }}</option>
                @endif
            </select>
        </td>
        <td><div class="info-box expiry-box">{{ $expiry }}</div></td>
        <td><div class="info-box available-box">{{ number_format((float) $batch?->quantity_available, 2, '.', '') }}</div></td>
        <td><div class="info-box reserved-box">{{ number_format((float) $batch?->reserved_quantity, 2, '.', '') }}</div></td>
        <td><div class="info-box free-stock-box">{{ number_format($recovered['free_stock'], 2, '.', '') }}</div></td>
        <td><div class="info-box purchase-price-box">{{ number_format((float) $batch?->purchase_price, 2, '.', '') }}</div></td>
        <td><input type="number" step="0.01" name="unit_price[]" class="mini-input unit-price" value="{{ $recovered['unit_price'] }}" oninput="calculateTotals()" required></td>
        <td><input type="number" step="0.01" name="quantity[]" class="mini-input quantity" value="{{ $recovered['quantity'] }}" oninput="calculateTotals()" required></td>
        <td><input type="number" step="0.0001" name="discount_amount[]" class="mini-input discount-amount" value="{{ $recovered['discount_amount'] }}" oninput="calculateTotals()" {{ !$canManageDiscounts ? 'readonly' : '' }}></td>
        <td><input type="number" step="0.01" class="mini-input line-total" value="{{ max(0, (float) $recovered['quantity'] * ((float) $recovered['unit_price'] - (float) $recovered['discount_amount'])) }}" readonly></td>
        <td><button type="button" class="btn btn-delete" onclick="removeRow(this)">Remove</button></td>
    </tr>
@endforeach
