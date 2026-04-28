<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insurance_claim_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('insurer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('adjustment_type')->default('writeoff');
            $table->decimal('amount', 15, 2);
            $table->timestamp('adjustment_date');
            $table->boolean('mark_claim_rejected')->default(false);
            $table->text('reason');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'branch_id', 'insurer_id'], 'insurance_claim_adjustments_scope_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insurance_claim_adjustments');
    }
};
