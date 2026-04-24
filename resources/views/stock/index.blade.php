<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock - KIM Rx</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; display: flex; background: #f5f7fb; }
        .content { flex: 1; padding: 20px; }
        .topbar, .panel { background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
        .panel-head { display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:16px; }
        .summary-grid { display:grid; grid-template-columns: repeat(4, minmax(180px, 1fr)); gap:16px; }
        .summary-card { background:#f8fafc; border:1px solid #e5e7eb; border-radius:10px; padding:14px; }
        .summary-card h4 { margin:0 0 8px; font-size:13px; color:#666; }
        .summary-card p { margin:0; font-weight:bold; }
        .btn { padding:8px 12px; border-radius:6px; color:white; text-decoration:none; border:none; cursor:pointer; display:inline-block; }
        .btn-back { background:#3949ab; }
        .btn-adjust { background:#1f7a4f; }
        .btn-view { background:#2563eb; }
        .alert-success { background:#e7f6ec; color:#1f7a4f; padding:12px; border-radius:8px; margin-bottom:15px; }
        .search-form { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:15px; }
        .search-form input { flex:1; min-width:260px; padding:10px; border:1px solid #ddd; border-radius:8px; }
        .table-wrap { overflow-x:auto; }
        table { width:100%; border-collapse:collapse; min-width:1460px; }
        table th, table td { padding:10px; border-bottom:1px solid #ddd; text-align:left; vertical-align:top; }
        table th { background:#f8f8f8; font-size:13px; }
        .muted { color:#666; font-size:13px; }
        .badge { display:inline-block; padding:6px 10px; border-radius:999px; font-size:12px; font-weight:bold; }
        .badge-expired { background:#fdecea; color:#b42318; }
        .badge-expiring { background:#fff4db; color:#9a6700; }
        .badge-safe { background:#e7f6ec; color:#1f7a4f; }
        @media (max-width: 900px) {
            body { flex-direction: column; }
            .summary-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    @include('layouts.sidebar')

    <div class="content" id="mainContent">
        <div class="topbar">
            <h3>Stock Control</h3>
            <p>Client: {{ $clientName }} | Branch: {{ $branchName }}</p>
        </div>

        <div class="panel">
            <div class="panel-head">
                <div>
                    <h2 style="margin:0;">Batch Stock Overview</h2>
                    <p class="muted" style="margin:6px 0 0;">Review batch balances, reserved quantities, expiry, supplier, and purchase invoice details before making any stock adjustment.</p>
                </div>
            </div>

            @if(session('success'))
                <div class="alert-success">{{ session('success') }}</div>
            @endif

            <div class="summary-grid">
                <div class="summary-card">
                    <h4>Active Batches</h4>
                    <p>{{ $batchCount }}</p>
                </div>
                <div class="summary-card">
                    <h4>Available Stock</h4>
                    <p>{{ number_format((float) $availableStock, 2) }}</p>
                </div>
                <div class="summary-card">
                    <h4>Reserved Stock</h4>
                    <p>{{ number_format((float) $reservedStock, 2) }}</p>
                </div>
                <div class="summary-card">
                    <h4>Free Stock</h4>
                    <p>{{ number_format((float) $freeStock, 2) }}</p>
                </div>
            </div>

            <div class="summary-grid" style="margin-top:16px;">
                <div class="summary-card">
                    <h4>Expiring in 90 Days</h4>
                    <p>{{ $expiringSoonCount }}</p>
                </div>
            </div>

            <form method="GET" action="{{ route('stock.index') }}" class="search-form">
                <input type="text" name="search" value="{{ $search }}" placeholder="Search by product, batch, supplier, barcode, strength, or purchase invoice...">
                <button type="submit" class="btn btn-back">Search</button>
            </form>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Batch</th>
                            <th>Supplier</th>
                            <th>Purchase Invoice</th>
                            <th>Expiry</th>
                            <th>Qty Received</th>
                            <th>Qty Available</th>
                            <th>Reserved</th>
                            <th>Free Stock</th>
                            <th>Prices</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($batches as $batch)
                            @php
                                $free = max(0, (float) $batch->quantity_available - (float) $batch->reserved_quantity);
                                $expiry = $batch->expiry_date;
                                $expiryBadge = null;
                                if ($expiry && $expiry->isPast()) {
                                    $expiryBadge = ['class' => 'badge-expired', 'label' => 'Expired'];
                                } elseif ($expiry && $expiry->lte(now()->addDays(90))) {
                                    $expiryBadge = ['class' => 'badge-expiring', 'label' => 'Expiring Soon'];
                                } else {
                                    $expiryBadge = ['class' => 'badge-safe', 'label' => 'OK'];
                                }
                            @endphp
                            <tr>
                                <td>
                                    <strong>{{ $batch->product?->name ?? 'Unknown Product' }}</strong><br>
                                    <span class="muted">
                                        {{ $batch->product?->strength ?: 'No strength' }}
                                        @if($batch->product?->unit?->name)
                                            | {{ $batch->product->unit->name }}
                                        @endif
                                    </span>
                                </td>
                                <td>{{ $batch->batch_number }}</td>
                                <td>{{ $batch->supplier?->name ?? 'N/A' }}</td>
                                <td>{{ $batch->purchaseItem?->purchase?->invoice_number ?? 'N/A' }}</td>
                                <td>
                                    {{ $expiry ? $expiry->format('d M Y') : 'N/A' }}<br>
                                    <span class="badge {{ $expiryBadge['class'] }}">{{ $expiryBadge['label'] }}</span>
                                </td>
                                <td>{{ number_format((float) $batch->quantity_received, 2) }}</td>
                                <td>{{ number_format((float) $batch->quantity_available, 2) }}</td>
                                <td>{{ number_format((float) $batch->reserved_quantity, 2) }}</td>
                                <td>{{ number_format($free, 2) }}</td>
                                <td>
                                    <span class="muted">Buy:</span> {{ number_format((float) $batch->purchase_price, 2) }}<br>
                                    <span class="muted">Retail:</span> {{ number_format((float) $batch->retail_price, 2) }}<br>
                                    <span class="muted">Wholesale:</span> {{ number_format((float) $batch->wholesale_price, 2) }}
                                </td>
                                <td>
                                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                        <a href="{{ route('stock.adjust.create', $batch->id) }}" class="btn btn-adjust">Adjust</a>
                                        @if($batch->product_id)
                                            <a href="{{ route('products.sources', $batch->product_id) }}" class="btn btn-view">Sources</a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11">No stock batches found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div style="margin-top:15px;">
                {{ $batches->withQueryString()->links() }}
            </div>
        </div>

        <div class="panel">
            <div class="panel-head">
                <div>
                    <h2 style="margin:0;">Recent Stock Adjustments</h2>
                    <p class="muted" style="margin:6px 0 0;">Shows what changed, why it changed, who adjusted it, and on which date.</p>
                </div>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Product</th>
                            <th>Batch</th>
                            <th>Invoice</th>
                            <th>Direction</th>
                            <th>Reason</th>
                            <th>Quantity</th>
                            <th>Before</th>
                            <th>After</th>
                            <th>Adjusted By</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($adjustments as $adjustment)
                            <tr>
                                <td>{{ $adjustment->adjustment_date?->format('d M Y H:i') }}</td>
                                <td>{{ $adjustment->product?->name ?? 'N/A' }}</td>
                                <td>{{ $adjustment->batch?->batch_number ?? 'N/A' }}</td>
                                <td>{{ $adjustment->purchase?->invoice_number ?? 'N/A' }}</td>
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
                                <td colspan="11">No stock adjustments have been recorded yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div style="margin-top:15px;">
                {{ $adjustments->withQueryString()->links() }}
            </div>
        </div>
    </div>
</body>
</html>
