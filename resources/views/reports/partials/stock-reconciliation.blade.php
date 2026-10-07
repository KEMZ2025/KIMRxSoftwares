<style>
    .stock-reconciliation { padding: 20px 0; }
    .stock-reconciliation .stock-values { display: flex; gap: 32px; flex-wrap: wrap; margin: 16px 0; }
    .stock-reconciliation .stock-values strong { display: block; font-size: 22px; }
    .stock-reconciliation .stock-basis { line-height: 1.5; max-width: 1050px; }
    .stock-reconciliation .stock-table-wrap { overflow-x: auto; }
    .stock-reconciliation table { width: 100%; border-collapse: collapse; min-width: 1100px; }
    .stock-reconciliation th, .stock-reconciliation td { padding: 9px 7px; border-bottom: 1px solid #cbd5e1; text-align: right; }
    .stock-reconciliation th:first-child, .stock-reconciliation td:first-child { text-align: left; width: 20%; }
    .stock-reconciliation th:nth-child(2), .stock-reconciliation td:nth-child(2) { text-align: left; }
    .stock-reconciliation .stock-review { color: #b45309; font-size: 12px; margin-top: 4px; }
    .stock-reconciliation .stock-review-summary { font-weight: 600; }
    @media print {
        @page { size: A4 landscape; }
        .stock-reconciliation table { min-width: 0; table-layout: fixed; }
        .stock-reconciliation th, .stock-reconciliation td { font-size: 9px; padding: 5px 3px; overflow-wrap: anywhere; }
        .stock-reconciliation .stock-review { font-size: 8px; }
        .stock-reconciliation .stock-table-wrap { overflow: visible; }
    }
    @if($isPdfDownload ?? false)
        .stock-reconciliation table { min-width: 0; table-layout: fixed; }
        .stock-reconciliation th, .stock-reconciliation td { font-size: 8px; padding: 5px 3px; overflow-wrap: anywhere; }
        .stock-reconciliation .stock-review { font-size: 8px; }
        .stock-reconciliation .stock-table-wrap { overflow: visible; }
    @endif
</style>
<section class="stock-reconciliation">
    <h2>Opening &amp; Closing Stock</h2>
    <p>Opening: start of {{ $stockReconciliation['from'] }}. Closing: end of {{ $stockReconciliation['to'] }}.</p>
    <div class="stock-values">
        <div>Opening stock value <strong>UGX {{ number_format($stockReconciliation['totals']['opening_value'], 2) }}</strong></div>
        <div>Closing stock value <strong>UGX {{ number_format($stockReconciliation['totals']['closing_value'], 2) }}</strong></div>
        <div>Batches <strong>{{ number_format($stockReconciliation['totals']['batch_count']) }}</strong></div>
    </div>
    <p class="stock-basis">{{ $stockReconciliation['basis'] }}</p>
    <p class="stock-basis">Migrated imports recorded through {{ $stockReconciliation['import_cutoff'] ?? 'the day before the start date' }} are assigned to opening stock. Later imports count as stock in. Import dates found: {{ implode(', ', $stockReconciliation['import_dates']) ?: 'None' }}.</p>
    @if($stockReconciliation['totals']['review_count'] || $stockReconciliation['unlinked'])
        <p class="stock-review-summary">Provisional values: {{ $stockReconciliation['totals']['review_count'] }} batches need review; {{ $stockReconciliation['unlinked'] }} unlinked history records/groups are excluded. Resolve these exceptions before relying on the totals as a complete stock valuation.</p>
    @endif
    <div class="stock-table-wrap">
        <table>
            <thead><tr><th>Product</th><th>Batch</th><th>Unit cost</th><th>Opening qty</th><th>Opening value</th><th>In qty</th><th>Sold qty</th><th>Other out</th><th>Closing qty</th><th>Closing value</th></tr></thead>
            <tbody>
                @forelse($stockReconciliation['rows'] as $row)
                    <tr>
                        <td>{{ $row['product'] }}@if($row['issues'])<div class="stock-review">{{ $row['issues'] }}</div>@endif</td>
                        <td>{{ $row['batch'] }}</td>
                        @foreach(['unit_cost', 'opening', 'opening_value', 'incoming', 'sold', 'other_out', 'closing', 'closing_value'] as $key)
                            <td>{{ number_format($row[$key], 2) }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="10">No stock history found for this period.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
