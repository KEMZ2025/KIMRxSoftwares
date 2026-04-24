<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_batches', function (Blueprint $table) {
            $table->foreignId('purchase_item_id')
                ->nullable()
                ->after('product_id')
                ->constrained('purchase_items')
                ->nullOnDelete();
        });

        DB::table('product_batches')
            ->orderBy('id')
            ->get()
            ->each(function ($batch) {
                $purchaseMovement = DB::table('stock_movements')
                    ->where('product_batch_id', $batch->id)
                    ->where('reference_type', 'purchase')
                    ->orderBy('id')
                    ->first();

                if (!$purchaseMovement) {
                    return;
                }

                $matchingItems = DB::table('purchase_items')
                    ->where('purchase_id', $purchaseMovement->reference_id)
                    ->where('product_id', $batch->product_id)
                    ->where('batch_number', $batch->batch_number)
                    ->pluck('id');

                if ($matchingItems->count() !== 1) {
                    return;
                }

                DB::table('product_batches')
                    ->where('id', $batch->id)
                    ->update(['purchase_item_id' => $matchingItems->first()]);
            });
    }

    public function down(): void
    {
        Schema::table('product_batches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('purchase_item_id');
        });
    }
};
