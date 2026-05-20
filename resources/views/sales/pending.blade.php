<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KIM Rx</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; display: flex; background: #f5f7fb; }

        .content { flex: 1; padding: 20px; }
        .topbar { background: white; padding: 15px; border-radius: 10px; margin-bottom: 20px; }
        .panel { background: white; padding: 20px; border-radius: 10px; }
        .alert-success { background: #e7f6ec; color: #1f7a4f; padding: 12px; border-radius: 8px; margin-bottom: 15px; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 1200px; }
        table th, table td { padding: 10px; border-bottom: 1px solid #ddd; text-align: left; }
        table th { background: #f8f8f8; font-size: 13px; }

        .btn {
            padding: 8px 12px;
            border-radius: 6px;
            color: white;
            text-decoration: none;
            border: none;
            cursor: pointer;
            display: inline-block;
        }

        .btn-view { background: #3949ab; }
        .filters { display: grid; grid-template-columns: minmax(240px, 1fr) auto; gap: 12px; align-items: end; margin-bottom: 18px; }
        .field label { display:block; font-size: 13px; font-weight: bold; margin-bottom: 6px; color:#1f2937; }
        .field input { width: 100%; padding: 9px 10px; border: 1px solid #d1d5db; border-radius: 6px; background:#fff; }
        .filter-actions { display:flex; gap: 8px; align-items: center; }
        .btn-filter { background:#15803d; }
        .btn-reset { background:#64748b; }

        @media (max-width: 900px) {
            body { flex-direction: column; }
        }
    </style>
</head>
<body>
        @include('layouts.sidebar')

    <div class="content" id="mainContent">
        <div class="topbar">
            <h3>Pending Sales</h3>
        </div>

        <div class="panel">
            @if(session('success'))
                <div class="alert-success">{{ session('success') }}</div>
            @endif

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Invoice</th>
                            <th>Sale Date</th>
                            <th>Type</th>
                            <th>Customer</th>
                            <th>Dispensed By</th>
                            <th>Total</th>
                            <th>Payment Type</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($sales as $sale)
                            <tr>
                                <td>{{ $loop->iteration + ($sales->currentPage() - 1) * $sales->perPage() }}</td>
                                <td>{{ $sale->invoice_number }}</td>
                                <td>{{ optional($sale->sale_date)->format('d M Y') }}</td>
                                <td>{{ ucfirst($sale->sale_type) }}</td>
                                <td>{{ $sale->customer?->name ?? 'Walk-in / N/A' }}</td>
                                <td>{{ $sale->servedByUser?->name ?? 'N/A' }}</td>
                                <td>{{ number_format((float) $sale->total_amount, 2) }}</td>
                                <td>{{ $sale->payment_type ? ucfirst($sale->payment_type) : 'Pending' }}</td>
                                <td>
                                    <a href="{{ route('sales.show', ['sale' => $sale->id] + request()->query() + ['return_to' => 'sales.pending']) }}" class="btn btn-view">Open</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9">No pending sales found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div style="margin-top:15px;">
                {{ $sales->links() }}
            </div>
        </div>
    </div>
</body>
</html>