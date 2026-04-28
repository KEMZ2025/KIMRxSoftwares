<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_backups', function (Blueprint $table) {
            $table->id();
            $table->string('filename')->unique();
            $table->string('disk_path');
            $table->string('backup_type', 50)->default('full_platform');
            $table->string('status', 30)->default('ready');
            $table->unsignedBigInteger('total_size_bytes')->nullable();
            $table->unsignedInteger('database_tables_count')->nullable();
            $table->unsignedBigInteger('database_rows_count')->nullable();
            $table->unsignedInteger('storage_files_count')->nullable();
            $table->unsignedBigInteger('storage_bytes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('restored_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('restored_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('manifest_json')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_backups');
    }
};
