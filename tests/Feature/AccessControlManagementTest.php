<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use App\Support\AccessControlBootstrapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AccessControlManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_custom_role_and_assign_it_to_a_user(): void
    {
        [$admin, $clientId, $branchId] = $this->createUserContext();

        app(AccessControlBootstrapper::class)->ensureForUser($admin);

        $this->actingAs($admin)->post(route('admin.roles.store'), [
            'name' => 'Front Desk',
            'description' => 'Handles quoting and invoice entry only.',
            'permission_keys' => [
                'dashboard.view',
                'sales.create',
                'sales.proforma',
                'customers.view',
            ],
        ])->assertRedirect(route('admin.roles.index'));

        $role = Role::query()
            ->where('client_id', $clientId)
            ->where('name', 'Front Desk')
            ->firstOrFail();

        $this->assertSame(4, $role->permissions()->count());

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Sarah Clerk',
            'email' => 'sarah@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'branch_id' => $branchId,
            'role_ids' => [$role->id],
            'is_active' => 1,
        ])->assertRedirect(route('admin.users.index'));

        $managedUser = User::query()->where('email', 'sarah@example.com')->firstOrFail();

        $this->assertDatabaseHas('user_roles', [
            'user_id' => $managedUser->id,
            'role_id' => $role->id,
        ]);
    }

    public function test_non_admin_user_cannot_open_admin_or_collection_screens_without_permission(): void
    {
        [$admin, $clientId, $branchId] = $this->createUserContext();

        app(AccessControlBootstrapper::class)->ensureForUser($admin);

        $cashierRole = Role::query()
            ->where('client_id', $clientId)
            ->where('name', 'Cashier')
            ->firstOrFail();

        $cashier = User::factory()->create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'email' => 'cashier@example.com',
            'is_active' => true,
        ]);

        $cashier->roles()->sync([$cashierRole->id]);

        $this->actingAs($cashier)
            ->get(route('dashboard'))
            ->assertOk();

        $this->actingAs($cashier)
            ->get(route('admin.users.index'))
            ->assertForbidden();

        $this->actingAs($cashier)
            ->get(route('stock.index'))
            ->assertForbidden();
    }

    public function test_login_redirects_user_to_first_allowed_screen_when_dashboard_is_not_assigned(): void
    {
        [$admin, $clientId, $branchId] = $this->createUserContext();

        app(AccessControlBootstrapper::class)->ensureForUser($admin);

        $role = Role::create([
            'client_id' => $clientId,
            'name' => 'Sales Entry Only',
            'code' => 'client-' . $clientId . '-sales-entry-only',
            'description' => 'Can enter sales without dashboard access.',
            'is_system_role' => false,
        ]);

        $role->permissions()->sync($this->permissionIds([
            'sales.create',
            'products.view',
        ]));

        $user = User::factory()->create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'email' => 'entryonly@example.com',
            'password' => 'password123',
            'is_active' => true,
        ]);
        $user->roles()->sync([$role->id]);

        $this->post(route('login.submit'), [
            'email' => 'entryonly@example.com',
            'password' => 'password123',
        ])->assertRedirect(route('sales.create'));
    }

    public function test_sale_detail_hides_sensitive_actions_when_role_only_has_view_permission(): void
    {
        [$admin, $clientId, $branchId] = $this->createUserContext();

        app(AccessControlBootstrapper::class)->ensureForUser($admin);

        $role = Role::create([
            'client_id' => $clientId,
            'name' => 'Pending Viewer',
            'code' => 'client-' . $clientId . '-pending-viewer',
            'description' => 'Can view pending sales without sensitive controls.',
            'is_system_role' => false,
        ]);

        $role->permissions()->sync($this->permissionIds([
            'sales.view_pending',
        ]));

        $user = User::factory()->create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'email' => 'viewer@example.com',
            'is_active' => true,
        ]);
        $user->roles()->sync([$role->id]);

        $sale = Sale::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'customer_id' => null,
            'served_by' => $admin->id,
            'invoice_number' => 'SALE-VIEW-001',
            'receipt_number' => null,
            'sale_type' => 'retail',
            'status' => 'pending',
            'payment_type' => 'cash',
            'payment_method' => null,
            'subtotal' => 1000,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 1000,
            'amount_paid' => 0,
            'amount_received' => 0,
            'balance_due' => 1000,
            'sale_date' => now()->toDateString(),
            'notes' => 'View-only pending sale.',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('sales.show', $sale));

        $response->assertOk();
        $response->assertDontSee('Edit Pending Sale');
        $response->assertDontSee('Approve Sale');
        $response->assertDontSee('Cancel Pending Sale');
    }

    public function test_last_administrator_cannot_deactivate_self_or_remove_user_management_access(): void
    {
        [$admin, $clientId, $branchId] = $this->createUserContext();

        app(AccessControlBootstrapper::class)->ensureForUser($admin);

        $dispenserRole = Role::query()
            ->where('client_id', $clientId)
            ->where('name', 'Dispenser')
            ->firstOrFail();

        $this->from(route('admin.users.edit', $admin))
            ->actingAs($admin)
            ->put(route('admin.users.update', $admin), [
                'name' => $admin->name,
                'email' => $admin->email,
                'branch_id' => $branchId,
                'role_ids' => [$dispenserRole->id],
                'is_active' => 1,
            ])
            ->assertRedirect(route('admin.users.edit', $admin))
            ->assertSessionHasErrors('role_ids');

        $this->from(route('admin.users.index'))
            ->actingAs($admin)
            ->patch(route('admin.users.status', $admin))
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHasErrors('status');
    }

    public function test_admin_cannot_create_active_user_when_client_package_is_full(): void
    {
        [$admin, $clientId, $branchId] = $this->createUserContext(activeUserLimit: 1);

        app(AccessControlBootstrapper::class)->ensureForUser($admin);

        $adminRole = Role::query()
            ->where('client_id', $clientId)
            ->where('name', 'Admin')
            ->firstOrFail();

        $this->from(route('admin.users.create'))
            ->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'Blocked User',
                'email' => 'blocked@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'branch_id' => $branchId,
                'role_ids' => [$adminRole->id],
                'is_active' => 1,
            ])
            ->assertRedirect(route('admin.users.create'))
            ->assertSessionHasErrors('is_active');

        $this->assertDatabaseMissing('users', [
            'email' => 'blocked@example.com',
        ]);
    }

    public function test_admin_cannot_reactivate_user_when_client_package_is_full(): void
    {
        [$admin, $clientId, $branchId] = $this->createUserContext(activeUserLimit: 1);

        app(AccessControlBootstrapper::class)->ensureForUser($admin);

        $managedUser = User::factory()->create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'email' => 'inactive-seat@example.com',
            'is_active' => false,
        ]);

        $this->from(route('admin.users.index'))
            ->actingAs($admin)
            ->patch(route('admin.users.status', $managedUser))
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHasErrors('status');

        $this->assertFalse($managedUser->fresh()->is_active);
    }

    private function createUserContext(?int $activeUserLimit = null): array
    {
        $clientId = $this->createClient('KimRx Access Control Client', $activeUserLimit);
        $branchId = $this->createBranch($clientId, 'Main Branch');

        $user = User::factory()->create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'is_active' => true,
        ]);

        return [$user, $clientId, $branchId];
    }

    private function createClient(string $name, ?int $activeUserLimit = null): int
    {
        return DB::table('clients')->insertGetId([
            'name' => $name,
            'business_mode' => 'both',
            'active_user_limit' => $activeUserLimit,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createBranch(int $clientId, string $name): int
    {
        return DB::table('branches')->insertGetId([
            'client_id' => $clientId,
            'name' => $name,
            'code' => strtoupper(substr($name, 0, 3)),
            'is_main' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function permissionIds(array $permissionKeys): array
    {
        return Permission::query()
            ->whereIn('permission_key', $permissionKeys)
            ->pluck('id')
            ->all();
    }
}
