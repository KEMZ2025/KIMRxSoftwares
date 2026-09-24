<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CopyClientCatalogueTest extends TestCase
{
    use RefreshDatabase;

    public function test_copy_requires_confirmation_and_only_copies_retail_catalogue_data(): void
    {
        [$sourceClient, $sourceBranch] = $this->workspace('VIP PHARMACY', 'both');
        [$targetClient, $targetBranch] = $this->workspace('ELOHIM DRUGSHOP', 'retail_only');

        $category = Category::query()->create(['client_id' => $sourceClient, 'name' => 'Tablets']);
        $unit = Unit::query()->create(['client_id' => $sourceClient, 'name' => 'Box']);
        Product::query()->create([
            'client_id' => $sourceClient, 'branch_id' => $sourceBranch,
            'category_id' => $category->id, 'unit_id' => $unit->id,
            'name' => 'Medicine A', 'strength' => '500mg', 'barcode' => 'MED-A',
            'purchase_price' => 100, 'retail_price' => 250, 'wholesale_price' => 180,
            'track_batch' => true, 'track_expiry' => true, 'is_active' => true,
        ]);
        Supplier::query()->create(['client_id' => $sourceClient, 'name' => 'Supplier A', 'phone' => '0700000000']);

        $arguments = ['source' => 'VIP PHARMACY', 'target' => 'ELOHIM DRUGSHOP'];
        $this->artisan('client:copy-catalogue', $arguments)->assertExitCode(0);
        $this->artisan('client:copy-catalogue', $arguments + ['--execute' => true])->assertExitCode(1);
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('suppliers', 1);

        $this->artisan('client:copy-catalogue', $arguments + [
            '--execute' => true, '--confirm' => 'ELOHIM DRUGSHOP',
        ])->assertExitCode(0);

        $copy = Product::query()->where('client_id', $targetClient)->sole();
        $this->assertSame($targetBranch, $copy->branch_id);
        $this->assertSame('Medicine A', $copy->name);
        $this->assertSame('250.00', $copy->retail_price);
        $this->assertSame('0.00', $copy->purchase_price);
        $this->assertSame('0.00', $copy->wholesale_price);
        $this->assertSame($targetClient, $copy->category->client_id);
        $this->assertSame($targetClient, $copy->unit->client_id);
        $this->assertDatabaseHas('suppliers', ['client_id' => $targetClient, 'name' => 'Supplier A']);
        foreach (['customers', 'product_batches', 'sales', 'purchases', 'stock_movements'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->artisan('client:copy-catalogue', $arguments + [
            '--execute' => true, '--confirm' => 'ELOHIM DRUGSHOP',
        ])->assertExitCode(1);
        $this->assertDatabaseCount('products', 2);
    }

    private function workspace(string $name, string $mode): array
    {
        $clientId = DB::table('clients')->insertGetId([
            'name' => $name, 'business_mode' => $mode, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $branchId = DB::table('branches')->insertGetId([
            'client_id' => $clientId, 'name' => 'Main Branch', 'code' => 'MAIN',
            'business_mode' => 'inherit', 'is_main' => true, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return [$clientId, $branchId];
    }
}
