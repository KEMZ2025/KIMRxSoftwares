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
    Schema::create('sales', function (Blueprint $table) {
        $table->id();
        $table->foreignId('client_id')->constrained()->cascadeOnDelete();
        $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
        $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
        $table->foreignId('served_by')->nullable()->constrained('users')->nullOnDelete();

        $table->string('invoice_number')->nullable();
        $table->string('sale_type')->default('retail'); // retail, wholesale, proforma
        $table->string('status')->default('pending'); // pending, approved, paid, partial, cancelled
        $table->string('payment_type')->default('cash'); // cash, credit, mixed

        $table->decimal('subtotal', 14, 2)->default(0);
        $table->decimal('discount_amount', 14, 2)->default(0);
        $table->decimal('tax_amount', 14, 2)->default(0);
        $table->decimal('total_amount', 14, 2)->default(0);
        $table->decimal('amount_paid', 14, 2)->default(0);
        $table->decimal('balance_due', 14, 2)->default(0);

        $table->dateTime('sale_date');
        $table->text('notes')->nullable();
        $table->boolean('is_active')->default(true);

        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
