<?php

namespace App\Support;

use App\Models\PlatformBackup;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class PlatformBackupService
{
    public function createFullBackup(?User $user = null, ?string $note = null): PlatformBackup
    {
        $this->ensureZipSupport();

        $timestamp = now()->format('Ymd-His');
        $filename = 'kimrx-full-backup-' . $timestamp . '-' . Str::lower(Str::random(6)) . '.zip';
        $diskPath = 'backups/platform/' . $filename;
        $absoluteZipPath = storage_path('app/' . $diskPath);
        $workingDirectory = $this->temporaryDirectory('backup-build-');

        File::ensureDirectoryExists(dirname($absoluteZipPath));

        try {
            $databaseManifest = $this->exportDatabase($workingDirectory);
            $fileManifest = $this->exportStorageFiles($workingDirectory);

            $manifest = [
                'backup_type' => PlatformBackup::TYPE_FULL_PLATFORM,
                'app_version' => (string) config('app.version', 'v1.0.0'),
                'created_at' => now()->toIso8601String(),
                'database_connection' => DB::connection()->getName(),
                'database_name' => DB::connection()->getDatabaseName(),
                'note' => $note,
                'database' => $databaseManifest,
                'files' => $fileManifest,
            ];

            File::put(
                $workingDirectory . DIRECTORY_SEPARATOR . 'manifest.json',
                json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            );

            $this->createZipFromDirectory($workingDirectory, $absoluteZipPath);

            return PlatformBackup::query()->updateOrCreate(
                ['filename' => $filename],
                [
                    'disk_path' => $diskPath,
                    'backup_type' => PlatformBackup::TYPE_FULL_PLATFORM,
                    'status' => PlatformBackup::STATUS_READY,
                    'total_size_bytes' => filesize($absoluteZipPath) ?: 0,
                    'database_tables_count' => (int) ($databaseManifest['tables_count'] ?? 0),
                    'database_rows_count' => (int) ($databaseManifest['rows_count'] ?? 0),
                    'storage_files_count' => (int) ($fileManifest['count'] ?? 0),
                    'storage_bytes' => (int) ($fileManifest['bytes'] ?? 0),
                    'created_by' => $user?->id,
                    'notes' => $note,
                    'manifest_json' => $manifest,
                ]
            );
        } finally {
            File::deleteDirectory($workingDirectory);
        }
    }

    public function syncCatalogFromDisk(): void
    {
        File::ensureDirectoryExists($this->backupRootDirectory());

        $seen = [];

        foreach (File::files($this->backupRootDirectory()) as $file) {
            if (strtolower($file->getExtension()) !== 'zip') {
                continue;
            }

            $filename = $file->getFilename();
            $manifest = $this->readManifestFromZip($file->getRealPath());

            if (!$manifest || ($manifest['backup_type'] ?? null) !== PlatformBackup::TYPE_FULL_PLATFORM) {
                continue;
            }

            $databaseManifest = $manifest['database'] ?? [];
            $fileManifest = $manifest['files'] ?? [];

            PlatformBackup::query()->updateOrCreate(
                ['filename' => $filename],
                [
                    'disk_path' => 'backups/platform/' . $filename,
                    'backup_type' => PlatformBackup::TYPE_FULL_PLATFORM,
                    'status' => PlatformBackup::STATUS_READY,
                    'total_size_bytes' => $file->getSize(),
                    'database_tables_count' => (int) ($databaseManifest['tables_count'] ?? 0),
                    'database_rows_count' => (int) ($databaseManifest['rows_count'] ?? 0),
                    'storage_files_count' => (int) ($fileManifest['count'] ?? 0),
                    'storage_bytes' => (int) ($fileManifest['bytes'] ?? 0),
                    'notes' => $manifest['note'] ?? null,
                    'manifest_json' => $manifest,
                    'created_at' => isset($manifest['created_at']) ? Carbon::parse($manifest['created_at']) : now(),
                    'updated_at' => now(),
                ]
            );

            $seen[] = $filename;
        }

        PlatformBackup::query()
            ->whereNotIn('filename', $seen)
            ->update(['status' => PlatformBackup::STATUS_MISSING]);
    }

    public function readManifest(PlatformBackup $backup): array
    {
        $manifest = $this->readManifestFromZip($backup->absolutePath());

        if (!$manifest) {
            throw new RuntimeException('This backup file could not be read or is missing its manifest.');
        }

        return $manifest;
    }

    public function latestReadyBackup(): ?PlatformBackup
    {
        return PlatformBackup::query()
            ->where('status', '!=', PlatformBackup::STATUS_MISSING)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
    }

    public function pruneFullBackupRetention(int $keep): array
    {
        $keep = max(1, $keep);

        $prunable = PlatformBackup::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->skip($keep)
            ->take(PHP_INT_MAX)
            ->get();

        $deletedFiles = 0;
        $deletedRecords = 0;
        $deletedFilenames = [];

        foreach ($prunable as $backup) {
            if ($backup->fileExists()) {
                File::delete($backup->absolutePath());
                $deletedFiles++;
            }

            $deletedFilenames[] = $backup->filename;
            $backup->delete();
            $deletedRecords++;
        }

        return [
            'deleted_files' => $deletedFiles,
            'deleted_records' => $deletedRecords,
            'filenames' => $deletedFilenames,
            'kept' => $keep,
        ];
    }

    public function restoreFullBackup(PlatformBackup $backup, ?User $user = null, bool $createSafetyBackup = true): void
    {
        $this->ensureZipSupport();

        if (!$backup->fileExists()) {
            throw new RuntimeException('The selected backup file is missing from disk.');
        }

        $manifest = $this->readManifest($backup);
        $restoreDirectory = $this->temporaryDirectory('backup-restore-');

        try {
            if ($createSafetyBackup) {
                $this->createFullBackup($user, 'Automatic pre-restore safety backup before restoring ' . $backup->filename);
            }

            $zip = new ZipArchive();
            if ($zip->open($backup->absolutePath()) !== true) {
                throw new RuntimeException('The selected backup archive could not be opened for restore.');
            }

            if (!$zip->extractTo($restoreDirectory)) {
                $zip->close();
                throw new RuntimeException('The selected backup archive could not be extracted for restore.');
            }

            $zip->close();

            $this->restoreDatabaseFromDirectory($restoreDirectory . DIRECTORY_SEPARATOR . 'database', $manifest['database'] ?? []);
            $this->restoreStorageFromDirectory($restoreDirectory . DIRECTORY_SEPARATOR . 'files');

            DB::purge();
            DB::reconnect();

            $this->syncCatalogFromDisk();

            PlatformBackup::query()
                ->where('filename', $backup->filename)
                ->update([
                    'status' => PlatformBackup::STATUS_RESTORED,
                    'restored_by' => $user?->id,
                    'restored_at' => now(),
                    'updated_at' => now(),
                ]);

            Artisan::call('optimize:clear');
        } finally {
            File::deleteDirectory($restoreDirectory);
        }
    }

    private function exportDatabase(string $workingDirectory): array
    {
        $databaseRoot = $workingDirectory . DIRECTORY_SEPARATOR . 'database';
        $schemaRoot = $databaseRoot . DIRECTORY_SEPARATOR . 'schema';
        $dataRoot = $databaseRoot . DIRECTORY_SEPARATOR . 'data';

        File::ensureDirectoryExists($schemaRoot);
        File::ensureDirectoryExists($dataRoot);

        $tables = $this->databaseTables();
        $pdo = DB::connection()->getPdo();
        $tablesManifest = [];
        $totalRows = 0;

        foreach ($tables as $index => $table) {
            $createTableSql = $this->tableSchemaSql($table);

            $fileStem = str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT) . '_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $table);
            $schemaFile = 'schema/' . $fileStem . '.sql';
            $dataFile = 'data/' . $fileStem . '.jsonl';

            File::put(
                $databaseRoot . DIRECTORY_SEPARATOR . $schemaFile,
                $this->dropTableSql($table) . "\n" . rtrim($createTableSql, ";\r\n") . ";\n"
            );

            $dataHandle = fopen($databaseRoot . DIRECTORY_SEPARATOR . $dataFile, 'wb');
            if ($dataHandle === false) {
                throw new RuntimeException('Could not create data export file for table ' . $table . '.');
            }

            $rowCount = 0;
            foreach (DB::table($table)->cursor() as $row) {
                $payload = [];

                foreach ((array) $row as $column => $value) {
                    $payload[$column] = $value;
                }

                fwrite($dataHandle, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
                $rowCount++;
            }

            fclose($dataHandle);

            $tablesManifest[] = [
                'name' => $table,
                'schema_file' => $schemaFile,
                'data_file' => $dataFile,
                'rows' => $rowCount,
            ];

            $totalRows += $rowCount;
        }

        unset($pdo);

        return [
            'tables_count' => count($tablesManifest),
            'rows_count' => $totalRows,
            'tables' => $tablesManifest,
        ];
    }

    private function exportStorageFiles(string $workingDirectory): array
    {
        $filesRoot = $workingDirectory . DIRECTORY_SEPARATOR . 'files' . DIRECTORY_SEPARATOR . 'storage-app';
        File::ensureDirectoryExists($filesRoot);

        $storageAppRoot = storage_path('app');
        $backupRoot = $this->backupRootDirectory();
        $count = 0;
        $bytes = 0;

        if (!is_dir($storageAppRoot)) {
            return [
                'root' => 'storage-app',
                'count' => 0,
                'bytes' => 0,
            ];
        }

        foreach (File::allFiles($storageAppRoot) as $file) {
            $sourcePath = $file->getRealPath();

            if (!$sourcePath) {
                continue;
            }

            if (str_starts_with($sourcePath, $backupRoot)) {
                continue;
            }

            $relativePath = Str::after($sourcePath, $storageAppRoot . DIRECTORY_SEPARATOR);
            $targetPath = $filesRoot . DIRECTORY_SEPARATOR . $relativePath;

            File::ensureDirectoryExists(dirname($targetPath));
            File::copy($sourcePath, $targetPath);

            $count++;
            $bytes += $file->getSize();
        }

        return [
            'root' => 'storage-app',
            'count' => $count,
            'bytes' => $bytes,
        ];
    }

    private function restoreDatabaseFromDirectory(string $databaseDirectory, array $databaseManifest): void
    {
        $tables = $databaseManifest['tables'] ?? [];

        $this->disableForeignKeyChecks();

        try {
            foreach ($this->databaseTables() as $table) {
                DB::statement($this->dropTableSql($table));
            }

            foreach ($tables as $tableManifest) {
                $schemaPath = $databaseDirectory . DIRECTORY_SEPARATOR . ($tableManifest['schema_file'] ?? '');

                if (!is_file($schemaPath)) {
                    throw new RuntimeException('Missing schema file for backup table restore.');
                }

                DB::unprepared((string) File::get($schemaPath));
            }

            foreach ($tables as $tableManifest) {
                $table = $tableManifest['name'] ?? null;
                $dataPath = $databaseDirectory . DIRECTORY_SEPARATOR . ($tableManifest['data_file'] ?? '');

                if (!$table || !is_file($dataPath)) {
                    continue;
                }

                $handle = fopen($dataPath, 'rb');
                if ($handle === false) {
                    throw new RuntimeException('Could not open backup data file for table restore.');
                }

                $batch = [];
                while (($line = fgets($handle)) !== false) {
                    $line = trim($line);

                    if ($line === '') {
                        continue;
                    }

                    $decoded = json_decode($line, true);
                    if (!is_array($decoded)) {
                        fclose($handle);
                        throw new RuntimeException('Backup data file contains invalid row payloads.');
                    }

                    $batch[] = $decoded;

                    if (count($batch) >= 200) {
                        DB::table($table)->insert($batch);
                        $batch = [];
                    }
                }

                fclose($handle);

                if (!empty($batch)) {
                    DB::table($table)->insert($batch);
                }
            }
        } finally {
            $this->enableForeignKeyChecks();
        }
    }

    private function restoreStorageFromDirectory(string $filesDirectory): void
    {
        $restoredRoot = $filesDirectory . DIRECTORY_SEPARATOR . 'storage-app';
        $storageAppRoot = storage_path('app');

        File::ensureDirectoryExists($storageAppRoot);

        foreach (File::directories($storageAppRoot) as $directory) {
            if (basename($directory) === 'backups') {
                continue;
            }

            File::deleteDirectory($directory);
        }

        foreach (File::files($storageAppRoot) as $file) {
            File::delete($file->getRealPath());
        }

        if (!is_dir($restoredRoot)) {
            return;
        }

        foreach (File::allFiles($restoredRoot) as $file) {
            $sourcePath = $file->getRealPath();

            if (!$sourcePath) {
                continue;
            }

            $relativePath = Str::after($sourcePath, $restoredRoot . DIRECTORY_SEPARATOR);
            $targetPath = $storageAppRoot . DIRECTORY_SEPARATOR . $relativePath;

            File::ensureDirectoryExists(dirname($targetPath));
            File::copy($sourcePath, $targetPath);
        }
    }

    private function createZipFromDirectory(string $sourceDirectory, string $zipPath): void
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('The backup archive could not be created.');
        }

        $sourceDirectory = rtrim($sourceDirectory, DIRECTORY_SEPARATOR);

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDirectory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $pathName = $item->getPathname();
            $relativePath = Str::after($pathName, $sourceDirectory . DIRECTORY_SEPARATOR);

            if ($item->isDir()) {
                $zip->addEmptyDir(str_replace('\\', '/', $relativePath));
                continue;
            }

            $zip->addFile($pathName, str_replace('\\', '/', $relativePath));
        }

        $zip->close();
    }

    private function readManifestFromZip(string $absoluteZipPath): ?array
    {
        if (!is_file($absoluteZipPath)) {
            return null;
        }

        $this->ensureZipSupport();

        $zip = new ZipArchive();
        if ($zip->open($absoluteZipPath) !== true) {
            return null;
        }

        $manifestContents = $zip->getFromName('manifest.json');
        $zip->close();

        if ($manifestContents === false) {
            return null;
        }

        $decoded = json_decode($manifestContents, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function databaseTables(): array
    {
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'mysql', 'mariadb' => collect(DB::select('SHOW TABLES'))
                ->map(fn ($row) => array_values((array) $row)[0] ?? null)
                ->filter()
                ->values()
                ->all(),
            'sqlite' => collect(DB::select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name"))
                ->map(fn ($row) => $row->name ?? array_values((array) $row)[0] ?? null)
                ->filter()
                ->values()
                ->all(),
            default => throw new RuntimeException('The backup service does not yet support the [' . $driver . '] database driver.'),
        };
    }

    private function tableSchemaSql(string $table): string
    {
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'mysql', 'mariadb' => $this->mysqlTableSchemaSql($table),
            'sqlite' => $this->sqliteTableSchemaSql($table),
            default => throw new RuntimeException('The backup service does not yet support schema export for the [' . $driver . '] database driver.'),
        };
    }

    private function mysqlTableSchemaSql(string $table): string
    {
        $schemaStatement = DB::selectOne('SHOW CREATE TABLE ' . $this->quotedIdentifier($table));
        $schemaValues = array_values((array) $schemaStatement);
        $createTableSql = $schemaValues[1] ?? null;

        if (!$createTableSql) {
            throw new RuntimeException('Could not read schema for table ' . $table . '.');
        }

        return (string) $createTableSql;
    }

    private function sqliteTableSchemaSql(string $table): string
    {
        $schemaStatement = DB::selectOne(
            "SELECT sql FROM sqlite_master WHERE type = 'table' AND name = ? LIMIT 1",
            [$table]
        );

        $createTableSql = $schemaStatement->sql ?? array_values((array) $schemaStatement)[0] ?? null;

        if (!$createTableSql) {
            throw new RuntimeException('Could not read schema for table ' . $table . '.');
        }

        return (string) $createTableSql;
    }

    private function disableForeignKeyChecks(): void
    {
        match (DB::connection()->getDriverName()) {
            'mysql', 'mariadb' => DB::statement('SET FOREIGN_KEY_CHECKS=0'),
            'sqlite' => DB::statement('PRAGMA foreign_keys = OFF'),
            default => null,
        };
    }

    private function enableForeignKeyChecks(): void
    {
        match (DB::connection()->getDriverName()) {
            'mysql', 'mariadb' => DB::statement('SET FOREIGN_KEY_CHECKS=1'),
            'sqlite' => DB::statement('PRAGMA foreign_keys = ON'),
            default => null,
        };
    }

    private function dropTableSql(string $table): string
    {
        return 'DROP TABLE IF EXISTS ' . $this->quotedIdentifier($table) . ';';
    }

    private function quotedIdentifier(string $value): string
    {
        return match (DB::connection()->getDriverName()) {
            'mysql', 'mariadb' => '`' . str_replace('`', '``', $value) . '`',
            'sqlite' => '"' . str_replace('"', '""', $value) . '"',
            default => $value,
        };
    }

    private function temporaryDirectory(string $prefix): string
    {
        $path = storage_path('app/backups/.tmp/' . $prefix . Str::uuid());
        File::ensureDirectoryExists($path);

        return $path;
    }

    private function backupRootDirectory(): string
    {
        return storage_path('app/backups/platform');
    }

    private function ensureZipSupport(): void
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('ZipArchive support is not available on this server, so platform backups cannot run yet.');
        }
    }
}
