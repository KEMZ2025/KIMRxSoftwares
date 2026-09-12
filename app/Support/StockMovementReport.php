<?php

namespace App\Support;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class StockMovementReport
{
    public const CLASSIFICATIONS = [
        'all' => 'All Movement Classes',
        'fast' => 'Fast Moving',
        'normal' => 'Normal Moving',
        'slow' => 'Slow Moving',
        'no_movement' => 'No Movement',
        'new' => 'New Product',
        'out_of_stock' => 'Out Of Stock',
    ];

    public function build(
        int $clientId,
        int $branchId,
        Carbon $dateFrom,
        Carbon $dateTo,
        string $classification = 'all',
        string $search = ''
    ): array {
        $analysisDays = max(1, $dateFrom->copy()->startOfDay()->diffInDays($dateTo->copy()->startOfDay()) + 1);
        $today = Carbon::today(config('app.timezone'));

        $selectedSales = $this->salesByProduct($clientId, $branchId)
            ->whereBetween('sales.sale_date', [$dateFrom->copy()->startOfDay(), $dateTo->copy()->endOfDay()])
            ->selectRaw('sale_items.product_id, SUM(sale_items.quantity) as quantity_sold, COUNT(DISTINCT sales.id) as invoice_count, SUM(sale_items.total_amount) as sales_value')
            ->groupBy('sale_items.product_id')
            ->get()
            ->keyBy(fn ($row) => (int) $row->product_id);

        $lastSales = $this->salesByProduct($clientId, $branchId)
            ->selectRaw('sale_items.product_id as product_id, MAX(sales.sale_date) as last_sale_date')
            ->groupBy('sale_items.product_id')
            ->pluck('last_sale_date', 'product_id');

        $rows = Product::query()
            ->with([
                'unit:id,name',
                'batches' => fn ($query) => $query
                    ->where('client_id', $clientId)
                    ->where('branch_id', $branchId)
                    ->where('is_active', true),
            ])
            ->where('client_id', $clientId)
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(function (Product $product) use ($selectedSales, $lastSales, $analysisDays, $today) {
                $sale = $selectedSales->get((int) $product->id);
                $quantitySold = (float) ($sale->quantity_sold ?? 0);
                $invoiceCount = (int) ($sale->invoice_count ?? 0);
                $salesValue = (float) ($sale->sales_value ?? 0);
                $freeStock = (float) $product->batches->sum(
                    fn ($batch) => max(0, (float) $batch->quantity_available - (float) $batch->reserved_quantity)
                );
                $stockValue = (float) $product->batches->sum(
                    fn ($batch) => max(0, (float) $batch->quantity_available - (float) $batch->reserved_quantity)
                        * max(0, (float) $batch->purchase_price)
                );
                $averageDailySales = $quantitySold / $analysisDays;
                $daysOfStock = $averageDailySales > 0 ? $freeStock / $averageDailySales : null;
                $firstStockDate = $product->batches->min('created_at') ?? $product->created_at;
                $stockAgeDays = $firstStockDate ? Carbon::parse($firstStockDate)->startOfDay()->diffInDays($today) : null;
                $lastSaleDate = $lastSales->get((int) $product->id);
                $daysSinceLastSale = $lastSaleDate
                    ? Carbon::parse($lastSaleDate)->startOfDay()->diffInDays($today)
                    : null;
                $nearestExpiry = $product->batches
                    ->filter(fn ($batch) => $batch->expiry_date
                        && max(0, (float) $batch->quantity_available - (float) $batch->reserved_quantity) > 0)
                    ->min('expiry_date');
                $movementClass = $this->classify(
                    $freeStock,
                    $quantitySold,
                    $invoiceCount,
                    $daysOfStock,
                    $stockAgeDays,
                    $daysSinceLastSale
                );

                return [
                    'product_id' => (int) $product->id,
                    'product_name' => trim($product->name.' '.($product->strength ?? '')),
                    'unit_name' => $product->unit?->name ?: 'N/A',
                    'batch_count' => $product->batches->count(),
                    'free_stock' => round($freeStock, 2),
                    'stock_value' => round($stockValue, 2),
                    'quantity_sold' => round($quantitySold, 2),
                    'invoice_count' => $invoiceCount,
                    'sales_value' => round($salesValue, 2),
                    'average_daily_sales' => round($averageDailySales, 2),
                    'days_of_stock' => $daysOfStock === null ? null : round($daysOfStock, 1),
                    'last_sale_date' => $lastSaleDate ? Carbon::parse($lastSaleDate) : null,
                    'days_since_last_sale' => $daysSinceLastSale,
                    'nearest_expiry' => $nearestExpiry ? Carbon::parse($nearestExpiry) : null,
                    'movement_class' => $movementClass,
                    'movement_label' => self::CLASSIFICATIONS[$movementClass],
                ];
            });

        $summary = collect(self::CLASSIFICATIONS)
            ->except('all')
            ->mapWithKeys(fn ($label, $key) => [
                $key => [
                    'label' => $label,
                    'count' => $rows->where('movement_class', $key)->count(),
                    'stock_value' => round((float) $rows->where('movement_class', $key)->sum('stock_value'), 2),
                ],
            ])
            ->all();

        $normalizedClassification = array_key_exists($classification, self::CLASSIFICATIONS)
            ? $classification
            : 'all';
        $normalizedSearch = Str::lower(trim($search));

        $filteredRows = $rows
            ->when($normalizedClassification !== 'all', fn (Collection $items) => $items->where('movement_class', $normalizedClassification))
            ->when($normalizedSearch !== '', fn (Collection $items) => $items->filter(
                fn (array $row) => Str::contains(Str::lower($row['product_name']), $normalizedSearch)
            ))
            ->sortBy([
                fn (array $left, array $right) => $this->sortRank($left['movement_class']) <=> $this->sortRank($right['movement_class']),
                fn (array $left, array $right) => strcasecmp($left['product_name'], $right['product_name']),
            ])
            ->values();

        return [
            'rows' => $filteredRows,
            'summary' => $summary,
            'analysis_days' => $analysisDays,
        ];
    }

    private function salesByProduct(int $clientId, int $branchId)
    {
        return SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.client_id', $clientId)
            ->where('sales.branch_id', $branchId)
            ->where('sales.is_active', true)
            ->where('sales.status', 'approved')
            ->where(function ($query) {
                $query->whereNull('sales.source')
                    ->orWhere('sales.source', Sale::SOURCE_LIVE);
            });
    }

    private function classify(
        float $freeStock,
        float $quantitySold,
        int $invoiceCount,
        ?float $daysOfStock,
        ?int $stockAgeDays,
        ?int $daysSinceLastSale
    ): string {
        if ($freeStock <= 0.0001) {
            return 'out_of_stock';
        }

        if ($stockAgeDays !== null && $stockAgeDays < 30 && $invoiceCount < 3) {
            return 'new';
        }

        if ($quantitySold <= 0) {
            return 'no_movement';
        }

        if (($daysSinceLastSale !== null && $daysSinceLastSale > 30) || ($daysOfStock !== null && $daysOfStock > 90)) {
            return 'slow';
        }

        if ($daysOfStock !== null && $daysOfStock <= 30 && $invoiceCount >= 2) {
            return 'fast';
        }

        return 'normal';
    }

    private function sortRank(string $classification): int
    {
        return match ($classification) {
            'no_movement' => 1,
            'slow' => 2,
            'new' => 3,
            'normal' => 4,
            'fast' => 5,
            'out_of_stock' => 6,
            default => 7,
        };
    }
}
