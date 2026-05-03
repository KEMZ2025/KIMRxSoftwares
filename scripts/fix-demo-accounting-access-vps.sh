#!/usr/bin/env bash
set -euo pipefail

cd /var/www/kimrx

php artisan tinker --execute='
use App\Models\ClientSetting;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\AccessControlBootstrapper;
use App\Support\ClientFeatureAccess;
use App\Support\PermissionCatalog;

$emails = ["admin@demo.com", "admin@vip.com"];
$users = User::query()->whereIn("email", $emails)->get();

if ($users->isEmpty()) {
    throw new RuntimeException("No demo admin user was found for admin@demo.com or admin@vip.com.");
}

foreach ($users as $user) {
    $clientId = (int) $user->client_id;

    if ($clientId <= 0) {
        echo "Skipped {$user->email}: no client attached.\n";
        continue;
    }

    app(AccessControlBootstrapper::class)->ensureForUser($user);

    $settings = ClientSetting::query()->firstOrCreate(
        ["client_id" => $clientId],
        ["business_mode" => $user->client?->business_mode ?? "both"] + ClientFeatureAccess::defaultSettingValues()
    );

    $settings->forceFill([
        "accounts_enabled" => true,
        "accounting_chart_enabled" => true,
        "accounting_general_ledger_enabled" => true,
        "accounting_trial_balance_enabled" => true,
        "accounting_journals_enabled" => true,
        "accounting_vouchers_enabled" => true,
        "accounting_profit_loss_enabled" => true,
        "accounting_balance_sheet_enabled" => true,
        "accounting_expenses_enabled" => true,
        "accounting_fixed_assets_enabled" => true,
    ])->save();

    $adminRole = Role::query()->firstOrCreate(
        ["client_id" => $clientId, "name" => "Admin"],
        [
            "code" => "client-" . $clientId . "-admin",
            "description" => "Full control over operations, accounting actions, and user administration.",
            "is_system_role" => true,
        ]
    );

    $adminRole->forceFill([
        "code" => $adminRole->code ?: "client-" . $clientId . "-admin",
        "description" => "Full control over operations, accounting actions, and user administration.",
        "is_system_role" => true,
    ])->save();

    $permissionIds = Permission::query()
        ->whereIn("permission_key", array_keys(PermissionCatalog::definitions()))
        ->pluck("id")
        ->all();

    $adminRole->permissions()->sync($permissionIds);
    $user->roles()->syncWithoutDetaching([$adminRole->id]);

    echo "Repaired accounting access for {$user->email} under client #{$clientId}.\n";
}
'

php artisan optimize:clear