<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('filename')->unique();
            $table->string('disk_path');
            $table->string('export_type')->default('client_export');
            $table->string('status')->default('ready');
            $table->unsignedBigInteger('total_size_bytes')->default(0);
            $table->unsignedInteger('database_tables_count')->default(0);
            $table->unsignedBigInteger('database_rows_count')->default(0);
            $table->unsignedInteger('storage_files_count')->default(0);
            $table->unsignedBigInteger('storage_bytes')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->json('manifest_json')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_exports');
    }
};
