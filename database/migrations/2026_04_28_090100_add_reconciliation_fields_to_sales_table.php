<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('insurance_claim_batch_id')
                ->nullable()
                ->after('insurer_id')
                ->constrained('insurance_claim_batches')
                ->nullOnDelete();
            $table->text('insurance_rejection_reason')->nullable()->after('insurance_status_notes');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('insurance_claim_batch_id');
            $table->dropColumn('insurance_rejection_reason');
        });
    }
};
