@extends('accounting.layout')

@section('title', 'KIM Rx')

@section('content')
    <div class="topbar">
        <div>
            <p class="eyebrow">Expense Correction</p>
            <h1>Edit Expense</h1>
            <p>Correct a posted expense. The reason and changed values will remain in the audit log.</p>
        </div>
        <div class="range-chip">Expense #{{ $expense->id }}</div>
    </div>

    <div class="panel">
        @include('accounting._tabs')

        @if ($errors->any())
            <div class="empty-state" style="margin-bottom:14px; border-color:#fda29b; color:#b42318;">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('accounting.expenses.update', $expense) }}" class="filter-form" style="display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:16px;">
            @csrf
            @method('PUT')

            <div>
                <label for="account_code"><strong>Expense Account</strong></label>
                <select id="account_code" name="account_code" style="width:100%; margin-top:8px; padding:12px; border:1px solid #d0d5dd; border-radius:8px;">
                    @foreach ($expenseAccounts as $account)
                        <option value="{{ $account['code'] }}" @selected(old('account_code', $expense->account_code) === $account['code'])>{{ $account['code'] }} - {{ $account['name'] }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="expense_date"><strong>Expense Date</strong></label>
                <input id="expense_date" type="date" name="expense_date" value="{{ old('expense_date', optional($expense->expense_date)->toDateString()) }}" required style="width:100%; margin-top:8px; padding:12px; border:1px solid #d0d5dd; border-radius:8px;">
            </div>

            <div>
                <label for="amount"><strong>Amount</strong></label>
                <input id="amount" type="number" name="amount" min="0.01" step="0.01" value="{{ old('amount', $expense->amount) }}" required style="width:100%; margin-top:8px; padding:12px; border:1px solid #d0d5dd; border-radius:8px;">
            </div>

            <div>
                <label for="payment_method"><strong>Payment Method</strong></label>
                <select id="payment_method" name="payment_method" required style="width:100%; margin-top:8px; padding:12px; border:1px solid #d0d5dd; border-radius:8px;">
                    @foreach ($paymentMethods as $key => $label)
                        <option value="{{ $key }}" @selected(old('payment_method', $expense->payment_method) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="payee_name"><strong>Payee</strong></label>
                <input id="payee_name" type="text" name="payee_name" value="{{ old('payee_name', $expense->payee_name) }}" style="width:100%; margin-top:8px; padding:12px; border:1px solid #d0d5dd; border-radius:8px;">
            </div>

            <div>
                <label for="reference_number"><strong>Reference</strong></label>
                <input id="reference_number" type="text" name="reference_number" value="{{ old('reference_number', $expense->reference_number) }}" style="width:100%; margin-top:8px; padding:12px; border:1px solid #d0d5dd; border-radius:8px;">
            </div>

            <div style="grid-column:1 / -1;">
                <label for="description"><strong>Description</strong></label>
                <input id="description" type="text" name="description" value="{{ old('description', $expense->description) }}" required style="width:100%; margin-top:8px; padding:12px; border:1px solid #d0d5dd; border-radius:8px;">
            </div>

            <div style="grid-column:1 / -1;">
                <label for="notes"><strong>Notes</strong></label>
                <textarea id="notes" name="notes" rows="3" style="width:100%; margin-top:8px; padding:12px; border:1px solid #d0d5dd; border-radius:8px;">{{ old('notes', $expense->notes) }}</textarea>
            </div>

            <div style="grid-column:1 / -1;">
                <label for="edit_reason"><strong>Reason For Correction</strong></label>
                <textarea id="edit_reason" name="edit_reason" rows="3" minlength="5" maxlength="500" required placeholder="Explain what was entered incorrectly" style="width:100%; margin-top:8px; padding:12px; border:1px solid #d0d5dd; border-radius:8px;">{{ old('edit_reason') }}</textarea>
            </div>

            <div style="grid-column:1 / -1; display:flex; justify-content:flex-end; gap:10px;">
                <a href="{{ route('accounting.expenses.show', $expense) }}" class="btn btn-light">Cancel</a>
                <button type="submit" class="btn btn-primary">Save Correction</button>
            </div>
        </form>
    </div>
@endsection
