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
    Schema::create('payments', function (Blueprint $table) {
        $table->id();
        $table->foreignId('client_id')->constrained()->cascadeOnDelete();
        $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
        $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
        $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
        $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();

        $table->string('payment_method'); // cash, mtn, airtel, bank
        $table->decimal('amount', 14, 2);
        $table->string('reference_number')->nullable();
        $table->dateTime('payment_date');
        $table->string('status')->default('received'); // received, reversed, pending
        $table->text('notes')->nullable();

        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
