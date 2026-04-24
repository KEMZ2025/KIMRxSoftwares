<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_settings', function (Blueprint $table) {
            $table->string('efris_auth_url')->nullable()->after('efris_device_serial');
            $table->string('efris_submission_url')->nullable()->after('efris_auth_url');
            $table->string('efris_reversal_url')->nullable()->after('efris_submission_url');
            $table->text('efris_username')->nullable()->after('efris_reversal_url');
            $table->text('efris_password')->nullable()->after('efris_username');
            $table->text('efris_client_id')->nullable()->after('efris_password');
            $table->text('efris_client_secret')->nullable()->after('efris_client_id');
        });
    }

    public function down(): void
    {
        Schema::table('client_settings', function (Blueprint $table) {
            $table->dropColumn([
                'efris_auth_url',
                'efris_submission_url',
                'efris_reversal_url',
                'efris_username',
                'efris_password',
                'efris_client_id',
                'efris_client_secret',
            ]);
        });
    }
};
