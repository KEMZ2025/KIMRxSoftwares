<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Insurance Claim - KIM Rx</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; display: flex; background: #f5f7fb; }
        .content { flex: 1; padding: 20px; }
        .topbar, .panel { background: #fff; padding: 20px; border-radius: 16px; margin-bottom: 20px; box-shadow: 0 14px 34px rgba(15, 23, 42, 0.06); }
        .grid, .form-grid { display: grid; grid-template-columns: repeat(3, minmax(220px, 1fr)); gap: 16px; }
        .info-box { background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 14px; padding: 14px; }
        .info-box h4 { margin: 0 0 8px; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; color: #526071; }
        .info-box p { margin: 0; font-weight: bold; color: #0f172a; }
        .muted { color: #667085; }
        .form-group { display: flex; flex-direction: column; }
        .form-group label { margin-bottom: 8px; font-weight: bold; }
        .form-group input, .form-group select, .form-group textarea { padding: 10px; border: 1px solid #d0d5dd; border-radius: 10px; }
        .btn { padding: 10px 14px; border: none; border-radius: 10px; color: #fff; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-primary { background: #155eef; }
        .btn-secondary { background: #0f766e; }
        .btn-danger { background: #b42318; }
        .btn-muted { background: #475467; }
        .badge { display: inline-block; padding: 6px 10px; border-radius: 999px; font-size: 12px; font-weight: bold; }
        .badge-draft { background: #eff6ff; color: #1d4ed8; }
        .badge-submitted { background: #eef2ff; color: #4338ca; }
        .badge-approved { background: #ecfdf3; color: #067647; }
        .badge-rejected { background: #fee4e2; color: #b42318; }
        .badge-part_paid { background: #fff7ed; color: #b54708; }
        .badge-paid { background: #dcfae6; color: #087443; }
        .badge-reconciled { background: #ecfeff; color: #0f766e; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 980px; }
        th, td { padding: 12px; border-bottom: 1px solid #eaecf0; text-align: left; }
        th { background: #f8fafc; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; color: #475467; }
        .alert-success, .alert-danger { padding: 12px; border-radius: 10px; margin-bottom: 16px; }
        .alert-success { background: #e7f6ec; color: #1f7a4f; }
        .alert-danger { background: #fdecea; color: #b42318; }
        @media (max-width: 900px) {
            body { flex-direction: column; }
            .grid, .form-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
@include('layouts.sidebar')

<div class="content" id="mainContent">
    <div class="topbar">
        <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; align-items:center;">
            <div>
                <h2 style="margin:0 0 8px;">Insurance Claim</h2>
                <p class="muted" style="margin:0;">Invoice {{ $sale->invoice_number }} billed to {{ $sale->insurer?->name ?? 'Unknown insurer' }}.</p>
            </div>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <a href="{{ route('insurance.claims.index') }}" class="btn btn-muted">Back to Claims Desk</a>
                <a href="{{ route('insurance.statements.index', ['insurer_id' => $sale->insurer_id]) }}" class="btn btn-muted">Open Statement</a>
                <a href="{{ route('sales.show', $sale) }}" class="btn btn-secondary">Open Sale</a>
            </div>
        </div>
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

        <div class="grid">
            <div class="info-box">
                <h4>Claim Status</h4>
                <p><span class="badge badge-{{ $sale->insurance_claim_status }}">{{ $sale->claim_status_label }}</span></p>
            </div>
            <div class="info-box">
                <h4>Patient</h4>
                <p>{{ $sale->customer?->name ?? 'Walk-in / N/A' }}</p>
            </div>
            <div class="info-box">
                <h4>Insurer</h4>
                <p>{{ $sale->insurer?->name ?? 'Not linked' }}</p>
            </div>
            <div class="info-box">
                <h4>Claim Batch</h4>
                <p>
                    @if($sale->insuranceClaimBatch)
                        <a href="{{ route('insurance.batches.show', $sale->insuranceClaimBatch) }}" style="color:#155eef; text-decoration:none;">{{ $sale->insuranceClaimBatch->batch_number }}</a>
                    @else
                        Unbatched
                    @endif
                </p>
            </div>
            <div class="info-box">
                <h4>Covered Amount</h4>
                <p>{{ number_format((float) $sale->insurance_covered_amount, 2) }}</p>
            </div>
            <div class="info-box">
                <h4>Outstanding Claim</h4>
                <p>{{ number_format((float) $sale->insurance_balance_due, 2) }}</p>
            </div>
            <div class="info-box">
                <h4>Patient Top-up</h4>
                <p>{{ number_format((float) $sale->patient_copay_amount, 2) }}</p>
            </div>
            <div class="info-box">
                <h4>Member Number</h4>
                <p>{{ $sale->insurance_member_number ?: 'Not provided' }}</p>
            </div>
            <div class="info-box">
                <h4>Card Number</h4>
                <p>{{ $sale->insurance_card_number ?: 'Not provided' }}</p>
            </div>
            <div class="info-box">
                <h4>Authorization No.</h4>
                <p>{{ $sale->insurance_authorization_number ?: 'Not provided' }}</p>
            </div>
            <div class="info-box">
                <h4>Rejection Reason</h4>
                <p>{{ $sale->insurance_rejection_reason ?: 'No rejection reason recorded.' }}</p>
            </div>
        </div>
    </div>

    <div class="panel">
        <div style="display:flex; justify-content:space-between; gap:12px; align-items:center; flex-wrap:wrap; margin-bottom:16px;">
            <div>
                <h3 style="margin:0;">Claim Status Control</h3>
                <p class="muted" style="margin:6px 0 0;">Use manual statuses up to approved/rejected. Part-paid and paid are driven by remittances.</p>
            </div>
            @if((float) $sale->insurance_balance_due > 0 && auth()->user()?->hasPermission('insurance.manage'))
                <a href="{{ route('insurance.payments.create', $sale) }}" class="btn btn-primary">Record Remittance</a>
            @endif
        </div>

        @if(auth()->user()?->hasPermission('insurance.manage'))
            <form method="POST" action="{{ route('insurance.claims.status.update', $sale) }}">
                @csrf
                @method('PUT')
                <div class="form-grid">
                    <div class="form-group">
                        <label for="insurance_claim_status">Claim Status</label>
                        <select name="insurance_claim_status" id="insurance_claim_status" required>
                            @foreach($claimStatuses as $statusValue => $statusLabel)
                                <option value="{{ $statusValue }}" {{ old('insurance_claim_status', $sale->insurance_claim_status) === $statusValue ? 'selected' : '' }}>{{ $statusLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label for="insurance_status_notes">Status Notes</label>
                        <textarea name="insurance_status_notes" id="insurance_status_notes" rows="3">{{ old('insurance_status_notes', $sale->insurance_status_notes) }}</textarea>
                    </div>
                </div>
                <div style="margin-top:16px;">
                    <button type="submit" class="btn btn-secondary">Update Claim Status</button>
                </div>
            </form>
        @else
            <div class="info-box">
                <h4>Current Status Notes</h4>
                <p>{{ $sale->insurance_status_notes ?: 'No notes recorded yet.' }}</p>
            </div>
        @endif
    </div>

    @if(auth()->user()?->hasPermission('insurance.manage'))
        <div class="panel">
            <div style="display:flex; justify-content:space-between; gap:12px; align-items:center; flex-wrap:wrap; margin-bottom:16px;">
                <div>
                    <h3 style="margin:0;">Claim Reconciliation</h3>
                    <p class="muted" style="margin:6px 0 0;">Use write-offs to clear insurer shortfalls or full rejections without touching remittance history.</p>
                </div>
            </div>

            <form method="POST" action="{{ route('insurance.claims.adjustments.store', $sale) }}">
                @csrf
                <div class="form-grid">
                    <div class="form-group">
                        <label for="adjustment_type">Adjustment Type</label>
                        <select name="adjustment_type" id="adjustment_type" required>
                            @foreach($adjustmentTypes as $typeValue => $typeLabel)
                                <option value="{{ $typeValue }}">{{ $typeLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="amount">Amount</label>
                        <input type="number" step="0.01" min="0.01" max="{{ number_format((float) $sale->insurance_balance_due, 2, '.', '') }}" name="amount" id="amount" value="{{ old('amount', number_format((float) $sale->insurance_balance_due, 2, '.', '')) }}" required>
                    </div>
                    <div class="form-group">
                        <label for="adjustment_date">Adjustment Date</label>
                        <input type="datetime-local" name="adjustment_date" id="adjustment_date" value="{{ old('adjustment_date', now()->format('Y-m-d\\TH:i')) }}" required>
                    </div>
                    <div class="form-group">
                        <label for="mark_claim_rejected">Full Rejection</label>
                        <select name="mark_claim_rejected" id="mark_claim_rejected">
                            <option value="0">No, just reconcile shortfall</option>
                            <option value="1">Yes, insurer rejected this remaining balance</option>
                        </select>
                    </div>
                    <div class="form-group" style="grid-column:1 / -1;">
                        <label for="reason">Reason</label>
                        <textarea name="reason" id="reason" rows="3" required>{{ old('reason') }}</textarea>
                    </div>
                    <div class="form-group" style="grid-column:1 / -1;">
                        <label for="notes">Notes</label>
                        <textarea name="notes" id="notes" rows="2">{{ old('notes') }}</textarea>
                    </div>
                </div>
                <div style="margin-top:16px;">
                    <button type="submit" class="btn btn-primary">Record Reconciliation Adjustment</button>
                </div>
            </form>
        </div>
    @endif

    <div class="panel">
        <h3 style="margin:0 0 14px;">Remittance History</h3>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Date</th>
                    <th>Entry Type</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Reference</th>
                    <th>Received By</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                @forelse($sale->insurancePayments->sortByDesc('payment_date') as $payment)
                    <tr>
                        <td>{{ $payment->payment_date?->format('d M Y H:i') }}</td>
                        <td>
                            <span class="badge {{ $payment->is_reversal ? 'badge-rejected' : 'badge-approved' }}">{{ $payment->entry_type_label }}</span><br>
                            <span class="muted">{{ $payment->reversal_status_label }}</span>
                        </td>
                        <td>{{ number_format((float) $payment->display_amount, 2) }}</td>
                        <td>{{ ucwords(str_replace('_', ' ', $payment->payment_method)) }}</td>
                        <td>{{ $payment->reference_number ?: 'N/A' }}</td>
                        <td>{{ $payment->receivedByUser?->name ?? 'System' }}</td>
                        <td>
                            @if(!$payment->is_reversal && (float) $payment->available_to_reverse > 0 && auth()->user()?->hasPermission('insurance.manage'))
                                <a href="{{ route('insurance.payments.reverse.create', $payment) }}" class="btn btn-danger">Reverse</a>
                            @else
                                <span class="muted">No action</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="muted" style="padding:24px; text-align:center;">No insurer remittances recorded yet.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="panel">
        <h3 style="margin:0 0 14px;">Adjustment History</h3>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Reason</th>
                    <th>Rejected?</th>
                    <th>Recorded By</th>
                </tr>
                </thead>
                <tbody>
                @forelse($sale->insuranceClaimAdjustments->sortByDesc('adjustment_date') as $adjustment)
                    <tr>
                        <td>{{ $adjustment->adjustment_date?->format('d M Y H:i') }}</td>
                        <td>{{ $adjustment->type_label }}</td>
                        <td>{{ number_format((float) $adjustment->amount, 2) }}</td>
                        <td>{{ $adjustment->reason }}</td>
                        <td>{{ $adjustment->mark_claim_rejected ? 'Yes' : 'No' }}</td>
                        <td>{{ $adjustment->createdByUser?->name ?? 'System' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="muted" style="padding:24px; text-align:center;">No reconciliation adjustments recorded yet.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="panel">
        <h3 style="margin:0 0 14px;">Claimed Items</h3>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>#</th>
                    <th>Product</th>
                    <th>Quantity</th>
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
                        <td>{{ number_format((float) $item->quantity, 2) }}</td>
                        <td>{{ number_format((float) $item->unit_price, 2) }}</td>
                        <td>{{ number_format((float) $item->discount_amount, 2) }}</td>
                        <td>{{ number_format((float) $item->total_amount, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="muted" style="padding:24px; text-align:center;">No claimed items found.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
