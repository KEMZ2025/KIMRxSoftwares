<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClientSuspensionTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_client_user_cannot_log_in(): void
    {
        [$clientId, $branchId] = $this->createClientWithBranch('Suspended Pharmacy', 'Main Branch', false);

        User::factory()->create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'email' => 'staff@example.com',
            'password' => 'secret-pass',
            'is_active' => true,
            'is_super_admin' => false,
        ]);

        $this->from(route('login'))
            ->post(route('login.submit'), [
                'email' => 'staff@example.com',
                'password' => 'secret-pass',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors([
                'email' => 'This pharmacy workspace is currently suspended. Contact the platform owner.',
            ]);

        $this->assertGuest();
    }

    public function test_authenticated_user_is_logged_out_when_their_client_is_suspended(): void
    {
        [$clientId, $branchId] = $this->createClientWithBranch('Running Pharmacy', 'Main Branch');

        $user = User::factory()->create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'email' => 'staff@example.com',
            'is_active' => true,
            'is_super_admin' => false,
        ]);

        $this->actingAs($user)
            ->get(route('account.password.edit'))
            ->assertOk();

        DB::table('clients')
            ->where('id', $clientId)
            ->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);

        $this->actingAs($user)
            ->get(route('account.password.edit'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors([
                'email' => 'This pharmacy workspace is currently suspended. Contact the platform owner.',
            ]);

        $this->assertGuest();
    }

    public function test_super_admin_can_still_log_in_when_home_client_is_inactive(): void
    {
        [$clientId, $branchId] = $this->createClientWithBranch('Owner Home', 'Main Branch', false);

        User::factory()->create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'email' => 'owner@example.com',
            'password' => 'secret-pass',
            'is_active' => true,
            'is_super_admin' => true,
        ]);

        $this->from(route('login'))
            ->post(route('login.submit'), [
                'email' => 'owner@example.com',
                'password' => 'secret-pass',
            ])
            ->assertRedirect(route('admin.platform.index'));
    }

    public function test_super_admin_can_switch_into_an_inactive_client_context_for_audit_or_reactivation(): void
    {
        [$homeClientId, $homeBranchId] = $this->createClientWithBranch('Owner Home', 'Owner Branch');
        [$suspendedClientId, $suspendedBranchId] = $this->createClientWithBranch('Suspended Pharmacy', 'Suspended Branch', false);

        $superAdmin = User::factory()->create([
            'client_id' => $homeClientId,
            'branch_id' => $homeBranchId,
            'is_active' => true,
            'is_super_admin' => true,
        ]);

        $this->actingAs($superAdmin)
            ->get(route('admin.platform.index'))
            ->assertOk()
            ->assertSee('Suspended Pharmacy');

        $this->actingAs($superAdmin)
            ->put(route('admin.platform.update'), [
                'client_id' => $suspendedClientId,
                'branch_id' => $suspendedBranchId,
            ])
            ->assertRedirect(route('admin.platform.index'));

        $this->actingAs($superAdmin)
            ->get(route('admin.platform.index'))
            ->assertOk()
            ->assertSee('Suspended Pharmacy')
            ->assertSee('Suspended Branch');
    }

    private function createClientWithBranch(string $clientName, string $branchName, bool $isActive = true): array
    {
        $clientId = DB::table('clients')->insertGetId([
            'name' => $clientName,
            'business_mode' => 'both',
            'is_active' => $isActive,
            'is_platform_sandbox' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $branchId = DB::table('branches')->insertGetId([
            'client_id' => $clientId,
            'name' => $branchName,
            'code' => strtoupper(substr($branchName, 0, 3)) . $clientId,
            'business_mode' => 'inherit',
            'is_main' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$clientId, $branchId];
    }
}
