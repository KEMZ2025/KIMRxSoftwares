<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insurance_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('insurer_id')->constrained('insurers')->cascadeOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reversal_of_payment_id')->nullable()->constrained('insurance_payments')->nullOnDelete();
            $table->string('payment_method');
            $table->decimal('amount', 14, 2);
            $table->string('reference_number')->nullable();
            $table->dateTime('payment_date');
            $table->string('status')->default('received');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'branch_id', 'payment_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insurance_payments');
    }
};
