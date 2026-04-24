<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_drawer_draws', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_drawer_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->text('reason');
            $table->foreignId('drawn_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('drawn_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_drawer_draws');
    }
};
