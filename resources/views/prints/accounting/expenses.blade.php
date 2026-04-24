@extends('prints.layout')

@php
    $pageTitle = 'Expenses';
    $pageBadge = 'Accounting';
    $rangeLabel = $from->format('d M Y') . ' to ' . $to->format('d M Y');
@endphp

@section('content')
    <div class="section">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Account</th>
                        <th>Payee</th>
                        <th>Method</th>
                        <th>Reference</th>
                        <th>Description</th>
                        <th>Entered By</th>
                        <th class="amount">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($expenses as $expense)
                        <tr>
                            <td>{{ optional($expense->expense_date)->format('d M Y H:i') }}</td>
                            <td>{{ $expense->account_code }}</td>
                            <td>{{ $expense->payee_name ?? 'N/A' }}</td>
                            <td>{{ $expense->payment_method }}</td>
                            <td>{{ $expense->reference_number ?? 'N/A' }}</td>
                            <td>{{ $expense->description }}</td>
                            <td>{{ $expense->enteredByUser?->name ?? 'System' }}</td>
                            <td class="amount">{{ number_format((float) $expense->amount, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8">No expenses in this range.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
