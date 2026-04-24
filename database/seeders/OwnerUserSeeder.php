<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class OwnerUserSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'owner@kimrx.com'],
            [
                'name' => 'Platform Owner',
                'password' => Hash::make('password123'),
                'client_id' => null,
                'branch_id' => null,
                'is_active' => true,
                'is_super_admin' => true,
            ]
        );
    }
}
