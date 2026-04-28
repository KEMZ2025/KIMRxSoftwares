<?php

namespace App\Support;

use App\Models\Client;
use App\Models\ClientExport;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class ClientExportService
{
    private const EXCLUDED_TABLES = [
        'cache',
        'cache_locks',
        'client_exports',
        'failed_jobs',
        'job_batches',
        'jobs',
        'migrations',
        'password_reset_tokens',
        'platform_backups',
        'platform_settings',
        'sessions',
    ];

    public function createClientExport(Client $client, ?User $user = null, ?string $note = null): ClientExport
    {
        $this->ensureZipSupport();

        $timestamp = now()->format('Ymd-His');
        $clientSlug = Str::slug((string) $client->name) ?: 'client-' . $client->id;
        $filename = 'kimrx-client-export-' . $clientSlug . '-' . $timestamp . '-' . Str::lower(Str::random(6)) . '.zip';
        $diskPath = 'backups/client-exports/' . $filename;
        $absoluteZipPath = storage_path('app/' . $diskPath);
        $workingDirectory = $this->temporaryDirectory('client-export-build-');

        File::ensureDirectoryExists(dirname($absoluteZipPath));

        try {
            $databaseManifest = $this->exportClientDatabase($client, $workingDirectory);
            $fileManifest = $this->exportClientFiles($client, $workingDirectory);

            $manifest = [
                'export_type' => ClientExport::TYPE_CLIENT_EXPORT,
                'app_version' => (string) config('app.version', 'v1.0.0'),
                'created_at' => now()->toIso8601String(),
                'database_connection' => DB::connection()->getName(),
                'database_name' => DB::connection()->getDatabaseName(),
                'note' => $note,
                'client' => [
                    'id' => $client->id,
                    'name' => $client->name,
                    'email' => $client->email,
                    'phone' => $client->phone,
                    'address' => $client->address,
                    'logo' => $client->logo,
                    'business_mode' => $client->business_mode,
                    'package_preset' => $client->package_preset,
                    'client_type' => $client->client_type,
                    'subscription_status' => $client->subscription_status,
                    'active_user_limit' => $client->active_user_limit,
                    'subscription_ends_at' => optional($client->subscription_ends_at)?->toDateString(),
                    'is_active' => (bool) $client->is_active,
                    'is_platform_sandbox' => (bool) $client->is_platform_sandbox,
                ],
                'database' => $databaseManifest,
                'files' => $fileManifest,
            ];

            File::put(
                $workingDirectory . DIRECTORY_SEPARATOR . 'manifest.json',
                json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            );

            $this->createZipFromDirectory($workingDirectory, $absoluteZipPath);

            return ClientExport::query()->updateOrCreate(
                ['filename' => $filename],
                [
                    'client_id' => $client->id,
                    'disk_path' => $diskPath,
                    'export_type' => ClientExport::TYPE_CLIENT_EXPORT,
                    'status' => ClientExport::STATUS_READY,
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
        File::ensureDirectoryExists($this->exportRootDirectory());

        $seen = [];

        foreach (File::files($this->exportRootDirectory()) as $file) {
            if (strtolower($file->getExtension()) !== 'zip') {
                continue;
            }

            $filename = $file->getFilename();
            $manifest = $this->readManifestFromZip($file->getRealPath());

            if (!$manifest || ($manifest['export_type'] ?? null) !== ClientExport::TYPE_CLIENT_EXPORT) {
                continue;
            }

            $databaseManifest = $manifest['database'] ?? [];
            $fileManifest = $manifest['files'] ?? [];
            $clientSnapshot = $manifest['client'] ?? [];

            ClientExport::query()->updateOrCreate(
                ['filename' => $filename],
                [
                    'client_id' => $clientSnapshot['id'] ?? null,
                    'disk_path' => 'backups/client-exports/' . $filename,
                    'export_type' => ClientExport::TYPE_CLIENT_EXPORT,
                    'status' => ClientExport::STATUS_READY,
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

        ClientExport::query()
            ->when(!empty($seen), fn ($query) => $query->whereNotIn('filename', $seen))
            ->when(empty($seen), fn ($query) => $query)
            ->update(['status' => ClientExport::STATUS_MISSING]);
    }

    public function readManifest(ClientExport $clientExport): array
    {
        $manifest = $this->readManifestFromZip($clientExport->absolutePath());

        if (!$manifest) {
            throw new RuntimeException('This client export file could not be read or is missing its manifest.');
        }

        return $manifest;
    }

    private function exportClientDatabase(Client $client, string $workingDirectory): array
    {
        $databaseRoot = $workingDirectory . DIRECTORY_SEPARATOR . 'database';
        $schemaRoot = $databaseRoot . DIRECTORY_SEPARATOR . 'schema';
        $dataRoot = $databaseRoot . DIRECTORY_SEPARATOR . 'data';

        File::ensureDirectoryExists($schemaRoot);
        File::ensureDirectoryExists($dataRoot);

        $tables = collect($this->databaseTables())
            ->reject(fn (string $table) => in_array($table, self::EXCLUDED_TABLES, true))
            ->values()
            ->all();

        $exportedRows = $this->collectTenantRows($client, $tables);
        $tablesManifest = [];
        $totalRows = 0;

        foreach ($tables as $index => $table) {
            $rows = $exportedRows[$table] ?? [];

            if (empty($rows)) {
                continue;
            }

            $fileStem = str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT) . '_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $table);
            $schemaFile = 'schema/' . $fileStem . '.sql';
            $dataFile = 'data/' . $fileStem . '.jsonl';

            File::put(
                $databaseRoot . DIRECTORY_SEPARATOR . $schemaFile,
                $this->dropTableSql($table) . "\n" . rtrim($this->tableSchemaSql($table), ";\r\n") . ";\n"
            );

            $dataHandle = fopen($databaseRoot . DIRECTORY_SEPARATOR . $dataFile, 'wb');
            if ($dataHandle === false) {
                throw new RuntimeException('Could not create data export file for table ' . $table . '.');
            }

            foreach ($rows as $row) {
                fwrite($dataHandle, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
            }

            fclose($dataHandle);

            $rowCount = count($rows);

            $tablesManifest[] = [
                'name' => $table,
                'schema_file' => $schemaFile,
                'data_file' => $dataFile,
                'rows' => $rowCount,
            ];

            $totalRows += $rowCount;
        }

        return [
            'tables_count' => count($tablesManifest),
            'rows_count' => $totalRows,
            'tables' => $tablesManifest,
        ];
    }

    private function collectTenantRows(Client $client, array $tables): array
    {
        $columnMap = [];
        foreach ($tables as $table) {
            $columnMap[$table] = Schema::getColumnListing($table);
        }

        $contexts = [
            'clients' => [(string) $client->id => true],
        ];

        $rowsByTable = [];
        $fingerprints = [];
        $progress = true;

        while ($progress) {
            $progress = false;

            foreach ($tables as $table) {
                $rows = $this->matchingRowsForTable($table, $columnMap[$table], $client->id, $contexts);

                foreach ($rows as $row) {
                    $payload = (array) $row;
                    ksort($payload);
                    $fingerprint = md5(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

                    if (isset($fingerprints[$table][$fingerprint])) {
                        continue;
                    }

                    $fingerprints[$table][$fingerprint] = true;
                    $rowsByTable[$table][] = $payload;
                    $this->seedContextsFromRow($table, $payload, $contexts);
                    $progress = true;
                }
            }
        }

        return $rowsByTable;
    }

    private function matchingRowsForTable(string $table, array $columns, int $clientId, array $contexts): array
    {
        $query = DB::table($table);
        $hasConstraint = false;

        $query->where(function ($builder) use ($table, $columns, $clientId, $contexts, &$hasConstraint) {
            if (in_array('client_id', $columns, true)) {
                $builder->orWhere('client_id', $clientId);
                $hasConstraint = true;
            }

            if (in_array('id', $columns, true)) {
                $tableContextValues = array_keys($contexts[$table] ?? []);

                if (!empty($tableContextValues)) {
                    $builder->orWhereIn('id', $tableContextValues);
                    $hasConstraint = true;
                }
            }

            foreach ($columns as $column) {
                if (!str_ends_with($column, '_id')) {
                    continue;
                }

                $contextKey = Str::plural(Str::beforeLast($column, '_id'));
                $values = array_keys($contexts[$contextKey] ?? []);

                if (empty($values)) {
                    continue;
                }

                $builder->orWhereIn($column, $values);
                $hasConstraint = true;
            }
        });

        if (!$hasConstraint) {
            return [];
        }

        return $query->get()->all();
    }

    private function seedContextsFromRow(string $table, array $row, array &$contexts): void
    {
        if (array_key_exists('id', $row) && $row['id'] !== null) {
            $contexts[$table][(string) $row['id']] = true;
        }

        foreach ($row as $column => $value) {
            if ($value === null || !str_ends_with((string) $column, '_id')) {
                continue;
            }

            $contextKey = Str::plural(Str::beforeLast((string) $column, '_id'));
            $contexts[$contextKey][(string) $value] = true;
        }
    }

    private function exportClientFiles(Client $client, string $workingDirectory): array
    {
        $filesRoot = $workingDirectory . DIRECTORY_SEPARATOR . 'files';
        File::ensureDirectoryExists($filesRoot);

        $count = 0;
        $bytes = 0;
        $items = [];
        $copiedSources = [];

        $managedLogoDirectory = public_path('uploads/client-logos/client-' . $client->id);
        if (is_dir($managedLogoDirectory)) {
            foreach (File::allFiles($managedLogoDirectory) as $file) {
                $sourcePath = $file->getRealPath();

                if (!$sourcePath || isset($copiedSources[$sourcePath])) {
                    continue;
                }

                $relativePath = 'public-assets/client-logos/client-' . $client->id . '/' . $file->getFilename();
                $targetPath = $filesRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

                File::ensureDirectoryExists(dirname($targetPath));
                File::copy($sourcePath, $targetPath);

                $copiedSources[$sourcePath] = true;
                $count++;
                $bytes += $file->getSize();
                $items[] = [
                    'source' => Str::after($sourcePath, public_path() . DIRECTORY_SEPARATOR),
                    'target' => $relativePath,
                    'bytes' => $file->getSize(),
                ];
            }
        }

        $logoPath = trim((string) $client->logo);
        if ($logoPath !== '' && !preg_match('/^https?:\/\//i', $logoPath)) {
            $candidate = public_path(str_replace('/', DIRECTORY_SEPARATOR, ltrim($logoPath, '/\\')));

            if (is_file($candidate) && !isset($copiedSources[$candidate])) {
                $relativePath = 'public-assets/' . str_replace('\\', '/', ltrim($logoPath, '/\\'));
                $targetPath = $filesRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

                File::ensureDirectoryExists(dirname($targetPath));
                File::copy($candidate, $targetPath);

                $copiedSources[$candidate] = true;
                $count++;
                $bytes += filesize($candidate) ?: 0;
                $items[] = [
                    'source' => str_replace('\\', '/', ltrim($logoPath, '/\\')),
                    'target' => $relativePath,
                    'bytes' => filesize($candidate) ?: 0,
                ];
            }
        }

        return [
            'root' => 'public-assets',
            'count' => $count,
            'bytes' => $bytes,
            'items' => $items,
        ];
    }

    private function createZipFromDirectory(string $sourceDirectory, string $zipPath): void
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('The client export archive could not be created.');
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
            default => throw new RuntimeException('The client export service does not yet support the [' . $driver . '] database driver.'),
        };
    }

    private function tableSchemaSql(string $table): string
    {
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'mysql', 'mariadb' => $this->mysqlTableSchemaSql($table),
            'sqlite' => $this->sqliteTableSchemaSql($table),
            default => throw new RuntimeException('The client export service does not yet support schema export for the [' . $driver . '] database driver.'),
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

    private function exportRootDirectory(): string
    {
        return storage_path('app/backups/client-exports');
    }

    private function ensureZipSupport(): void
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('ZipArchive support is not available on this server, so client exports cannot run yet.');
        }
    }
}
