<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use App\Support\AccessControlBootstrapper;
use App\Support\PermissionCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RoleManagementController extends Controller
{
    public function __construct(
        protected AccessControlBootstrapper $bootstrapper,
    ) {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $this->bootstrapper->ensureForUser($user);

        $roles = Role::query()
            ->where('client_id', $user->client_id)
            ->withCount('users')
            ->with('permissions')
            ->orderByDesc('is_system_role')
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return view('admin.roles.index', [
            'user' => $user,
            'clientName' => optional($user->client)->name ?? 'N/A',
            'branchName' => optional($user->branch)->name ?? 'N/A',
            'roles' => $roles,
            'totalRoles' => Role::query()->where('client_id', $user->client_id)->count(),
            'systemRoles' => Role::query()->where('client_id', $user->client_id)->where('is_system_role', true)->count(),
            'customRoles' => Role::query()->where('client_id', $user->client_id)->where('is_system_role', false)->count(),
            'permissionCount' => Permission::query()->count(),
        ]);
    }

    public function create(Request $request)
    {
        $user = $request->user();
        $this->bootstrapper->ensureForUser($user);

        return view('admin.roles.create', [
            'user' => $user,
            'clientName' => optional($user->client)->name ?? 'N/A',
            'branchName' => optional($user->branch)->name ?? 'N/A',
            'permissionGroups' => PermissionCatalog::groupedDefinitions(),
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $this->bootstrapper->ensureForUser($user);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'name')->where(fn ($query) => $query->where('client_id', $user->client_id)),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'permission_keys' => ['required', 'array', 'min:1'],
            'permission_keys.*' => ['string', Rule::in(array_keys(PermissionCatalog::definitions()))],
        ]);

        $role = Role::create([
            'client_id' => $user->client_id,
            'name' => $validated['name'],
            'code' => $this->generateRoleCode((int) $user->client_id, $validated['name']),
            'description' => $validated['description'] ?? null,
            'is_system_role' => false,
        ]);

        $role->permissions()->sync($this->permissionIdsFromKeys($validated['permission_keys']));

        return redirect()
            ->route('admin.roles.index')
            ->with('success', 'Role created successfully.');
    }

    public function edit(Request $request, Role $role)
    {
        $user = $request->user();
        $this->bootstrapper->ensureForUser($user);
        $this->ensureSameClient($user, $role);

        $role->load('permissions');

        return view('admin.roles.edit', [
            'user' => $user,
            'role' => $role,
            'clientName' => optional($user->client)->name ?? 'N/A',
            'branchName' => optional($user->branch)->name ?? 'N/A',
            'permissionGroups' => PermissionCatalog::groupedDefinitions(),
            'selectedPermissionKeys' => $role->permissions->pluck('permission_key')->all(),
        ]);
    }

    public function update(Request $request, Role $role)
    {
        $user = $request->user();
        $this->bootstrapper->ensureForUser($user);
        $this->ensureSameClient($user, $role);

        if ($role->isProtectedAdminRole()) {
            $role->update([
                'description' => $request->input('description'),
            ]);

            return redirect()
                ->route('admin.roles.index')
                ->with('success', 'Admin role details refreshed. Its full-access permissions remain locked.');
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'name')
                    ->ignore($role->id)
                    ->where(fn ($query) => $query->where('client_id', $user->client_id)),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'permission_keys' => ['required', 'array', 'min:1'],
            'permission_keys.*' => ['string', Rule::in(array_keys(PermissionCatalog::definitions()))],
        ]);

        $role->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        $role->permissions()->sync($this->permissionIdsFromKeys($validated['permission_keys']));

        return redirect()
            ->route('admin.roles.index')
            ->with('success', 'Role updated successfully.');
    }

    protected function ensureSameClient($user, Role $role): void
    {
        abort_unless((int) $role->client_id === (int) $user->client_id, 404);
    }

    protected function permissionIdsFromKeys(array $permissionKeys): array
    {
        return Permission::query()
            ->whereIn('permission_key', $permissionKeys)
            ->pluck('id')
            ->all();
    }

    protected function generateRoleCode(int $clientId, string $name): string
    {
        $base = 'client-' . $clientId . '-custom-' . Str::slug($name);
        $code = $base;
        $suffix = 1;

        while (Role::query()->where('code', $code)->exists()) {
            $suffix++;
            $code = $base . '-' . $suffix;
        }

        return $code;
    }
}
