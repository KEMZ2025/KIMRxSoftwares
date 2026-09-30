@extends('lpos.layout')

@php
    $editing = $order !== null;
    $savedLines = $editing ? $order->items->map(fn ($item) => [
        'product_id' => $item->product_id,
        'description' => $item->description,
        'unit_name' => $item->unit_name,
        'quantity' => $item->quantity,
        'unit_price' => $item->unit_price,
    ])->all() : [['product_id' => '', 'description' => '', 'unit_name' => '', 'quantity' => 1, 'unit_price' => 0]];
    $lines = old('items', $savedLines);
    $selectedSupplierId = old('supplier_id', $order?->supplier_id);
    $selectedSupplier = $suppliers->firstWhere('id', $selectedSupplierId);
@endphp

@section('page-title', $editing ? 'Edit ' . $order->order_number : 'New Local Purchase Order')

@section('top-actions')
    <a class="btn" href="{{ $editing ? route('lpos.show', $order) : route('lpos.index') }}">Back to LPOs</a>
@endsection

@section('content')
<style>
    .lpo-supplier-search { position: relative; }
    .lpo-supplier-results { position: absolute; z-index: 20; top: 100%; left: 0; right: 0; max-height: 240px; overflow-y: auto; background: #fff; border: 1px solid #a9bdc9; border-radius: 5px; box-shadow: 0 8px 22px rgba(22, 38, 56, .14); }
    .lpo-supplier-results[hidden] { display: none; }
    .lpo-supplier-option { display: block; width: 100%; padding: 9px 11px; border: 0; border-bottom: 1px solid #e4ebef; border-radius: 0; background: #fff; color: #182d3e; font: inherit; font-size: 13px; text-align: left; cursor: pointer; }
    .lpo-supplier-option:last-child { border-bottom: 0; }
    .lpo-supplier-option:hover, .lpo-supplier-option.active { background: #e8f5ee; }
    .lpo-supplier-empty { padding: 9px 11px; color: #607184; font-size: 13px; }
</style>
<form id="lpo-form" method="POST" action="{{ $editing ? route('lpos.update', $order) : route('lpos.store') }}">
    @csrf
    @if($editing)
        @method('PUT')
        <input type="hidden" name="version" value="{{ old('version', $order->version) }}">
    @else
        <input type="hidden" name="submission_token" value="{{ old('submission_token', $submissionToken) }}">
    @endif

    <section class="panel">
        <h2>Order Details</h2>
        <div class="form-grid">
            <div class="field lpo-supplier-search">
                <label for="supplier_search">Supplier *</label>
                <input id="supplier_search" type="text" value="{{ $selectedSupplier?->name }}" placeholder="Type supplier name" autocomplete="off" role="combobox" aria-autocomplete="list" aria-controls="supplier_results" aria-expanded="false" required>
                <select name="supplier_id" id="supplier_id" hidden tabindex="-1" aria-hidden="true">
                    <option value="">Select supplier</option>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" @selected((string) $selectedSupplierId === (string) $supplier->id)>{{ $supplier->name }}</option>
                    @endforeach
                </select>
                <div id="supplier_results" class="lpo-supplier-results" role="listbox" hidden></div>
            </div>
            <div class="field"><label for="order_date">Order Date *</label><input id="order_date" type="date" name="order_date" value="{{ old('order_date', $order?->order_date?->format('Y-m-d') ?? now()->toDateString()) }}" required></div>
            <div class="field"><label for="expected_delivery_date">Expected Delivery</label><input id="expected_delivery_date" type="date" name="expected_delivery_date" value="{{ old('expected_delivery_date', $order?->expected_delivery_date?->format('Y-m-d')) }}"></div>
            <div class="field wide"><label for="delivery_address">Deliver To</label><input id="delivery_address" name="delivery_address" maxlength="1000" value="{{ old('delivery_address', $order?->delivery_address ?? $user->branch?->address) }}"></div>
        </div>
    </section>

    <section class="panel">
        <div class="page-head" style="margin-bottom:14px"><h2 style="margin:0">Items</h2><button type="button" class="btn" id="add-line">Add Item</button></div>
        <div class="table-wrap">
            <table class="line-table">
                <thead><tr><th style="width:30%">Product</th><th style="width:27%">Item Description</th><th>Unit</th><th class="numeric">Qty *</th><th class="numeric">Unit Cost *</th><th class="numeric">Total</th><th>Action</th></tr></thead>
                <tbody id="lpo-lines">
                    @foreach($lines as $line)
                        <tr class="line-row">
                            <td><select class="product-select" name="items[{{ $loop->index }}][product_id]" aria-label="Product">
                                <option value="">Custom item</option>
                                @foreach($products as $product)
                                    <option value="{{ $product->id }}" data-name="{{ $product->name }}" data-unit="{{ $product->unit?->name }}" data-price="{{ $product->purchase_price }}" @selected((string) ($line['product_id'] ?? '') === (string) $product->id)>{{ $product->name }}</option>
                                @endforeach
                            </select></td>
                            <td><input class="description-input" name="items[{{ $loop->index }}][description]" maxlength="255" placeholder="Medicine or supply" value="{{ $line['description'] ?? '' }}" aria-label="Item description"></td>
                            <td><input class="unit-input" name="items[{{ $loop->index }}][unit_name]" maxlength="80" placeholder="Unit" value="{{ $line['unit_name'] ?? '' }}" aria-label="Unit"></td>
                            <td><input class="quantity-input numeric" type="number" name="items[{{ $loop->index }}][quantity]" min="0.01" max="99999.99" step="0.01" value="{{ $line['quantity'] ?? 1 }}" required aria-label="Quantity"></td>
                            <td><input class="price-input numeric" type="number" name="items[{{ $loop->index }}][unit_price]" min="0" max="999999.99" step="0.01" value="{{ $line['unit_price'] ?? 0 }}" required aria-label="Unit cost"></td>
                            <td class="numeric line-total">0.00</td>
                            <td><button class="btn btn-danger remove-line" type="button" title="Remove item" aria-label="Remove item">Remove</button></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="form-grid" style="margin-top:16px">
            <div class="field"><label for="discount_amount">Discount (UGX)</label><input id="discount_amount" name="discount_amount" type="number" min="0" step="0.01" value="{{ old('discount_amount', $order?->discount_amount ?? 0) }}"></div>
            <div class="field"><label for="tax_amount">Tax (UGX)</label><input id="tax_amount" name="tax_amount" type="number" min="0" step="0.01" value="{{ old('tax_amount', $order?->tax_amount ?? 0) }}"></div>
            <div class="summary" aria-live="polite"><div><span>Subtotal</span><span id="subtotal">0.00</span></div><div class="grand"><span>Total (UGX)</span><span id="grand-total">0.00</span></div></div>
            <div class="field wide"><label for="notes">Notes / Terms</label><textarea id="notes" name="notes" maxlength="2000">{{ old('notes', $order?->notes) }}</textarea></div>
        </div>
    </section>

    <div class="form-actions">
        <a class="btn" href="{{ $editing ? route('lpos.show', $order) : route('lpos.index') }}">Cancel</a>
        <button class="btn btn-primary" type="submit">{{ $editing ? 'Save Changes' : 'Save Draft LPO' }}</button>
    </div>
</form>

<template id="lpo-line-template">
    <tr class="line-row">
        <td><select class="product-select" name="items[0][product_id]" aria-label="Product"><option value="">Custom item</option>@foreach($products as $product)<option value="{{ $product->id }}" data-name="{{ $product->name }}" data-unit="{{ $product->unit?->name }}" data-price="{{ $product->purchase_price }}">{{ $product->name }}</option>@endforeach</select></td>
        <td><input class="description-input" name="items[0][description]" maxlength="255" placeholder="Medicine or supply" aria-label="Item description"></td>
        <td><input class="unit-input" name="items[0][unit_name]" maxlength="80" placeholder="Unit" aria-label="Unit"></td>
        <td><input class="quantity-input numeric" type="number" name="items[0][quantity]" min="0.01" max="99999.99" step="0.01" value="1" required aria-label="Quantity"></td>
        <td><input class="price-input numeric" type="number" name="items[0][unit_price]" min="0" max="999999.99" step="0.01" value="0" required aria-label="Unit cost"></td>
        <td class="numeric line-total">0.00</td>
        <td><button class="btn btn-danger remove-line" type="button" title="Remove item" aria-label="Remove item">Remove</button></td>
    </tr>
</template>

<script>
(() => {
    const form = document.getElementById('lpo-form');
    const lines = document.getElementById('lpo-lines');
    const supplierSearch = document.getElementById('supplier_search');
    const supplierSelect = document.getElementById('supplier_id');
    const supplierResults = document.getElementById('supplier_results');
    const suppliers = Array.from(supplierSelect.options).filter(option => option.value);
    let activeSupplier = -1;
    const closeSuppliers = () => {
        supplierResults.hidden = true;
        supplierSearch.setAttribute('aria-expanded', 'false');
        supplierSearch.removeAttribute('aria-activedescendant');
        activeSupplier = -1;
    };
    const validateSupplier = () => supplierSearch.setCustomValidity(
        supplierSelect.value ? '' : 'Choose a supplier from the matching results.'
    );
    const chooseSupplier = option => {
        supplierSelect.value = option.value;
        supplierSearch.value = option.textContent.trim();
        validateSupplier();
        closeSuppliers();
    };
    const renderSuppliers = () => {
        const query = supplierSearch.value.trim().toLocaleLowerCase();
        supplierResults.replaceChildren();
        activeSupplier = -1;
        if (!query) {
            closeSuppliers();
            return;
        }
        const matches = suppliers.filter(option => option.textContent.toLocaleLowerCase().includes(query)).slice(0, 8);
        matches.forEach((option, index) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.id = `supplier_result_${index}`;
            button.className = 'lpo-supplier-option';
            button.setAttribute('role', 'option');
            button.textContent = option.textContent.trim();
            button.addEventListener('pointerdown', event => {
                event.preventDefault();
                chooseSupplier(option);
            });
            button.addEventListener('click', () => chooseSupplier(option));
            supplierResults.appendChild(button);
        });
        if (!matches.length) {
            const empty = document.createElement('div');
            empty.className = 'lpo-supplier-empty';
            empty.textContent = 'No matching supplier';
            supplierResults.appendChild(empty);
        }
        supplierResults.hidden = false;
        supplierSearch.setAttribute('aria-expanded', 'true');
    };
    supplierSearch.addEventListener('input', () => {
        const query = supplierSearch.value.trim().toLocaleLowerCase();
        const exact = suppliers.filter(option => option.textContent.trim().toLocaleLowerCase() === query);
        supplierSelect.value = exact.length === 1 ? exact[0].value : '';
        validateSupplier();
        renderSuppliers();
    });
    supplierSearch.addEventListener('focus', renderSuppliers);
    supplierSearch.addEventListener('blur', () => setTimeout(closeSuppliers, 100));
    supplierSearch.addEventListener('keydown', event => {
        const options = Array.from(supplierResults.querySelectorAll('.lpo-supplier-option'));
        if (event.key === 'Escape') {
            closeSuppliers();
            return;
        }
        if (!options.length || supplierResults.hidden) return;
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            activeSupplier = (activeSupplier + (event.key === 'ArrowDown' ? 1 : -1) + options.length) % options.length;
            options.forEach((option, index) => option.classList.toggle('active', index === activeSupplier));
            supplierSearch.setAttribute('aria-activedescendant', options[activeSupplier].id);
            options[activeSupplier].scrollIntoView({ block: 'nearest' });
        } else if (event.key === 'Enter' && activeSupplier >= 0) {
            event.preventDefault();
            options[activeSupplier].click();
        }
    });
    validateSupplier();
    const money = value => Number(value || 0).toLocaleString('en-UG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const renumber = () => lines.querySelectorAll('.line-row').forEach((row, index) => {
        row.querySelectorAll('[name]').forEach(input => input.name = input.name.replace(/items\[\d+\]/, `items[${index}]`));
    });
    const calculate = () => {
        let subtotal = 0;
        lines.querySelectorAll('.line-row').forEach(row => {
            const quantity = Number(row.querySelector('.quantity-input').value || 0);
            const price = Number(row.querySelector('.price-input').value || 0);
            const total = Math.round(quantity * price * 100) / 100;
            row.querySelector('.line-total').textContent = money(total);
            subtotal += total;
        });
        const discount = Number(document.getElementById('discount_amount').value || 0);
        const tax = Number(document.getElementById('tax_amount').value || 0);
        document.getElementById('subtotal').textContent = money(subtotal);
        document.getElementById('grand-total').textContent = money(Math.max(0, subtotal - discount + tax));
    };

    document.getElementById('add-line').addEventListener('click', () => {
        if (lines.querySelectorAll('.line-row').length >= 100) return;
        lines.appendChild(document.getElementById('lpo-line-template').content.cloneNode(true));
        renumber();
        calculate();
        lines.lastElementChild.querySelector('.product-select').focus();
    });
    lines.addEventListener('change', event => {
        if (!event.target.classList.contains('product-select')) return;
        const row = event.target.closest('.line-row');
        const option = event.target.selectedOptions[0];
        if (!option?.value) return;
        row.querySelector('.description-input').value = option.dataset.name || '';
        row.querySelector('.unit-input').value = option.dataset.unit || '';
        if (Number(row.querySelector('.price-input').value || 0) === 0) {
            row.querySelector('.price-input').value = option.dataset.price || 0;
        }
        calculate();
    });
    lines.addEventListener('click', event => {
        if (!event.target.classList.contains('remove-line')) return;
        if (lines.querySelectorAll('.line-row').length === 1) return;
        event.target.closest('.line-row').remove();
        renumber();
        calculate();
    });
    form.addEventListener('input', calculate);
    form.addEventListener('submit', () => {
        form.querySelector('button[type=submit]').disabled = true;
    });
    calculate();
})();
</script>
@endsection
