<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\ClientSetting;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\ClientFeatureAccess;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairElohimAccountingAccess extends Command
{
    private const CLIENT_NAME = 'ELOHIM DRUGSHOP';

    protected $signature = 'client:repair-elohim-accounting-access {--execute} {--confirm=}';

    protected $description = 'Preview or restore Elohim Admin access to every accounting section';

    public function handle(): int
    {
        $client = Client::query()->where('name', self::CLIENT_NAME)->first();
        if (!$client) {
            $this->error('Elohim client was not found. Nothing was changed.');
            return self::FAILURE;
        }

        $settings = ClientSetting::query()->where('client_id', $client->id)->first();
        $admin = User::query()->where('client_id', $client->id)->where('email', 'admin@elohim.com')->first();
        $role = Role::query()->where('client_id', $client->id)->where('name', 'Admin')->first();
        if (!$settings || !$admin || !$role) {
            $this->error('Elohim settings, administrator, or Admin role is missing. Nothing was changed.');
            return self::FAILURE;
        }

        $flags = array_merge(['accounts_enabled'], array_column(ClientFeatureAccess::accountingFeatureDefinitions(), 'field'));
        $permissionKeys = [
            'accounting.view', 'accounting.chart', 'accounting.general_ledger',
            'accounting.trial_balance', 'accounting.journals', 'accounting.vouchers',
            'accounting.profit_loss', 'accounting.balance_sheet',
            'accounting.expenses.view', 'accounting.expenses.manage',
            'accounting.fixed_assets.view', 'accounting.fixed_assets.manage',
        ];
        $permissions = Permission::query()->whereIn('permission_key', $permissionKeys)->get()->keyBy('permission_key');
        $missingDefinitions = array_diff($permissionKeys, $permissions->keys()->all());
        if ($missingDefinitions) {
            $this->error('Accounting permission definitions are missing: ' . implode(', ', $missingDefinitions));
            return self::FAILURE;
        }

        $disabledFlags = array_values(array_filter($flags, fn ($flag) => !(bool) $settings->{$flag}));
        $assignedKeys = $role->permissions()->pluck('permission_key')->all();
        $missingPermissions = array_values(array_diff($permissionKeys, $assignedKeys));
        $adminHasRole = $admin->roles()->whereKey($role->id)->exists();

        $this->line('Client: ' . self::CLIENT_NAME . ' (ID ' . $client->id . ')');
        $this->line('Disabled accounting flags: ' . ($disabledFlags ? implode(', ', $disabledFlags) : 'none'));
        $this->line('Missing Admin permissions: ' . ($missingPermissions ? implode(', ', $missingPermissions) : 'none'));
        $this->line('Elohim administrator has Admin role: ' . ($adminHasRole ? 'yes' : 'no'));

        if (!$this->option('execute')) {
            $this->info('Preview only. No records were changed.');
            return self::SUCCESS;
        }

        if ($this->option('confirm') !== self::CLIENT_NAME) {
            $this->error('Pass --confirm="ELOHIM DRUGSHOP" to execute. Nothing was changed.');
            return self::FAILURE;
        }

        DB::transaction(function () use ($settings, $role, $admin, $flags, $permissions, $missingPermissions, $adminHasRole): void {
            $settings->forceFill(array_fill_keys($flags, true))->save();
            if ($missingPermissions) {
                $missingPermissionIds = $permissions
                    ->filter(fn (Permission $permission) => in_array($permission->permission_key, $missingPermissions, true))
                    ->pluck('id')->all();
                $role->permissions()->syncWithoutDetaching($missingPermissionIds);
            }
            if (!$adminHasRole) {
                $admin->roles()->syncWithoutDetaching([$role->id]);
            }
        });

        $this->info('Elohim accounting access restored. No operational or accounting entries were changed.');
        return self::SUCCESS;
    }
}
