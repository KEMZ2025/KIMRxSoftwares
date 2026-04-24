<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Client;
use App\Models\ClientSetting;
use App\Models\Role;
use App\Models\User;
use App\Support\AccessControlBootstrapper;
use App\Support\ClientFeatureAccess;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $client = Client::query()->firstOrCreate(
                ['name' => 'VIP Pharmacy'],
                [
                    'email' => 'vip@example.com',
                    'phone' => '0700000000',
                    'address' => 'Kampala',
                    'logo' => null,
                    'business_mode' => 'both',
                    'client_type' => Client::TYPE_DEMO,
                    'subscription_status' => Client::STATUS_ACTIVE,
                    'is_active' => true,
                    'is_platform_sandbox' => false,
                ]
            );

            $branch = Branch::query()->firstOrCreate(
                [
                    'client_id' => $client->id,
                    'code' => 'MAIN',
                ],
                [
                    'name' => 'Main Branch',
                    'phone' => '0700000000',
                    'email' => 'main@vip.com',
                    'address' => 'Kampala',
                    'business_mode' => 'inherit',
                    'is_main' => true,
                    'is_active' => true,
                ]
            );

            ClientSetting::query()->firstOrCreate(
                ['client_id' => $client->id],
                ['business_mode' => 'both'] + ClientFeatureAccess::defaultSettingValues()
            );

            $user = User::query()->updateOrCreate(
                ['email' => 'admin@vip.com'],
                [
                    'name' => 'Admin User',
                    'password' => Hash::make('password123'),
                    'client_id' => $client->id,
                    'branch_id' => $branch->id,
                    'is_active' => true,
                    'is_super_admin' => false,
                ]
            );

            app(AccessControlBootstrapper::class)->ensureForUser($user);

            $adminRole = Role::query()
                ->where('client_id', $client->id)
                ->where('name', 'Admin')
                ->first();

            if ($adminRole) {
                $user->roles()->syncWithoutDetaching([$adminRole->id]);
            }
        });
    }
}
