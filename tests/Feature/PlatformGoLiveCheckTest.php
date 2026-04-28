<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\PlatformGoLiveCheckService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PlatformGoLiveCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_go_live_check_can_run_in_non_production_rehearsal_mode_without_failures(): void
    {
        $this->createSuperAdmin();
        $this->ensurePublicStorageDirectory();

        Config::set('app.debug', false);
        Config::set('app.url', 'https://kimrx.test');
        Config::set('app.key', 'base64:test-key-for-go-live-check');
        Config::set('session.secure', true);
        Config::set('mail.default', 'smtp');

        $result = app(PlatformGoLiveCheckService::class)->run(true);

        $this->assertSame(0, $result['summary']['failed']);
        $this->assertTrue(collect($result['checks'])->contains(fn (array $check) => $check['key'] === 'super_admin' && $check['status'] === PlatformGoLiveCheckService::STATUS_PASS));
    }

    public function test_go_live_check_flags_missing_application_key_as_blocking_issue(): void
    {
        $this->createSuperAdmin();
        $this->ensurePublicStorageDirectory();

        Config::set('app.debug', false);
        Config::set('app.key', '');

        $result = app(PlatformGoLiveCheckService::class)->run(true);

        $appKeyCheck = collect($result['checks'])->firstWhere('key', 'app_key');

        $this->assertNotNull($appKeyCheck);
        $this->assertSame(PlatformGoLiveCheckService::STATUS_FAIL, $appKeyCheck['status']);
    }

    public function test_go_live_check_command_fails_when_strict_production_requirements_are_not_met(): void
    {
        $this->createSuperAdmin();
        $this->ensurePublicStorageDirectory();

        Config::set('app.key', 'base64:test-key-for-go-live-check');

        $exitCode = Artisan::call('platform:go-live-check');
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Go-live readiness is blocked', $output);
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
