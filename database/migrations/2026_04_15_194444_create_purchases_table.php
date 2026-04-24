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
    Schema::create('purchases', function (Blueprint $table) {
        $table->id();
        $table->foreignId('client_id')->constrained()->cascadeOnDelete();
        $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
        $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();

        $table->string('invoice_number')->nullable();
        $table->date('purchase_date');

        $table->decimal('subtotal', 14, 2)->default(0);
        $table->decimal('discount_amount', 14, 2)->default(0);
        $table->decimal('tax_amount', 14, 2)->default(0);
        $table->decimal('total_amount', 14, 2)->default(0);

        $table->string('payment_type')->default('credit'); // cash, credit, mixed
        $table->string('payment_status')->default('pending'); // pending, partial, paid
        $table->text('notes')->nullable();

        $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        $table->boolean('is_active')->default(true);

        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
