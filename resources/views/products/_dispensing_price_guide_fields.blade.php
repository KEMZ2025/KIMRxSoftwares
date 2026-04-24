@php
    $guideRows = collect(old('guide_quantity', []))
        ->map(function ($quantity, $index) {
            return [
                'quantity' => $quantity,
                'label' => old('guide_label.' . $index, ''),
                'amount' => old('guide_amount.' . $index, ''),
            ];
        })
        ->values();

    if ($guideRows->isEmpty() && isset($product)) {
        $guideRows = collect($product->normalizedDispensingPriceGuide())
            ->map(fn (array $line) => [
                'quantity' => $line['quantity'],
                'label' => $line['label'],
                'amount' => $line['amount'],
            ])
            ->values();
    }

    if ($guideRows->isEmpty()) {
        $guideRows = collect([
            ['quantity' => '', 'label' => '', 'amount' => ''],
        ]);
    }
@endphp

<div class="form-group full">
    <div class="guide-card">
        <div class="guide-card-head">
            <div>
                <label style="margin-bottom:6px;">Dispensing Price Guide</label>
                <div class="helper-text" style="margin-top:0;">
                    Optional display-only guide for dispensers. Example: `1 strip`, `1 packet`, or `5 packets` with their quick quote amount.
                </div>
            </div>
            <button type="button" class="btn btn-secondary guide-add-btn" id="add-dispensing-guide-row">Add Guide Line</button>
        </div>

        <div class="guide-grid guide-grid-header">
            <span>Quantity</span>
            <span>Label</span>
            <span>Amount</span>
            <span>Action</span>
        </div>

        <div id="dispensing-guide-rows">
            @foreach($guideRows as $row)
                <div class="guide-grid guide-row">
                    <input type="number" step="0.01" min="0.01" max="999999.99" name="guide_quantity[]" value="{{ $row['quantity'] }}" placeholder="e.g. 1 or 5">
                    <input type="text" name="guide_label[]" value="{{ $row['label'] }}" placeholder="e.g. strip, packet, packets">
                    <input type="number" step="0.01" min="0" max="999999999.99" name="guide_amount[]" value="{{ $row['amount'] }}" placeholder="e.g. 2500">
                    <button type="button" class="btn btn-danger-soft remove-dispensing-guide-row">Remove</button>
                </div>
            @endforeach
        </div>

        <div class="helper-text">
            This guide only shows quick reference amounts in the POS. It does not auto-fill quantity, change totals, touch stock, or alter accounting.
        </div>
    </div>
</div>

<template id="dispensing-guide-row-template">
    <div class="guide-grid guide-row">
        <input type="number" step="0.01" min="0.01" max="999999.99" name="guide_quantity[]" value="" placeholder="e.g. 1 or 5">
        <input type="text" name="guide_label[]" value="" placeholder="e.g. strip, packet, packets">
        <input type="number" step="0.01" min="0" max="999999999.99" name="guide_amount[]" value="" placeholder="e.g. 2500">
        <button type="button" class="btn btn-danger-soft remove-dispensing-guide-row">Remove</button>
    </div>
</template>
