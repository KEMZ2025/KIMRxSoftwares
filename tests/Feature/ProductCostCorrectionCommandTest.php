<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Client;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCostCorrectionCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_previews_then_corrects_only_zero_product_batch_and_sale_costs(): void
    {
        $client = Client::query()->create([
            'name' => 'VIP PHARMACY',
            'business_mode' => 'both',
            'is_active' => true,
        ]);
        $branch = Branch::query()->create([
            'client_id' => $client->id,
            'name' => 'Main Branch',
            'code' => 'MAIN',
            'is_main' => true,
            'is_active' => true,
        ]);
        $category = Category::query()->create([
            'client_id' => $client->id,
            'name' => 'Cough Preparations',
            'is_active' => true,
        ]);
        $unit = Unit::query()->create([
            'client_id' => $client->id,
            'name' => 'Bottle',
            'short_name' => 'BTL',
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'client_id' => $client->id,
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'name' => 'Jenacof DS 100ml',
            'purchase_price' => 0,
            'retail_price' => 5000,
            'wholesale_price' => 4500,
            'is_active' => true,
        ]);
        $zeroBatch = $this->createBatch($client, $branch, $product, 'ZERO-COST', 0);
        $pricedBatch = $this->createBatch($client, $branch, $product, 'KNOWN-COST', 2100);
        $sale = Sale::query()->create([
            'client_id' => $client->id,
            'branch_id' => $branch->id,
            'invoice_number' => 'JENA-001',
            'status' => 'approved',
            'sale_type' => 'retail',
            'payment_type' => 'cash',
            'total_amount' => 15000,
            'amount_paid' => 15000,
            'sale_date' => now(),
            'is_active' => true,
        ]);
        $zeroCostSaleItem = SaleItem::query()->create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'product_batch_id' => $zeroBatch->id,
            'quantity' => 2,
            'purchase_price' => 0,
            'unit_price' => 5000,
            'total_amount' => 10000,
        ]);
        $knownCostSaleItem = SaleItem::query()->create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'product_batch_id' => $pricedBatch->id,
            'quantity' => 1,
            'purchase_price' => 2100,
            'unit_price' => 5000,
            'total_amount' => 5000,
        ]);

        $arguments = [
            'clientName' => 'VIP PHARMACY',
            'productName' => 'Jenacof DS 100ml',
            'purchasePrice' => '2600',
        ];

        $this->artisan('kimrx:correct-zero-product-cost', $arguments)
            ->expectsOutputToContain('Preview only. Nothing has been changed.')
            ->assertSuccessful();

        $this->assertSame(0.0, (float) $product->fresh()->purchase_price);
        $this->assertSame(0.0, (float) $zeroBatch->fresh()->purchase_price);
        $this->assertSame(0.0, (float) $zeroCostSaleItem->fresh()->purchase_price);

        $this->artisan('kimrx:correct-zero-product-cost', $arguments + ['--confirm' => 'YES'])
            ->expectsOutputToContain('Purchase cost correction completed.')
            ->assertSuccessful();

        $this->assertSame(2600.0, (float) $product->fresh()->purchase_price);
        $this->assertSame(2600.0, (float) $zeroBatch->fresh()->purchase_price);
        $this->assertSame(2100.0, (float) $pricedBatch->fresh()->purchase_price);
        $this->assertSame(2600.0, (float) $zeroCostSaleItem->fresh()->purchase_price);
        $this->assertSame(2100.0, (float) $knownCostSaleItem->fresh()->purchase_price);
        $this->assertDatabaseHas('audit_logs', [
            'client_id' => $client->id,
            'event_key' => 'product.zero_cost_corrected',
            'subject_id' => $product->id,
        ]);
    }

    private function createBatch(
        Client $client,
        Branch $branch,
        Product $product,
        string $batchNumber,
        float $purchasePrice
    ): ProductBatch {
        return ProductBatch::query()->create([
            'client_id' => $client->id,
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'batch_number' => $batchNumber,
            'expiry_date' => now()->addYear()->toDateString(),
            'purchase_price' => $purchasePrice,
            'retail_price' => 5000,
            'wholesale_price' => 4500,
            'quantity_received' => 10,
            'quantity_available' => 7,
            'reserved_quantity' => 0,
            'is_active' => true,
        ]);
    }
}
