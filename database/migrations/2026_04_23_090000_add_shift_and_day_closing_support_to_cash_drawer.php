<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_drawer_sessions', function (Blueprint $table) {
            $table->dateTime('day_closed_at')->nullable()->after('opened_by');
            $table->foreignId('day_closed_by')->nullable()->after('day_closed_at')->constrained('users')->nullOnDelete();
            $table->decimal('day_closing_expected_balance', 14, 2)->nullable()->after('day_closed_by');
            $table->decimal('day_closing_counted_balance', 14, 2)->nullable()->after('day_closing_expected_balance');
            $table->decimal('day_closing_variance', 14, 2)->nullable()->after('day_closing_counted_balance');
            $table->text('day_closing_note')->nullable()->after('day_closing_variance');
            $table->dateTime('day_reopened_at')->nullable()->after('day_closing_note');
            $table->foreignId('day_reopened_by')->nullable()->after('day_reopened_at')->constrained('users')->nullOnDelete();
            $table->text('day_reopening_note')->nullable()->after('day_reopened_by');
        });

        Schema::create('cash_drawer_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_drawer_session_id')->constrained('cash_drawer_sessions')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('opened_by')->constrained('users')->cascadeOnDelete();
            $table->decimal('opening_balance', 14, 2)->default(0);
            $table->text('opening_note')->nullable();
            $table->dateTime('opened_at');
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('closing_expected_balance', 14, 2)->nullable();
            $table->decimal('closing_counted_balance', 14, 2)->nullable();
            $table->decimal('closing_variance', 14, 2)->nullable();
            $table->decimal('banked_amount', 14, 2)->default(0);
            $table->decimal('handover_amount', 14, 2)->default(0);
            $table->text('closing_note')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'branch_id', 'opened_at']);
            $table->index(['branch_id', 'closed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_drawer_shifts');

        Schema::table('cash_drawer_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('day_closed_by');
            $table->dropConstrainedForeignId('day_reopened_by');
            $table->dropColumn([
                'day_closed_at',
                'day_closing_expected_balance',
                'day_closing_counted_balance',
                'day_closing_variance',
                'day_closing_note',
                'day_reopened_at',
                'day_reopening_note',
            ]);
        });
    }
};
