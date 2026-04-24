<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('asset_name');
            $table->string('asset_category', 40);
            $table->string('asset_code')->nullable();
            $table->date('acquisition_date');
            $table->decimal('acquisition_cost', 14, 2);
            $table->decimal('salvage_value', 14, 2)->default(0);
            $table->unsignedInteger('useful_life_months');
            $table->string('payment_method')->nullable();
            $table->string('vendor_name')->nullable();
            $table->string('reference_number')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['client_id', 'branch_id', 'asset_category']);
            $table->index(['client_id', 'branch_id', 'acquisition_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_assets');
    }
};
