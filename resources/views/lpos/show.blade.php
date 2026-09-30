@extends('lpos.layout')

@section('page-title', $order->order_number)

@section('top-actions')
    <div class="actions">
        <a class="btn" href="{{ route('lpos.index') }}">All LPOs</a>
        <a class="btn" href="{{ route('lpos.print', $order) }}" target="_blank" rel="noopener">Print A4</a>
        @if($order->status === 'draft' && $user->hasPermission('purchases.create'))
            <a class="btn" href="{{ route('lpos.edit', $order) }}">Edit Draft</a>
        @endif
    </div>
@endsection

@section('content')
<section class="panel">
    <div class="page-head" style="margin-bottom:18px">
        <h2 style="margin:0">Order Details</h2>
        <span class="badge badge-{{ $order->status }}">{{ $order->status }}</span>
    </div>
    <dl class="detail-grid">
        <div><dt>Supplier</dt><dd>{{ $order->supplier_name }}</dd></div>
        <div><dt>Order Date</dt><dd>{{ $order->order_date->format('d M Y') }}</dd></div>
        <div><dt>Expected Delivery</dt><dd>{{ $order->expected_delivery_date?->format('d M Y') ?? 'N/A' }}</dd></div>
        <div><dt>Supplier Phone</dt><dd>{{ $order->supplier_phone ?: 'N/A' }}</dd></div>
        <div><dt>Delivery Address</dt><dd>{{ $order->delivery_address ?: ($order->branch?->address ?: 'N/A') }}</dd></div>
        <div><dt>Prepared By</dt><dd>{{ $order->createdByUser?->name ?? 'N/A' }}</dd></div>
    </dl>
</section>

<section class="panel">
    <h2>Ordered Items</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>#</th><th>Item</th><th>Unit</th><th class="numeric">Qty</th><th class="numeric">Unit Cost</th><th class="numeric">Line Total (UGX)</th></tr></thead>
            <tbody>
                @foreach($order->items as $item)
                    <tr>
                        <td>{{ $item->line_number }}</td>
                        <td>{{ $item->description }}</td>
                        <td>{{ $item->unit_name ?: 'N/A' }}</td>
                        <td class="numeric">{{ number_format((float) $item->quantity, 2) }}</td>
                        <td class="numeric">{{ number_format((float) $item->unit_price, 2) }}</td>
                        <td class="numeric">{{ number_format((float) $item->line_total, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="summary" style="margin-top:14px">
        <div><span>Subtotal</span><span>{{ number_format((float) $order->subtotal, 2) }}</span></div>
        <div><span>Discount</span><span>{{ number_format((float) $order->discount_amount, 2) }}</span></div>
        <div><span>Tax</span><span>{{ number_format((float) $order->tax_amount, 2) }}</span></div>
        <div class="grand"><span>Total (UGX)</span><span>{{ number_format((float) $order->total_amount, 2) }}</span></div>
    </div>
</section>

@if($order->notes || $order->status === 'cancelled')
    <section class="panel">
        @if($order->notes)<h2>Notes / Terms</h2><p style="white-space:pre-wrap">{{ $order->notes }}</p>@endif
        @if($order->status === 'cancelled')<p><strong>Cancellation reason:</strong> {{ $order->cancellation_reason }}</p>@endif
    </section>
@endif

@if($user->hasPermission('purchases.create'))
    @if($order->status === 'draft')
        <form method="POST" action="{{ route('lpos.issue', $order) }}" style="margin-bottom:16px">
            @csrf
            <input type="hidden" name="version" value="{{ $order->version }}">
            <button class="btn btn-primary" type="submit">Issue LPO</button>
        </form>
    @endif
    @if($order->status !== 'cancelled')
        <section class="panel danger-zone">
            <h2>Cancel LPO</h2>
            <form method="POST" action="{{ route('lpos.cancel', $order) }}" class="filters">
                @csrf
                <input type="hidden" name="version" value="{{ $order->version }}">
                <div class="field" style="flex:1;min-width:240px"><label for="reason">Reason *</label><input id="reason" name="reason" maxlength="1000" required placeholder="Reason for cancellation"></div>
                <button class="btn btn-danger" type="submit">Cancel LPO</button>
            </form>
        </section>
    @endif
@endif
@endsection
