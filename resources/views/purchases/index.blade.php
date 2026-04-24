<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchases - KIM Rx</title>
    <style>
        * { box-sizing: border-box; }

        :root {
            --sidebar-start: #1f7a4f;
            --sidebar-end: #6a1b9a;
            --sidebar-card: rgba(255,255,255,0.08);
            --sidebar-card-hover: rgba(255,255,255,0.16);
            --sidebar-card-active: rgba(255,255,255,0.22);
            --page-bg: #f5f7fb;
            --panel-bg: #ffffff;
            --text-main: #222;
            --text-soft: #666;
            --success: #1f7a4f;
            --danger: #b42318;
            --warning: #a56a00;
            --info: #3949ab;
            --receive: #ff9800;
            --shadow-soft: 0 8px 24px rgba(0,0,0,0.06);
        }

        body { margin: 0; font-family: Arial, sans-serif; background: var(--page-bg); }

        .layout {
            display: flex;
            min-height: 100vh;
        }

        .content {
            flex: 1;
            width: 100%;
            max-width: 100%;
            margin-left: 260px;
            padding: 20px;
            transition: margin-left 0.3s ease;
        }

        .content.expanded { margin-left: 80px; }

        .topbar { background: white; padding: 15px; border-radius: 10px; margin-bottom: 20px; box-shadow: var(--shadow-soft); }
        .panel { background: white; padding: 20px; border-radius: 10px; box-shadow: var(--shadow-soft); }

        .panel-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 16px;
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

        .btn-add { background: green; }
        .btn-view { background: #3949ab; }
        .btn-receive { background: #ff9800; }

        .alert-success {
            background: #e7f6ec;
            color: #1f7a4f;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .table-wrap {
            overflow-x: auto;
            max-width: 100%;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            background: #fff;
        }
        table { width: 100%; border-collapse: collapse; min-width: 1000px; }
        table th, table td { padding: 8px 7px; border-bottom: 1px solid #ddd; text-align: left; font-size: 13px; vertical-align: top; }
        table th { background: #f8f8f8; font-size: 13px; }

        .purchase-table .col-no { width: 42px; }
        .purchase-table .col-invoice { width: 126px; }
        .purchase-table .col-date { width: 88px; }
        .purchase-table .col-supplier { width: 126px; }
        .purchase-table .col-entered { width: 150px; }
        .purchase-table .col-money { width: 92px; }
        .purchase-table .col-payment { width: 98px; }
        .purchase-table .col-invoice-status { width: 108px; }
        .purchase-table .col-action { width: 84px; }

        .action-stack {
            display: flex;
            flex-direction: column;
            gap: 6px;
            align-items: flex-start;
        }

        .purchase-table .btn {
            padding: 6px 9px;
            font-size: 12px;
            white-space: nowrap;
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
        .badge-partial-received { background: #fff4db; color: #a56a00; }
        .badge-closed { background: #eceff1; color: #37474f; }

        .muted { color: #666; font-size: 13px; }
        .inline-note { display:block; margin-top:4px; color:#666; font-size:12px; }

        @media (max-width: 900px) {

            .content,
            .content.expanded {
                margin-left: 0;
            }

            .layout {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="layout">
        @include('layouts.sidebar')

        <div class="content" id="mainContent">
            <div class="topbar">
                <h3>Welcome, {{ $user->name }}</h3>
                <p>Client: {{ $clientName }} | Branch: {{ $branchName }}</p>
            </div>

            <div class="panel">
                <div class="panel-head">
                    <div>
                        <h2 style="margin:0;">Purchases</h2>
                        <p class="muted" style="margin:6px 0 0;">Manage all supplier invoices and balances</p>
                    </div>

                    <a href="{{ route('purchases.create') }}" class="btn btn-add">Add Purchase</a>
                </div>

                @if(session('success'))
                    <div class="alert-success">
                        {{ session('success') }}
                    </div>
                @endif

                <div class="table-wrap">
                    <table class="purchase-table">
                        <colgroup>
                            <col class="col-no">
                            <col class="col-invoice">
                            <col class="col-date">
                            <col class="col-supplier">
                            <col class="col-entered">
                            <col class="col-money">
                            <col class="col-money">
                            <col class="col-money">
                            <col class="col-payment">
                            <col class="col-invoice-status">
                            <col class="col-action">
                        </colgroup>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Invoice Number</th>
                                <th>Purchase Date</th>
                                <th>Supplier</th>
                                <th>Entered By</th>
                                <th>Total Amount</th>
                                <th>Amount Paid</th>
                                <th>Balance Due</th>
                                <th>Payment Status</th>
                                <th>Invoice Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($purchases as $purchase)
                                <tr>
                                    <td>{{ $loop->iteration + ($purchases->currentPage() - 1) * $purchases->perPage() }}</td>
                                    <td>{{ $purchase->invoice_number }}</td>
                                    <td>{{ optional($purchase->purchase_date)->format('d M Y') }}</td>
                                    <td>{{ $purchase->supplier?->name ?? 'N/A' }}</td>
                                    <td>
                                        {{ $purchase->createdByUser?->name ?? 'System' }}
                                        <span class="inline-note">
                                            {{ optional($purchase->created_at)->format('d M Y H:i') ?: 'Entry time unavailable' }}
                                        </span>
                                    </td>
                                    <td>{{ number_format((float) $purchase->total_amount, 2) }}</td>
                                    <td>{{ number_format((float) $purchase->amount_paid, 2) }}</td>
                                    <td>{{ number_format((float) $purchase->balance_due, 2) }}</td>
                                    <td>
                                        @if($purchase->payment_status === 'paid')
                                            <span class="badge badge-paid">Paid</span>
                                        @elseif($purchase->payment_status === 'partial')
                                            <span class="badge badge-partial">Partial</span>
                                        @else
                                            <span class="badge badge-pending">Pending</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($purchase->invoice_status === 'draft')
                                            <span class="badge badge-draft">Draft</span>
                                        @elseif($purchase->invoice_status === 'partial_received')
                                            <span class="badge badge-partial-received">Partial Received</span>
                                        @elseif($purchase->invoice_status === 'closed')
                                            <span class="badge badge-closed">Closed</span>
                                        @else
                                            <span class="badge badge-received">Fully Received</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="action-stack">
                                            <a href="{{ route('purchases.show', $purchase->id) }}" class="btn btn-view">View</a>
                                            <a href="{{ route('purchases.receive', $purchase->id) }}" class="btn btn-receive">Receive</a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="11">No purchases found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div style="margin-top: 15px;">
                    {{ $purchases->links() }}
                </div>
            </div>
        </div>
    </div>

</body>
</html>
