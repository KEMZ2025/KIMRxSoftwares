<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('branches')
            ->where(function ($query): void {
                $query->where('email', 'main@vip.com')
                    ->orWhere('phone', '0700000000');
            })
            ->where('address', 'Kampala')
            ->update(['address' => null]);

        DB::table('branches')->where('email', 'main@vip.com')->update(['email' => null]);
        DB::table('branches')->where('phone', '0700000000')->update(['phone' => null]);

        DB::table('clients')
            ->where(function ($query): void {
                $query->where('email', 'vip@example.com')
                    ->orWhere('phone', '0700000000');
            })
            ->where('address', 'Kampala')
            ->update(['address' => null]);

        DB::table('clients')->where('email', 'vip@example.com')->update(['email' => null]);
        DB::table('clients')->where('phone', '0700000000')->update(['phone' => null]);
    }

    public function down(): void
    {
        // The old VIP placeholder contacts should not be restored.
    }
};