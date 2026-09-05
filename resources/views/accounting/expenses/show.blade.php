@extends('accounting.layout')

@section('title', 'KIM Rx')

@section('content')
    <style>
        .expense-receipt { max-width:760px; margin:0 auto; }
        .expense-receipt__header { text-align:center; padding-bottom:16px; border-bottom:2px solid #172033; }
        .expense-receipt__grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:0; margin-top:18px; border:1px solid #98a2b3; }
        .expense-receipt__field { padding:12px; border-right:1px solid #98a2b3; border-bottom:1px solid #98a2b3; }
        .expense-receipt__field:nth-child(even) { border-right:0; }
        .expense-receipt__label { color:#667085; font-size:12px; font-weight:700; text-transform:uppercase; }
        .expense-receipt__value { margin-top:5px; font-weight:700; overflow-wrap:anywhere; }
        @media (max-width:640px) {
            .expense-receipt__grid { grid-template-columns:1fr; }
            .expense-receipt__field { border-right:0; }
        }
        @media print {
            .topbar, .tabbar-wrap, .receipt-actions, aside, nav { display:none !important; }
            body, .content, .panel { background:#fff !important; box-shadow:none !important; margin:0 !important; padding:0 !important; }
            .expense-receipt { max-width:none; }
        }
    </style>

    <div class="topbar">
        <div>
            <p class="eyebrow">Expense Record</p>
            <h1>Expense Receipt</h1>
            <p>View and print the complete posted expense.</p>
        </div>
        <div class="range-chip">#{{ $expense->id }}</div>
    </div>

    <div class="panel">
        @include('accounting._tabs')

        @if (session('success'))
            <div class="badge badge-emerald" style="margin-bottom:14px;">{{ session('success') }}</div>
        @endif

        <div class="expense-receipt">
            <div class="expense-receipt__header">
                <h2 style="margin:0 0 6px;">{{ $clientName }}</h2>
                <div>{{ $branchName }}</div>
                <h3 style="margin:14px 0 0;">EXPENSE RECEIPT #{{ $expense->id }}</h3>
                <div style="margin-top:8px;">
                    <span class="badge {{ $expense->is_active ? 'badge-emerald' : 'badge-amber' }}">{{ $expense->is_active ? 'Active' : 'Voided' }}</span>
                </div>
            </div>

            <div class="expense-receipt__grid">
                @foreach ([
                    'Expense Date' => optional($expense->expense_date)->format('d M Y'),
                    'Amount' => number_format((float) $expense->amount, 2),
                    'Account' => $expense->account_code.' - '.$expense->account_name,
                    'Payment Method' => $expense->payment_method,
                    'Payee' => $expense->payee_name ?: 'N/A',
                    'Reference' => $expense->reference_number ?: 'N/A',
                    'Description' => $expense->description,
                    'Entered By' => $expense->enteredByUser?->name ?? 'N/A',
                    'Votes' => $expense->source_of_funds ?: 'Not recorded',
                    'Posted At' => optional($expense->created_at)->format('d M Y H:i'),
                ] as $label => $value)
                    <div class="expense-receipt__field">
                        <div class="expense-receipt__label">{{ $label }}</div>
                        <div class="expense-receipt__value">{{ $value }}</div>
                    </div>
                @endforeach
            </div>

            @unless ($expense->is_active)
                <div style="margin-top:16px; padding:14px; border:1px solid #f79009; background:#fffaeb;">
                    <strong>Void Reason:</strong> {{ $expense->void_reason }}<br>
                    <span>Voided by {{ $expense->voidedByUser?->name ?? 'N/A' }}{{ $expense->voided_at ? ' on '.$expense->voided_at->format('d M Y H:i') : '' }}</span>
                </div>
            @endunless

            <div class="receipt-actions" style="display:flex; justify-content:flex-end; gap:10px; margin-top:18px;">
                <a href="{{ route('accounting.expenses.index', ['status' => $expense->is_active ? 'active' : 'voided']) }}" class="btn btn-light">Back</a>
                @if ($expense->is_active && auth()->user()?->hasPermission('accounting.expenses.manage'))
                    <a href="{{ route('accounting.expenses.edit', $expense) }}" class="btn btn-primary">Edit</a>
                @endif
                <button type="button" class="btn btn-primary" onclick="window.print()">Print</button>
            </div>
        </div>
    </div>
@endsection
