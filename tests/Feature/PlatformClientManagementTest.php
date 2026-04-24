<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use App\Models\Role;
use App\Support\PlatformSandboxManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlatformClientManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_client_with_initial_main_branch_and_default_roles(): void
    {
        [$homeClientId, $homeBranchId] = $this->createClientWithBranch('Owner Home', 'Owner Branch');

        $superAdmin = User::factory()->create([
            'client_id' => $homeClientId,
            'branch_id' => $homeBranchId,
            'is_active' => true,
            'is_super_admin' => true,
        ]);

        $this->actingAs($superAdmin)
            ->post(route('admin.platform.clients.store'), [
                'name' => 'New Client',
                'email' => 'newclient@example.com',
                'phone' => '0700000000',
                'address' => 'Kampala',
                'business_mode' => 'both',
                'client_type' => Client::TYPE_PAYING,
                'subscription_status' => Client::STATUS_ACTIVE,
                'active_user_limit' => 5,
                'subscription_ends_at' => now()->addMonth()->toDateString(),
                'is_active' => 1,
                'initial_branch_name' => 'Main Branch',
                'initial_branch_code' => 'MAIN',
                'initial_branch_email' => 'main@example.com',
                'initial_branch_phone' => '0711111111',
                'initial_branch_address' => 'Main Address',
                'initial_branch_business_mode' => 'retail_only',
                'initial_branch_is_active' => 1,
            ])
            ->assertRedirect();

        $client = Client::query()->where('name', 'New Client')->firstOrFail();

        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'client_type' => Client::TYPE_PAYING,
            'subscription_status' => Client::STATUS_ACTIVE,
            'active_user_limit' => 5,
        ]);

        $this->assertDatabaseHas('branches', [
            'client_id' => $client->id,
            'name' => 'Main Branch',
            'is_main' => true,
            'business_mode' => 'retail_only',
        ]);

        $this->assertSame(5, Role::query()->where('client_id', $client->id)->count());
    }

    public function test_super_admin_can_add_another_branch_to_existing_client(): void
    {
        [$homeClientId, $homeBranchId] = $this->createClientWithBranch('Owner Home', 'Owner Branch');
        [$managedClientId] = $this->createClientWithBranch('Managed Client', 'Original Branch', 'both');

        $superAdmin = User::factory()->create([
            'client_id' => $homeClientId,
            'branch_id' => $homeBranchId,
            'is_active' => true,
            'is_super_admin' => true,
        ]);

        $client = Client::query()->findOrFail($managedClientId);

        $this->actingAs($superAdmin)
            ->post(route('admin.platform.branches.store', $client), [
                'name' => 'Second Branch',
                'code' => 'SEC',
                'phone' => '0722222222',
                'email' => 'second@example.com',
                'address' => 'Second Address',
                'business_mode' => 'wholesale_only',
                'is_main' => 0,
                'is_active' => 1,
            ])
            ->assertRedirect(route('admin.platform.branches.index', $client));

        $this->assertDatabaseHas('branches', [
            'client_id' => $client->id,
            'name' => 'Second Branch',
            'business_mode' => 'wholesale_only',
            'is_main' => false,
        ]);
    }

    public function test_super_admin_can_update_client_module_and_accounting_feature_access(): void
    {
        [$homeClientId, $homeBranchId] = $this->createClientWithBranch('Owner Home', 'Owner Branch');
        [$managedClientId] = $this->createClientWithBranch('Package Client', 'Main Branch', 'both');

        $superAdmin = User::factory()->create([
            'client_id' => $homeClientId,
            'branch_id' => $homeBranchId,
            'is_active' => true,
            'is_super_admin' => true,
        ]);

        $client = Client::query()->findOrFail($managedClientId);

        $this->actingAs($superAdmin)
            ->put(route('admin.platform.clients.update', $client), [
                'name' => 'Package Client',
                'email' => 'package@example.com',
                'phone' => '0770000000',
                'address' => 'Package Road',
                'business_mode' => 'both',
                'client_type' => Client::TYPE_TRIAL,
                'subscription_status' => Client::STATUS_GRACE,
                'active_user_limit' => 3,
                'subscription_ends_at' => now()->addDays(14)->toDateString(),
                'is_active' => 1,
                'retail_pos_enabled' => 1,
                'wholesale_pos_enabled' => 0,
                'proforma_enabled' => 0,
                'dispensing_price_guide_enabled' => 1,
                'purchases_enabled' => 1,
                'suppliers_enabled' => 1,
                'customers_enabled' => 1,
                'inventory_enabled' => 1,
                'expiry_alerts_enabled' => 0,
                'cash_drawer_enabled' => 1,
                'accounts_enabled' => 1,
                'reports_enabled' => 0,
                'efris_enabled' => 1,
                'accounting_chart_enabled' => 1,
                'accounting_general_ledger_enabled' => 0,
                'accounting_trial_balance_enabled' => 1,
                'accounting_journals_enabled' => 0,
                'accounting_vouchers_enabled' => 0,
                'accounting_profit_loss_enabled' => 1,
                'accounting_balance_sheet_enabled' => 0,
                'accounting_expenses_enabled' => 0,
                'accounting_fixed_assets_enabled' => 1,
            ])
            ->assertRedirect(route('admin.platform.clients.index'));

        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'client_type' => Client::TYPE_TRIAL,
            'subscription_status' => Client::STATUS_GRACE,
            'active_user_limit' => 3,
        ]);

        $this->assertDatabaseHas('client_settings', [
            'client_id' => $client->id,
            'reports_enabled' => false,
            'expiry_alerts_enabled' => false,
            'cash_drawer_enabled' => true,
            'efris_enabled' => true,
            'dispensing_price_guide_enabled' => true,
            'accounting_general_ledger_enabled' => false,
            'accounting_journals_enabled' => false,
            'accounting_profit_loss_enabled' => true,
            'accounting_fixed_assets_enabled' => true,
        ]);
    }

    public function test_super_admin_can_see_accounting_module_toggle_in_accounting_access_detail(): void
    {
        [$homeClientId, $homeBranchId] = $this->createClientWithBranch('Owner Home', 'Owner Branch');
        [$managedClientId] = $this->createClientWithBranch('Package Client', 'Main Branch', 'both');

        $superAdmin = User::factory()->create([
            'client_id' => $homeClientId,
            'branch_id' => $homeBranchId,
            'is_active' => true,
            'is_super_admin' => true,
        ]);

        $client = Client::query()->findOrFail($managedClientId);

        $this->actingAs($superAdmin)
            ->get(route('admin.platform.clients.edit', $client))
            ->assertOk()
            ->assertSeeInOrder([
                'Client Type',
                'Subscription Status',
                'Active User Limit',
                'Subscription End Date',
                'Paid Module Access',
                'Expiry Alert Reminders',
                'Cash Drawer Control',
                'Reports',
                'URA / EFRIS Integration',
                'Accounting Access Detail',
                'Accounting Module',
                'Chart Of Accounts',
            ]);
    }

    public function test_branch_mode_cannot_exceed_client_mode(): void
    {
        [$homeClientId, $homeBranchId] = $this->createClientWithBranch('Owner Home', 'Owner Branch');
        [$managedClientId] = $this->createClientWithBranch('Wholesale Client', 'Original Branch', 'wholesale_only');

        $superAdmin = User::factory()->create([
            'client_id' => $homeClientId,
            'branch_id' => $homeBranchId,
            'is_active' => true,
            'is_super_admin' => true,
        ]);

        $client = Client::query()->findOrFail($managedClientId);

        $this->from(route('admin.platform.branches.create', $client))
            ->actingAs($superAdmin)
            ->post(route('admin.platform.branches.store', $client), [
                'name' => 'Retail Branch',
                'code' => 'RTL',
                'business_mode' => 'retail_only',
                'is_main' => 0,
                'is_active' => 1,
            ])
            ->assertRedirect(route('admin.platform.branches.create', $client))
            ->assertSessionHasErrors('business_mode');
    }

    public function test_non_super_admin_cannot_open_client_setup_area(): void
    {
        [$clientId, $branchId] = $this->createClientWithBranch('Normal Client', 'Main Branch');

        $user = User::factory()->create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'is_active' => true,
            'is_super_admin' => false,
        ]);

        $this->actingAs($user)
            ->get(route('admin.platform.clients.index'))
            ->assertForbidden();
    }

    public function test_platform_client_setup_excludes_platform_sandbox_from_live_client_listing(): void
    {
        [$homeClientId, $homeBranchId] = $this->createClientWithBranch('Owner Home', 'Owner Branch');
        $this->createClientWithBranch('Managed Client', 'Main Branch');

        $superAdmin = User::factory()->create([
            'client_id' => $homeClientId,
            'branch_id' => $homeBranchId,
            'is_active' => true,
            'is_super_admin' => true,
        ]);

        app(PlatformSandboxManager::class)->ensure();

        $this->actingAs($superAdmin)
            ->get(route('admin.platform.clients.index'))
            ->assertOk()
            ->assertSee('Live Clients')
            ->assertSee('Managed Client')
            ->assertDontSee(PlatformSandboxManager::CLIENT_NAME);
    }

    public function test_super_admin_can_update_global_support_contacts_from_owner_workspace(): void
    {
        [$homeClientId, $homeBranchId] = $this->createClientWithBranch('Owner Home', 'Owner Branch');

        $superAdmin = User::factory()->create([
            'client_id' => $homeClientId,
            'branch_id' => $homeBranchId,
            'is_active' => true,
            'is_super_admin' => true,
        ]);

        $this->actingAs($superAdmin)
            ->put(route('admin.platform.support.update'), [
                'company_name' => 'KIM RETAIL SOFTWARE SYSTEMS',
                'contact_person' => 'Support Desk',
                'phone_primary' => '+256700111222',
                'phone_secondary' => '+256701222333',
                'email' => 'support@kimretail.test',
                'whatsapp' => '+256700111222',
                'website' => 'https://kimretail.test',
                'hours' => 'Daily 8:00 AM - 6:00 PM',
                'response_note' => 'Share screenshots and the branch before calling support.',
            ])
            ->assertRedirect(route('admin.platform.index'));

        $this->assertDatabaseHas('platform_settings', [
            'company_name' => 'KIM RETAIL SOFTWARE SYSTEMS',
            'contact_person' => 'Support Desk',
            'phone_primary' => '+256700111222',
            'email' => 'support@kimretail.test',
            'website' => 'https://kimretail.test',
        ]);

        $this->actingAs($superAdmin)
            ->get(route('admin.platform.index'))
            ->assertOk()
            ->assertSee('Support Contacts Shown To Clients')
            ->assertSee('support@kimretail.test');
    }

    private function createClientWithBranch(string $clientName, string $branchName, string $businessMode = 'both'): array
    {
        $clientId = DB::table('clients')->insertGetId([
            'name' => $clientName,
            'business_mode' => $businessMode,
            'is_active' => true,
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
