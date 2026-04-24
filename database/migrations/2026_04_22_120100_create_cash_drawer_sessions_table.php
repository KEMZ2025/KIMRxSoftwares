<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_drawer_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->date('session_date');
            $table->decimal('opening_balance', 14, 2)->default(0);
            $table->text('opening_note')->nullable();
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['client_id', 'branch_id', 'session_date'], 'cash_drawer_session_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_drawer_sessions');
    }
};
