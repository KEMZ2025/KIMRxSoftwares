<div class="sr-picker" data-stock-product-picker data-url="{{ route('stock-requests.products') }}">
    <label for="{{ $pickerId }}">{{ $pickerLabel ?? 'Medicine' }}</label>
    <input id="{{ $pickerId }}" type="search" class="sr-input" placeholder="Type medicine name..." autocomplete="off"
        role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="{{ $pickerId }}-results"
        data-product-search value="{{ $pickerProduct?->name ?? '' }}" maxlength="100">
    <input type="hidden" name="product_id" data-product-id value="{{ $pickerProduct?->id ?? '' }}">
    <div id="{{ $pickerId }}-results" class="sr-product-results" role="listbox" aria-label="Matching medicines" hidden></div>
    <div class="sr-picker-status sr-muted" data-product-status role="status"></div>
</div>
