<?php

namespace App\Support;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AccessControlBootstrapper
{
    public function ensureForUser(?User $user): void
    {
        if (!$user || !$user->client_id) {
            return;
        }

        $this->ensureForClient((int) $user->client_id, $user);
    }

    public function ensureForClient(int $clientId, ?User $user = null): void
    {
        if (!$this->tablesReady()) {
            return;
        }

        $this->ensurePermissions();
        $this->ensureRoles($clientId);

        if ($user) {
            $this->ensureInitialAdministrator($clientId, $user);
        }
    }

    protected function tablesReady(): bool
    {
        return Schema::hasTable('permissions')
            && Schema::hasTable('roles')
            && Schema::hasTable('role_permissions')
            && Schema::hasTable('user_roles');
    }

    protected function ensurePermissions(): void
    {
        foreach (PermissionCatalog::definitions() as $key => $definition) {
            Permission::query()->updateOrCreate(
                ['permission_key' => $key],
                [
                    'module_name' => $definition['module'],
                    'action_name' => $definition['action'],
                    'description' => $definition['description'],
                ]
            );
        }
    }

    protected function ensureRoles(int $clientId): void
    {
        $permissionsByKey = Permission::query()
            ->whereIn('permission_key', array_keys(PermissionCatalog::definitions()))
            ->get()
            ->keyBy('permission_key');

        foreach (PermissionCatalog::defaultRoles() as $slug => $definition) {
            $role = Role::query()->firstOrCreate(
                [
                    'client_id' => $clientId,
                    'name' => $definition['name'],
                ],
                [
                    'code' => 'client-' . $clientId . '-' . Str::slug($slug),
                    'description' => $definition['description'],
                    'is_system_role' => $definition['is_system_role'],
                ]
            );

            if (!$role->wasRecentlyCreated) {
                $role->fill([
                    'description' => $role->description ?: $definition['description'],
                    'is_system_role' => $role->is_system_role || $definition['is_system_role'],
                ])->save();
            }

            $permissionIds = collect($definition['permissions'])
                ->map(fn (string $permissionKey) => $permissionsByKey->get($permissionKey)?->id)
                ->filter()
                ->values()
                ->all();

            if ($role->isProtectedAdminRole()) {
                $role->permissions()->sync($permissionIds);
                continue;
            }

            if (!$role->permissions()->exists()) {
                $role->permissions()->sync($permissionIds);
            }
        }
    }

    protected function ensureInitialAdministrator(int $clientId, User $user): void
    {
        if ($user->roles()->exists()) {
            return;
        }

        $clientHasAnyRoleAssignments = Role::query()
            ->where('client_id', $clientId)
            ->whereHas('users')
            ->exists();

        if ($clientHasAnyRoleAssignments) {
            return;
        }

        $adminRole = Role::query()
            ->where('client_id', $clientId)
            ->where('name', 'Admin')
            ->first();

        if ($adminRole) {
            $user->roles()->syncWithoutDetaching([$adminRole->id]);
        }
    }
}
