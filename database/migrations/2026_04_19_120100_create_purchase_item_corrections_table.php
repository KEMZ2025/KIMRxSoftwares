<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_item_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('old_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('new_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('old_batch_number')->nullable();
            $table->string('new_batch_number')->nullable();
            $table->date('old_expiry_date')->nullable();
            $table->date('new_expiry_date')->nullable();
            $table->decimal('old_unit_cost', 14, 2)->default(0);
            $table->decimal('new_unit_cost', 14, 2)->default(0);
            $table->decimal('old_retail_price', 14, 2)->default(0);
            $table->decimal('new_retail_price', 14, 2)->default(0);
            $table->decimal('old_wholesale_price', 14, 2)->default(0);
            $table->decimal('new_wholesale_price', 14, 2)->default(0);
            $table->unsignedInteger('affected_batch_count')->default(0);
            $table->unsignedInteger('affected_sale_count')->default(0);
            $table->unsignedInteger('affected_sale_item_count')->default(0);
            $table->text('reason');
            $table->foreignId('corrected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_item_corrections');
    }
};
