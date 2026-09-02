@if(\App\Support\StockRequestBook::canRecord(auth()->user()))
    <div class="sr-sale-heading">
        <h3>Sale Items</h3>
        <button type="button" class="sr-button sr-request-launch" data-stock-request-open>
            <img src="{{ asset('vendor/lucide-stock-requests/notebook-pen.svg') }}" width="18" height="18" alt=""> Record Request
        </button>
    </div>
@else
    <h3>Sale Items</h3>
@endif
