<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Support\PlatformBackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class RemoveElohimCatalogue extends Command
{
    private const CLIENT_NAME = 'ELOHIM DRUGSHOP';

    private const RELATED_TABLES = [
        'product_batches',
        'stock_movements',
        'stock_adjustments',
        'stock_requests',
        'sales',
        'purchases',
        'purchase_item_corrections',
        'supplier_payments',
        'hms_prescription_items',
    ];

    protected $signature = 'client:remove-elohim-catalogue
        {--execute : Remove the verified records}
        {--confirm= : Exact client name required to execute}
        {--expected-products=1876 : Expected total product count}
        {--expected-suppliers=187 : Expected total supplier count}';

    protected $description = 'Remove Elohim\'s unused imported products and suppliers, leaving other clients untouched';

    public function handle(PlatformBackupService $backupService): int
    {
        $clients = Client::query()->where('name', self::CLIENT_NAME)->limit(2)->get();
        if ($clients->count() !== 1) {
            $this->error('Expected exactly one Elohim client. Nothing was changed.');
            return self::FAILURE;
        }

        $clientId = (int) $clients->first()->id;
        $expectedProducts = filter_var($this->option('expected-products'), FILTER_VALIDATE_INT);
        $expectedSuppliers = filter_var($this->option('expected-suppliers'), FILTER_VALIDATE_INT);
        if ($expectedProducts === false || $expectedProducts < 1 || $expectedSuppliers === false || $expectedSuppliers < 1) {
            $this->error('Both expected counts must be positive integers.');
            return self::FAILURE;
        }

        $counts = $this->counts($clientId);
        $this->line('Client: ' . self::CLIENT_NAME . " (ID {$clientId})");
        $this->table(['Record type', 'Count'], collect($counts)->map(fn ($count, $name) => [$name, $count])->values()->all());
        $this->line('Categories, units, customers, users, branches, settings, and other clients will be kept.');

        try {
            $this->assertSafe($counts, $expectedProducts, $expectedSuppliers);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }

        if (!$this->option('execute')) {
            $this->info('Preview only. No records were changed.');
            return self::SUCCESS;
        }

        if ($this->option('confirm') !== self::CLIENT_NAME) {
            $this->error('Pass --confirm="ELOHIM DRUGSHOP" to execute.');
            return self::FAILURE;
        }

        try {
            $backup = $backupService->createFullBackup(null, 'Before removing Elohim imported catalogue');
            $this->info('Safety backup created: ' . $backup->filename);

            DB::transaction(function () use ($clientId, $expectedProducts, $expectedSuppliers): void {
                Client::query()->whereKey($clientId)->lockForUpdate()->firstOrFail();
                $this->assertSafe($this->counts($clientId), $expectedProducts, $expectedSuppliers);

                DB::table('products')->where('client_id', $clientId)->delete();
                DB::table('suppliers')->where('client_id', $clientId)->delete();
            });
        } catch (\Throwable $exception) {
            $this->error('Nothing was deleted: ' . $exception->getMessage());
            return self::FAILURE;
        }

        $this->info("Removed {$expectedProducts} products and {$expectedSuppliers} suppliers from Elohim only.");
        return self::SUCCESS;
    }

    private function counts(int $clientId): array
    {
        $counts = [
            'products' => DB::table('products')->where('client_id', $clientId)->count(),
            'suppliers' => DB::table('suppliers')->where('client_id', $clientId)->count(),
        ];

        foreach (self::RELATED_TABLES as $table) {
            if (Schema::hasTable($table)) {
                $counts[$table] = DB::table($table)->where('client_id', $clientId)->count();
            }
        }

        return $counts;
    }

    private function assertSafe(array $counts, int $expectedProducts, int $expectedSuppliers): void
    {
        if ($counts['products'] !== $expectedProducts || $counts['suppliers'] !== $expectedSuppliers) {
            throw new RuntimeException('Catalogue counts differ from the imported totals. Nothing was deleted.');
        }

        foreach (self::RELATED_TABLES as $table) {
            if (($counts[$table] ?? 0) > 0) {
                throw new RuntimeException("Elohim has {$table} records. Nothing was deleted.");
            }
        }
    }
}
