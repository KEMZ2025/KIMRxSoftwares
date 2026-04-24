<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('efris_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->string('environment', 20)->default('sandbox');
            $table->string('document_kind', 30);
            $table->string('status', 30)->default('ready');
            $table->string('next_action', 30)->default('submit_sale');
            $table->string('reference_number')->nullable();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestamp('prepared_at')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('reversal_required_at')->nullable();
            $table->text('last_error_message')->nullable();
            $table->json('payload_snapshot')->nullable();
            $table->json('response_snapshot')->nullable();
            $table->timestamps();

            $table->unique('sale_id');
            $table->index(['client_id', 'branch_id', 'status', 'next_action'], 'efris_documents_scope_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('efris_documents');
    }
};
