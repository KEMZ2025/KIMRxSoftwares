<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RetailOnlyPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_retail_only_product_can_be_created_and_updated_without_wholesale_price(): void
    {
        [$user, $clientId, $branchId] = $this->workspace('retail_only');
        [$categoryId, $unitId] = $this->catalogueFields($clientId);

        $this->actingAs($user)->post(route('products.store'), [
            'name' => 'Retail Medicine',
            'category_id' => $categoryId,
            'unit_id' => $unitId,
            'retail_price' => 1500,
            'is_active' => 1,
        ])->assertRedirect(route('products.index'));

        $product = DB::table('products')->where('client_id', $clientId)->where('name', 'Retail Medicine')->first();
        $this->assertNotNull($product);
        $this->assertEquals(0, (float) $product->wholesale_price);

        $this->actingAs($user)->put(route('products.update', $product->id), [
            'name' => 'Retail Medicine',
            'category_id' => $categoryId,
            'unit_id' => $unitId,
            'retail_price' => 1800,
            'is_active' => 1,
        ])->assertRedirect(route('products.index'));

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'branch_id' => $branchId,
            'retail_price' => 1800,
            'wholesale_price' => 0,
        ]);
    }

    public function test_retail_only_purchase_can_save_without_wholesale_price_but_still_checks_retail_cost(): void
    {
        [$user, $clientId, $branchId] = $this->workspace('retail_only');
        $supplierId = DB::table('suppliers')->insertGetId([
            'client_id' => $clientId, 'name' => 'Test Supplier', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $productId = DB::table('products')->insertGetId([
            'client_id' => $clientId, 'branch_id' => $branchId, 'name' => 'Retail Tablets',
            'purchase_price' => 0, 'retail_price' => 15, 'wholesale_price' => 0,
            'track_batch' => true, 'track_expiry' => false, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $data = [
            'invoice_number' => 'RETAIL-001', 'supplier_id' => $supplierId,
            'purchase_date' => now()->toDateString(), 'payment_type' => 'cash',
            'amount_paid' => 20, 'product_id' => [$productId],
            'batch_number' => ['RETAIL-BATCH-001'], 'expiry_date' => [null], 'ordered_quantity' => [2],
            'received_now_quantity' => [2], 'unit_cost' => [10], 'retail_price' => [15],
        ];

        $this->actingAs($user)->post(route('purchases.store'), $data)
            ->assertRedirect(route('purchases.index'));
        $this->assertDatabaseHas('purchase_items', [
            'product_id' => $productId, 'retail_price' => 15, 'wholesale_price' => 0,
        ]);

        $data['invoice_number'] = 'RETAIL-002';
        $data['retail_price'] = [9];
        $this->from(route('purchases.create'))->actingAs($user)->post(route('purchases.store'), $data)
            ->assertSessionHasErrors('retail_price.0');
    }

    public function test_mixed_mode_product_still_requires_wholesale_price(): void
    {
        [$user, $clientId] = $this->workspace('both');
        [$categoryId, $unitId] = $this->catalogueFields($clientId);

        $this->actingAs($user)->post(route('products.store'), [
            'name' => 'Mixed Medicine', 'category_id' => $categoryId,
            'unit_id' => $unitId, 'retail_price' => 1500,
        ])->assertSessionHasErrors('wholesale_price');
    }

    private function workspace(string $businessMode): array
    {
        $clientId = DB::table('clients')->insertGetId([
            'name' => 'Pricing Test', 'business_mode' => $businessMode,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $branchId = DB::table('branches')->insertGetId([
            'client_id' => $clientId, 'name' => 'Main Branch', 'code' => 'MAIN',
            'business_mode' => 'inherit', 'is_main' => true, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $user = User::factory()->create([
            'client_id' => $clientId, 'branch_id' => $branchId, 'is_active' => true,
        ]);

        return [$user, $clientId, $branchId];
    }

    private function catalogueFields(int $clientId): array
    {
        $categoryId = DB::table('categories')->insertGetId([
            'client_id' => $clientId, 'name' => 'Medicines', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $unitId = DB::table('units')->insertGetId([
            'client_id' => $clientId, 'name' => 'Tablet', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$categoryId, $unitId];
    }
}
