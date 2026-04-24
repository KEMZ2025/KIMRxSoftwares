@extends('accounting.layout')

@section('title', 'Post Expense')

@section('content')
    <div class="topbar">
        <div>
            <p class="eyebrow">Manual Expense Posting</p>
            <h1>Post Expense</h1>
            <p>Record an operating expense and send it straight into the accounting ledger.</p>
        </div>
        <div class="range-chip">Expense Entry</div>
    </div>

    <div class="panel">
        @include('accounting._tabs')

        <h2>Expense Details</h2>
        <p class="panel-subtitle">This posts a balanced entry: expense account debit and payment-method asset credit.</p>

        <form method="POST" action="{{ route('accounting.expenses.store') }}" class="filter-form" style="display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap:16px;">
            @csrf

            <div>
                <label for="account_code"><strong>Expense Account</strong></label>
                <select id="account_code" name="account_code" style="width:100%; margin-top:8px; padding:12px; border-radius:12px; border:1px solid #d0d5dd;">
                    <option value="">Choose account</option>
                    @foreach ($expenseAccounts as $account)
                        <option value="{{ $account['code'] }}" @selected(old('account_code') === $account['code'])>
                            {{ $account['code'] }} - {{ $account['name'] }}
                        </option>
                    @endforeach
                </select>
                @error('account_code') <div style="color:#b42318; margin-top:6px;">{{ $message }}</div> @enderror
            </div>

            <div>
                <label for="expense_date"><strong>Expense Date</strong></label>
                <input id="expense_date" type="date" name="expense_date" value="{{ old('expense_date', now()->toDateString()) }}" style="width:100%; margin-top:8px; padding:12px; border-radius:12px; border:1px solid #d0d5dd;">
                @error('expense_date') <div style="color:#b42318; margin-top:6px;">{{ $message }}</div> @enderror
            </div>

            <div>
                <label for="amount"><strong>Amount</strong></label>
                <input id="amount" type="number" name="amount" min="0.01" step="0.01" value="{{ old('amount') }}" placeholder="0" style="width:100%; margin-top:8px; padding:12px; border-radius:12px; border:1px solid #d0d5dd;">
                @error('amount') <div style="color:#b42318; margin-top:6px;">{{ $message }}</div> @enderror
            </div>

            <div>
                <label for="payment_method"><strong>Payment Method</strong></label>
                <select id="payment_method" name="payment_method" style="width:100%; margin-top:8px; padding:12px; border-radius:12px; border:1px solid #d0d5dd;">
                    <option value="">Choose method</option>
                    @foreach ($paymentMethods as $key => $label)
                        <option value="{{ $key }}" @selected(old('payment_method') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('payment_method') <div style="color:#b42318; margin-top:6px;">{{ $message }}</div> @enderror
            </div>

            <div>
                <label for="payee_name"><strong>Payee</strong></label>
                <input id="payee_name" type="text" name="payee_name" value="{{ old('payee_name') }}" placeholder="Who was paid?" style="width:100%; margin-top:8px; padding:12px; border-radius:12px; border:1px solid #d0d5dd;">
                @error('payee_name') <div style="color:#b42318; margin-top:6px;">{{ $message }}</div> @enderror
            </div>

            <div>
                <label for="reference_number"><strong>Reference</strong></label>
                <input id="reference_number" type="text" name="reference_number" value="{{ old('reference_number') }}" placeholder="Receipt, voucher, or transfer ref" style="width:100%; margin-top:8px; padding:12px; border-radius:12px; border:1px solid #d0d5dd;">
                @error('reference_number') <div style="color:#b42318; margin-top:6px;">{{ $message }}</div> @enderror
            </div>

            <div style="grid-column: 1 / -1;">
                <label for="description"><strong>Description</strong></label>
                <input id="description" type="text" name="description" value="{{ old('description') }}" placeholder="Rent for April, transport, repairs..." style="width:100%; margin-top:8px; padding:12px; border-radius:12px; border:1px solid #d0d5dd;">
                @error('description') <div style="color:#b42318; margin-top:6px;">{{ $message }}</div> @enderror
            </div>

            <div style="grid-column: 1 / -1;">
                <label for="notes"><strong>Notes</strong></label>
                <textarea id="notes" name="notes" rows="4" placeholder="Optional accounting note..." style="width:100%; margin-top:8px; padding:12px; border-radius:12px; border:1px solid #d0d5dd;">{{ old('notes') }}</textarea>
                @error('notes') <div style="color:#b42318; margin-top:6px;">{{ $message }}</div> @enderror
            </div>

            <div style="grid-column: 1 / -1; display:flex; gap:12px; justify-content:flex-end;">
                <a href="{{ route('accounting.expenses.index') }}" class="btn btn-light">Back To Expenses</a>
                <button type="submit" class="btn btn-primary">Post Expense</button>
            </div>
        </form>
    </div>
@endsection
