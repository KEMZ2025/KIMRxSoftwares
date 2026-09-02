@if(\App\Support\StockRequestBook::canRecord(auth()->user()))
    @include('stock_requests._assets')
    <div class="sr-toast" id="stock-request-toast" role="status" hidden></div>
    <dialog class="sr-dialog" id="stock-request-dialog" aria-labelledby="stock-request-title">
        <header class="sr-heading">
            <h2 id="stock-request-title">Record Request</h2>
            <button type="button" class="sr-icon-button" data-stock-request-close aria-label="Close request" title="Close">
                <img src="{{ asset('vendor/lucide-stock-requests/x.svg') }}" width="20" height="20" alt="">
            </button>
        </header>
        <form method="POST" action="{{ route('stock-requests.store') }}" data-stock-request-form data-unsaved-warning="false">
            @csrf
            <input type="hidden" name="submission_token" value="{{ \Illuminate\Support\Str::uuid() }}">
            <fieldset class="sr-modes">
                <legend class="sr-visually-hidden">Medicine source</legend>
                <label><input type="radio" name="request_mode" value="existing" checked> Existing Medicine</label>
                <label><input type="radio" name="request_mode" value="new"> New Medicine</label>
            </fieldset>
            <div data-existing-fields>
                @include('stock_requests._picker', ['pickerId' => 'sr-record-product', 'pickerProduct' => null])
            </div>
            <div data-new-fields hidden>
                <label for="sr-medicine-name">Medicine Name</label>
                <input id="sr-medicine-name" class="sr-input" name="medicine_name" maxlength="255" required disabled>
                <div class="sr-field-grid">
                    <div><label for="sr-strength">Strength</label><input id="sr-strength" class="sr-input" name="strength" maxlength="100" disabled></div>
                    <div><label for="sr-form">Form</label><input id="sr-form" class="sr-input" name="dosage_form" maxlength="100" placeholder="Tablet, syrup..." disabled></div>
                </div>
            </div>
            <div class="sr-field-grid">
                <div><label for="sr-quantity">Quantity <span class="sr-muted">(optional)</span></label><input id="sr-quantity" class="sr-input" type="number" name="quantity" min="0.01" max="99999999.99" step="0.01" inputmode="decimal"></div>
                <div><label for="sr-unit">Unit</label><input id="sr-unit" class="sr-input" name="unit_name" maxlength="100" placeholder="Tablet, bottle, pack..."></div>
            </div>
            <label for="sr-note">Note <span class="sr-muted">(optional)</span></label>
            <textarea id="sr-note" class="sr-input" name="note" maxlength="1000" rows="2"></textarea>
            <div class="sr-error" data-request-error role="alert" hidden></div>
            <footer class="sr-dialog-actions">
                <button type="button" class="sr-button" data-stock-request-close>Cancel</button>
                <button type="submit" class="sr-button sr-primary" data-request-save>Record Request</button>
            </footer>
        </form>
    </dialog>
@endif
