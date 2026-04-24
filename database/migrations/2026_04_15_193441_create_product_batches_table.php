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
    Schema::create('product_batches', function (Blueprint $table) {
        $table->id();
        $table->foreignId('client_id')->constrained()->cascadeOnDelete();
        $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
        $table->foreignId('product_id')->constrained()->cascadeOnDelete();

        $table->string('batch_number');
        $table->date('expiry_date')->nullable();

        $table->decimal('purchase_price', 14, 2)->default(0);
        $table->decimal('retail_price', 14, 2)->default(0);
        $table->decimal('wholesale_price', 14, 2)->default(0);

        $table->decimal('quantity_received', 14, 2)->default(0);
        $table->decimal('quantity_available', 14, 2)->default(0);

        $table->boolean('is_active')->default(true);
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_batches');
    }
};
