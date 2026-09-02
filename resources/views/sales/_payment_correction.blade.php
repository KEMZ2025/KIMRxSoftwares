<style>
    .payment-correction { border-block: 1px solid #cbd5e1; padding: 16px 0; margin-bottom: 24px; scroll-margin-top: 16px; }
    .payment-correction summary { cursor: pointer; font-size: 16px; font-weight: 700; }
    .payment-correction .correction-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; margin-top: 18px; }
    .payment-correction .correction-grid > * { min-width: 0; }
    .payment-correction input, .payment-correction select, .payment-correction textarea { width: 100%; max-width: 100%; font: inherit; }
    .payment-correction output { display: block; padding: 10px 0; font-weight: 700; }
    .payment-correction .correction-full { grid-column: 1 / -1; }
    .payment-correction .correction-confirm { display: flex; align-items: flex-start; gap: 10px; margin: 18px 0; }
    .payment-correction .correction-confirm input { width: 18px; height: 18px; flex: 0 0 18px; margin: 0; }
    .payment-correction button { display: inline-flex; align-items: center; gap: 8px; }
    .payment-correction button img { width: 18px; height: 18px; filter: brightness(0) invert(1); }
    @media (max-width: 700px) { .payment-correction .correction-grid { grid-template-columns: minmax(0, 1fr); } }
</style>
<details class="payment-correction" id="payment-correction" @if($errors->paymentCorrection->any()) open @endif>
    <summary>Correct Payment</summary>
    @if($errors->paymentCorrection->any())
        <div class="alert-danger" role="alert" style="margin-top:16px;">{{ $errors->paymentCorrection->first() }}</div>
    @endif
    <form id="payment-correction-form" method="POST" action="{{ route('sales.correctApprovedPayment', $sale->id) }}">
        @csrf
        @method('PUT')
        <input type="hidden" name="payment_correction_token" value="{{ $paymentCorrectionToken }}">
        <div class="correction-grid">
            <div class="form-group">
                <label>Recorded payment</label>
                <output>{{ number_format((float) $sale->amount_paid, 2) }}</output>
            </div>
            <div class="form-group">
                <label for="correction-customer">Credit customer</label>
                <select id="correction-customer" name="correction_customer_id" required>
                    <option value="">Select Customer</option>
                    @foreach($customers as $customer)
                        <option value="{{ $customer->id }}" @selected(old('correction_customer_id', $sale->customer_id) == $customer->id)>{{ $customer->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label for="corrected-amount">Actually received at approval</label>
                <input id="corrected-amount" name="corrected_amount_received" type="number" min="0" max="{{ max(0, min((float) $sale->amount_paid, (float) $sale->total_amount) - 0.01) }}" step="0.01" value="{{ old('corrected_amount_received') }}" required>
            </div>
            <div class="form-group">
                <label for="corrected-balance">Credit balance</label>
                <output id="corrected-balance" for="corrected-amount" aria-live="polite">Not calculated</output>
            </div>
            <div class="form-group correction-full">
                <label for="correction-reason">Reason for correction</label>
                <textarea id="correction-reason" name="correction_reason" rows="2" maxlength="1000" required>{{ old('correction_reason') }}</textarea>
            </div>
        </div>
        <label class="correction-confirm">
            <input type="checkbox" name="confirm_unreceived_payment" value="1" required>
            <span>The removed amount was never received. This is not a refund.</span>
        </label>
        <button type="submit" class="btn btn-save"><img src="{{ asset('vendor/lucide-stock-requests/notebook-pen.svg') }}" alt="">Save as Credit</button>
    </form>
</details>
<script>
(() => {
    const panel = document.getElementById('payment-correction');
    const amount = document.getElementById('corrected-amount');
    const balance = document.getElementById('corrected-balance');
    const total = @json((float) $sale->total_amount);
    const updateBalance = () => {
        balance.textContent = amount.value !== '' && amount.validity.valid
            ? (total - Number(amount.value)).toLocaleString('en', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
            : 'Not calculated';
    };
    const openFromLink = () => { if (location.hash === '#payment-correction') panel.open = true; };
    amount.addEventListener('input', updateBalance);
    window.addEventListener('hashchange', openFromLink);
    openFromLink();
    updateBalance();
})();
</script>
