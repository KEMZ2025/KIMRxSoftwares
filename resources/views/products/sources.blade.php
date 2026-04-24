<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Sources - KIM Rx</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; display: flex; min-height: 100vh; background: #f4f6fb; color: #222; }

        .content { flex: 1; padding: 24px; }
        .topbar, .panel {
            background: white;
            border-radius: 18px;
            padding: 20px 22px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.06);
        }

        .topbar { margin-bottom: 22px; }
        .topbar h3 { margin: 0 0 8px; }
        .topbar p { margin: 0; color: #666; }

        .panel {
            margin-bottom: 20px;
        }

        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 18px;
        }

        .panel-header h2 { margin: 0 0 6px; }
        .panel-header p { margin: 0; color: #666; }

        .btn {
            display: inline-block;
            padding: 10px 14px;
            border-radius: 10px;
            color: white;
            text-decoration: none;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
        }

        .btn-back { background: #6a1b9a; }
        .btn-sale { background: #1565c0; }

        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 1100px; }
        table th, table td {
            padding: 13px 12px;
            border-bottom: 1px solid #e7e7e7;
            text-align: left;
            font-size: 14px;
            vertical-align: middle;
        }

        table th {
            background: #fafafa;
            font-size: 13px;
            text-transform: uppercase;
            color: #444;
        }

        .empty-row {
            text-align: center;
            color: #666;
            padding: 20px 0;
        }

        @media (max-width: 900px) {
            body { flex-direction: column; }
            .content { padding: 16px; }
        }
    </style>
</head>
<body>

    @include('layouts.sidebar')

    <main class="content" id="mainContent">
        <div class="topbar">
            <h3>Welcome, {{ $user->name }}</h3>
            <p>Client: {{ $clientName }} | Branch: {{ $branchName }}</p>
        </div>

        <section class="panel">
            <div class="panel-header">
                <div>
                    <h2>Purchase Sources for {{ $product->name }}</h2>
                    <p>See where this drug was bought from by batch and supplier.</p>
                </div>

                <a href="{{ route('products.index') }}" class="btn btn-back">Back to Products</a>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Supplier</th>
                            <th>Batch No</th>
                            <th>Expiry</th>
                            <th>Purchase Price</th>
                            <th>Retail Price</th>
                            <th>Wholesale Price</th>
                            <th>Qty Received</th>
                            <th>Qty Available</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($batches as $batch)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $batch->supplier?->name ?? 'N/A' }}</td>
                                <td>{{ $batch->batch_number ?? 'N/A' }}</td>
                                <td>{{ $batch->expiry_date ? $batch->expiry_date->format('Y-m-d') : 'N/A' }}</td>
                                <td>{{ number_format((float) $batch->purchase_price, 2) }}</td>
                                <td>{{ number_format((float) $batch->retail_price, 2) }}</td>
                                <td>{{ number_format((float) $batch->wholesale_price, 2) }}</td>
                                <td>{{ number_format((float) $batch->quantity_received, 2) }}</td>
                                <td>{{ number_format((float) $batch->quantity_available, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="empty-row">No purchase source history found for this product.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel">
            <div class="panel-header">
                <div>
                    <h2>Sales History for {{ $product->name }}</h2>
                    <p>See who bought this drug, on which date, and open the full sale to view other items.</p>
                </div>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Sale Date</th>
                            <th>Customer</th>
                            <th>Invoice No</th>
                            <th>Receipt No</th>
                            <th>Batch</th>
                            <th>Qty Sold</th>
                            <th>Unit Price</th>
                            <th>Total</th>
                            <th>Served By</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($salesHistory as $item)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ optional($item->sale?->sale_date)->format('Y-m-d') ?? 'N/A' }}</td>
                                <td>{{ $item->sale?->customer?->name ?? 'Walk-in / N/A' }}</td>
                                <td>{{ $item->sale?->invoice_number ?? 'N/A' }}</td>
                                <td>{{ $item->sale?->receipt_number ?? 'N/A' }}</td>
                                <td>{{ $item->batch?->batch_number ?? 'N/A' }}</td>
                                <td>{{ number_format((float) $item->quantity, 2) }}</td>
                                <td>{{ number_format((float) $item->unit_price, 2) }}</td>
                                <td>{{ number_format((float) $item->total_amount, 2) }}</td>
                                <td>{{ $item->sale?->servedByUser?->name ?? 'N/A' }}</td>
                                <td>
                                    @if($item->sale)
                                        <a href="{{ route('sales.show', $item->sale->id) }}" class="btn btn-sale">Open Sale</a>
                                    @else
                                        N/A
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="empty-row">No sales history found for this product.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </main>

</body>
</html>
