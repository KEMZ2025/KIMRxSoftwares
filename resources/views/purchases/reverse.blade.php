<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KIM Rx - Purchase Reversal</title>
    <link rel="icon" href="{{ asset('favicon.png') }}">
    <style>
        *{box-sizing:border-box}body{margin:0;display:flex;font-family:Arial,sans-serif;background:#f5f7fb;color:#182230}
        .content{flex:1;min-width:0;padding:24px}.workspace{max-width:1000px;margin:auto}h1{font-size:24px;margin:18px 0}h2{font-size:18px}
        a{color:#175cd3}.summary{display:flex;flex-wrap:wrap;gap:24px;padding:18px 0;border-block:1px solid #d0d5dd}.summary span{display:block;color:#475467;font-size:13px;margin-bottom:6px}
        table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:12px 8px;border-bottom:1px solid #d0d5dd;font-size:14px}.table-wrap{overflow:auto;margin:20px 0}
        form{padding:20px 0}.fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}label{display:block;font-size:14px;font-weight:600;margin-bottom:7px}
        input:not([type=checkbox]),select,textarea{font:inherit;padding:10px;border:1px solid #98a2b3;border-radius:6px;width:100%;background:white;color:#182230}textarea{min-height:90px}.wide{grid-column:1/-1}
        .check{display:flex;align-items:flex-start;gap:10px;font-weight:400;line-height:1.5;margin:16px 0}.check input{margin-top:4px;flex:none}
        .alert{padding:14px;border-radius:6px;margin:16px 0;background:#fff1f0;color:#912018}.success{background:#ecfdf3;color:#05603a}
        button{background:#b42318;color:white;border:0;border-radius:6px;padding:12px 18px;font-weight:600;cursor:pointer}button:disabled{opacity:.6}.actions{display:flex;gap:20px;align-items:center;margin-top:20px}
        [hidden]{display:none!important}@media(max-width:700px){body{flex-direction:column}.content{padding:16px}.fields{grid-template-columns:1fr}.summary{gap:16px}}
    </style>
</head>
<body>
@include('layouts.sidebar')
<main class="content"><div class="workspace">
    <a href="{{ route('purchases.show', $purchase) }}">Back to invoice</a>
    <h1>Reverse Payment / Cancel Purchase</h1>
    @if(session('success'))<div class="alert success" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
    <div class="summary">
        <div><span>Invoice</span><strong>{{ $purchase->invoice_number }}</strong></div>
        <div><span>Supplier</span><strong>{{ $purchase->supplier?->name }}</strong></div>
        <div><span>Total</span><strong>{{ number_format($purchase->total_amount,2) }}</strong></div>
        <div><span>Net paid</span><strong>{{ number_format($purchase->amount_paid,2) }}</strong></div>
        <div><span>Balance due</span><strong>{{ number_format($purchase->balance_due,2) }}</strong></div>
    </div>
    <div class="table-wrap"><table><thead><tr><th>Payment</th><th>Date</th><th>Method</th><th>Amount</th></tr></thead><tbody>
        @forelse($purchase->supplierPayments as $payment)
            <tr><td>{{ $payment->reversal_of_payment_id ? 'Reversal of #' . $payment->reversal_of_payment_id : '#' . $payment->id }}</td><td>{{ $payment->payment_date->format('d M Y H:i') }}</td><td>{{ $methods[$payment->payment_method] ?? $payment->payment_method }}</td><td>{{ number_format($payment->amount,2) }}</td></tr>
        @empty<tr><td colspan="4">No recorded payments.</td></tr>@endforelse
    </tbody></table></div>
    <form method="POST" action="{{ route('purchases.reversal.store', $purchase) }}" id="reversal-form">
        @csrf
        <div class="fields">
            <div><label for="action">Action</label><select name="action" id="action"><option value="cancel" @selected(old('action','cancel')==='cancel')>Cancel entire purchase</option><option value="payment" @selected(old('action')==='payment')>Reverse one payment</option></select></div>
            <div data-payment><label for="payment_id">Payment</label><select name="payment_id" id="payment_id"><option value="">Select payment</option>@foreach($payments as $payment)<option value="{{ $payment->id }}" @selected(old('payment_id')==$payment->id)>#{{ $payment->id }} - {{ number_format($payment->amount,2) }}</option>@endforeach</select></div>
            <div><label for="mode">Payment reason</label><select name="mode" id="mode"><option value="refund" @selected(old('mode','refund')==='refund')>Supplier refunded the money</option><option value="mistake" @selected(old('mode')==='mistake')>Payment entry was a mistake; no money changed hands</option></select></div>
            <div><label for="refund_method">Refund received into</label><select name="refund_method" id="refund_method">@foreach($methods as $key=>$label)<option value="{{ $key }}" @selected(old('refund_method','petty_cash')===$key)>{{ $label }}</option>@endforeach</select></div>
            <div><label for="effective_date">Return / refund date</label><input type="date" id="effective_date" name="effective_date" value="{{ old('effective_date',now()->toDateString()) }}" min="{{ $purchase->purchase_date->toDateString() }}" max="{{ now()->toDateString() }}" required></div>
            <div data-cancel><label for="confirm_invoice">Confirm invoice number</label><input id="confirm_invoice" name="confirm_invoice" value="{{ old('confirm_invoice') }}" autocomplete="off"></div>
            <div class="wide"><label for="reason">Reason</label><textarea id="reason" name="reason" minlength="10" maxlength="1000" required>{{ old('reason') }}</textarea></div>
        </div>
        <label class="check"><input type="checkbox" name="money_confirmed" value="1" required @checked(old('money_confirmed'))>I confirm the refund has been received in full, or the selected payment was recorded in error and no money changed hands.</label>
        <div data-cancel>
            <label class="check"><input type="checkbox" name="goods_returned" value="1" @checked(old('goods_returned'))>All goods on this invoice were returned, or were never received.</label>
            <label class="check"><input type="checkbox" name="reverse_payments" value="1" @checked(old('reverse_payments'))>Reverse all remaining payments on this invoice as part of cancellation.</label>
            <div class="alert">Cancellation removes the remaining stock and the invoice from active purchases. Original records remain in the audit history. Sold, reserved or adjusted stock blocks cancellation.</div>
        </div>
        <label class="check"><input type="checkbox" name="late_entries_confirmed" value="1" @checked(old('late_entries_confirmed'))>Any entries recorded after the confirmed return/refund date were entered late by mistake.</label>
        <div class="actions"><button type="submit" id="submit-reversal">Confirm cancellation</button><a href="{{ route('purchases.show', $purchase) }}">Go back</a></div>
    </form>
</div></main>
<script>
const action = document.getElementById('action');
function setAction() {
    const cancel = action.value === 'cancel';
    document.querySelectorAll('[data-cancel], [data-payment]').forEach(el => {
        const enabled = el.hasAttribute('data-cancel') ? cancel : !cancel;
        el.hidden = !enabled;
        el.querySelectorAll('input,select').forEach(input => input.disabled = !enabled);
    });
    document.getElementById('confirm_invoice').required = cancel;
    document.querySelector('[name="goods_returned"]').required = cancel;
    document.getElementById('payment_id').required = !cancel;
    document.getElementById('submit-reversal').textContent = cancel ? 'Confirm cancellation' : 'Confirm payment reversal';
}
action.addEventListener('change', setAction); setAction();
</script>
</body></html>
