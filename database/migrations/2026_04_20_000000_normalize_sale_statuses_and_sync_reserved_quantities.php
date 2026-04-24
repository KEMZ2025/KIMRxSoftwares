<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('sales')
            ->where('status', 'completed')
            ->update([
                'status' => 'approved',
            ]);

        DB::table('product_batches')->update([
            'reserved_quantity' => 0,
        ]);

        $pendingReserved = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->whereNotNull('sale_items.product_batch_id')
            ->where('sales.status', 'pending')
            ->where('sales.is_active', true)
            ->groupBy('sale_items.product_batch_id')
            ->selectRaw('sale_items.product_batch_id, SUM(sale_items.quantity) as reserved_quantity')
            ->get();

        foreach ($pendingReserved as $row) {
            DB::table('product_batches')
                ->where('id', $row->product_batch_id)
                ->update([
                    'reserved_quantity' => $row->reserved_quantity,
                ]);
        }
    }

    public function down(): void
    {
    }
};
