<?php

namespace App\Support;

use App\Models\ProductBatch;
use App\Models\StockAdjustment;
use App\Models\StockMovement;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class StockReconciliationReport
{
    public function build(User $user, Carbon $from, Carbon $to, ?Carbon $importCutoff = null): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();
        $batches = ProductBatch::query()->with('product:id,name')
            ->where('client_id', $user->client_id)->where('branch_id', $user->branch_id)
            ->get()->keyBy('id');
        $movements = StockMovement::query()
            ->where('client_id', $user->client_id)->where('branch_id', $user->branch_id)
            ->get()->groupBy('product_batch_id');
        $adjustments = StockAdjustment::query()
            ->where('client_id', $user->client_id)->where('branch_id', $user->branch_id)
            ->get()->groupBy('product_batch_id');

        // Sales do not write stock_movements. Use current approved lines once,
        // giving a restated report that follows later edits and cancellations.
        $sales = DB::table('sale_items')->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.client_id', $user->client_id)->where('sales.branch_id', $user->branch_id)
            ->where('sales.status', 'approved')->where('sales.is_active', true)
            ->where(fn ($q) => $q->whereNull('sales.source')->orWhere('sales.source', 'live'))
            ->select('sale_items.product_batch_id')
            ->selectRaw('SUM(sale_items.quantity) as all_sold')
            ->selectRaw('SUM(CASE WHEN sales.sale_date < ? THEN sale_items.quantity ELSE 0 END) as before_sold', [$from])
            ->selectRaw('SUM(CASE WHEN sales.sale_date >= ? AND sales.sale_date <= ? THEN sale_items.quantity ELSE 0 END) as period_sold', [$from, $to])
            ->groupBy('sale_items.product_batch_id')->get()->keyBy('product_batch_id');

        $importDates = [];
        $rows = collect();
        foreach ($batches as $batch) {
            $opening = 0.0;
            $incoming = 0.0;
            $outgoing = 0.0;
            $allNet = 0.0;
            $importedOpening = 0.0;
            $historyCount = 0;
            $issues = [];
            $apply = function (Carbon $date, float $in, float $out, bool $openingImport = false) use (
                &$opening, &$incoming, &$outgoing, &$allNet, &$importedOpening, &$historyCount, $from, $to
            ) {
                $historyCount++;
                $allNet += $in - $out;
                if ($openingImport || $date->lt($from)) {
                    $opening += $in - $out;
                    if ($openingImport) {
                        $importedOpening += $in - $out;
                    }
                } elseif ($date->lte($to)) {
                    $incoming += $in;
                    $outgoing += $out;
                }
            };
            $batchAdjustments = $adjustments->get($batch->id, collect())->keyBy('id');
            foreach ($movements->get($batch->id, collect()) as $movement) {
                if ($movement->reference_type === 'sale' || str_starts_with($movement->movement_type, 'sale_')) {
                    continue;
                }
                if ($movement->reference_type === 'stock_adjustment' && $batchAdjustments->has($movement->reference_id)) {
                    continue;
                }
                if ($movement->reference_type === 'stock_adjustment') {
                    $issues[] = 'Adjustment date missing; recorded date used';
                }
                $date = $movement->created_at;
                $isImport = $movement->movement_type === 'import_opening_in';
                if ($isImport) {
                    $importDates[$date->toDateString()] = true;
                }
                $openingImport = $isImport && ($date->lt($from)
                    || ($importCutoff && $date->lte($importCutoff->copy()->endOfDay())));
                $apply($date, (float) $movement->quantity_in, (float) $movement->quantity_out, $openingImport);
            }
            foreach ($batchAdjustments as $adjustment) {
                $apply($adjustment->adjustment_date,
                    $adjustment->direction === 'increase' ? (float) $adjustment->quantity : 0,
                    $adjustment->direction === 'decrease' ? (float) $adjustment->quantity : 0);
            }
            $sold = $sales->get($batch->id);
            $opening = round($opening - (float) ($sold->before_sold ?? 0), 2);
            $soldInPeriod = (float) ($sold->period_sold ?? 0);
            $closing = round($opening + $incoming - $outgoing - $soldInPeriod, 2);
            $difference = round((float) $batch->quantity_available - ($allNet - (float) ($sold->all_sold ?? 0)), 2);
            if ($historyCount === 0 && ((float) $batch->quantity_received != 0 || (float) $batch->quantity_available != 0 || $sold)) {
                $issues[] = 'Opening/receipt history missing';
            }
            if (abs($difference) >= 0.01) {
                $issues[] = 'Current stock differs by ' . number_format($difference, 2);
            }
            if ($opening < 0 || $closing < 0) {
                $issues[] = 'Negative reconstructed balance';
            }
            $cost = (float) $batch->purchase_price;
            if ($cost <= 0 && ($opening != 0 || $closing != 0)) {
                $issues[] = 'Zero cost: verify free stock or missing cost';
            }
            // Keep exhausted and inactive batches when they contributed to this period.
            if ($opening == 0 && $closing == 0 && $incoming == 0 && $outgoing == 0 && $soldInPeriod == 0 && $issues === []) {
                continue;
            }
            $rows->push([
                'product' => $batch->product?->name ?? 'Unknown product',
                'batch' => $batch->batch_number,
                'batch_id' => $batch->id,
                'unit_cost' => $cost,
                'imported_opening' => round($importedOpening, 2),
                'opening' => $opening,
                'opening_value' => round($opening * $cost, 2),
                'incoming' => round($incoming, 2),
                'sold' => round($soldInPeriod, 2),
                'other_out' => round($outgoing, 2),
                'closing' => $closing,
                'closing_value' => round($closing * $cost, 2),
                'issues' => implode('; ', array_unique($issues)),
            ]);
        }
        $unlinked = $movements->filter(fn ($items, $id) => !$batches->has($id))->sum(fn ($items) => $items->count())
            + $adjustments->filter(fn ($items, $id) => !$batches->has($id))->sum(fn ($items) => $items->count())
            + $sales->filter(fn ($item, $id) => !$batches->has($id))->count();
        $totals = [];
        foreach (['opening_value', 'closing_value'] as $key) {
            $totals[$key] = round($rows->sum($key), 2);
        }
        $totals['review_count'] = $rows->where('issues', '!=', '')->count();
        $totals['batch_count'] = $rows->count();
        $dates = array_keys($importDates);
        sort($dates);

        return [
            'rows' => $rows->sortBy(fn ($row) => mb_strtolower($row['product']) . '|' . $row['batch'])->values(),
            'totals' => $totals,
            'unlinked' => $unlinked,
            'import_dates' => $dates,
            'import_cutoff' => $importCutoff?->toDateString(),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'basis' => 'Reconstructed quantities at current recorded batch purchase costs, not archived historical valuations. Includes reserved and expired stock still on hand. Sales use invoice dates and current approved quantities; receipts use recorded receipt dates; adjustments use adjustment dates. Later corrections and cancellations restate earlier periods.',
        ];
    }

    public function csvRows(array $report): array
    {
        $rows = [
            ['Stock Reconciliation', $report['from'], $report['to']],
            ['Valuation basis', $report['basis']],
            ['Opening imports through', $report['import_cutoff'] ?? 'Before start date only'],
            ['Opening stock value', $report['totals']['opening_value']],
            ['Closing stock value', $report['totals']['closing_value']],
            ['Batches needing review', $report['totals']['review_count']],
            ['Unlinked history groups/records excluded', $report['unlinked']],
            [],
            ['Product', 'Batch', 'Batch ID', 'Unit cost', 'Migrated opening qty', 'Opening qty', 'Opening value', 'In qty', 'Sold qty', 'Other out qty', 'Closing qty', 'Closing value', 'Review'],
        ];
        foreach ($report['rows'] as $row) {
            $rows[] = array_values($row);
        }
        return $rows;
    }
}
