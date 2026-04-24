<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_batch_id')->constrained('product_batches')->cascadeOnDelete();
            $table->foreignId('purchase_id')->nullable()->constrained('purchases')->nullOnDelete();
            $table->string('direction');
            $table->string('reason');
            $table->decimal('quantity', 14, 2);
            $table->decimal('quantity_received_before', 14, 2)->default(0);
            $table->decimal('quantity_received_after', 14, 2)->default(0);
            $table->decimal('quantity_available_before', 14, 2)->default(0);
            $table->decimal('quantity_available_after', 14, 2)->default(0);
            $table->decimal('reserved_quantity_before', 14, 2)->default(0);
            $table->decimal('reserved_quantity_after', 14, 2)->default(0);
            $table->text('note')->nullable();
            $table->foreignId('adjusted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('adjustment_date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustments');
    }
};
