<div class="unit-price-controls">
    <input type="number" step="0.01" name="unit_price[]" class="mini-input unit-price" value="{{ $unitPriceValue ?? 0 }}" oninput="calculateTotals()" required>
    @unless($isProforma ?? false)
        <button type="button" class="customer-price-history-trigger" onclick="openCustomerPriceHistory(this)" title="Customer's previous price" aria-label="View this customer's previous price" hidden>
            <img src="{{ asset('vendor/lucide-stock-requests/eye.svg') }}" alt="" aria-hidden="true">
        </button>
    @endunless
</div>
