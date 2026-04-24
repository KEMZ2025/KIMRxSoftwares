<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('customers', 'outstanding_balance')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->decimal('outstanding_balance', 12, 2)->default(0)->after('credit_limit');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('customers', 'outstanding_balance')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn('outstanding_balance');
            });
        }
    }
};
