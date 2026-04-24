<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Client;
use App\Models\Role;
use App\Models\User;
use App\Support\AccessControlBootstrapper;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    public function __construct(
        protected AccessControlBootstrapper $bootstrapper,
    ) {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $this->bootstrapper->ensureForUser($user);

        $query = User::query()
            ->where('client_id', $user->client_id)
            ->where('is_super_admin', false)
            ->with(['branch', 'roles.permissions'])
            ->orderBy('name');

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%')
                    ->orWhereHas('roles', fn ($roleQuery) => $roleQuery->where('name', 'like', '%' . $search . '%'))
                    ->orWhereHas('branch', fn ($branchQuery) => $branchQuery->where('name', 'like', '%' . $search . '%'));
            });
        }

        $users = $query->paginate(12)->withQueryString();

        $summaryQuery = User::query()
            ->where('client_id', $user->client_id)
            ->where('is_super_admin', false);
        $seatSummary = $this->userSeatSummary($user);

        return view('admin.users.index', [
            'user' => $user,
            'clientName' => optional($user->client)->name ?? 'N/A',
            'branchName' => optional($user->branch)->name ?? 'N/A',
            'users' => $users,
            'userSeatSummary' => $seatSummary,
            'totalUsers' => (clone $summaryQuery)->count(),
            'activeUsers' => (clone $summaryQuery)->where('is_active', true)->count(),
            'inactiveUsers' => (clone $summaryQuery)->where('is_active', false)->count(),
            'administrators' => $this->activeAdministratorCount((int) $user->client_id),
        ]);
    }

    public function create(Request $request)
    {
        $user = $request->user();
        $this->bootstrapper->ensureForUser($user);

        return view('admin.users.create', [
            'user' => $user,
            'clientName' => optional($user->client)->name ?? 'N/A',
            'branchName' => optional($user->branch)->name ?? 'N/A',
            'branches' => $this->availableBranches((int) $user->client_id),
            'roles' => $this->availableRoles((int) $user->client_id),
            'userSeatSummary' => $this->userSeatSummary($user),
        ]);
    }

    public function store(Request $request)
    {
        $admin = $request->user();
        $this->bootstrapper->ensureForUser($admin);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'branch_id' => [
                'required',
                Rule::exists('branches', 'id')->where(fn ($query) => $query
                    ->where('client_id', $admin->client_id)
                    ->where('is_active', true)),
            ],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => [
                'integer',
                Rule::exists('roles', 'id')->where(fn ($query) => $query->where('client_id', $admin->client_id)),
            ],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if ($request->boolean('is_active', true) && ! $this->hasSeatAvailable($admin)) {
            return back()
                ->withInput()
                ->withErrors([
                    'is_active' => $this->seatLimitErrorMessage($admin),
                ]);
        }

        $managedUser = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'client_id' => $admin->client_id,
            'branch_id' => $validated['branch_id'],
            'is_active' => $request->boolean('is_active', true),
            'is_super_admin' => false,
        ]);

        $managedUser->roles()->sync($validated['role_ids']);

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User created and roles assigned successfully.');
    }

    public function edit(Request $request, User $managedUser)
    {
        $admin = $request->user();
        $this->bootstrapper->ensureForUser($admin);
        $this->ensureSameClient($admin, $managedUser);

        $managedUser->load('roles');

        return view('admin.users.edit', [
            'user' => $admin,
            'managedUser' => $managedUser,
            'clientName' => optional($admin->client)->name ?? 'N/A',
            'branchName' => optional($admin->branch)->name ?? 'N/A',
            'branches' => $this->availableBranches((int) $admin->client_id),
            'roles' => $this->availableRoles((int) $admin->client_id),
            'userSeatSummary' => $this->userSeatSummary($admin),
        ]);
    }

    public function update(Request $request, User $managedUser)
    {
        $admin = $request->user();
        $this->bootstrapper->ensureForUser($admin);
        $this->ensureSameClient($admin, $managedUser);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($managedUser->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'branch_id' => [
                'required',
                Rule::exists('branches', 'id')->where(fn ($query) => $query
                    ->where('client_id', $admin->client_id)
                    ->where('is_active', true)),
            ],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => [
                'integer',
                Rule::exists('roles', 'id')->where(fn ($query) => $query->where('client_id', $admin->client_id)),
            ],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $selectedRoles = Role::query()
            ->where('client_id', $admin->client_id)
            ->whereIn('id', $validated['role_ids'])
            ->with('permissions')
            ->get();

        $newStatus = $request->boolean('is_active', true);

        if (! $managedUser->is_active && $newStatus && ! $this->hasSeatAvailable($admin)) {
            return back()->withErrors([
                'is_active' => $this->seatLimitErrorMessage($admin),
            ])->withInput();
        }

        if ((int) $managedUser->id === (int) $admin->id && !$newStatus) {
            return back()->withErrors([
                'is_active' => 'You cannot deactivate your own account.',
            ])->withInput();
        }

        if ((int) $managedUser->id === (int) $admin->id && !$this->selectionKeepsAdministrativeAccess($selectedRoles)) {
            return back()->withErrors([
                'role_ids' => 'You cannot remove your own user-management access from this account.',
            ])->withInput();
        }

        if (!$this->selectionKeepsAdministrativeAccess($selectedRoles) && $this->wouldRemoveLastAdministrator($admin, $managedUser, $newStatus)) {
            return back()->withErrors([
                'role_ids' => 'At least one active administrator with user access must remain for this client.',
            ])->withInput();
        }

        $managedUser->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'branch_id' => $validated['branch_id'],
            'is_active' => $newStatus,
        ]);

        if (!empty($validated['password'])) {
            $managedUser->password = $validated['password'];
        }

        $managedUser->save();
        $managedUser->roles()->sync($validated['role_ids']);

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User updated successfully.');
    }

    public function toggleStatus(Request $request, User $managedUser)
    {
        $admin = $request->user();
        $this->bootstrapper->ensureForUser($admin);
        $this->ensureSameClient($admin, $managedUser);

        if ($managedUser->isSuperAdmin()) {
            return back()->withErrors([
                'status' => 'The platform owner account cannot be activated or deactivated from client administration.',
            ]);
        }

        if ((int) $managedUser->id === (int) $admin->id) {
            return back()->withErrors([
                'status' => 'You cannot change your own active status here.',
            ]);
        }

        $nextStatus = !$managedUser->is_active;
        $managedUser->load('roles.permissions');

        if (!$nextStatus && $this->wouldRemoveLastAdministrator($admin, $managedUser, false)) {
            return back()->withErrors([
                'status' => 'At least one active administrator with user access must remain for this client.',
            ]);
        }

        if ($nextStatus && ! $this->hasSeatAvailable($admin)) {
            return back()->withErrors([
                'status' => $this->seatLimitErrorMessage($admin),
            ]);
        }

        $managedUser->update(['is_active' => $nextStatus]);

        return redirect()
            ->route('admin.users.index')
            ->with('success', $nextStatus ? 'User reactivated successfully.' : 'User deactivated successfully.');
    }

    protected function ensureSameClient(User $admin, User $managedUser): void
    {
        abort_if($managedUser->isSuperAdmin(), 403, 'The platform owner account is managed separately.');
        abort_unless((int) $managedUser->client_id === (int) $admin->client_id, 404);
    }

    protected function availableBranches(int $clientId)
    {
        return Branch::query()
            ->where('client_id', $clientId)
            ->where('is_active', true)
            ->orderByDesc('is_main')
            ->orderBy('name')
            ->get();
    }

    protected function availableRoles(int $clientId)
    {
        return Role::query()
            ->where('client_id', $clientId)
            ->with('permissions')
            ->orderByDesc('is_system_role')
            ->orderBy('name')
            ->get();
    }

    protected function selectionKeepsAdministrativeAccess($roles): bool
    {
        return $roles->contains(function (Role $role) {
            return $role->permissions->contains(function ($permission) {
                return in_array($permission->permission_key, ['users.manage', 'roles.manage'], true);
            });
        });
    }

    protected function activeAdministratorCount(int $clientId, ?int $excludingUserId = null): int
    {
        return User::query()
            ->where('client_id', $clientId)
            ->where('is_super_admin', false)
            ->where('is_active', true)
            ->when($excludingUserId, fn ($query) => $query->where('id', '!=', $excludingUserId))
            ->whereHas('roles.permissions', fn ($query) => $query->whereIn('permission_key', ['users.manage', 'roles.manage']))
            ->count();
    }

    protected function wouldRemoveLastAdministrator(User $admin, User $managedUser, bool $nextStatus): bool
    {
        if ($nextStatus) {
            return false;
        }

        $managedUser->loadMissing('roles.permissions');

        $managedUserHasAdminAccess = $this->selectionKeepsAdministrativeAccess($managedUser->roles);

        if (!$managedUserHasAdminAccess) {
            return false;
        }

        return $this->activeAdministratorCount((int) $admin->client_id, (int) $managedUser->id) === 0;
    }

    protected function hasSeatAvailable(User $admin): bool
    {
        $admin->loadMissing('client');
        $client = $admin->client;

        if (! $client instanceof Client) {
            return true;
        }

        return ! $client->activeUserLimitReached(
            $this->activeManagedUserCount((int) $admin->client_id)
        );
    }

    protected function seatLimitErrorMessage(User $admin): string
    {
        $admin->loadMissing('client');
        $client = $admin->client;

        if (! $client instanceof Client || $client->hasUnlimitedActiveUsers()) {
            return 'This client package has no active-user seats available right now.';
        }

        return 'This client package allows only ' . $client->activeUserLimitLabel()
            . ' active user' . ((int) $client->active_user_limit === 1 ? '' : 's')
            . '. Deactivate another user or increase the seat limit first.';
    }

    protected function activeManagedUserCount(int $clientId): int
    {
        return User::query()
            ->where('client_id', $clientId)
            ->where('is_super_admin', false)
            ->where('is_active', true)
            ->count();
    }

    protected function userSeatSummary(User $admin): array
    {
        $admin->loadMissing('client');
        $client = $admin->client;
        $activeUsers = $this->activeManagedUserCount((int) $admin->client_id);

        if (! $client instanceof Client) {
            return [
                'has_limit' => false,
                'used' => $activeUsers,
                'limit_label' => 'Unlimited',
                'remaining_label' => 'Unlimited',
                'is_full' => false,
            ];
        }

        $remainingSeats = $client->remainingActiveUserSeats($activeUsers);

        return [
            'has_limit' => ! $client->hasUnlimitedActiveUsers(),
            'used' => $activeUsers,
            'limit_label' => $client->activeUserLimitLabel(),
            'remaining_label' => $remainingSeats === null ? 'Unlimited' : number_format($remainingSeats),
            'is_full' => $client->activeUserLimitReached($activeUsers),
        ];
    }
}
