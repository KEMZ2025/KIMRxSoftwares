<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\PlatformBackup;
use App\Models\User;
use App\Support\AccessControlBootstrapper;
use App\Support\PlatformSandboxManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SuperAdminContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_login_redirects_to_owner_workspace(): void
    {
        [$homeClientId, $homeBranchId] = $this->createClientWithBranch('Home Client', 'Home Branch');

        User::factory()->create([
            'client_id' => $homeClientId,
            'branch_id' => $homeBranchId,
            'is_active' => true,
            'is_super_admin' => true,
            'email' => 'owner@example.com',
            'password' => Hash::make('secret-pass'),
        ]);

        $this->post(route('login.submit'), [
            'email' => 'owner@example.com',
            'password' => 'secret-pass',
        ])->assertRedirect(route('admin.platform.index'));
    }

    public function test_super_admin_can_switch_context_and_manage_another_client_without_client_roles(): void
    {
        [$homeClientId, $homeBranchId] = $this->createClientWithBranch('Home Client', 'Home Branch');
        [$targetClientId, $targetBranchId] = $this->createClientWithBranch('Target Client', 'Target Branch');

        $superAdmin = User::factory()->create([
            'client_id' => $homeClientId,
            'branch_id' => $homeBranchId,
            'is_active' => true,
            'is_super_admin' => true,
            'email' => 'owner@example.com',
        ]);

        User::factory()->create([
            'name' => 'Target Staff',
            'email' => 'target@example.com',
            'client_id' => $targetClientId,
            'branch_id' => $targetBranchId,
            'is_active' => true,
        ]);

        User::factory()->create([
            'name' => 'Home Staff',
            'email' => 'home@example.com',
            'client_id' => $homeClientId,
            'branch_id' => $homeBranchId,
            'is_active' => true,
        ]);

        $this->actingAs($superAdmin)
            ->put(route('admin.platform.update'), [
                'client_id' => $targetClientId,
                'branch_id' => $targetBranchId,
            ])
            ->assertRedirect(route('admin.platform.index'));

        $this->actingAs($superAdmin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Target Client')
            ->assertSee('target@example.com')
            ->assertDontSee('home@example.com');
    }

    public function test_super_admin_starts_in_owner_workspace_until_a_client_context_is_selected(): void
    {
        [$homeClientId, $homeBranchId] = $this->createClientWithBranch('Home Client', 'Home Branch');

        $superAdmin = User::factory()->create([
            'client_id' => $homeClientId,
            'branch_id' => $homeBranchId,
            'is_active' => true,
            'is_super_admin' => true,
            'email' => 'owner@example.com',
        ]);

        $this->actingAs($superAdmin)
            ->get(route('admin.platform.index'))
            ->assertOk()
            ->assertSee('Owner Workspace')
            ->assertSee('No client selected');

        $this->actingAs($superAdmin)
            ->get(route('dashboard'))
            ->assertRedirect(route('admin.platform.index'));
    }

    public function test_owner_workspace_provisions_and_activates_platform_sandbox(): void
    {
        [$homeClientId, $homeBranchId] = $this->createClientWithBranch('Home Client', 'Home Branch');

        $superAdmin = User::factory()->create([
            'client_id' => $homeClientId,
            'branch_id' => $homeBranchId,
            'is_active' => true,
            'is_super_admin' => true,
            'email' => 'owner@example.com',
        ]);

        $this->actingAs($superAdmin)
            ->get(route('admin.platform.index'))
            ->assertOk()
            ->assertSee('Built-In Testing Sandbox')
            ->assertSee(PlatformSandboxManager::CLIENT_NAME);

        $sandboxClient = DB::table('clients')
            ->where('is_platform_sandbox', true)
            ->first();

        $this->assertNotNull($sandboxClient);

        $sandboxBranch = DB::table('branches')
            ->where('client_id', $sandboxClient->id)
            ->where('code', PlatformSandboxManager::BRANCH_CODE)
            ->first();

        $this->assertNotNull($sandboxBranch);

        $this->actingAs($superAdmin)
            ->post(route('admin.platform.sandbox'))
            ->assertRedirect(route('admin.platform.index'));

        $this->actingAs($superAdmin)
            ->get(route('admin.platform.index'))
            ->assertOk()
            ->assertSee(PlatformSandboxManager::CLIENT_NAME)
            ->assertSee(PlatformSandboxManager::BRANCH_NAME);
    }

    public function test_super_admin_can_clear_selected_client_context_and_return_to_owner_workspace(): void
    {
        [$homeClientId, $homeBranchId] = $this->createClientWithBranch('Home Client', 'Home Branch');
        [$targetClientId, $targetBranchId] = $this->createClientWithBranch('Target Client', 'Target Branch');

        $superAdmin = User::factory()->create([
            'client_id' => $homeClientId,
            'branch_id' => $homeBranchId,
            'is_active' => true,
            'is_super_admin' => true,
            'email' => 'owner@example.com',
        ]);

        $this->actingAs($superAdmin)
            ->put(route('admin.platform.update'), [
                'client_id' => $targetClientId,
                'branch_id' => $targetBranchId,
            ])
            ->assertRedirect(route('admin.platform.index'));

        $this->actingAs($superAdmin)
            ->delete(route('admin.platform.clear'))
            ->assertRedirect(route('admin.platform.index'));

        $this->actingAs($superAdmin)
            ->get(route('admin.platform.index'))
            ->assertOk()
            ->assertSee('Owner Workspace')
            ->assertSee('No client selected');
    }

    public function test_owner_workspace_shows_business_summary_layer(): void
    {
        [$homeClientId, $homeBranchId] = $this->createClientWithBranch('Home Client', 'Home Branch');
        [$payingClientId, $payingBranchId] = $this->createClientWithBranch('Paying Client', 'Pay Branch');
        [$trialClientId] = $this->createClientWithBranch('Trial Client', 'Trial Branch');
        [$demoClientId] = $this->createClientWithBranch('Demo Client', 'Demo Branch');

        DB::table('clients')->where('id', $payingClientId)->update([
            'client_type' => Client::TYPE_PAYING,
            'package_preset' => 'professional',
            'subscription_status' => Client::STATUS_OVERDUE,
            'subscription_ends_at' => now()->subDay(),
            'active_user_limit' => 1,
        ]);

        DB::table('clients')->where('id', $trialClientId)->update([
            'client_type' => Client::TYPE_TRIAL,
            'package_preset' => 'essential',
            'subscription_status' => Client::STATUS_GRACE,
            'subscription_ends_at' => now()->addDays(3),
            'active_user_limit' => 2,
        ]);

        DB::table('clients')->where('id', $demoClientId)->update([
            'client_type' => Client::TYPE_DEMO,
            'package_preset' => 'premium',
            'subscription_status' => Client::STATUS_ACTIVE,
            'active_user_limit' => 5,
        ]);

        $superAdmin = User::factory()->create([
            'client_id' => $homeClientId,
            'branch_id' => $homeBranchId,
            'is_active' => true,
            'is_super_admin' => true,
            'email' => 'owner@example.com',
        ]);

        User::factory()->create([
            'client_id' => $payingClientId,
            'branch_id' => $payingBranchId,
            'is_active' => true,
            'is_super_admin' => false,
            'email' => 'staff@paying.example',
        ]);

        PlatformBackup::query()->create([
            'filename' => 'kimrx-summary-backup.zip',
            'disk_path' => 'backups/platform/kimrx-summary-backup.zip',
            'backup_type' => PlatformBackup::TYPE_FULL_PLATFORM,
            'status' => PlatformBackup::STATUS_READY,
            'total_size_bytes' => 2048,
            'database_tables_count' => 10,
            'database_rows_count' => 250,
            'storage_files_count' => 3,
            'storage_bytes' => 1024,
            'created_by' => $superAdmin->id,
            'notes' => 'Summary test backup',
            'manifest_json' => [],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($superAdmin)
            ->get(route('admin.platform.index'))
            ->assertOk()
            ->assertSee('Package Mix')
            ->assertSee('Subscription Health')
            ->assertSee('Seat Pressure')
            ->assertSee('Backup Health')
            ->assertSee('Owner Attention Board')
            ->assertSee('Paying Client')
            ->assertSee('Trial Client')
            ->assertSee('Seat limit reached')
            ->assertSee('Professional')
            ->assertSee('Ready Backups');
    }

    public function test_client_admin_cannot_open_platform_context_screen(): void
    {
        [$clientId, $branchId] = $this->createClientWithBranch('Client One', 'Main Branch');

        $admin = User::factory()->create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'is_active' => true,
        ]);

        app(AccessControlBootstrapper::class)->ensureForUser($admin);

        $this->actingAs($admin)
            ->get(route('admin.platform.index'))
            ->assertForbidden();
    }

    public function test_client_admin_cannot_edit_or_deactivate_super_admin_account(): void
    {
        [$clientId, $branchId] = $this->createClientWithBranch('Protected Client', 'Main Branch');

        $superAdmin = User::factory()->create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'is_active' => true,
            'is_super_admin' => true,
            'email' => 'owner@example.com',
        ]);

        $clientAdmin = User::factory()->create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'is_active' => true,
            'email' => 'admin@example.com',
        ]);

        app(AccessControlBootstrapper::class)->ensureForUser($clientAdmin);

        $this->actingAs($clientAdmin)
            ->get(route('admin.users.edit', $superAdmin))
            ->assertForbidden();

        $this->actingAs($clientAdmin)
            ->patch(route('admin.users.status', $superAdmin))
            ->assertForbidden();
    }

    private function createClientWithBranch(string $clientName, string $branchName): array
    {
        $clientId = DB::table('clients')->insertGetId([
            'name' => $clientName,
            'business_mode' => 'both',
            'is_active' => true,
            'is_platform_sandbox' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $branchId = DB::table('branches')->insertGetId([
            'client_id' => $clientId,
            'name' => $branchName,
            'code' => strtoupper(substr($branchName, 0, 3)) . $clientId,
            'is_main' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$clientId, $branchId];
    }
}
