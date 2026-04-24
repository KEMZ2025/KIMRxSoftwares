<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->decimal('amount_paid', 14, 2)->default(0)->after('total_amount');
            $table->decimal('balance_due', 14, 2)->default(0)->after('amount_paid');
            $table->date('due_date')->nullable()->after('payment_status');
            $table->string('invoice_status')->default('draft')->after('due_date');
            // draft, partial_received, fully_received, closed
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn([
                'amount_paid',
                'balance_due',
                'due_date',
                'invoice_status',
            ]);
        });
    }
};