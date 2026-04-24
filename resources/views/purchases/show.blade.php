<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Details - KIM Rx</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; display: flex; background: #f5f7fb; }

        .content { flex: 1; width: 100%; max-width: 100%; padding: 20px; }
        .topbar { background: white; padding: 15px; border-radius: 14px; margin-bottom: 20px; box-shadow: 0 12px 30px rgba(15, 23, 42, 0.06); }
        .panel { background: white; padding: 20px; border-radius: 16px; margin-bottom: 20px; box-shadow: 0 14px 34px rgba(15, 23, 42, 0.06); max-width: 100%; }

        .grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(220px, 1fr));
            gap: 16px;
        }

        .info-box {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            padding: 14px;
            border-radius: 10px;
        }

        .info-box h4 {
            margin: 0 0 8px;
            font-size: 13px;
            color: #666;
        }

        .info-box p {
            margin: 0;
            font-weight: bold;
        }

        .badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .badge-paid { background: #e7f6ec; color: #1f7a4f; }
        .badge-partial { background: #fff4db; color: #a56a00; }
        .badge-pending { background: #fdecea; color: #b42318; }

        .badge-draft { background: #eef2ff; color: #3949ab; }
        .badge-received { background: #e7f6ec; color: #1f7a4f; }
        .badge-closed { background: #eceff1; color: #37474f; }
        .badge-corrected { background: #fff4db; color: #9a6700; }
        .badge-sales-protected { background: #eef4ff; color: #1d4ed8; }
        .alert-info { background: #eef4ff; color: #1d4ed8; padding: 12px; border-radius: 8px; margin: 16px 0 0; }
        .alert-warning { background: #fff7e6; color: #9a6700; padding: 12px; border-radius: 8px; margin: 16px 0 0; }
        .summary-grid { display:grid; grid-template-columns: repeat(4, minmax(180px, 1fr)); gap: 16px; margin: 18px 0; }
        .summary-card { background:#f8fafc; border:1px solid #e5e7eb; border-radius: 10px; padding: 14px; }
        .summary-card h4 { margin: 0 0 8px; font-size: 13px; color:#666; }
        .summary-card p { margin: 0; font-weight: bold; }
        .small-note { display:block; margin-top:6px; color:#666; font-size:12px; }

        .table-wrap {
            overflow-x: auto;
            max-width: 100%;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            background: #fff;
        }
        table { width: 100%; border-collapse: collapse; }
        table th, table td { padding: 8px 7px; border-bottom: 1px solid #ddd; text-align: left; font-size: 13px; vertical-align: top; }
        table th { background: #f8f8f8; font-size: 12px; white-space: nowrap; }
        table td { line-height: 1.35; word-break: break-word; }
        .purchase-items-table { min-width: 980px; }
        .correction-table { min-width: 860px; }
        .purchase-items-table .col-no { width: 42px; }
        .purchase-items-table .col-product { width: 130px; }
        .purchase-items-table .col-batch { width: 86px; }
        .purchase-items-table .col-expiry { width: 88px; }
        .purchase-items-table .col-qty { width: 72px; }
        .purchase-items-table .col-price { width: 86px; }
        .purchase-items-table .col-total { width: 94px; }
        .purchase-items-table .col-status { width: 92px; }
        .purchase-items-table .col-correction { width: 146px; }
        .purchase-items-table .col-action { width: 72px; }
        .btn-small { padding: 6px 8px; font-size: 11.5px; border-radius: 6px; }

        .btn {
            padding: 8px 12px;
            border-radius: 6px;
            color: white;
            text-decoration: none;
            border: none;
            cursor: pointer;
            display: inline-block;
            background: green;
        }

        .muted { color: #666; font-size: 13px; }

        @media (max-width: 1100px) {
            .summary-grid { grid-template-columns: repeat(2, minmax(180px, 1fr)); }
        }

        @media (max-width: 900px) {
            body { flex-direction: column; }
            .grid { grid-template-columns: 1fr; }
            .summary-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
        @include('layouts.sidebar')

    <div class="content" id="mainContent">
        <div class="topbar">
            <h3>Purchase Details</h3>
            <p>Invoice: {{ $purchase->invoice_number }}</p>
        </div>

        <div class="panel">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:16px;">
                <div>
                    <h2 style="margin:0;">Invoice Information</h2>
                    <p class="muted" style="margin:6px 0 0;">Supplier invoice, payment, and receiving summary</p>
                </div>

                <div style="display:flex; gap:10px; flex-wrap:wrap;">
    <a href="{{ route('purchases.index') }}" class="btn">Back to Purchases</a>
    <a href="{{ route('purchases.edit', $purchase->id) }}" class="btn" style="background:#ff9800;">Edit Header</a>
    <a href="{{ route('purchases.add-items', $purchase->id) }}" class="btn" style="background:#1f7a4f;">Add Items</a>
</div>
            </div>

            @if($stockSummary['has_locked_stock'])
                <div class="alert-warning">
                    Supplier changes are locked on this purchase because some received stock has already been reserved or sold.
                </div>
            @elseif($stockSummary['has_received_stock'])
                <div class="alert-info">
                    Supplier can still be changed on this purchase because the received stock has not been reserved or sold yet.
                </div>
            @endif

            <div class="grid">
                <div class="info-box">
                    <h4>Invoice Number</h4>
                    <p>{{ $purchase->invoice_number }}</p>
                </div>

                <div class="info-box">
                    <h4>Supplier</h4>
                    <p>{{ $purchase->supplier?->name ?? 'N/A' }}</p>
                </div>

                <div class="info-box">
                    <h4>Purchase Date</h4>
                    <p>{{ optional($purchase->purchase_date)->format('d M Y') }}</p>
                </div>

                <div class="info-box">
                    <h4>Entered By</h4>
                    <p>{{ $purchase->createdByUser?->name ?? 'System' }}</p>
                    <span class="small-note">
                        Captured {{ optional($purchase->created_at)->format('d M Y H:i') ?: 'N/A' }}
                    </span>
                </div>

                <div class="info-box">
                    <h4>Payment Type</h4>
                    <p>{{ ucfirst($purchase->payment_type) }}</p>
                </div>

                <div class="info-box">
                    <h4>Total Amount</h4>
                    <p>{{ number_format((float) $purchase->total_amount, 2) }}</p>
                </div>

                <div class="info-box">
                    <h4>Amount Paid</h4>
                    <p>{{ number_format((float) $purchase->amount_paid, 2) }}</p>
                </div>

                <div class="info-box">
                    <h4>Balance Due</h4>
                    <p>{{ number_format((float) $purchase->balance_due, 2) }}</p>
                </div>

                <div class="info-box">
                    <h4>Due Date</h4>
                    <p>{{ $purchase->due_date ? $purchase->due_date->format('d M Y') : 'N/A' }}</p>
                </div>

                <div class="info-box">
                    <h4>Payment Status</h4>
                    <p>
                        @if($purchase->payment_status === 'paid')
                            <span class="badge badge-paid">Paid</span>
                        @elseif($purchase->payment_status === 'partial')
                            <span class="badge badge-partial">Partial</span>
                        @else
                            <span class="badge badge-pending">Pending</span>
                        @endif
                    </p>
                </div>

                <div class="info-box">
                    <h4>Invoice Status</h4>
                    <p>
                        @if($purchase->invoice_status === 'draft')
                            <span class="badge badge-draft">Draft</span>
                        @elseif($purchase->invoice_status === 'closed')
                            <span class="badge badge-closed">Closed</span>
                        @else
                            <span class="badge badge-received">Fully Received</span>
                        @endif
                    </p>
                </div>

                <div class="info-box">
                    <h4>Received Stock</h4>
                    <p>{{ number_format((float) $stockSummary['received_quantity'], 2) }}</p>
                </div>

                <div class="info-box">
                    <h4>Available Stock</h4>
                    <p>{{ number_format((float) $stockSummary['available_quantity'], 2) }}</p>
                </div>

                <div class="info-box">
                    <h4>Reserved Stock</h4>
                    <p>{{ number_format((float) $stockSummary['reserved_quantity'], 2) }}</p>
                </div>

                <div class="info-box">
                    <h4>Sold Stock</h4>
                    <p>{{ number_format((float) $stockSummary['sold_quantity'], 2) }}</p>
                </div>

                <div class="info-box" style="grid-column: 1 / -1;">
                    <h4>Notes</h4>
                    <p>{{ $purchase->notes ?: 'No notes provided.' }}</p>
                </div>
            </div>
        </div>

        <div class="panel">
            <h2 style="margin-top:0;">Purchase Items</h2>
            <p class="muted" style="margin-top:0;">All products captured under this invoice</p>
            @php
                $correctionsByItem = $purchase->corrections->groupBy('purchase_item_id');
            @endphp

            <div class="table-wrap">
                <table class="purchase-items-table">
                    <colgroup>
                        <col class="col-no">
                        <col class="col-product">
                        <col class="col-batch">
                        <col class="col-expiry">
                        <col class="col-qty">
                        <col class="col-qty">
                        <col class="col-qty">
                        <col class="col-price">
                        <col class="col-price">
                        <col class="col-price">
                        <col class="col-total">
                        <col class="col-status">
                        <col class="col-correction">
                        <col class="col-action">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Product</th>
                            <th>Batch Number</th>
                            <th>Expiry Date</th>
                            <th>Ordered Qty</th>
                            <th>Received Qty</th>
                            <th>Remaining Qty</th>
                            <th>Unit Cost</th>
                            <th>Retail Price</th>
                            <th>Wholesale Price</th>
                            <th>Total Cost</th>
                            <th>Line Status</th>
                            <th>Correction Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($purchase->items as $item)
                            @php
                                $itemCorrections = $correctionsByItem->get($item->id, collect());
                                $latestCorrection = $itemCorrections->first();
                            @endphp
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $item->product?->name ?? 'N/A' }}</td>
                                <td>{{ $item->batch_number }}</td>
                                <td>{{ $item->expiry_date ? $item->expiry_date->format('d M Y') : 'N/A' }}</td>
                                <td>{{ number_format((float) $item->ordered_quantity, 2) }}</td>
                                <td>{{ number_format((float) $item->received_quantity, 2) }}</td>
                                <td>{{ number_format((float) $item->remaining_quantity, 2) }}</td>
                                <td>{{ number_format((float) $item->unit_cost, 2) }}</td>
                                <td>{{ number_format((float) $item->retail_price, 2) }}</td>
                                <td>{{ number_format((float) $item->wholesale_price, 2) }}</td>
                                <td>{{ number_format((float) $item->total_cost, 2) }}</td>
                                <td>{{ ucwords(str_replace('_', ' ', $item->line_status)) }}</td>
                                <td>
                                    @if($itemCorrections->isNotEmpty())
                                        <span class="badge badge-corrected">
                                            Corrected {{ $itemCorrections->count() }} {{ $itemCorrections->count() === 1 ? 'time' : 'times' }}
                                        </span>
                                        @if((int) ($latestCorrection?->affected_sale_count ?? 0) > 0)
                                            <span class="badge badge-sales-protected" style="margin-top:6px;">Sales Preserved</span>
                                        @endif
                                        <span class="small-note">
                                            Last change:
                                            {{ $latestCorrection?->created_at?->format('d M Y H:i') ?? 'N/A' }}
                                        </span>
                                    @else
                                        <span class="muted">No correction logged</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('purchases.items.correct', [$purchase->id, $item->id]) }}" class="btn btn-small" style="background:#ff9800;">
                                        Correct
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="14">No purchase items found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($purchase->corrections->isNotEmpty())
            @php
                $latestCorrection = $purchase->corrections->first();
                $totalAffectedSales = (int) $purchase->corrections->sum('affected_sale_count');
                $totalAffectedSaleItems = (int) $purchase->corrections->sum('affected_sale_item_count');
            @endphp
            <div class="panel">
                <h2 style="margin-top:0;">Correction History</h2>
                <p class="muted" style="margin-top:0;">Every stock-sensitive purchase correction stays visible here for traceability.</p>
                <div class="alert-info" style="margin-top:12px;">
                    Customer receipts remain unchanged. Corrections move the source batch and purchase record while preserving the original sale history.
                </div>

                <div class="summary-grid">
                    <div class="summary-card">
                        <h4>Corrections Logged</h4>
                        <p>{{ $purchase->corrections->count() }}</p>
                    </div>
                    <div class="summary-card">
                        <h4>Sales Preserved</h4>
                        <p>{{ $totalAffectedSales }}</p>
                    </div>
                    <div class="summary-card">
                        <h4>Sale Lines Reassigned</h4>
                        <p>{{ $totalAffectedSaleItems }}</p>
                    </div>
                    <div class="summary-card">
                        <h4>Last Correction</h4>
                        <p>{{ $latestCorrection?->created_at?->format('d M Y H:i') ?? 'N/A' }}</p>
                        <span class="small-note">{{ $latestCorrection?->correctedBy?->name ?? 'System' }}</span>
                    </div>
                </div>

                <div class="table-wrap">
                    <table class="correction-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Changed By</th>
                                <th>From Product</th>
                                <th>To Product</th>
                                <th>From Batch</th>
                                <th>To Batch</th>
                                <th>Affected Sales</th>
                                <th>Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($purchase->corrections as $correction)
                                <tr>
                                    <td>{{ $correction->created_at?->format('d M Y H:i') }}</td>
                                    <td>{{ $correction->correctedBy?->name ?? 'System' }}</td>
                                    <td>{{ $correction->oldProduct?->name ?? 'N/A' }}</td>
                                    <td>{{ $correction->newProduct?->name ?? 'N/A' }}</td>
                                    <td>{{ $correction->old_batch_number ?: 'N/A' }}</td>
                                    <td>{{ $correction->new_batch_number ?: 'N/A' }}</td>
                                    <td>{{ $correction->affected_sale_count }}</td>
                                    <td>{{ $correction->reason }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</body>
</html>
