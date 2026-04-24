<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Purchase - KIM Rx</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; display: flex; background: #f5f7fb; }

        .content { flex: 1; padding: 20px; }
        .topbar { background: white; padding: 15px; border-radius: 10px; margin-bottom: 20px; }
        .panel { background: white; padding: 20px; border-radius: 10px; }
        .form-row { display: grid; grid-template-columns: repeat(2, minmax(220px, 1fr)); gap: 16px; margin-bottom: 16px; }
        .form-group { display: flex; flex-direction: column; }
        .form-group label { margin-bottom: 8px; font-weight: bold; }
        .form-group input, .form-group select, .form-group textarea {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        .full { grid-column: 1 / -1; }

        .alert-danger { background: #fdecea; color: #b42318; padding: 12px; border-radius: 8px; margin-bottom: 15px; }
        .alert-success { background: #e7f6ec; color: #1f7a4f; padding: 12px; border-radius: 8px; margin-bottom: 15px; }
        .alert-info { background: #eef4ff; color: #1d4ed8; padding: 12px; border-radius: 8px; margin-bottom: 15px; }
        .alert-warning { background: #fff7e6; color: #9a6700; padding: 12px; border-radius: 8px; margin-bottom: 15px; }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(160px, 1fr));
            gap: 14px;
            margin-bottom: 20px;
        }

        .summary-box {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            padding: 14px;
            border-radius: 10px;
        }

        .summary-box h4 {
            margin: 0 0 8px;
            font-size: 13px;
            color: #666;
        }

        .summary-box p {
            margin: 0;
            font-weight: bold;
        }

        .btn {
            padding: 8px 12px;
            border-radius: 6px;
            color: white;
            text-decoration: none;
            border: none;
            cursor: pointer;
            display: inline-block;
        }

        .btn-save { background: green; }
        .btn-back { background: #3949ab; }
        .hint-text { margin-top: 8px; color: #666; font-size: 12px; }

        @media (max-width: 900px) {
            body { flex-direction: column; }
            .form-row, .summary-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
        @include('layouts.sidebar')

    <div class="content" id="mainContent">
        <div class="topbar">
            <h3>Edit Purchase Header</h3>
            <p>Invoice: {{ $purchase->invoice_number }}</p>
        </div>

        <div class="panel">
            @if(session('success'))
                <div class="alert-success">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="alert-danger">
                    <ul style="margin:0; padding-left:18px;">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if($stockSummary['has_locked_stock'])
                <div class="alert-warning">
                    Supplier changes are locked for this purchase because some received stock has already been reserved or sold. You can still update the invoice number, dates, payment details, and notes.
                </div>
            @elseif($stockSummary['has_received_stock'])
                <div class="alert-info">
                    This purchase already has received stock, but none of it has been reserved or sold yet. If you change the supplier, the linked received batches will be updated too.
                </div>
            @else
                <div class="alert-info">
                    No stock has been received on this purchase yet, so header changes only affect the purchase record.
                </div>
            @endif

            <div class="summary-grid">
                <div class="summary-box">
                    <h4>Total Amount</h4>
                    <p>{{ number_format((float) $purchase->total_amount, 2) }}</p>
                </div>
                <div class="summary-box">
                    <h4>Current Amount Paid</h4>
                    <p>{{ number_format((float) $purchase->amount_paid, 2) }}</p>
                </div>
                <div class="summary-box">
                    <h4>Current Balance Due</h4>
                    <p>{{ number_format((float) $purchase->balance_due, 2) }}</p>
                </div>
                <div class="summary-box">
                    <h4>Received Stock</h4>
                    <p>{{ number_format((float) $stockSummary['received_quantity'], 2) }}</p>
                </div>
                <div class="summary-box">
                    <h4>Available Stock</h4>
                    <p>{{ number_format((float) $stockSummary['available_quantity'], 2) }}</p>
                </div>
                <div class="summary-box">
                    <h4>Reserved Stock</h4>
                    <p>{{ number_format((float) $stockSummary['reserved_quantity'], 2) }}</p>
                </div>
                <div class="summary-box">
                    <h4>Sold Stock</h4>
                    <p>{{ number_format((float) $stockSummary['sold_quantity'], 2) }}</p>
                </div>
            </div>

            <form method="POST" action="{{ route('purchases.update', $purchase->id) }}">
                @csrf
                @method('PUT')

                <div class="form-row">
                    <div class="form-group">
                        <label for="invoice_number">Invoice Number</label>
                        <input type="text" name="invoice_number" id="invoice_number" value="{{ old('invoice_number', $purchase->invoice_number) }}" required>
                    </div>

                    <div class="form-group">
                        <label for="purchase_date">Purchase Date</label>
                        <input type="date" name="purchase_date" id="purchase_date" value="{{ old('purchase_date', optional($purchase->purchase_date)->format('Y-m-d')) }}" required>
                    </div>

                    <div class="form-group">
                        <label for="supplier_id">Supplier</label>
                        @if($stockSummary['can_change_supplier'])
                            <select name="supplier_id" id="supplier_id" required>
                                <option value="">Select Supplier</option>
                                @foreach($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}" {{ old('supplier_id', $purchase->supplier_id) == $supplier->id ? 'selected' : '' }}>
                                        {{ $supplier->name }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="hint-text">
                                @if($stockSummary['has_received_stock'])
                                    Linked unsold batches will follow this supplier change automatically.
                                @else
                                    No received stock is linked yet, so this changes only the purchase header.
                                @endif
                            </div>
                        @else
                            <input type="hidden" name="supplier_id" value="{{ $purchase->supplier_id }}">
                            <select id="supplier_id" disabled>
                                @foreach($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}" {{ $purchase->supplier_id == $supplier->id ? 'selected' : '' }}>
                                        {{ $supplier->name }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="hint-text">
                                Supplier is locked because stock from this purchase is already tied to active sales or reservations.
                            </div>
                        @endif
                    </div>

                    <div class="form-group">
                        <label for="payment_type">Payment Type</label>
                        <select name="payment_type" id="payment_type" required>
                            <option value="cash" {{ old('payment_type', $purchase->payment_type) == 'cash' ? 'selected' : '' }}>Cash</option>
                            <option value="credit" {{ old('payment_type', $purchase->payment_type) == 'credit' ? 'selected' : '' }}>Credit</option>
                            <option value="mixed" {{ old('payment_type', $purchase->payment_type) == 'mixed' ? 'selected' : '' }}>Mixed</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="amount_paid">Amount Paid</label>
                        <input type="number" step="0.01" name="amount_paid" id="amount_paid" value="{{ old('amount_paid', $purchase->amount_paid) }}">
                    </div>

                    <div class="form-group">
                        <label for="due_date">Expected Payment Date</label>
                        <input type="date" name="due_date" id="due_date" value="{{ old('due_date', optional($purchase->due_date)->format('Y-m-d')) }}">
                    </div>

                    <div class="form-group full">
                        <label for="notes">Notes</label>
                        <textarea name="notes" id="notes">{{ old('notes', $purchase->notes) }}</textarea>
                    </div>
                </div>

                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <button type="submit" class="btn btn-save">Update Purchase Header</button>
                    <a href="{{ route('purchases.show', $purchase->id) }}" class="btn btn-back">Back to Details</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
