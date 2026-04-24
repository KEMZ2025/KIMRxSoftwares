<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Adjust Stock - KIM Rx</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; display: flex; background: #f5f7fb; }
        .content { flex: 1; padding: 20px; }
        .topbar, .panel { background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
        .summary-grid { display:grid; grid-template-columns: repeat(4, minmax(180px, 1fr)); gap:16px; margin-bottom:16px; }
        .summary-card { background:#f8fafc; border:1px solid #e5e7eb; border-radius:10px; padding:14px; }
        .summary-card h4 { margin:0 0 8px; font-size:13px; color:#666; }
        .summary-card p { margin:0; font-weight:bold; }
        .form-grid { display:grid; grid-template-columns: repeat(2, minmax(220px, 1fr)); gap:16px; }
        .form-group { display:flex; flex-direction:column; }
        .form-group label { margin-bottom:8px; font-weight:bold; }
        .form-group input, .form-group select, .form-group textarea { padding:10px; border:1px solid #ddd; border-radius:8px; }
        .field-hint { margin-top:6px; color:#666; font-size:13px; }
        .full { grid-column: 1 / -1; }
        .btn { padding:8px 12px; border-radius:6px; color:white; text-decoration:none; border:none; cursor:pointer; display:inline-block; }
        .btn-save { background:#1f7a4f; }
        .btn-back { background:#3949ab; }
        .btn-secondary { background:#2563eb; }
        .alert-danger { background:#fdecea; color:#b42318; padding:12px; border-radius:8px; margin-bottom:15px; }
        .alert-info { background:#eef4ff; color:#1d4ed8; padding:12px; border-radius:8px; margin-bottom:16px; }
        .muted { color:#666; font-size:13px; }
        .table-wrap { overflow-x:auto; }
        table { width:100%; border-collapse:collapse; min-width:980px; }
        table th, table td { padding:10px; border-bottom:1px solid #ddd; text-align:left; vertical-align:top; }
        table th { background:#f8f8f8; font-size:13px; }
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
            <h3>Adjust Stock</h3>
            <p>Client: {{ $clientName }} | Branch: {{ $branchName }}</p>
        </div>

        <div class="panel">
            <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; align-items:center; margin-bottom:16px;">
                <div>
                    <h2 style="margin:0;">Batch {{ $batch->batch_number }}</h2>
                    <p class="muted" style="margin:6px 0 0;">Adjust this batch only. Decreases cannot touch reserved stock.</p>
                </div>
                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <a href="{{ route('stock.index') }}" class="btn btn-back">Back to Stock</a>
                    @if($batch->product_id)
                        <a href="{{ route('products.sources', $batch->product_id) }}" class="btn btn-secondary">Product Sources</a>
                    @endif
                </div>
            </div>

            @if ($errors->any())
                <div class="alert-danger">
                    <ul style="margin:0; padding-left:18px;">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="summary-grid">
                <div class="summary-card">
                    <h4>Product</h4>
                    <p>{{ $batch->product?->name ?? 'Unknown Product' }}</p>
                </div>
                <div class="summary-card">
                    <h4>Supplier</h4>
                    <p>{{ $batch->supplier?->name ?? 'N/A' }}</p>
                </div>
                <div class="summary-card">
                    <h4>Purchase Invoice</h4>
                    <p>{{ $batch->purchaseItem?->purchase?->invoice_number ?? 'N/A' }}</p>
                </div>
                <div class="summary-card">
                    <h4>Expiry Date</h4>
                    <p>{{ $batch->expiry_date ? $batch->expiry_date->format('d M Y') : 'N/A' }}</p>
                </div>
            </div>

            <div class="summary-grid" style="margin-top:16px;">
                <div class="summary-card">
                    <h4>Qty Received</h4>
                    <p>{{ number_format((float) $batch->quantity_received, 2) }}</p>
                </div>
                <div class="summary-card">
                    <h4>Qty Available</h4>
                    <p>{{ number_format((float) $batch->quantity_available, 2) }}</p>
                </div>
                <div class="summary-card">
                    <h4>Reserved Stock</h4>
                    <p>{{ number_format((float) $batch->reserved_quantity, 2) }}</p>
                </div>
                <div class="summary-card">
                    <h4>Free Stock</h4>
                    <p>{{ number_format((float) $freeStock, 2) }}</p>
                </div>
            </div>
        </div>

        <div class="panel">
            <h2 style="margin-top:0;">Record Adjustment</h2>

            <div class="alert-info" id="adjustmentHelpBox">
                Enter only the stock amount you want to add or remove on this batch, not the final batch balance.
            </div>

            <form method="POST" action="{{ route('stock.adjust.store', $batch->id) }}">
                @csrf

                <div class="form-grid">
                    <div class="form-group">
                        <label for="direction">Adjustment Direction *</label>
                        <select name="direction" id="direction" required>
                            <option value="">Select direction</option>
                            @foreach($directionOptions as $value => $label)
                                <option value="{{ $value }}" {{ old('direction') === $value ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="reason">Reason *</label>
                        <select name="reason" id="reason" required>
                            <option value="">Select reason</option>
                            @foreach($reasonOptions as $value => $label)
                                <option value="{{ $value }}" {{ old('reason') === $value ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="quantity">Adjustment Quantity *</label>
                        <input type="number" step="0.01" min="0.01" name="quantity" id="quantity" value="{{ old('quantity') }}" placeholder="Enter amount to change" required>
                        <span class="field-hint" id="quantityHint">
                            Enter only the stock you want to change, not the final available quantity.
                        </span>
                    </div>

                    <div class="form-group">
                        <label for="adjustment_date">Adjustment Date *</label>
                        <input type="datetime-local" name="adjustment_date" id="adjustment_date" value="{{ old('adjustment_date', now()->format('Y-m-d\\TH:i')) }}" required>
                    </div>

                    <div class="form-group full">
                        <label for="note">Notes</label>
                        <textarea name="note" id="note" rows="4" placeholder="Add extra details, especially if the reason is Other.">{{ old('note') }}</textarea>
                    </div>
                </div>

                <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:16px;">
                    <button type="submit" class="btn btn-save">Save Adjustment</button>
                    <a href="{{ route('stock.index') }}" class="btn btn-back">Back to Stock</a>
                </div>
            </form>
        </div>

        <div class="panel">
            <h2 style="margin-top:0;">Recent Adjustments on This Batch</h2>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Direction</th>
                            <th>Reason</th>
                            <th>Quantity</th>
                            <th>Available Before</th>
                            <th>Available After</th>
                            <th>Adjusted By</th>
                            <th>Note</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($batch->stockAdjustments->take(10) as $adjustment)
                            <tr>
                                <td>{{ $adjustment->adjustment_date?->format('d M Y H:i') }}</td>
                                <td>{{ $adjustment->direction_label }}</td>
                                <td>{{ $adjustment->reason_label }}</td>
                                <td>{{ number_format((float) $adjustment->quantity, 2) }}</td>
                                <td>{{ number_format((float) $adjustment->quantity_available_before, 2) }}</td>
                                <td>{{ number_format((float) $adjustment->quantity_available_after, 2) }}</td>
                                <td>{{ $adjustment->adjustedByUser?->name ?? 'System' }}</td>
                                <td>{{ $adjustment->note ?: 'No notes' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8">No adjustments have been recorded on this batch yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const directionInput = document.getElementById('direction');
            const quantityHint = document.getElementById('quantityHint');
            const helpBox = document.getElementById('adjustmentHelpBox');
            const freeStock = '{{ number_format((float) $freeStock, 2) }}';

            if (!directionInput || !quantityHint || !helpBox) {
                return;
            }

            function updateAdjustmentHelp() {
                if (directionInput.value === 'increase') {
                    quantityHint.textContent = 'Enter how much stock you are adding to this batch, not the final available quantity.';
                    helpBox.textContent = 'Increase means add extra stock onto this batch. Enter only the amount being added.';
                    return;
                }

                if (directionInput.value === 'decrease') {
                    quantityHint.textContent = 'Enter how much stock you are removing from this batch, not the final available quantity.';
                    helpBox.textContent = 'Decrease means remove stock from this batch. Enter only the amount being removed. It cannot be more than the current free stock of ' + freeStock + '.';
                    return;
                }

                quantityHint.textContent = 'Enter only the stock you want to change, not the final available quantity.';
                helpBox.textContent = 'Enter only the stock amount you want to add or remove on this batch, not the final batch balance.';
            }

            directionInput.addEventListener('change', updateAdjustmentHelp);
            updateAdjustmentHelp();
        })();
    </script>
</body>
</html>
