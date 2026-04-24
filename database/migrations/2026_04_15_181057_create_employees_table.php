<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    Schema::create('employees', function (Blueprint $table) {
        $table->id();
        $table->foreignId('client_id')->constrained()->cascadeOnDelete();
        $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->string('employee_no')->unique();
        $table->string('job_title')->nullable();
        $table->string('phone')->nullable();
        $table->text('address')->nullable();
        $table->date('hire_date')->nullable();
        $table->boolean('is_active')->default(true);
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
