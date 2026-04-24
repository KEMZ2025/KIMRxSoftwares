<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    Schema::create('sale_items', function (Blueprint $table) {
        $table->id();
        $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
        $table->foreignId('product_id')->constrained()->cascadeOnDelete();

        $table->decimal('quantity', 14, 2);
        $table->decimal('purchase_price', 14, 2)->default(0);
        $table->decimal('unit_price', 14, 2)->default(0);
        $table->decimal('discount_amount', 14, 2)->default(0);
        $table->decimal('total_amount', 14, 2)->default(0);

        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
