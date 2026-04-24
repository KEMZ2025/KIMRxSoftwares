<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('payments', 'reversal_of_payment_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->foreignId('reversal_of_payment_id')
                    ->nullable()
                    ->after('received_by')
                    ->constrained('payments')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('payments', 'reversal_of_payment_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropConstrainedForeignId('reversal_of_payment_id');
            });
        }
    }
};
