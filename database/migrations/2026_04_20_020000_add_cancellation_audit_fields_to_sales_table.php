<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('cancelled_by')->nullable()->after('served_by')->constrained('users')->nullOnDelete();
            $table->foreignId('restored_by')->nullable()->after('cancelled_by')->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable()->after('sale_date');
            $table->text('cancel_reason')->nullable()->after('cancelled_at');
            $table->string('cancelled_from_status', 32)->nullable()->after('cancel_reason');
            $table->timestamp('restored_at')->nullable()->after('cancelled_from_status');
            $table->text('restore_reason')->nullable()->after('restored_at');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropConstrainedForeignId('restored_by');
            $table->dropColumn([
                'cancelled_at',
                'cancel_reason',
                'cancelled_from_status',
                'restored_at',
                'restore_reason',
            ]);
        });
    }
};
