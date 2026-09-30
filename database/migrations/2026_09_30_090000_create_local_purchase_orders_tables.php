<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('local_purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('supplier_name');
            $table->string('supplier_phone', 60)->nullable();
            $table->text('supplier_address')->nullable();
            $table->string('order_number', 32)->nullable()->unique();
            $table->uuid('submission_token');
            $table->date('order_date');
            $table->date('expected_delivery_date')->nullable();
            $table->text('delivery_address')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('draft');
            $table->decimal('subtotal', 16, 2)->default(0);
            $table->decimal('discount_amount', 16, 2)->default(0);
            $table->decimal('tax_amount', 16, 2)->default(0);
            $table->decimal('total_amount', 16, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(['client_id', 'branch_id', 'submission_token'], 'lpo_submission_unique');
            $table->index(['client_id', 'branch_id', 'order_date'], 'lpo_branch_date');
            $table->index(['client_id', 'branch_id', 'status'], 'lpo_branch_status');
        });

        Schema::create('local_purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('local_purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('line_number');
            $table->string('description');
            $table->string('unit_name', 80)->nullable();
            $table->decimal('quantity', 12, 2);
            $table->decimal('unit_price', 16, 2);
            $table->decimal('line_total', 16, 2);
            $table->timestamps();

            $table->unique(['local_purchase_order_id', 'line_number'], 'lpo_item_line_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('local_purchase_order_items');
        Schema::dropIfExists('local_purchase_orders');
    }
};
