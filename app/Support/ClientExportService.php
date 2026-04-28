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
use Throwable;
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

    public function importClientExportAsClone(
        ClientExport $clientExport,
        string $restoredClientName,
        ?User $user = null,
        bool $activateImportedClient = false
    ): Client {
        $this->ensureZipSupport();

        if (!$clientExport->fileExists()) {
            throw new RuntimeException('The selected client export file is missing from disk.');
        }

        $manifest = $this->readManifest($clientExport);
        $extractDirectory = $this->temporaryDirectory('client-export-import-');

        try {
            $this->extractZipToDirectory($clientExport->absolutePath(), $extractDirectory);

            return DB::transaction(function () use ($manifest, $extractDirectory, $restoredClientName, $activateImportedClient) {
                $clientSnapshot = $manifest['client'] ?? [];
                $newClient = $this->createImportedClient($clientSnapshot, $restoredClientName, $activateImportedClient);
                $tables = $this->loadExportedTables($manifest, $extractDirectory);

                $mappings = [
                    'clients' => [
                        (string) ($clientSnapshot['id'] ?? 0) => $newClient->id,
                    ],
                ];

                $importedRows = [];

                if (isset($tables['permissions'])) {
                    $importedRows['permissions'] = $this->importPermissionsTable($tables['permissions'], $mappings);
                    unset($tables['permissions']);
                }

                $pass = 0;
                $maxPasses = max(3, count($tables) * 5);

                while (!empty($tables) && $pass < $maxPasses) {
                    $pass++;
                    $progressMade = false;

                    foreach (array_keys($tables) as $table) {
                        $pendingRows = $tables[$table];
                        $remainingRows = [];

                        foreach ($pendingRows as $row) {
                            if (!$this->rowCanBeImported($row, $mappings)) {
                                $remainingRows[] = $row;
                                continue;
                            }

                            $insertedRow = $this->importTableRow($table, $row, $newClient, $mappings);
                            $importedRows[$table][] = $insertedRow;
                            $progressMade = true;
                        }

                        if (empty($remainingRows)) {
                            unset($tables[$table]);
                            continue;
                        }

                        $tables[$table] = $remainingRows;
                    }

                    if (!$progressMade) {
                        break;
                    }
                }

                if (!empty($tables)) {
                    $blocked = collect($tables)
                        ->map(fn (array $rows, string $table) => $table . ' (' . count($rows) . ')')
                        ->values()
                        ->implode(', ');

                    throw new RuntimeException('The client import could not finish because some rows still had unresolved dependencies: ' . $blocked . '.');
                }

                $this->importManagedFiles($manifest['files'] ?? [], $extractDirectory, $newClient, $clientSnapshot);

                return $newClient->fresh();
            });
        } catch (Throwable $exception) {
            if ($exception instanceof RuntimeException) {
                throw $exception;
            }

            throw new RuntimeException('The client import could not be completed: ' . $exception->getMessage(), previous: $exception);
        } finally {
            File::deleteDirectory($extractDirectory);
        }
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

                $contextKey = $this->contextKeyForColumn($column, $contexts);
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

            $contextKey = $this->contextKeyForColumn((string) $column, $contexts);
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

    private function createImportedClient(array $clientSnapshot, string $restoredClientName, bool $activateImportedClient): Client
    {
        $subscriptionStatus = $activateImportedClient
            ? Client::STATUS_ACTIVE
            : ($clientSnapshot['subscription_status'] ?? Client::STATUS_ACTIVE);

        return Client::query()->create([
            'name' => $restoredClientName,
            'email' => $clientSnapshot['email'] ?? null,
            'phone' => $clientSnapshot['phone'] ?? null,
            'address' => $clientSnapshot['address'] ?? null,
            'logo' => null,
            'business_mode' => $clientSnapshot['business_mode'] ?? 'both',
            'package_preset' => $clientSnapshot['package_preset'] ?? null,
            'client_type' => $clientSnapshot['client_type'] ?? Client::TYPE_PAYING,
            'subscription_status' => $subscriptionStatus,
            'active_user_limit' => $clientSnapshot['active_user_limit'] ?? null,
            'subscription_ends_at' => $clientSnapshot['subscription_ends_at'] ?? null,
            'is_active' => $activateImportedClient,
            'is_platform_sandbox' => false,
        ]);
    }

    private function loadExportedTables(array $manifest, string $extractDirectory): array
    {
        $databaseManifest = $manifest['database'] ?? [];
        $tables = $databaseManifest['tables'] ?? [];
        $databaseDirectory = $extractDirectory . DIRECTORY_SEPARATOR . 'database';
        $rowsByTable = [];

        foreach ($tables as $tableManifest) {
            $table = $tableManifest['name'] ?? null;
            $dataFile = $tableManifest['data_file'] ?? null;

            if (!$table || !$dataFile) {
                continue;
            }

            $dataPath = $databaseDirectory . DIRECTORY_SEPARATOR . $dataFile;
            if (!is_file($dataPath)) {
                throw new RuntimeException('The client export is missing data for table ' . $table . '.');
            }

            $handle = fopen($dataPath, 'rb');
            if ($handle === false) {
                throw new RuntimeException('The client export data file for table ' . $table . ' could not be opened.');
            }

            $rows = [];
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);

                if ($line === '') {
                    continue;
                }

                $decoded = json_decode($line, true);
                if (!is_array($decoded)) {
                    fclose($handle);
                    throw new RuntimeException('The client export contains an invalid row payload for table ' . $table . '.');
                }

                $rows[] = $decoded;
            }

            fclose($handle);
            $rowsByTable[$table] = $rows;
        }

        unset($rowsByTable['clients']);

        return $rowsByTable;
    }

    private function importPermissionsTable(array $rows, array &$mappings): array
    {
        $importedRows = [];

        foreach ($rows as $row) {
            $permission = DB::table('permissions')
                ->where('permission_key', $row['permission_key'] ?? '')
                ->first();

            if (!$permission) {
                $permissionId = DB::table('permissions')->insertGetId([
                    'module_name' => $row['module_name'] ?? 'imported',
                    'action_name' => $row['action_name'] ?? 'imported',
                    'permission_key' => $row['permission_key'] ?? 'imported.' . Str::lower(Str::random(10)),
                    'description' => $row['description'] ?? null,
                    'created_at' => $row['created_at'] ?? now(),
                    'updated_at' => $row['updated_at'] ?? now(),
                ]);

                $permission = DB::table('permissions')->find($permissionId);
            }

            if (isset($row['id'])) {
                $mappings['permissions'][(string) $row['id']] = $permission->id;
            }

            $importedRows[] = (array) $permission;
        }

        return $importedRows;
    }

    private function rowCanBeImported(array $row, array $mappings): bool
    {
        foreach ($row as $column => $value) {
            if ($value === null || !str_ends_with((string) $column, '_id') || $column === 'client_id') {
                continue;
            }

            $contextKey = $this->contextKeyForColumn((string) $column, $mappings);
            $context = $mappings[$contextKey] ?? null;

            if ($context === null || !array_key_exists((string) $value, $context)) {
                return false;
            }
        }

        return true;
    }

    private function importTableRow(string $table, array $row, Client $newClient, array &$mappings): array
    {
        $payload = $row;
        $originalId = $payload['id'] ?? null;

        if (array_key_exists('id', $payload)) {
            unset($payload['id']);
        }

        foreach ($payload as $column => $value) {
            if ($column === 'client_id') {
                $payload[$column] = $newClient->id;
                continue;
            }

            if ($value === null || !str_ends_with((string) $column, '_id')) {
                continue;
            }

            $contextKey = $this->contextKeyForColumn((string) $column, $mappings);
            if (isset($mappings[$contextKey][(string) $value])) {
                $payload[$column] = $mappings[$contextKey][(string) $value];
            }
        }

        $payload = $this->normalizeImportedRow($table, $payload, $newClient);

        $newId = DB::table($table)->insertGetId($payload);
        $inserted = (array) DB::table($table)->find($newId);

        if ($originalId !== null) {
            $mappings[$table][(string) $originalId] = $newId;
        }

        return $inserted;
    }

    private function normalizeImportedRow(string $table, array $payload, Client $newClient): array
    {
        if ($table === 'users') {
            $payload['email'] = $this->uniqueUserEmail((string) ($payload['email'] ?? ''), $newClient->id);
        }

        if ($table === 'roles') {
            $payload['client_id'] = $newClient->id;
            $payload['code'] = $this->uniqueRoleCode((string) ($payload['code'] ?? ''), $newClient->id);
        }

        if ($table === 'branches') {
            $payload['client_id'] = $newClient->id;
        }

        if ($table === 'client_settings') {
            $payload['client_id'] = $newClient->id;
        }

        if ($table === 'insurers') {
            $payload['client_id'] = $newClient->id;
            $payload['name'] = $this->uniqueInsurerName((string) ($payload['name'] ?? 'Imported Insurer'), $newClient->id);
        }

        return $payload;
    }

    private function uniqueUserEmail(string $email, int $clientId): string
    {
        $email = trim($email);

        if ($email === '') {
            $email = 'restored-user-' . Str::lower(Str::random(8)) . '@client-' . $clientId . '.local';
        }

        if (!DB::table('users')->where('email', $email)->exists()) {
            return $email;
        }

        [$local, $domain] = str_contains($email, '@')
            ? explode('@', $email, 2)
            : [$email, 'client-' . $clientId . '.local'];

        $counter = 1;
        do {
            $candidate = $local . '+restored' . $counter . '@' . $domain;
            $counter++;
        } while (DB::table('users')->where('email', $candidate)->exists());

        return $candidate;
    }

    private function uniqueRoleCode(string $code, int $clientId): string
    {
        $base = trim($code) !== ''
            ? trim($code)
            : 'RESTORED-ROLE-' . $clientId;

        if (!DB::table('roles')->where('code', $base)->exists()) {
            return $base;
        }

        $counter = 1;
        do {
            $suffix = '-IMP' . $counter;
            $candidate = Str::limit($base, max(1, 255 - strlen($suffix)), '') . $suffix;
            $counter++;
        } while (DB::table('roles')->where('code', $candidate)->exists());

        return $candidate;
    }

    private function uniqueInsurerName(string $name, int $clientId): string
    {
        $base = trim($name) !== '' ? trim($name) : 'Imported Insurer';

        if (!DB::table('insurers')->where('client_id', $clientId)->where('name', $base)->exists()) {
            return $base;
        }

        $counter = 1;
        do {
            $candidate = $base . ' (' . $counter . ')';
            $counter++;
        } while (DB::table('insurers')->where('client_id', $clientId)->where('name', $candidate)->exists());

        return $candidate;
    }

    private function importManagedFiles(array $fileManifest, string $extractDirectory, Client $newClient, array $clientSnapshot): void
    {
        $items = $fileManifest['items'] ?? [];

        if (empty($items)) {
            return;
        }

        $logoSource = trim((string) ($clientSnapshot['logo'] ?? ''));
        $logoPath = null;
        $targetDirectory = public_path('uploads/client-logos/client-' . $newClient->id);

        if (!File::exists($targetDirectory)) {
            File::makeDirectory($targetDirectory, 0755, true);
        }

        foreach ($items as $item) {
            $archivedPath = $item['target'] ?? null;

            if (!$archivedPath) {
                continue;
            }

            $sourcePath = $extractDirectory . DIRECTORY_SEPARATOR . 'files' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $archivedPath);
            if (!is_file($sourcePath)) {
                continue;
            }

            $filename = basename((string) $archivedPath);
            $targetPath = $targetDirectory . DIRECTORY_SEPARATOR . $filename;
            File::copy($sourcePath, $targetPath);

            $sourceReference = ltrim((string) ($item['source'] ?? ''), '/\\');
            if ($logoPath === null && ($sourceReference === ltrim($logoSource, '/\\') || str_ends_with($logoSource, $filename))) {
                $logoPath = 'uploads/client-logos/client-' . $newClient->id . '/' . $filename;
            }
        }

        if ($logoPath !== null) {
            $newClient->forceFill(['logo' => $logoPath])->save();
        }
    }

    private function extractZipToDirectory(string $archivePath, string $destination): void
    {
        $zip = new ZipArchive();
        if ($zip->open($archivePath) !== true) {
            throw new RuntimeException('The selected client export archive could not be opened.');
        }

        if (!$zip->extractTo($destination)) {
            $zip->close();
            throw new RuntimeException('The selected client export archive could not be extracted.');
        }

        $zip->close();
    }

    private function contextKeyForColumn(string $column, array $contexts): string
    {
        $special = [
            'reversal_of_payment_id' => 'payments',
        ];

        if (isset($special[$column])) {
            return $special[$column];
        }

        $base = Str::beforeLast($column, '_id');
        $candidates = [Str::plural($base)];

        $segments = explode('_', $base);
        if (count($segments) > 1) {
            $candidates[] = Str::plural(end($segments));
        }

        foreach ($candidates as $candidate) {
            if (array_key_exists($candidate, $contexts)) {
                return $candidate;
            }
        }

        return $candidates[0];
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
