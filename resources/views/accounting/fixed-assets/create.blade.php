@extends('accounting.layout')

@section('title', 'Add Fixed Asset')

@section('content')
    <div class="topbar">
        <div>
            <p class="eyebrow">Asset Register</p>
            <h1>Add Fixed Asset</h1>
            <p>Register a fixed asset so acquisition and depreciation start flowing into accounting.</p>
        </div>
        <div class="range-chip">Fixed Asset Entry</div>
    </div>

    <div class="panel">
        @include('accounting._tabs')

        <h2>Asset Details</h2>
        <p class="panel-subtitle">This posts a fixed asset acquisition immediately, then depreciation is generated monthly into the journals.</p>

        <form method="POST" action="{{ route('accounting.fixed-assets.store') }}" class="filter-form" style="display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap:16px;">
            @csrf

            <div>
                <label for="asset_name"><strong>Asset Name</strong></label>
                <input id="asset_name" type="text" name="asset_name" value="{{ old('asset_name') }}" placeholder="Laptop, shelves, office desk..." style="width:100%; margin-top:8px; padding:12px; border-radius:12px; border:1px solid #d0d5dd;">
                @error('asset_name') <div style="color:#b42318; margin-top:6px;">{{ $message }}</div> @enderror
            </div>

            <div>
                <label for="asset_category"><strong>Asset Category</strong></label>
                <select id="asset_category" name="asset_category" style="width:100%; margin-top:8px; padding:12px; border-radius:12px; border:1px solid #d0d5dd;">
                    <option value="">Choose category</option>
                    @foreach ($fixedAssetDefinitions as $key => $definition)
                        <option value="{{ $key }}" @selected(old('asset_category') === $key)>{{ $definition['label'] }}</option>
                    @endforeach
                </select>
                @error('asset_category') <div style="color:#b42318; margin-top:6px;">{{ $message }}</div> @enderror
            </div>

            <div>
                <label for="asset_code"><strong>Asset Code</strong></label>
                <input id="asset_code" type="text" name="asset_code" value="{{ old('asset_code') }}" placeholder="Optional tag or code" style="width:100%; margin-top:8px; padding:12px; border-radius:12px; border:1px solid #d0d5dd;">
                @error('asset_code') <div style="color:#b42318; margin-top:6px;">{{ $message }}</div> @enderror
            </div>

            <div>
                <label for="acquisition_date"><strong>Acquisition Date</strong></label>
                <input id="acquisition_date" type="date" name="acquisition_date" value="{{ old('acquisition_date', now()->toDateString()) }}" style="width:100%; margin-top:8px; padding:12px; border-radius:12px; border:1px solid #d0d5dd;">
                @error('acquisition_date') <div style="color:#b42318; margin-top:6px;">{{ $message }}</div> @enderror
            </div>

            <div>
                <label for="acquisition_cost"><strong>Acquisition Cost</strong></label>
                <input id="acquisition_cost" type="number" name="acquisition_cost" min="0.01" step="0.01" value="{{ old('acquisition_cost') }}" placeholder="0" style="width:100%; margin-top:8px; padding:12px; border-radius:12px; border:1px solid #d0d5dd;">
                @error('acquisition_cost') <div style="color:#b42318; margin-top:6px;">{{ $message }}</div> @enderror
            </div>

            <div>
                <label for="salvage_value"><strong>Salvage Value</strong></label>
                <input id="salvage_value" type="number" name="salvage_value" min="0" step="0.01" value="{{ old('salvage_value', 0) }}" placeholder="0" style="width:100%; margin-top:8px; padding:12px; border-radius:12px; border:1px solid #d0d5dd;">
                @error('salvage_value') <div style="color:#b42318; margin-top:6px;">{{ $message }}</div> @enderror
            </div>

            <div>
                <label for="useful_life_months"><strong>Useful Life (Months)</strong></label>
                <input id="useful_life_months" type="number" name="useful_life_months" min="1" step="1" value="{{ old('useful_life_months', 36) }}" placeholder="36" style="width:100%; margin-top:8px; padding:12px; border-radius:12px; border:1px solid #d0d5dd;">
                @error('useful_life_months') <div style="color:#b42318; margin-top:6px;">{{ $message }}</div> @enderror
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
                <label for="vendor_name"><strong>Vendor / Supplier</strong></label>
                <input id="vendor_name" type="text" name="vendor_name" value="{{ old('vendor_name') }}" placeholder="Who supplied the asset?" style="width:100%; margin-top:8px; padding:12px; border-radius:12px; border:1px solid #d0d5dd;">
                @error('vendor_name') <div style="color:#b42318; margin-top:6px;">{{ $message }}</div> @enderror
            </div>

            <div>
                <label for="reference_number"><strong>Reference</strong></label>
                <input id="reference_number" type="text" name="reference_number" value="{{ old('reference_number') }}" placeholder="Invoice or payment reference" style="width:100%; margin-top:8px; padding:12px; border-radius:12px; border:1px solid #d0d5dd;">
                @error('reference_number') <div style="color:#b42318; margin-top:6px;">{{ $message }}</div> @enderror
            </div>

            <div style="grid-column: 1 / -1;">
                <label for="notes"><strong>Notes</strong></label>
                <textarea id="notes" name="notes" rows="4" placeholder="Optional asset note..." style="width:100%; margin-top:8px; padding:12px; border-radius:12px; border:1px solid #d0d5dd;">{{ old('notes') }}</textarea>
                @error('notes') <div style="color:#b42318; margin-top:6px;">{{ $message }}</div> @enderror
            </div>

            <div style="grid-column: 1 / -1; display:flex; gap:12px; justify-content:flex-end;">
                <a href="{{ route('accounting.fixed-assets.index') }}" class="btn btn-light">Back To Fixed Assets</a>
                <button type="submit" class="btn btn-primary">Add Fixed Asset</button>
            </div>
        </form>
    </div>
@endsection
