<?php

namespace App\Support;

use App\Models\ProductBatch;
use App\Models\SaleItem;
use Illuminate\Support\Collection;

class BatchReservationService
{
    public static function syncForBranch(int $clientId, int $branchId, array $ignoredSaleIds = []): void
    {
        $batches = ProductBatch::query()
            ->where('client_id', $clientId)
            ->where('branch_id', $branchId)
            ->get();

        self::syncCollection($batches, $clientId, $branchId, $ignoredSaleIds);
    }

    public static function syncCollection(Collection $batches, int $clientId, int $branchId, array $ignoredSaleIds = []): void
    {
        $batchIds = $batches->pluck('id')
            ->filter()
            ->unique()
            ->values();

        if ($batchIds->isEmpty()) {
            return;
        }

        $liveReserved = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->whereIn('sale_items.product_batch_id', $batchIds)
            ->where('sales.client_id', $clientId)
            ->where('sales.branch_id', $branchId)
            ->where('sales.status', 'pending')
            ->where('sales.is_active', true)
            ->when(!empty($ignoredSaleIds), function ($query) use ($ignoredSaleIds) {
                $query->whereNotIn('sales.id', $ignoredSaleIds);
            })
            ->groupBy('sale_items.product_batch_id')
            ->selectRaw('sale_items.product_batch_id, SUM(sale_items.quantity) as reserved_quantity')
            ->pluck('reserved_quantity', 'sale_items.product_batch_id');

        foreach ($batches as $batch) {
            $reserved = (float) ($liveReserved[$batch->id] ?? 0);

            if (abs((float) $batch->reserved_quantity - $reserved) > 0.0001) {
                ProductBatch::query()
                    ->whereKey($batch->id)
                    ->update([
                        'reserved_quantity' => $reserved,
                    ]);
            }

            $batch->reserved_quantity = $reserved;
        }
    }

    public static function syncSingle(ProductBatch $batch): void
    {
        self::syncCollection(collect([$batch]), (int) $batch->client_id, (int) $batch->branch_id);
    }
}
