<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_super_admin')->default(false)->after('is_active');
        });

        $existingSuperAdmin = DB::table('users')
            ->where('is_super_admin', true)
            ->exists();

        if (!$existingSuperAdmin) {
            $firstUserId = DB::table('users')->orderBy('id')->value('id');

            if ($firstUserId) {
                DB::table('users')
                    ->where('id', $firstUserId)
                    ->update([
                        'is_super_admin' => true,
                        'updated_at' => now(),
                    ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_super_admin');
        });
    }
};
