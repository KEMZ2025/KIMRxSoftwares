@extends('prints.layout')

@php
    $pageTitle = 'Payment Vouchers';
    $pageBadge = 'Accounting';
    $rangeLabel = $from->format('d M Y') . ' to ' . $to->format('d M Y');
@endphp

@section('content')
    <div class="section">
        <div class="summary-grid">
            <div class="summary-card">
                <div class="label">Total Disbursed</div>
                <div class="value">{{ number_format((float) $vouchers['totalAmount'], 2) }}</div>
            </div>
            <div class="summary-card">
                <div class="label">Voucher Count</div>
                <div class="value">{{ $vouchers['voucherCount'] }}</div>
            </div>
            <div class="summary-card">
                <div class="label">Suppliers Paid</div>
                <div class="value">{{ $vouchers['suppliersPaid'] }}</div>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="table-wrap">
            <table>
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
                    @foreach($vouchers['rows'] as $voucher)
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
    </div>
@endsection
