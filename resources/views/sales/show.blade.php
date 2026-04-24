<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $sale->status === 'proforma' ? 'Proforma Invoice Details' : 'Sale Details' }} - KIM Rx</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; display: flex; background: #f5f7fb; }

        .content { flex: 1; padding: 20px; }
        .topbar, .panel { background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
        .grid { display: grid; grid-template-columns: repeat(3, minmax(220px, 1fr)); gap: 16px; }
        .info-box { background: #f8fafc; border: 1px solid #e5e7eb; padding: 14px; border-radius: 10px; }
        .info-box h4 { margin: 0 0 8px; font-size: 13px; color: #666; }
        .info-box p { margin: 0; font-weight: bold; }
        .alert-success { background: #e7f6ec; color: #1f7a4f; padding: 12px; border-radius: 8px; margin-bottom: 15px; }
        .alert-danger { background: #fdecea; color: #b42318; padding: 12px; border-radius: 8px; margin-bottom: 15px; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 1200px; }
        table th, table td { padding: 10px; border-bottom: 1px solid #ddd; text-align: left; }
        table th { background: #f8f8f8; font-size: 13px; }
        .btn { padding: 8px 12px; border-radius: 6px; color: white; text-decoration: none; border: none; cursor: pointer; display: inline-block; }
        .btn-back { background: #3949ab; }
        .btn-approve { background: green; }
        .btn-cancel { background: red; }
        .btn-reverse { background: #b42318; }
        .btn-restore { background: #0f766e; }
        .btn-convert { background: #1d4ed8; }
        .form-row { display: grid; grid-template-columns: repeat(3, minmax(220px, 1fr)); gap: 16px; margin-top: 16px; }
        .form-group { display: flex; flex-direction: column; }
        .form-group label { margin-bottom: 8px; font-weight: bold; }
        .form-group input, .form-group select, .form-group textarea { padding: 10px; border: 1px solid #ddd; border-radius: 8px; }
        .badge { display:inline-block; padding:6px 10px; border-radius:999px; font-size:12px; font-weight:bold; }
        .badge-reversal { background:#fdecea; color:#b42318; }
        .badge-payment { background:#e7f6ec; color:#1f7a4f; }
        .badge-cancelled { background:#fdecea; color:#b42318; }
        .badge-restored { background:#eef2ff; color:#3949ab; }
        .badge-legacy { background:#fff7ed; color:#9a3412; }
        .badge-efris-ready { background:#ecfdf3; color:#067647; }
        .badge-efris-pending { background:#eff6ff; color:#1d4ed8; }
        .badge-efris-warn { background:#fff7ed; color:#9a3412; }
        .badge-efris-failed { background:#fee4e2; color:#b42318; }
        .muted { color:#666; }
        .audit-grid { display:grid; grid-template-columns: repeat(4, minmax(180px, 1fr)); gap: 14px; }
        .audit-note { margin-top: 14px; padding: 14px; border-radius: 10px; background: #fff7ed; color: #9a3412; border: 1px solid #fed7aa; }
        .audit-note strong { display:block; margin-bottom: 4px; }
        .insurance-panel {
            margin-top: 16px;
            padding: 16px;
            border-radius: 14px;
            border: 1px solid #d7e8f3;
            background: #f8fbff;
        }
        .insurance-panel-head h4 { margin: 0 0 4px; }
        .insurance-panel-head p { margin: 0 0 14px; font-size: 12px; color: #526071; }
        .insurance-summary-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(160px, 1fr));
            gap: 12px;
            margin-top: 14px;
        }
        .insurance-summary-box {
            background: #fff;
            border: 1px solid #d7e8f3;
            border-radius: 12px;
            padding: 12px;
        }
        .insurance-summary-label {
            display: block;
            font-size: 11px;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: #526071;
            margin-bottom: 6px;
        }

        @media (max-width: 900px) {
            body { flex-direction: column; }
            .grid, .form-row, .audit-grid, .insurance-summary-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
        @include('layouts.sidebar')

    @php
        $isLegacyPaymentMethod = $sale->payment_method === 'Legacy Unspecified';
        $efrisDocument = $sale->efrisDocument;
        $efrisBadgeClass = match (true) {
            !$efrisEnabled => 'badge-legacy',
            !$efrisDocument && $efrisEnabled => 'badge-efris-warn',
            $efrisDocument?->status === 'failed' => 'badge-efris-failed',
            $efrisDocument?->status === 'accepted' => 'badge-efris-ready',
            $efrisDocument?->status === 'submitted' => 'badge-efris-pending',
            $efrisDocument?->next_action === 'submit_reversal' => 'badge-efris-warn',
            default => 'badge-efris-pending',
        };
        $efrisLabel = !$efrisEnabled
            ? 'Module Disabled'
            : ($efrisDocument?->statusLabel()
                ?? ($sale->status === 'approved' ? 'Not Prepared Yet' : 'Will Prepare On Approval'));
    @endphp

    <div class="content" id="mainContent">
        <div class="topbar">
            <h3>{{ $sale->status === 'proforma' ? 'Proforma Invoice Details' : 'Sale Details' }}</h3>
            <p>Invoice: {{ $sale->invoice_number }}</p>
        </div>

        <div class="panel">
            @if(session('success'))
                <div class="alert-success">{{ session('success') }}</div>
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

            <div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:16px;">
                <a href="{{ $backUrl }}" class="btn btn-back">{{ $backLabel }}</a>
                @if($printOptions['small'])
                    <a href="{{ route('sales.print.pos', ['sale' => $sale->id, 'autoprint' => 1]) }}" class="btn btn-back" style="background:#0f766e;" target="_blank" rel="noopener">
                        {{ $sale->status === 'approved' ? 'Print POS Receipt' : ($sale->status === 'proforma' ? 'Print POS Proforma' : 'Print POS Invoice') }}
                    </a>
                @endif
                @if($printOptions['large'])
                    <a href="{{ route('sales.print.a4', ['sale' => $sale->id, 'autoprint' => 1]) }}" class="btn btn-back" style="background:#155eef;" target="_blank" rel="noopener">
                        {{ $sale->status === 'approved' ? 'Print A4 Receipt' : ($sale->status === 'proforma' ? 'Print A4 Proforma' : 'Print A4 Invoice') }}
                    </a>
                @endif

                @if($sale->status === 'pending' && $user->hasPermission('sales.edit'))
                    <a href="{{ route('sales.edit', $sale->id) }}" class="btn btn-back" style="background:#ff9800;">Edit Pending Sale</a>
                @endif

                @if($sale->status === 'proforma' && $user->hasPermission('sales.proforma'))
                    <a href="{{ route('sales.editProforma', $sale->id) }}" class="btn btn-back" style="background:#ff9800;">Edit Proforma Invoice</a>
                @endif

                @if($sale->status === 'approved' && $user->hasPermission('sales.edit_approved'))
                    <a href="{{ route('sales.editApproved', $sale->id) }}" class="btn btn-back" style="background:#ff9800;">Edit Approved Sale</a>
                @endif

                @if($sale->isInsuranceSale() && $user->hasAnyPermission(['insurance.view', 'insurance.manage']) && \Illuminate\Support\Facades\Route::has('insurance.claims.show'))
                    <a href="{{ route('insurance.claims.show', $sale->id) }}" class="btn btn-back" style="background:#0f766e;">Open Claim Desk</a>
                @endif

                @if($sale->status === 'approved' && !$sale->isInsuranceSale() && $sale->customer_id && (float) $sale->balance_due > 0 && $user->hasPermission('customers.collections.collect'))
                    <a href="{{ route('customers.collections.create', $sale->id) }}" class="btn btn-approve" style="background:#0f766e;">Receive Payment</a>
                @endif

                @if($sale->status === 'proforma' && $user->hasPermission('sales.create'))
                    <form method="POST" action="{{ route('sales.proforma.convert', $sale->id) }}" style="margin:0;">
                        @csrf
                        <button type="submit" class="btn btn-convert">Convert to Pending Sale</button>
                    </form>
                @endif
            </div>

            <div class="grid">
                <div class="info-box">
                    <h4>Invoice Number</h4>
                    <p>{{ $sale->invoice_number }}</p>
                </div>
                <div class="info-box">
                    <h4>Receipt Number</h4>
                    <p>
                        @if($sale->status === 'proforma' && !$sale->receipt_number)
                            Not generated for proforma stage
                        @else
                            {{ $sale->receipt_number ?? 'Not generated yet' }}
                        @endif
                    </p>
                </div>
                <div class="info-box">
                    <h4>Status</h4>
                    <p>{{ ucfirst($sale->status) }}</p>
                </div>
                <div class="info-box">
                    <h4>URA / EFRIS</h4>
                    <p><span class="badge {{ $efrisBadgeClass }}">{{ $efrisLabel }}</span></p>
                </div>
                <div class="info-box">
                    <h4>Sale Type</h4>
                    <p>{{ ucfirst($sale->sale_type) }}</p>
                </div>
                <div class="info-box">
                    <h4>Payment Type</h4>
                    <p>{{ $sale->payment_type ? ucfirst($sale->payment_type) : 'Pending' }}</p>
                </div>
                <div class="info-box">
                    <h4>Payment Method</h4>
                    <p>
                        @if($sale->status === 'proforma' && !$sale->payment_method)
                            Not applicable until converted
                        @else
                            {{ $sale->payment_method ?? 'Not set yet' }}
                        @endif
                    </p>
                    @if($isLegacyPaymentMethod)
                        <div style="margin-top:10px;">
                            <span class="badge badge-legacy">Legacy repaired record</span>
                        </div>
                    @endif
                </div>
                <div class="info-box">
                    <h4>Customer</h4>
                    <p>{{ $sale->customer?->name ?? 'Walk-in / N/A' }}</p>
                </div>
                <div class="info-box">
                    <h4>Dispensed By</h4>
                    <p>{{ $sale->servedByUser?->name ?? 'N/A' }}</p>
                </div>
                <div class="info-box">
                    <h4>Approved By</h4>
                    <p>{{ $sale->approvedByUser?->name ?? ($sale->status === 'approved' ? 'N/A' : 'Not approved yet') }}</p>
                </div>
                <div class="info-box">
                    <h4>Total Amount</h4>
                    <p>{{ number_format((float) $sale->total_amount, 2) }}</p>
                </div>
                <div class="info-box">
                    <h4>Amount Received</h4>
                    <p>{{ number_format((float) $sale->amount_received, 2) }}</p>
                </div>
                <div class="info-box">
                    <h4>Amount Applied (Paid)</h4>
                    <p>{{ number_format((float) $sale->amount_paid, 2) }}</p>
                </div>
                <div class="info-box">
                    <h4>Change Returned</h4>
                    <p>{{ number_format(max(0, (float) $sale->amount_received - (float) $sale->total_amount), 2) }}</p>
                </div>
                <div class="info-box">
                    <h4>Balance Due</h4>
                    <p>{{ number_format((float) $sale->balance_due, 2) }}</p>
                </div>
                <div class="info-box">
                    <h4>Sale Date</h4>
                    <p>{{ optional($sale->sale_date)->format('d M Y') }}</p>
                </div>
                @if($sale->isInsuranceSale())
                    <div class="info-box">
                        <h4>Insurer</h4>
                        <p>{{ $sale->insurer?->name ?? 'Not linked' }}</p>
                    </div>
                    <div class="info-box">
                        <h4>Claim Status</h4>
                        <p>{{ $sale->claim_status_label }}</p>
                    </div>
                    <div class="info-box">
                        <h4>Patient Top-up</h4>
                        <p>{{ number_format((float) $sale->patient_copay_amount, 2) }}</p>
                    </div>
                    <div class="info-box">
                        <h4>Insurer Covered Amount</h4>
                        <p>{{ number_format((float) $sale->insurance_covered_amount, 2) }}</p>
                    </div>
                    <div class="info-box">
                        <h4>Insurance Balance Due</h4>
                        <p>{{ number_format((float) $sale->insurance_balance_due, 2) }}</p>
                    </div>
                    <div class="info-box">
                        <h4>Member Number</h4>
                        <p>{{ $sale->insurance_member_number ?: 'Not provided' }}</p>
                    </div>
                @endif
            </div>

            @if($sale->status === 'proforma')
                <div class="audit-note" style="margin-top:16px; background:#eff6ff; color:#1d4ed8; border-color:#bfdbfe;">
                    <strong>Proforma Safety</strong>
                    This document is a stock-safe quote. It keeps full invoice details, but it does not reserve stock, deduct stock, receive payment, or change receivables until you convert it to a pending sale.
                </div>
            @endif

            @if($efrisEnabled)
                <div class="audit-note" style="margin-top:16px; background:#f8fafc; color:#334155; border-color:#cbd5e1;">
                    <strong>URA / EFRIS Status</strong>
                    @if($efrisDocument)
                        This sale is tracked for fiscal compliance in <strong style="display:inline;">{{ strtoupper($efrisDocument->environment) }}</strong> mode.
                        The next queued action is <strong style="display:inline;">{{ $efrisDocument->next_action === 'complete' ? 'complete' : str_replace('_', ' ', $efrisDocument->next_action) }}</strong>.
                        <div style="margin-top:10px; display:flex; gap:14px; flex-wrap:wrap;">
                            <span><strong style="display:inline;">Attempts:</strong> {{ (int) $efrisDocument->attempt_count }}</span>
                            @if($efrisDocument->accepted_at)
                                <span><strong style="display:inline;">Accepted:</strong> {{ $efrisDocument->accepted_at->format('d M Y H:i') }}</span>
                            @endif
                            @if(data_get($efrisDocument->response_snapshot, 'tracking_number'))
                                <span><strong style="display:inline;">Tracking:</strong> {{ data_get($efrisDocument->response_snapshot, 'tracking_number') }}</span>
                            @endif
                        </div>
                        @if($efrisDocument->last_error_message)
                            <div style="margin-top:10px; color:#b42318;">
                                <strong style="display:inline;">Last error:</strong> {{ $efrisDocument->last_error_message }}
                            </div>
                        @endif
                    @else
                        The EFRIS module is enabled for this client. A readiness record will be prepared automatically when this sale reaches the approved stage, then it can be processed from Settings.
                    @endif
                </div>
            @endif

            @if($isLegacyPaymentMethod)
                <div class="audit-note" style="margin-top:16px;">
                    <strong>Legacy Payment Note</strong>
                    This approved sale came from older data where the payment channel was not captured. The paid amount was preserved during cleanup, but the exact original method could not be recovered, so this record stays marked as <strong style="display:inline;">Legacy Unspecified</strong> for audit clarity.
                </div>
            @endif

            @if($sale->isInsuranceSale())
                <div class="insurance-panel">
                    <div class="insurance-panel-head">
                        <h4>Insurance Claim Snapshot</h4>
                        <p>Patient top-up and insurer receivable are tracked separately on this invoice so remittances do not mix with customer collections.</p>
                    </div>
                    <div class="insurance-summary-grid">
                        <div class="insurance-summary-box">
                            <span class="insurance-summary-label">Patient Top-up</span>
                            <strong>{{ number_format((float) $sale->patient_copay_amount, 2) }}</strong>
                        </div>
                        <div class="insurance-summary-box">
                            <span class="insurance-summary-label">Insurer Claim</span>
                            <strong>{{ number_format((float) $sale->insurance_covered_amount, 2) }}</strong>
                        </div>
                        <div class="insurance-summary-box">
                            <span class="insurance-summary-label">Outstanding Claim</span>
                            <strong>{{ number_format((float) $sale->insurance_balance_due, 2) }}</strong>
                        </div>
                    </div>
                    <div style="margin-top:14px; display:flex; gap:10px; flex-wrap:wrap;">
                        @if($user->hasAnyPermission(['insurance.view', 'insurance.manage']) && \Illuminate\Support\Facades\Route::has('insurance.claims.show'))
                            <a href="{{ route('insurance.claims.show', $sale->id) }}" class="btn btn-back" style="background:#0f766e;">Open Claim Desk</a>
                        @endif
                        @if($sale->status === 'approved' && (float) $sale->insurance_balance_due > 0 && $user->hasPermission('insurance.manage') && \Illuminate\Support\Facades\Route::has('insurance.payments.create'))
                            <a href="{{ route('insurance.payments.create', $sale->id) }}" class="btn btn-approve" style="background:#155eef;">Record Remittance</a>
                        @endif
                    </div>
                    @if($sale->insurance_status_notes)
                        <div class="audit-note" style="margin-top:14px; background:#eff6ff; color:#1d4ed8; border-color:#bfdbfe;">
                            <strong>Insurance Notes</strong>
                            {{ $sale->insurance_status_notes }}
                        </div>
                    @endif
                </div>
            @endif
        </div>

        @if($sale->status === 'cancelled' || $sale->restored_at)
        <div class="panel">
            <div style="display:flex; justify-content:space-between; gap:12px; align-items:flex-start; flex-wrap:wrap; margin-bottom:16px;">
                <div>
                    <h2 style="margin:0;">Cancellation Audit</h2>
                    <p class="muted" style="margin:6px 0 0;">
                        @if($sale->status === 'cancelled')
                            This sale is currently cancelled and can only be restored if the original batch stock is still available.
                        @else
                            This sale was previously cancelled and later restored. The latest cancellation and restore details remain visible here.
                        @endif
                    </p>
                </div>
                @if($sale->status === 'cancelled')
                    <span class="badge badge-cancelled">Cancelled</span>
                @elseif($sale->restored_at)
                    <span class="badge badge-restored">Restored</span>
                @endif
            </div>

            <div class="audit-grid">
                <div class="info-box">
                    <h4>Cancelled From</h4>
                    <p>{{ ucfirst($sale->cancelled_from_status ?: $restoreStatus ?: 'Pending') }}</p>
                </div>
                <div class="info-box">
                    <h4>Cancelled By</h4>
                    <p>{{ $sale->cancelledByUser?->name ?? 'Not captured' }}</p>
                </div>
                <div class="info-box">
                    <h4>Cancelled On</h4>
                    <p>{{ $sale->cancelled_at?->format('d M Y H:i') ?? 'Not captured' }}</p>
                </div>
                <div class="info-box">
                    <h4>Cancellation Reason</h4>
                    <p>{{ $sale->cancel_reason ?: 'No reason recorded' }}</p>
                </div>
            </div>

            @if($sale->restored_at)
                <div class="audit-grid" style="margin-top:14px;">
                    <div class="info-box">
                        <h4>Restored To</h4>
                        <p>{{ ucfirst($sale->status) }}</p>
                    </div>
                    <div class="info-box">
                        <h4>Restored By</h4>
                        <p>{{ $sale->restoredByUser?->name ?? 'Not captured' }}</p>
                    </div>
                    <div class="info-box">
                        <h4>Restored On</h4>
                        <p>{{ $sale->restored_at?->format('d M Y H:i') ?? 'Not restored' }}</p>
                    </div>
                    <div class="info-box">
                        <h4>Restore Reason</h4>
                        <p>{{ $sale->restore_reason ?: 'No restore reason recorded' }}</p>
                    </div>
                </div>
            @endif

            @if($sale->status === 'cancelled')
                <div class="audit-note">
                    <strong>Restore Target</strong>
                    @if(($restoreStatus ?? 'pending') === 'proforma')
                        This invoice will return as <strong style="display:inline;">Proforma</strong> and will stay stock-safe, with no reserved or deducted stock added during the restore.
                    @else
                        This invoice will return as <strong style="display:inline;">{{ ucfirst($restoreStatus ?? 'pending') }}</strong> so stock, receipt state, and customer balance follow the original sale stage instead of creating a fresh invoice.
                    @endif
                </div>
            @endif
        </div>
        @endif

        <div class="panel">
            <h2 style="margin-top:0;">Sale Items</h2>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Product</th>
                            <th>Batch</th>
                            <th>Expiry</th>
                            <th>Quantity</th>
                            <th>Purchase Price</th>
                            <th>Unit Price</th>
                            <th>Discount</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($sale->items as $item)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $item->product?->name ?? 'N/A' }}</td>
                                <td>{{ $item->batch?->batch_number ?? 'N/A' }}</td>
                                <td>{{ $item->batch && $item->batch->expiry_date ? $item->batch->expiry_date->format('d M Y') : 'N/A' }}</td>
                                <td>{{ number_format((float) $item->quantity, 2) }}</td>
                                <td>{{ number_format((float) $item->purchase_price, 2) }}</td>
                                <td>{{ number_format((float) $item->unit_price, 2) }}</td>
                                <td>{{ number_format((float) $item->discount_amount, 2) }}</td>
                                <td>{{ number_format((float) $item->total_amount, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9">No items found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($sale->payments->isNotEmpty())
        <div class="panel">
            <h2 style="margin-top:0;">Payment History</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Date Received</th>
                            <th>Entry Type</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Reference</th>
                            <th>Received By</th>
                            <th>Reversible Left</th>
                            <th>Notes</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($sale->payments->sortByDesc('payment_date') as $payment)
                            <tr>
                                <td>{{ $payment->payment_date?->format('d M Y H:i') }}</td>
                                <td>
                                    <span class="badge {{ $payment->is_reversal ? 'badge-reversal' : 'badge-payment' }}">
                                        {{ $payment->entry_type_label }}
                                    </span><br>
                                    <span class="muted">{{ $payment->reversal_status_label }}</span>
                                </td>
                                <td>{{ number_format((float) $payment->display_amount, 2) }}</td>
                                <td>{{ ucwords(str_replace('_', ' ', $payment->payment_method)) }}</td>
                                <td>{{ $payment->reference_number ?: 'N/A' }}</td>
                                <td>{{ $payment->receivedByUser?->name ?? 'System' }}</td>
                                <td>
                                    @if($payment->is_reversal)
                                        <span class="muted">N/A</span>
                                    @else
                                        {{ number_format((float) $payment->available_to_reverse, 2) }}
                                    @endif
                                </td>
                                <td>{{ $payment->notes ?: 'No notes' }}</td>
                                <td>
                                    @if(!$payment->is_reversal && (float) $payment->available_to_reverse > 0 && $user->hasPermission('customers.collections.reverse'))
                                        <a href="{{ route('customers.collections.reverse.create', $payment->id) }}" class="btn btn-reverse">Reverse</a>
                                    @else
                                        <span class="muted">No action</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        @if($sale->isInsuranceSale() && $sale->insurancePayments->isNotEmpty())
        <div class="panel">
            <h2 style="margin-top:0;">Insurance Remittance History</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Date Received</th>
                            <th>Entry Type</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Reference</th>
                            <th>Received By</th>
                            <th>Reversible Left</th>
                            <th>Notes</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($sale->insurancePayments->sortByDesc('payment_date') as $payment)
                            <tr>
                                <td>{{ $payment->payment_date?->format('d M Y H:i') }}</td>
                                <td>
                                    <span class="badge {{ $payment->is_reversal ? 'badge-reversal' : 'badge-payment' }}">
                                        {{ $payment->entry_type_label }}
                                    </span><br>
                                    <span class="muted">{{ $payment->reversal_status_label }}</span>
                                </td>
                                <td>{{ number_format((float) $payment->display_amount, 2) }}</td>
                                <td>{{ ucwords(str_replace('_', ' ', $payment->payment_method)) }}</td>
                                <td>{{ $payment->reference_number ?: 'N/A' }}</td>
                                <td>{{ $payment->receivedByUser?->name ?? 'System' }}</td>
                                <td>
                                    @if($payment->is_reversal)
                                        <span class="muted">N/A</span>
                                    @else
                                        {{ number_format((float) $payment->available_to_reverse, 2) }}
                                    @endif
                                </td>
                                <td>{{ $payment->notes ?: 'No notes' }}</td>
                                <td>
                                    @if(!$payment->is_reversal && (float) $payment->available_to_reverse > 0 && $user->hasPermission('insurance.manage') && \Illuminate\Support\Facades\Route::has('insurance.payments.reverse.create'))
                                        <a href="{{ route('insurance.payments.reverse.create', $payment->id) }}" class="btn btn-reverse">Reverse</a>
                                    @else
                                        <span class="muted">No action</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        @if($sale->status === 'pending' && $user->hasPermission('sales.approve'))
        <div class="panel">
            <h2 style="margin-top:0;">Approve Sale</h2>
            <form method="POST" action="{{ route('sales.approve', $sale->id) }}">
                @csrf

                <div class="form-row">
                    <div class="form-group">
                        <label for="payment_type">Payment Type</label>
                        <select name="payment_type" id="payment_type" onchange="updateApprovalInsuranceFields()" required>
                            <option value="cash" {{ $sale->payment_type === 'cash' ? 'selected' : '' }}>Cash</option>
                            <option value="credit" {{ $sale->payment_type === 'credit' ? 'selected' : '' }}>Credit</option>
                            @if($insuranceEnabled ?? false)
                                <option value="insurance" {{ $sale->payment_type === 'insurance' ? 'selected' : '' }}>Insurance</option>
                            @endif
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="payment_method" id="payment-method-label">Payment Method</label>
                        <select name="payment_method" id="payment_method" required>
                            <option value="Cash" {{ old('payment_method', $sale->payment_method) === 'Cash' ? 'selected' : '' }}>Cash</option>
                            <option value="MTN" {{ old('payment_method', $sale->payment_method) === 'MTN' ? 'selected' : '' }}>MTN</option>
                            <option value="Airtel" {{ old('payment_method', $sale->payment_method) === 'Airtel' ? 'selected' : '' }}>Airtel</option>
                            <option value="Bank" {{ old('payment_method', $sale->payment_method) === 'Bank' ? 'selected' : '' }}>Bank</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="amount_received">Amount Received</label>
                        <input type="number" step="0.01" name="amount_received" id="amount_received" value="{{ old('amount_received', $sale->total_amount) }}" required>
                    </div>
                </div>

                @if($insuranceEnabled ?? false)
                    @include('sales._insurance_billing_fields', ['sale' => $sale, 'insurers' => $insurers, 'insuranceTotal' => (float) $sale->total_amount])
                @endif

                <div style="margin-top:16px;">
                    <button type="submit" class="btn btn-approve">Approve Sale</button>
                </div>
            </form>
        </div>
        @endif

        @if($sale->status === 'pending' && $user->hasPermission('sales.cancel'))
        <div class="panel">
            <h2 style="margin-top:0;">Cancel Pending Sale</h2>
            <form method="POST" action="{{ route('sales.cancel', $sale->id) }}">
                @csrf

                <div class="form-group">
                    <label for="cancel_reason_pending">Reason for cancellation</label>
                    <textarea name="cancel_reason" id="cancel_reason_pending" rows="4" required></textarea>
                </div>

                <div style="margin-top:16px;">
                    <button type="submit" class="btn btn-cancel">Cancel Pending Sale</button>
                </div>
            </form>
        </div>
        @endif

        @if($sale->status === 'approved' && $user->hasPermission('sales.cancel'))
        <div class="panel">
            <h2 style="margin-top:0;">Cancel Approved Sale</h2>
            <form method="POST" action="{{ route('sales.cancel', $sale->id) }}">
                @csrf

                <div class="form-group">
                    <label for="cancel_reason_approved">Reason for cancellation</label>
                    <textarea name="cancel_reason" id="cancel_reason_approved" rows="4" required></textarea>
                </div>

                <div style="margin-top:16px;">
                    <button type="submit" class="btn btn-cancel">Cancel Approved Sale</button>
                </div>
            </form>
        </div>
        @endif

        @if($sale->status === 'proforma' && $user->hasPermission('sales.cancel'))
        <div class="panel">
            <h2 style="margin-top:0;">Cancel Proforma Invoice</h2>
            <p class="muted" style="margin-top:0;">Cancelling a proforma keeps the audit trail but still does not touch stock or reserved stock.</p>
            <form method="POST" action="{{ route('sales.cancel', $sale->id) }}">
                @csrf

                <div class="form-group">
                    <label for="cancel_reason_proforma">Reason for cancellation</label>
                    <textarea name="cancel_reason" id="cancel_reason_proforma" rows="4" required></textarea>
                </div>

                <div style="margin-top:16px;">
                    <button type="submit" class="btn btn-cancel">Cancel Proforma Invoice</button>
                </div>
            </form>
        </div>
        @endif

        @if($sale->status === 'cancelled' && $user->hasPermission('sales.restore'))
        <div class="panel">
            <h2 style="margin-top:0;">Reverse Cancellation</h2>
            <p class="muted" style="margin-top:0;">
                @if(($restoreStatus ?? 'pending') === 'proforma')
                    Restore this invoice to its original <strong>Proforma</strong> state. No stock or reserved stock will move during this restore.
                @else
                    Restore this invoice to its original <strong>{{ ucfirst($restoreStatus ?? 'pending') }}</strong> state. Restoration will fail automatically if the original batch stock is no longer free.
                @endif
            </p>
            <form method="POST" action="{{ route('sales.restore', $sale->id) }}">
                @csrf

                <div class="form-group">
                    <label for="restore_reason">Reason for restore</label>
                    <textarea name="restore_reason" id="restore_reason" rows="4" required>{{ old('restore_reason') }}</textarea>
                </div>

                <div style="margin-top:16px;">
                    <button type="submit" class="btn btn-restore">{{ ($restoreStatus ?? 'pending') === 'proforma' ? 'Restore Proforma Invoice' : 'Restore Sale' }}</button>
                </div>
            </form>
        </div>
        @endif
    </div>

    <script>
        const approvalInsuranceEnabled = @json((bool) ($insuranceEnabled ?? false));
        const approvalSaleTotal = Number(@json((float) $sale->total_amount));

        function updateApprovalInsuranceFields() {
            if (!approvalInsuranceEnabled) {
                return;
            }

            const paymentTypeInput = document.getElementById('payment_type');
            const insurancePanel = document.getElementById('insurance-fields-panel');
            const insurerInput = document.getElementById('insurer_id');
            const coveredInput = document.getElementById('insurance_covered_amount');
            const amountReceivedInput = document.getElementById('amount_received');
            const paymentMethodLabel = document.getElementById('payment-method-label');

            if (!paymentTypeInput || !insurancePanel || !insurerInput || !coveredInput || !amountReceivedInput) {
                return;
            }

            const isInsurance = paymentTypeInput.value === 'insurance';
            insurancePanel.style.display = isInsurance ? 'block' : 'none';

            if (isInsurance) {
                insurerInput.setAttribute('required', 'required');
                coveredInput.setAttribute('required', 'required');
                amountReceivedInput.readOnly = true;
                paymentMethodLabel.textContent = 'Patient Top-up Method';
            } else {
                insurerInput.removeAttribute('required');
                coveredInput.removeAttribute('required');
                amountReceivedInput.readOnly = false;
                paymentMethodLabel.textContent = 'Payment Method';
            }

            updateApprovalInsurancePreview();
        }

        function updateApprovalInsurancePreview() {
            if (!approvalInsuranceEnabled) {
                return;
            }

            const paymentTypeInput = document.getElementById('payment_type');
            const coveredInput = document.getElementById('insurance_covered_amount');
            const amountReceivedInput = document.getElementById('amount_received');
            const hiddenCopayInput = document.getElementById('insurance_patient_copay_amount');

            if (!paymentTypeInput || !coveredInput || !amountReceivedInput || !hiddenCopayInput) {
                return;
            }

            let covered = Number(coveredInput.value || 0);
            if (!Number.isFinite(covered) || covered < 0) {
                covered = 0;
            }

            if (covered > approvalSaleTotal) {
                covered = approvalSaleTotal;
                coveredInput.value = approvalSaleTotal.toFixed(2);
            }

            const patientCopay = Math.max(0, approvalSaleTotal - covered);
            const isInsurance = paymentTypeInput.value === 'insurance';

            document.getElementById('insurance-total-preview').textContent = approvalSaleTotal.toFixed(2);
            document.getElementById('insurance-patient-copay-preview').textContent = patientCopay.toFixed(2);
            document.getElementById('insurance-balance-preview').textContent = (isInsurance ? covered : 0).toFixed(2);
            hiddenCopayInput.value = patientCopay.toFixed(2);

            if (isInsurance) {
                amountReceivedInput.value = patientCopay.toFixed(2);
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            if (!approvalInsuranceEnabled) {
                return;
            }

            updateApprovalInsuranceFields();

            const coveredInput = document.getElementById('insurance_covered_amount');
            if (coveredInput) {
                coveredInput.addEventListener('input', updateApprovalInsurancePreview);
            }
        });
    </script>
  </body>
  </html>
