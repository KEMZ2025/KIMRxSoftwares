@extends('lpos.layout')

@section('page-title', 'Local Purchase Orders')

@section('top-actions')
    @if($user->hasPermission('purchases.create'))
        <a class="btn btn-primary" href="{{ route('lpos.create') }}">New LPO</a>
    @endif
@endsection

@section('content')
<section class="panel">
    <form class="filters" method="GET" action="{{ route('lpos.index') }}">
        <div class="field"><label for="q">Order or supplier</label><input id="q" name="q" value="{{ $search }}" placeholder="Search LPO number or supplier"></div>
        <div class="field"><label for="status">Status</label><select id="status" name="status">
            <option value="">All statuses</option>
            @foreach(['draft' => 'Draft', 'issued' => 'Issued', 'cancelled' => 'Cancelled'] as $value => $label)
                <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
            @endforeach
        </select></div>
        <div class="field"><label for="date_from">From</label><input id="date_from" type="date" name="date_from" value="{{ $dateFrom }}"></div>
        <div class="field"><label for="date_to">To</label><input id="date_to" type="date" name="date_to" value="{{ $dateTo }}"></div>
        <button class="btn btn-primary" type="submit">Apply</button>
        <a class="btn" href="{{ route('lpos.index') }}">Clear</a>
    </form>
</section>

<section class="panel">
    <div class="table-wrap">
        <table>
            <thead><tr><th>Order</th><th>Date</th><th>Supplier</th><th>Expected</th><th>Status</th><th class="numeric">Total (UGX)</th><th>Action</th></tr></thead>
            <tbody>
                @forelse($orders as $order)
                    <tr>
                        <td><strong>{{ $order->order_number }}</strong></td>
                        <td>{{ $order->order_date->format('d M Y') }}</td>
                        <td>{{ $order->supplier_name }}</td>
                        <td>{{ $order->expected_delivery_date?->format('d M Y') ?? 'N/A' }}</td>
                        <td><span class="badge badge-{{ $order->status }}">{{ $order->status }}</span></td>
                        <td class="numeric">{{ number_format((float) $order->total_amount, 2) }}</td>
                        <td><a class="btn" href="{{ route('lpos.show', $order) }}" aria-label="Open {{ $order->order_number }}">Open</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">No local purchase orders found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top:14px">{{ $orders->links() }}</div>
</section>
@endsection
