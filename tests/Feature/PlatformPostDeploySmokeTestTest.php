<?php

namespace Tests\Feature;

use App\Models\ClientSetting;
use App\Models\Role;
use App\Models\User;
use App\Support\AccessControlBootstrapper;
use App\Support\PlatformPostDeploySmokeTestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PlatformPostDeploySmokeTestTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_deploy_smoke_test_passes_core_checks_with_owner_and_tenant_admin(): void
    {
        $this->ensurePublicStorageDirectory();

        $this->createSuperAdmin();
        $tenantAdmin = $this->createTenantAdminWithModules();

        $result = app(PlatformPostDeploySmokeTestService::class)->run();

        $this->assertSame(0, $result['summary']['failed']);
        $this->assertTrue(collect($result['checks'])->contains(fn (array $check) => $check['key'] === 'owner_workspace' && $check['status'] === PlatformPostDeploySmokeTestService::STATUS_PASS));
        $this->assertTrue(collect($result['checks'])->contains(fn (array $check) => $check['key'] === 'sales_screen' && $check['status'] === PlatformPostDeploySmokeTestService::STATUS_PASS));
        $this->assertTrue(collect($result['checks'])->contains(fn (array $check) => $check['key'] === 'insurance_screen' && $check['status'] === PlatformPostDeploySmokeTestService::STATUS_PASS));
        $this->assertNotNull($tenantAdmin);
    }

    public function test_post_deploy_smoke_test_warns_when_optional_module_user_is_missing(): void
    {
        $this->ensurePublicStorageDirectory();

        $this->createSuperAdmin();
        $this->createTenantAdminWithModules(false, false);

        $result = app(PlatformPostDeploySmokeTestService::class)->run();

        $cashDrawerCheck = collect($result['checks'])->firstWhere('key', 'cash_drawer_screen');
        $insuranceCheck = collect($result['checks'])->firstWhere('key', 'insurance_screen');

        $this->assertNotNull($cashDrawerCheck);
        $this->assertNotNull($insuranceCheck);
        $this->assertSame(PlatformPostDeploySmokeTestService::STATUS_WARNING, $cashDrawerCheck['status']);
        $this->assertSame(PlatformPostDeploySmokeTestService::STATUS_WARNING, $insuranceCheck['status']);
    }

    public function test_post_deploy_smoke_test_command_fails_when_owner_workspace_is_missing(): void
    {
        $this->ensurePublicStorageDirectory();

        $this->createTenantAdminWithModules();

        $exitCode = Artisan::call('platform:post-deploy-smoke-test');
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Post-deploy smoke test found one or more blocking failures.', $output);
    }

    private function createSuperAdmin(): User
    {
        [$clientId, $branchId] = $this->createClientWithBranch('Owner Home', 'Owner Branch');

        return User::factory()->create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'is_active' => true,
            'is_super_admin' => true,
        ]);
    }

    private function createTenantAdminWithModules(bool $enableCashDrawer = true, bool $enableInsurance = true): User
    {
        [$clientId, $branchId] = $this->createClientWithBranch('Smoke Test Pharmacy', 'Main Branch');

        $user = User::factory()->create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'is_active' => true,
            'is_super_admin' => false,
        ]);

        app(AccessControlBootstrapper::class)->ensureForClient($clientId, $user);

        $adminRole = Role::query()
            ->where('client_id', $clientId)
            ->where('name', 'Admin')
            ->firstOrFail();

        $user->roles()->syncWithoutDetaching([$adminRole->id]);

        $settings = ClientSetting::query()->firstOrCreate(
            ['client_id' => $clientId],
            ['business_mode' => 'both'] + \App\Support\ClientFeatureAccess::defaultSettingValues()
        );

        $settings->forceFill([
            'reports_enabled' => true,
            'cash_drawer_enabled' => $enableCashDrawer,
            'insurance_enabled' => $enableInsurance,
        ])->save();

        return $user->fresh();
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

    private function ensurePublicStorageDirectory(): void
    {
        File::ensureDirectoryExists(public_path('storage'));
    }
}
