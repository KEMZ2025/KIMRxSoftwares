@php
    $insuranceTotal = (float) ($insuranceTotal ?? $sale->total_amount ?? 0);
    $selectedInsurerId = old('insurer_id', $sale->insurer_id);
    $coveredAmount = (float) old(
        'insurance_covered_amount',
        ($sale->insurance_covered_amount ?? 0) > 0
            ? $sale->insurance_covered_amount
            : 0
    );
    $patientCopay = (float) old(
        'insurance_patient_copay_amount',
        ($sale->patient_copay_amount ?? 0) > 0
            ? $sale->patient_copay_amount
            : max(0, $insuranceTotal - $coveredAmount)
    );
    $insuranceBalance = (float) old(
        'insurance_balance_due',
        ($sale->insurance_balance_due ?? 0) > 0
            ? $sale->insurance_balance_due
            : max(0, $coveredAmount)
    );
@endphp

<div class="insurance-panel full" id="insurance-fields-panel" style="display:none;">
    <div class="insurance-panel-head">
        <h4>Insurance Billing</h4>
        <p>Capture the insurer claim portion and the patient top-up separately so claims and collections stay clean.</p>
    </div>

    <div class="form-row" style="margin-bottom:0;">
        <div class="form-group">
            <label for="insurer_id">Insurer</label>
            <select name="insurer_id" id="insurer_id">
                <option value="">Select Insurer</option>
                @foreach($insurers as $insurer)
                    <option value="{{ $insurer->id }}" {{ (string) $selectedInsurerId === (string) $insurer->id ? 'selected' : '' }}>
                        {{ $insurer->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="form-group">
            <label for="insurance_plan_name">Plan / Scheme</label>
            <input type="text" name="insurance_plan_name" id="insurance_plan_name" value="{{ old('insurance_plan_name', $sale->insurance_plan_name) }}" placeholder="Scheme name">
        </div>

        <div class="form-group">
            <label for="insurance_covered_amount">Insurer Covered Amount</label>
            <input type="number" step="0.01" min="0" name="insurance_covered_amount" id="insurance_covered_amount" value="{{ number_format($coveredAmount, 2, '.', '') }}">
        </div>

        <div class="form-group">
            <label for="insurance_member_number">Member Number</label>
            <input type="text" name="insurance_member_number" id="insurance_member_number" value="{{ old('insurance_member_number', $sale->insurance_member_number) }}" placeholder="Membership number">
        </div>

        <div class="form-group">
            <label for="insurance_card_number">Card Number</label>
            <input type="text" name="insurance_card_number" id="insurance_card_number" value="{{ old('insurance_card_number', $sale->insurance_card_number) }}" placeholder="Card number">
        </div>

        <div class="form-group">
            <label for="insurance_authorization_number">Authorization Number</label>
            <input type="text" name="insurance_authorization_number" id="insurance_authorization_number" value="{{ old('insurance_authorization_number', $sale->insurance_authorization_number) }}" placeholder="Pre-auth reference">
        </div>

        <div class="form-group full">
            <label for="insurance_status_notes">Insurance Notes</label>
            <textarea name="insurance_status_notes" id="insurance_status_notes" rows="3" placeholder="Coverage notes, claim notes, or rejection details">{{ old('insurance_status_notes', $sale->insurance_status_notes) }}</textarea>
        </div>
    </div>

    <input type="hidden" name="insurance_patient_copay_amount" id="insurance_patient_copay_amount" value="{{ number_format($patientCopay, 2, '.', '') }}">

    <div class="insurance-summary-grid">
        <div class="insurance-summary-box">
            <span class="insurance-summary-label">Invoice Total</span>
            <strong id="insurance-total-preview">{{ number_format($insuranceTotal, 2) }}</strong>
        </div>
        <div class="insurance-summary-box">
            <span class="insurance-summary-label">Patient Top-up</span>
            <strong id="insurance-patient-copay-preview">{{ number_format($patientCopay, 2) }}</strong>
        </div>
        <div class="insurance-summary-box">
            <span class="insurance-summary-label">Insurer Claim Balance</span>
            <strong id="insurance-balance-preview">{{ number_format($insuranceBalance, 2) }}</strong>
        </div>
    </div>
</div>
