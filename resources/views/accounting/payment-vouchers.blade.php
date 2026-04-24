@extends('accounting.layout')

@section('title', 'Payment Vouchers')

@section('content')
    <div class="topbar">
        <div>
            <p class="eyebrow">Outgoing Payments</p>
            <h1>Payment Vouchers</h1>
            <p>Supplier disbursement vouchers posted against purchase invoices in this branch.</p>
        </div>
        <div class="range-chip">{{ $from->format('d M Y') }} to {{ $to->format('d M Y') }}</div>
    </div>

    <div class="panel">
        @include('accounting._tabs')

        <div class="filters">
            <div>
                <h2 style="margin:0 0 6px;">Voucher Filters</h2>
                <p class="panel-subtitle" style="margin:0;">Filter supplier disbursements by date and payment method.</p>
            </div>
            <form method="GET" class="filter-form">
                <input type="date" name="from" value="{{ $from->toDateString() }}">
                <input type="date" name="to" value="{{ $to->toDateString() }}">
                <select name="method">
                    <option value="">All Methods</option>
                    @foreach (array_keys($vouchers['methodTotals']) as $methodOption)
                        <option value="{{ $methodOption }}" @selected($method === $methodOption)>{{ $methodOption }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-primary">Apply Filters</button>
                <a href="{{ route('accounting.vouchers') }}" class="btn btn-light">Reset</a>
            </form>
        </div>
    </div>

    <div class="cards-grid" style="margin-bottom:20px;">
        <div class="summary-card tone-violet">
            <div class="label">Total Disbursed</div>
            <div class="value">{{ number_format((float) $vouchers['totalAmount'], 2) }}</div>
        </div>
        <div class="summary-card tone-blue">
            <div class="label">Voucher Count</div>
            <div class="value">{{ $vouchers['voucherCount'] }}</div>
        </div>
        <div class="summary-card tone-amber">
            <div class="label">Suppliers Paid</div>
            <div class="value">{{ $vouchers['suppliersPaid'] }}</div>
        </div>
        <div class="summary-card tone-teal">
            <div class="label">Largest Method</div>
            <div class="value">
                {{ $vouchers['methodTotals'] ? array_key_first($vouchers['methodTotals']) : 'N/A' }}
            </div>
        </div>
    </div>

    <div class="two-up">
        <div class="panel">
            <h2>Payment Voucher List</h2>
            <p class="panel-subtitle">Each row below is an outgoing supplier payment tied to a specific purchase invoice.</p>

            @if ($vouchers['rows']->isEmpty())
                <div class="empty-state">No supplier disbursements matched that filter window.</div>
            @else
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Voucher</th>
                                <th>Date</th>
                                <th>Supplier</th>
                                <th>Invoice</th>
                                <th>Method</th>
                                <th>Paid By</th>
                                <th class="amount">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($vouchers['rows'] as $voucher)
                                <tr>
                                    <td>{{ $voucher['voucher_number'] }}</td>
                                    <td>{{ optional($voucher['payment_date'])->format('d M Y H:i') }}</td>
                                    <td>{{ $voucher['supplier_name'] }}</td>
                                    <td>{{ $voucher['invoice_number'] }}</td>
                                    <td>{{ $voucher['payment_method'] }}</td>
                                    <td>{{ $voucher['paid_by'] }}</td>
                                    <td class="amount">{{ number_format((float) $voucher['amount'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="panel">
            <h2>Method Breakdown</h2>
            <p class="panel-subtitle">This breakdown helps accountants review where supplier money was paid from.</p>
            @if (empty($vouchers['methodTotals']))
                <div class="empty-state">No payment methods are available inside this filter window.</div>
            @else
                <div class="mini-stat-list">
                    @foreach ($vouchers['methodTotals'] as $methodName => $amount)
                        <div class="mini-stat">
                            <div class="name">{{ $methodName }}</div>
                            <div class="amount">{{ number_format((float) $amount, 2) }}</div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@endsection
