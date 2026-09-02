<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('medicine_name');
            $table->string('strength', 100)->nullable();
            $table->string('dosage_form', 100)->nullable();
            $table->decimal('quantity', 12, 2)->nullable();
            $table->string('unit_name', 100)->nullable();
            $table->text('note')->nullable();
            $table->string('status', 20)->default('pending');
            $table->char('request_key', 64);
            $table->uuid('submission_token');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['client_id', 'branch_id', 'submission_token'], 'stock_requests_submission_unique');
            $table->index(['client_id', 'branch_id', 'created_at'], 'stock_requests_branch_date');
            $table->index(['client_id', 'branch_id', 'request_key'], 'stock_requests_branch_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_requests');
    }
};
