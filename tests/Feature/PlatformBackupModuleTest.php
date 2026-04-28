<?php

namespace Tests\Feature;

use App\Models\PlatformBackup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;
use ZipArchive;

class PlatformBackupModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        File::deleteDirectory(storage_path('app/backups/platform'));
        File::deleteDirectory(storage_path('app/backups/.tmp'));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/backups/platform'));
        File::deleteDirectory(storage_path('app/backups/.tmp'));

        parent::tearDown();
    }

    public function test_super_admin_can_open_platform_backup_screen(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $this->actingAs($superAdmin)
            ->get(route('admin.platform.backups.index'))
            ->assertOk()
            ->assertSee('Full Backup And Restore Control')
            ->assertSee('Create A Full Backup');
    }

    public function test_non_super_admin_cannot_open_platform_backup_screen(): void
    {
        [$clientId, $branchId] = $this->createClientWithBranch('Normal Client', 'Main Branch');

        $user = User::factory()->create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'is_active' => true,
            'is_super_admin' => false,
        ]);

        $this->actingAs($user)
            ->get(route('admin.platform.backups.index'))
            ->assertForbidden();
    }

    public function test_super_admin_can_create_and_review_full_backup(): void
    {
        if (!class_exists(ZipArchive::class)) {
            $this->markTestSkipped('ZipArchive support is not available.');
        }

        $superAdmin = $this->createSuperAdmin();

        $response = $this->actingAs($superAdmin)
            ->post(route('admin.platform.backups.store'), [
                'notes' => 'Before package preset rollout.',
            ]);

        $backup = PlatformBackup::query()->latest('id')->first();

        $this->assertNotNull($backup);
        $response->assertRedirect(route('admin.platform.backups.show', $backup));
        $this->assertSame('Before package preset rollout.', $backup->notes);
        $this->assertTrue($backup->fileExists());
        $this->assertFileExists($backup->absolutePath());

        $this->actingAs($superAdmin)
            ->get(route('admin.platform.backups.show', $backup))
            ->assertOk()
            ->assertSee($backup->filename)
            ->assertSee('Before package preset rollout.')
            ->assertSee('Database Table Manifest');
    }

    public function test_restore_requires_exact_filename_confirmation(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $backup = PlatformBackup::query()->create([
            'filename' => 'kimrx-full-backup-test.zip',
            'disk_path' => 'backups/platform/kimrx-full-backup-test.zip',
            'backup_type' => PlatformBackup::TYPE_FULL_PLATFORM,
            'status' => PlatformBackup::STATUS_READY,
            'total_size_bytes' => 0,
            'database_tables_count' => 0,
            'database_rows_count' => 0,
            'storage_files_count' => 0,
            'storage_bytes' => 0,
            'created_by' => $superAdmin->id,
            'notes' => 'Guard test backup',
            'manifest_json' => [],
        ]);

        $this->from(route('admin.platform.backups.index'))
            ->actingAs($superAdmin)
            ->put(route('admin.platform.backups.restore', $backup), [
                'restore_confirmation' => 'wrong-name.zip',
                'create_safety_backup' => 1,
            ])
            ->assertRedirect(route('admin.platform.backups.index'))
            ->assertSessionHasErrors('restore_confirmation');

        $this->assertDatabaseHas('platform_backups', [
            'id' => $backup->id,
            'status' => PlatformBackup::STATUS_READY,
        ]);
    }

    private function createSuperAdmin(): User
    {
        [$homeClientId, $homeBranchId] = $this->createClientWithBranch('Owner Home', 'Owner Branch');

        return User::factory()->create([
            'client_id' => $homeClientId,
            'branch_id' => $homeBranchId,
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
}
