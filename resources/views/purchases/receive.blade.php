<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receive Items - KIM Rx</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; display: flex; background: #f5f7fb; }

        .content { flex: 1; padding: 20px; }
        .topbar { background: white; padding: 15px; border-radius: 10px; margin-bottom: 20px; }
        .panel { background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; }

        .alert-danger { background: #fdecea; color: #b42318; padding: 12px; border-radius: 8px; margin-bottom: 15px; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 1050px; }
        table th, table td { padding: 10px; border-bottom: 1px solid #ddd; text-align: left; }
        table th { background: #f8f8f8; font-size: 13px; }

        .mini-input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 6px;
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

        .muted { color: #666; font-size: 13px; }

        @media (max-width: 900px) {
            body { flex-direction: column; }
        }
    </style>
</head>
<body>
        @include('layouts.sidebar')

    <div class="content" id="mainContent">
        <div class="topbar">
            <h3>Receive Items</h3>
            <p>Invoice: {{ $purchase->invoice_number }} | Supplier: {{ $purchase->supplier?->name ?? 'N/A' }}</p>
        </div>

        <div class="panel">
            <h2 style="margin-top:0;">Receive Remaining Items</h2>
            <p class="muted">Enter only the quantity being received now.</p>

            @if ($errors->any())
                <div class="alert-danger">
                    <ul style="margin:0; padding-left:18px;">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('purchases.storeReceive', $purchase->id) }}">
                @csrf

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Product</th>
                                <th>Batch Number</th>
                                <th>Expiry Date</th>
                                <th>Ordered</th>
                                <th>Received</th>
                                <th>Remaining</th>
                                <th>Receive Now</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($purchase->items as $index => $item)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $item->product?->name ?? 'N/A' }}</td>
                                    <td>{{ $item->batch_number }}</td>
                                    <td>{{ $item->expiry_date ? $item->expiry_date->format('d M Y') : 'N/A' }}</td>
                                    <td>{{ number_format((float) $item->ordered_quantity, 2) }}</td>
                                    <td>{{ number_format((float) $item->received_quantity, 2) }}</td>
                                    <td>{{ number_format((float) $item->remaining_quantity, 2) }}</td>
                                    <td>
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            max="{{ (float) $item->remaining_quantity }}"
                                            name="received_quantity[]"
                                            class="mini-input"
                                            value="0"
                                        >
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8">No purchase items found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div style="margin-top:16px; display:flex; gap:10px; flex-wrap:wrap;">
                    <button type="submit" class="btn btn-save">Save Receiving</button>
                    <a href="{{ route('purchases.show', $purchase->id) }}" class="btn btn-back">Back to Details</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>