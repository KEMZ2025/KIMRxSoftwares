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
    Schema::create('stock_movements', function (Blueprint $table) {
        $table->id();
        $table->foreignId('client_id')->constrained()->cascadeOnDelete();
        $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
        $table->foreignId('product_id')->constrained()->cascadeOnDelete();
        $table->foreignId('product_batch_id')->nullable()->constrained('product_batches')->nullOnDelete();

        $table->string('movement_type'); // purchase_in, sale_out, return_in, adjustment_in, adjustment_out, damage_out
        $table->string('reference_type')->nullable(); // purchase, sale, adjustment, return
        $table->unsignedBigInteger('reference_id')->nullable();

        $table->decimal('quantity_in', 14, 2)->default(0);
        $table->decimal('quantity_out', 14, 2)->default(0);
        $table->decimal('balance_after', 14, 2)->default(0);

        $table->text('note')->nullable();
        $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
