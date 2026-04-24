<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('account_code', 10);
            $table->dateTime('expense_date');
            $table->decimal('amount', 14, 2);
            $table->string('payment_method')->nullable();
            $table->string('payee_name')->nullable();
            $table->string('reference_number')->nullable();
            $table->string('description');
            $table->text('notes')->nullable();
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['client_id', 'branch_id', 'expense_date']);
            $table->index(['client_id', 'branch_id', 'account_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_expenses');
    }
};
