<?php

namespace Tests\Feature;

use App\Models\PlatformBackup;
use App\Support\PlatformBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RemoveElohimCatalogueTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_does_not_delete_and_execution_removes_only_elohim_records(): void
    {
        [$vipId, $vipBranchId] = $this->workspace('VIP PHARMACY');
        [$elohimId, $elohimBranchId] = $this->workspace('ELOHIM DRUGSHOP');
        $this->product($vipId, $vipBranchId, 'VIP Medicine');
        $this->product($elohimId, $elohimBranchId, 'Imported Medicine');
        $this->supplier($vipId, 'VIP Supplier');
        $this->supplier($elohimId, 'Imported Supplier');

        $options = ['--expected-products' => 1, '--expected-suppliers' => 1];
        $this->artisan('client:remove-elohim-catalogue', $options)->assertExitCode(0);
        $this->artisan('client:remove-elohim-catalogue', $options + ['--execute' => true])->assertExitCode(1);
        $this->assertDatabaseCount('products', 2);

        $backupService = $this->mock(PlatformBackupService::class);
        $backupService->shouldReceive('createFullBackup')->once()
            ->andReturn(new PlatformBackup(['filename' => 'safety.zip']));

        $this->artisan('client:remove-elohim-catalogue', $options + [
            '--execute' => true, '--confirm' => 'ELOHIM DRUGSHOP',
        ])->assertExitCode(0);

        $this->assertDatabaseMissing('products', ['client_id' => $elohimId]);
        $this->assertDatabaseMissing('suppliers', ['client_id' => $elohimId]);
        $this->assertDatabaseHas('products', ['client_id' => $vipId, 'name' => 'VIP Medicine']);
        $this->assertDatabaseHas('suppliers', ['client_id' => $vipId, 'name' => 'VIP Supplier']);
    }

    public function test_existing_stock_or_changed_counts_block_deletion(): void
    {
        [$elohimId, $branchId] = $this->workspace('ELOHIM DRUGSHOP');
        $productId = $this->product($elohimId, $branchId, 'Imported Medicine');
        $this->supplier($elohimId, 'Imported Supplier');

        $this->artisan('client:remove-elohim-catalogue', [
            '--expected-products' => 2, '--expected-suppliers' => 1,
            '--execute' => true, '--confirm' => 'ELOHIM DRUGSHOP',
        ])->assertExitCode(1);

        DB::table('product_batches')->insert([
            'client_id' => $elohimId, 'branch_id' => $branchId, 'product_id' => $productId,
            'batch_number' => 'NEW-STOCK', 'quantity_received' => 1, 'quantity_available' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->artisan('client:remove-elohim-catalogue', [
            '--expected-products' => 1, '--expected-suppliers' => 1,
            '--execute' => true, '--confirm' => 'ELOHIM DRUGSHOP',
        ])->assertExitCode(1);

        $this->assertDatabaseHas('products', ['id' => $productId]);
        $this->assertDatabaseHas('suppliers', ['client_id' => $elohimId]);
    }

    private function workspace(string $name): array
    {
        $clientId = DB::table('clients')->insertGetId([
            'name' => $name, 'business_mode' => 'retail_only', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $branchId = DB::table('branches')->insertGetId([
            'client_id' => $clientId, 'name' => 'Main Branch', 'code' => 'MAIN',
            'business_mode' => 'inherit', 'is_main' => true, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$clientId, $branchId];
    }

    private function product(int $clientId, int $branchId, string $name): int
    {
        return DB::table('products')->insertGetId([
            'client_id' => $clientId, 'branch_id' => $branchId, 'name' => $name,
            'purchase_price' => 0, 'retail_price' => 100, 'wholesale_price' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function supplier(int $clientId, string $name): void
    {
        DB::table('suppliers')->insert([
            'client_id' => $clientId, 'name' => $name,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
