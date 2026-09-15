<?php

namespace App\Support;

use App\Models\ProductBatch;

class StockValuation
{
    public static function prices(ProductBatch $batch): array
    {
        $batchRetailPrice = (float) $batch->retail_price;
        $batchWholesalePrice = (float) $batch->wholesale_price;
        $productRetailPrice = (float) ($batch->product?->retail_price ?? 0);
        $productWholesalePrice = (float) ($batch->product?->wholesale_price ?? 0);
        $batchHasCopiedPrices = abs($batchRetailPrice - $batchWholesalePrice) < 0.0001;
        $productHasSplitPrices = $productRetailPrice > 0 && $productWholesalePrice > 0
            && abs($productRetailPrice - $productWholesalePrice) >= 0.0001;
        $retailPrice = ($batchRetailPrice <= 0 || ($batchHasCopiedPrices && $productHasSplitPrices)) && $productRetailPrice > 0
            ? $productRetailPrice : $batchRetailPrice;
        $wholesalePrice = ($batchWholesalePrice <= 0 || ($batchHasCopiedPrices && $productHasSplitPrices)) && $productWholesalePrice > 0
            ? $productWholesalePrice : $batchWholesalePrice;
        return ['purchase' => (float) $batch->purchase_price, 'retail' => $retailPrice, 'wholesale' => $wholesalePrice];
    }
}
