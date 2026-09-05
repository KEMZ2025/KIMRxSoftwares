<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounting_expenses', function (Blueprint $table) {
            $table->string('void_reason', 500)->nullable()->after('is_active');
            $table->timestamp('voided_at')->nullable()->after('void_reason');
            $table->foreignId('voided_by')->nullable()->after('voided_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('accounting_expenses', function (Blueprint $table) {
            $table->dropForeign(['voided_by']);
            $table->dropColumn(['void_reason', 'voided_at', 'voided_by']);
        });
    }
};
