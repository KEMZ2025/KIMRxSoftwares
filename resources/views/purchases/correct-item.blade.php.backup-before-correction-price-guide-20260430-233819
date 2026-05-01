<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Correct Purchase Item - KIM Rx</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; display: flex; background: #f5f7fb; }
        .content { flex: 1; padding: 20px; }
        .topbar, .panel { background: white; border-radius: 12px; padding: 20px; }
        .topbar { margin-bottom: 20px; }
        .panel { margin-bottom: 20px; }
        .summary-grid, .form-grid {
            display: grid;
            gap: 16px;
        }
        .summary-grid { grid-template-columns: repeat(4, minmax(160px, 1fr)); }
        .form-grid { grid-template-columns: repeat(2, minmax(220px, 1fr)); }
        .summary-box {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 14px;
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
        .form-group { display: flex; flex-direction: column; }
        .form-group label { margin-bottom: 8px; font-weight: bold; }
        .form-group input, .form-group select, .form-group textarea {
            padding: 10px;
            border: 1px solid #d0d7de;
            border-radius: 8px;
        }
        .full { grid-column: 1 / -1; }
        .readonly-value {
            min-height: 42px;
            display: flex;
            align-items: center;
            padding: 10px 12px;
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
        }
        .alert-danger, .alert-info, .alert-warning {
            border-radius: 10px;
            padding: 12px;
            margin-bottom: 16px;
        }
        .alert-danger { background: #fdecea; color: #b42318; }
        .alert-info { background: #eef4ff; color: #1d4ed8; }
        .alert-warning { background: #fff7e6; color: #9a6700; }
        .btn {
            padding: 10px 14px;
            border: none;
            border-radius: 8px;
            color: white;
            text-decoration: none;
            cursor: pointer;
            display: inline-block;
        }
        .btn-save { background: #1f7a4f; }
        .btn-back { background: #3949ab; }
        .muted { color: #666; font-size: 13px; }

        @media (max-width: 900px) {
            body { flex-direction: column; }
            .summary-grid, .form-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    @include('layouts.sidebar')

    <div class="content" id="mainContent">
        <div class="topbar">
            <h3>Correct Purchase Item</h3>
            <p>Invoice: {{ $purchase->invoice_number }}</p>
        </div>

        <div class="panel">
            @if ($errors->any())
                <div class="alert-danger">
                    <ul style="margin:0; padding-left:18px;">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if($correctionSummary['sale_item_count'] > 0)
                <div class="alert-warning">
                    This correction keeps existing sale receipts on the originally sold drug. Any affected sale lines will be moved to another valid batch of that same sold drug before this purchase item is corrected. If no valid batch has enough stock, the correction will be blocked.
                </div>
            @else
                <div class="alert-info">
                    No sales are tied to this purchase item yet, so this correction only updates the purchase item, linked stock batches, and the audit log.
                </div>
            @endif

            <div class="summary-grid" style="margin-bottom:20px;">
                <div class="summary-box">
                    <h4>Linked Batches</h4>
                    <p>{{ $correctionSummary['batch_count'] }}</p>
                </div>
                <div class="summary-box">
                    <h4>Affected Sale Lines</h4>
                    <p>{{ $correctionSummary['sale_item_count'] }}</p>
                </div>
                <div class="summary-box">
                    <h4>Affected Sales</h4>
                    <p>{{ $correctionSummary['sale_count'] }}</p>
                </div>
                <div class="summary-box">
                    <h4>Other Valid Batches</h4>
                    <p>{{ $correctionSummary['alternative_batch_count'] }}</p>
                </div>
                <div class="summary-box">
                    <h4>Free Stock In Other Batches</h4>
                    <p>{{ number_format((float) $correctionSummary['alternative_free_stock'], 2) }}</p>
                </div>
                <div class="summary-box">
                    <h4>Sold Qty</h4>
                    <p>{{ number_format((float) $correctionSummary['sold_quantity'], 2) }}</p>
                </div>
                <div class="summary-box">
                    <h4>Received Qty</h4>
                    <p>{{ number_format((float) $correctionSummary['received_quantity'], 2) }}</p>
                </div>
                <div class="summary-box">
                    <h4>Available Qty</h4>
                    <p>{{ number_format((float) $correctionSummary['available_quantity'], 2) }}</p>
                </div>
                <div class="summary-box">
                    <h4>Reserved Qty</h4>
                    <p>{{ number_format((float) $correctionSummary['reserved_quantity'], 2) }}</p>
                </div>
                <div class="summary-box">
                    <h4>Current Product</h4>
                    <p>{{ $item->product?->name ?? 'N/A' }}</p>
                </div>
            </div>

            <form method="POST" action="{{ route('purchases.items.updateCorrection', [$purchase->id, $item->id]) }}">
                @csrf
                @method('PUT')

                <div class="form-grid">
                    <div class="form-group">
                        <label>Ordered Quantity</label>
                        <div class="readonly-value">{{ number_format((float) $item->ordered_quantity, 2) }}</div>
                        <div class="muted" style="margin-top:8px;">Quantity stays locked in this correction flow.</div>
                    </div>

                    <div class="form-group">
                        <label>Received Quantity</label>
                        <div class="readonly-value">{{ number_format((float) $item->received_quantity, 2) }}</div>
                        <div class="muted" style="margin-top:8px;">Use stock adjustments for quantity mistakes after receiving.</div>
                    </div>

                    <div class="form-group">
                        <label for="product_id">Correct Product</label>
                        <select name="product_id" id="product_id" required>
                            @foreach($products as $product)
                                <option value="{{ $product->id }}" {{ old('product_id', $item->product_id) == $product->id ? 'selected' : '' }}>
                                    {{ $product->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="batch_number">Correct Batch Number</label>
                        <input type="text" name="batch_number" id="batch_number" value="{{ old('batch_number', $item->batch_number) }}" required>
                    </div>

                    <div class="form-group">
                        <label for="expiry_date">Expiry Date</label>
                        <input type="date" name="expiry_date" id="expiry_date" value="{{ old('expiry_date', optional($item->expiry_date)->format('Y-m-d')) }}">
                    </div>

                    <div class="form-group">
                        <label for="unit_cost">Unit Cost</label>
                        <input type="number" step="0.01" min="0.01" name="unit_cost" id="unit_cost" value="{{ old('unit_cost', $item->unit_cost) }}" required>
                    </div>

                    <div class="form-group">
                        <label for="retail_price">Retail Price</label>
                        <input type="number" step="0.01" min="0" name="retail_price" id="retail_price" value="{{ old('retail_price', $item->retail_price) }}" required>
                    </div>

                    <div class="form-group">
                        <label for="wholesale_price">Wholesale Price</label>
                        <input type="number" step="0.01" min="0" name="wholesale_price" id="wholesale_price" value="{{ old('wholesale_price', $item->wholesale_price) }}" required>
                    </div>

                    <div class="form-group full">
                        <label for="reason">Correction Reason</label>
                        <textarea name="reason" id="reason" rows="4" required>{{ old('reason') }}</textarea>
                        <div class="muted" style="margin-top:8px;">Example: Product was entered as syrup instead of tablets on receiving.</div>
                    </div>
                </div>

                <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:20px;">
                    <button type="submit" class="btn btn-save">Save Correction</button>
                    <a href="{{ route('purchases.show', $purchase->id) }}" class="btn btn-back">Back to Purchase</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
