<?php

namespace App\Support;

use App\Models\ProductBatch;
use App\Models\SaleItem;
use App\Models\StockRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class StockRequestBook
{
    public const LABELS = ['pending' => 'Pending', 'ordered' => 'Ordered', 'available' => 'Available', 'closed' => 'Closed'];

    public static function enabled(?User $user): bool
    {
        return $user && (int) $user->client_id > 0 && (int) $user->branch_id > 0
            && strcasecmp(trim((string) $user->client?->name), 'VIP PHARMACY') === 0
            && $user->branch?->client_id == $user->client_id
            && (bool) ($user->clientSettingsModel()?->inventory_enabled ?? true);
    }

    public static function canView(?User $user): bool
    {
        return self::enabled($user) && $user->hasAnyPermission([
            'sales.create', 'sales.edit', 'sales.edit_approved', 'sales.approve',
            'stock.view', 'stock.adjust', 'purchases.view', 'purchases.create',
        ]);
    }

    public static function canRecord(?User $user): bool
    {
        return self::enabled($user) && $user->hasAnyPermission([
            'sales.create', 'sales.edit', 'sales.edit_approved', 'sales.approve',
            'stock.adjust', 'purchases.create',
        ]);
    }

    public static function canManage(?User $user): bool
    {
        return self::enabled($user) && $user->hasAnyPermission(['stock.adjust', 'purchases.create']);
    }

    public static function usableStock(User $user): Builder
    {
        // Read live reservations without synchronizing or changing any stock records.
        $reserved = SaleItem::query()->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.client_id', $user->client_id)->where('sales.branch_id', $user->branch_id)
            ->where('sales.status', 'pending')->where('sales.is_active', true)
            ->selectRaw('sale_items.product_batch_id, SUM(sale_items.quantity) AS reserved')
            ->groupBy('sale_items.product_batch_id');

        return ProductBatch::query()
            ->join('products', 'products.id', '=', 'product_batches.product_id')
            ->leftJoinSub($reserved, 'request_reservations', 'request_reservations.product_batch_id', '=', 'product_batches.id')
            ->where('product_batches.client_id', $user->client_id)
            ->where('product_batches.branch_id', $user->branch_id)
            ->where('products.client_id', $user->client_id)
            ->where('products.is_active', true)->where('product_batches.is_active', true)
            ->where(function ($query) {
                $query->whereDate('product_batches.expiry_date', '>=', today()->toDateString())
                    ->orWhere(function ($query) {
                        $query->whereNull('product_batches.expiry_date')->where('products.track_expiry', false);
                    });
            })
            ->selectRaw('product_batches.product_id, SUM(CASE WHEN product_batches.quantity_available > COALESCE(request_reservations.reserved, 0) THEN product_batches.quantity_available - COALESCE(request_reservations.reserved, 0) ELSE 0 END) AS free_stock')
            ->groupBy('product_batches.product_id');
    }

    public static function forUser(User $user): Builder
    {
        $rows = StockRequest::query()
            ->leftJoinSub(self::usableStock($user), 'request_stock', 'request_stock.product_id', '=', 'stock_requests.product_id')
            ->where('stock_requests.client_id', $user->client_id)
            ->where('stock_requests.branch_id', $user->branch_id)
            ->select('stock_requests.*')
            ->selectRaw('COALESCE(request_stock.free_stock, 0) AS free_stock')
            ->selectRaw("CASE WHEN stock_requests.status = 'closed' THEN 'closed' WHEN COALESCE(request_stock.free_stock, 0) > 0 THEN 'available' ELSE stock_requests.status END AS display_status");

        return StockRequest::query()->fromSub($rows, 'stock_requests');
    }
}
