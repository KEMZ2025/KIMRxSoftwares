<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('supplier_payments', function (Blueprint $table) {
            $table->foreignId('reversal_of_payment_id')->nullable()->unique()->constrained('supplier_payments')->restrictOnDelete();
        });
        Schema::create('purchase_cancellations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('effective_at');
            $table->decimal('amount', 14, 2);
            $table->text('reason');
            $table->json('snapshot');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_cancellations');
        Schema::table('supplier_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reversal_of_payment_id');
        });
    }
};
