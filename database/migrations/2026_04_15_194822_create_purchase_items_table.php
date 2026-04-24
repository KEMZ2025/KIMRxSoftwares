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
    Schema::create('purchase_items', function (Blueprint $table) {
        $table->id();
        $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
        $table->foreignId('product_id')->constrained()->cascadeOnDelete();

        $table->string('batch_number');
        $table->date('expiry_date')->nullable();

        $table->decimal('quantity', 14, 2);
        $table->decimal('unit_cost', 14, 2);
        $table->decimal('total_cost', 14, 2);

        $table->decimal('retail_price', 14, 2)->default(0);
        $table->decimal('wholesale_price', 14, 2)->default(0);

        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_items');
    }
};
