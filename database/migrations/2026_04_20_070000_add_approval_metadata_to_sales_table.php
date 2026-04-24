<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (!Schema::hasColumn('sales', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->after('served_by')->constrained('users')->nullOnDelete();
            }

            if (!Schema::hasColumn('sales', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('sale_date');
            }
        });

        DB::table('sales')
            ->where('status', 'approved')
            ->whereNull('approved_by')
            ->whereNotNull('served_by')
            ->update([
                'approved_by' => DB::raw('served_by'),
            ]);

        DB::table('sales')
            ->where('status', 'approved')
            ->whereNull('approved_at')
            ->update([
                'approved_at' => DB::raw('COALESCE(updated_at, created_at)'),
            ]);
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (Schema::hasColumn('sales', 'approved_by')) {
                $table->dropConstrainedForeignId('approved_by');
            }

            if (Schema::hasColumn('sales', 'approved_at')) {
                $table->dropColumn('approved_at');
            }
        });
    }
};
