<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Client;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\StockMovement;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoLiveResetCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_go_live_reset_keeps_products_by_default(): void
    {
        [$client, $branch] = $this->createClientWithBranch('VIP PHARMACY');
        $product = $this->createProduct($client, $branch, 'Paracetamol');

        $this->artisan('kimrx:reset-client-go-live', [
            'clientName' => 'VIP PHARMACY',
            '--confirm' => 'YES',
            '--skip-backup' => true,
        ])->assertSuccessful();

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'client_id' => $client->id,
        ]);
    }

    public function test_go_live_reset_can_delete_only_selected_client_products(): void
    {
        [$vipClient, $vipBranch] = $this->createClientWithBranch('VIP PHARMACY');
        [$otherClient, $otherBranch] = $this->createClientWithBranch('Other Pharmacy');

        $vipProduct = $this->createProduct($vipClient, $vipBranch, 'Paracetamol');
        $otherProduct = $this->createProduct($otherClient, $otherBranch, 'Amoxicillin');

        $vipBatch = $this->createBatch($vipClient, $vipBranch, $vipProduct);
        $otherBatch = $this->createBatch($otherClient, $otherBranch, $otherProduct);

        StockMovement::query()->create([
            'client_id' => $vipClient->id,
            'branch_id' => $vipBranch->id,
            'product_id' => $vipProduct->id,
            'product_batch_id' => $vipBatch->id,
            'movement_type' => 'import_opening_in',
            'reference_type' => 'data_import',
            'quantity_in' => 10,
            'quantity_out' => 0,
            'balance_after' => 10,
        ]);

        StockMovement::query()->create([
            'client_id' => $otherClient->id,
            'branch_id' => $otherBranch->id,
            'product_id' => $otherProduct->id,
            'product_batch_id' => $otherBatch->id,
            'movement_type' => 'import_opening_in',
            'reference_type' => 'data_import',
            'quantity_in' => 5,
            'quantity_out' => 0,
            'balance_after' => 5,
        ]);

        $this->artisan('kimrx:reset-client-go-live', [
            'clientName' => 'VIP PHARMACY',
            '--confirm' => 'YES',
            '--skip-backup' => true,
            '--delete-products' => true,
        ])->assertSuccessful();

        $this->assertDatabaseMissing('products', ['id' => $vipProduct->id]);
        $this->assertDatabaseMissing('product_batches', ['id' => $vipBatch->id]);
        $this->assertDatabaseMissing('stock_movements', ['product_id' => $vipProduct->id]);

        $this->assertDatabaseHas('products', ['id' => $otherProduct->id]);
        $this->assertDatabaseHas('product_batches', ['id' => $otherBatch->id]);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $otherProduct->id]);

        $this->assertDatabaseHas('categories', ['client_id' => $vipClient->id]);
        $this->assertDatabaseHas('units', ['client_id' => $vipClient->id]);
    }

    private function createClientWithBranch(string $name): array
    {
        $client = Client::query()->create([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '', $name)) . '@example.com',
            'business_mode' => 'both',
            'is_active' => true,
        ]);

        $branch = Branch::query()->create([
            'client_id' => $client->id,
            'name' => 'Main Branch',
            'code' => 'MAIN-' . $client->id,
            'business_mode' => 'inherit',
            'is_main' => true,
            'is_active' => true,
        ]);

        return [$client, $branch];
    }

    private function createProduct(Client $client, Branch $branch, string $name): Product
    {
        $category = Category::query()->create([
            'client_id' => $client->id,
            'name' => $name . ' Category',
            'is_active' => true,
        ]);

        $unit = Unit::query()->create([
            'client_id' => $client->id,
            'name' => $name . ' Unit',
            'is_active' => true,
        ]);

        return Product::query()->create([
            'client_id' => $client->id,
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'name' => $name,
            'strength' => '500mg',
            'barcode' => strtoupper(substr($name, 0, 4)) . '-' . $client->id,
            'purchase_price' => 1000,
            'retail_price' => 1500,
            'wholesale_price' => 1400,
            'track_batch' => true,
            'track_expiry' => true,
            'expiry_alert_days' => 90,
            'is_active' => true,
        ]);
    }

    private function createBatch(Client $client, Branch $branch, Product $product): ProductBatch
    {
        return ProductBatch::query()->create([
            'client_id' => $client->id,
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'batch_number' => 'OPEN-' . $product->id,
            'expiry_date' => '2027-12-31',
            'purchase_price' => 1000,
            'retail_price' => 1500,
            'wholesale_price' => 1400,
            'quantity_received' => 10,
            'quantity_available' => 10,
            'reserved_quantity' => 0,
            'is_active' => true,
        ]);
    }
}
