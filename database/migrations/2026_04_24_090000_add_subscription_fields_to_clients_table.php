<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('client_type')->default('paying')->after('business_mode');
            $table->string('subscription_status')->default('active')->after('client_type');
            $table->unsignedInteger('active_user_limit')->nullable()->after('subscription_status');
            $table->date('subscription_ends_at')->nullable()->after('active_user_limit');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn([
                'client_type',
                'subscription_status',
                'active_user_limit',
                'subscription_ends_at',
            ]);
        });
    }
};
