<?php

namespace Tests\Feature;

use App\Models\PlatformBackup;
use App\Support\PlatformBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;
use ZipArchive;

class PlatformBackupAutomationTest extends TestCase
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

    public function test_automatic_backup_command_creates_full_platform_backup(): void
    {
        if (!class_exists(ZipArchive::class)) {
            $this->markTestSkipped('ZipArchive support is not available.');
        }

        config()->set('backup.platform.auto_enabled', true);
        config()->set('backup.platform.retention_count', 14);
        config()->set('backup.platform.skip_if_recent_minutes', 0);

        Artisan::call('platform:backup:auto');

        $backup = PlatformBackup::query()->latest('id')->first();

        $this->assertNotNull($backup);
        $this->assertSame('Automatic scheduled platform backup', $backup->notes);
        $this->assertTrue($backup->fileExists());
    }

    public function test_retention_prunes_older_platform_backups(): void
    {
        File::ensureDirectoryExists(storage_path('app/backups/platform'));

        $records = collect([
            ['filename' => 'auto-backup-oldest.zip', 'created_at' => now()->subDays(3)],
            ['filename' => 'auto-backup-middle.zip', 'created_at' => now()->subDays(2)],
            ['filename' => 'auto-backup-latest.zip', 'created_at' => now()->subDay()],
        ])->map(function (array $entry) {
            File::put(storage_path('app/backups/platform/' . $entry['filename']), 'backup');

            $backup = PlatformBackup::query()->create([
                'filename' => $entry['filename'],
                'disk_path' => 'backups/platform/' . $entry['filename'],
                'backup_type' => PlatformBackup::TYPE_FULL_PLATFORM,
                'status' => PlatformBackup::STATUS_READY,
                'total_size_bytes' => 6,
                'database_tables_count' => 0,
                'database_rows_count' => 0,
                'storage_files_count' => 0,
                'storage_bytes' => 0,
                'notes' => 'Retention test backup',
                'manifest_json' => [],
            ]);

            DB::table('platform_backups')
                ->where('id', $backup->id)
                ->update([
                    'created_at' => $entry['created_at'],
                    'updated_at' => $entry['created_at'],
                ]);

            return $backup;
        });

        $result = app(PlatformBackupService::class)->pruneFullBackupRetention(1);

        $this->assertSame(2, $result['deleted_records']);
        $this->assertDatabaseCount('platform_backups', 1);
        $this->assertDatabaseHas('platform_backups', ['filename' => 'auto-backup-latest.zip']);
        $this->assertFileDoesNotExist(storage_path('app/backups/platform/auto-backup-oldest.zip'));
        $this->assertFileDoesNotExist(storage_path('app/backups/platform/auto-backup-middle.zip'));
        $this->assertFileExists(storage_path('app/backups/platform/auto-backup-latest.zip'));
    }
}
