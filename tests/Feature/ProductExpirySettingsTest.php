<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductExpirySettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_update_persists_configured_expiry_alert_days(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext();
        $categoryId = $this->createCategory($clientId, 'Tablets');
        $unitId = $this->createUnit($clientId, 'Box');

        $product = Product::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'category_id' => $categoryId,
            'unit_id' => $unitId,
            'name' => 'Expiry Managed Product',
            'purchase_price' => 10,
            'retail_price' => 15,
            'wholesale_price' => 13,
            'track_batch' => true,
            'track_expiry' => true,
            'expiry_alert_days' => 90,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->put(route('products.update', $product), [
            'category_id' => $categoryId,
            'unit_id' => $unitId,
            'name' => 'Expiry Managed Product',
            'strength' => '500mg',
            'barcode' => 'EXP-001',
            'description' => 'Updated expiry alert days.',
            'purchase_price' => 10,
            'retail_price' => 15,
            'wholesale_price' => 13,
            'track_batch' => 1,
            'track_expiry' => 1,
            'expiry_alert_days' => 15,
            'guide_quantity' => [1, 5],
            'guide_label' => ['strip', 'packets'],
            'guide_amount' => [2500, 12000],
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('products.index'));

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'track_expiry' => true,
            'expiry_alert_days' => 15,
        ]);

        $product->refresh();

        $this->assertSame([
            ['quantity' => 1.0, 'label' => 'strip', 'amount' => 2500.0],
            ['quantity' => 5.0, 'label' => 'packets', 'amount' => 12000.0],
        ], $product->normalizedDispensingPriceGuide());
    }

    public function test_product_store_can_save_dispensing_price_guide_lines(): void
    {
        [$user, $clientId] = $this->createUserContext();
        $categoryId = $this->createCategory($clientId, 'Capsules');
        $unitId = $this->createUnit($clientId, 'Packet');

        $response = $this->actingAs($user)->post(route('products.store'), [
            'category_id' => $categoryId,
            'unit_id' => $unitId,
            'name' => 'Guide Configured Product',
            'strength' => '250mg',
            'barcode' => 'GUIDE-001',
            'description' => 'Created with a dispensing guide.',
            'purchase_price' => 7,
            'retail_price' => 10,
            'wholesale_price' => 9,
            'track_batch' => 1,
            'track_expiry' => 0,
            'guide_quantity' => [1, 1, 5],
            'guide_label' => ['strip', 'packet', 'packets'],
            'guide_amount' => [1000, 8000, 38000],
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('products.index'));

        $product = Product::query()
            ->where('client_id', $clientId)
            ->where('name', 'Guide Configured Product')
            ->firstOrFail();

        $this->assertSame([
            ['quantity' => 1.0, 'label' => 'strip', 'amount' => 1000.0],
            ['quantity' => 1.0, 'label' => 'packet', 'amount' => 8000.0],
            ['quantity' => 5.0, 'label' => 'packets', 'amount' => 38000.0],
        ], $product->normalizedDispensingPriceGuide());
    }

    private function createUserContext(): array
    {
        $clientId = $this->createClient('KimRx Test Client');
        $branchId = $this->createBranch($clientId, 'Main Branch');

        $user = User::factory()->create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'is_active' => true,
        ]);

        return [$user, $clientId, $branchId];
    }

    private function createClient(string $name): int
    {
        return DB::table('clients')->insertGetId([
            'name' => $name,
            'business_mode' => 'both',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createBranch(int $clientId, string $name): int
    {
        return DB::table('branches')->insertGetId([
            'client_id' => $clientId,
            'name' => $name,
            'code' => strtoupper(substr($name, 0, 3)),
            'is_main' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createCategory(int $clientId, string $name): int
    {
        return DB::table('categories')->insertGetId([
            'client_id' => $clientId,
            'name' => $name,
            'description' => null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createUnit(int $clientId, string $name): int
    {
        return DB::table('units')->insertGetId([
            'client_id' => $clientId,
            'name' => $name,
            'short_name' => strtoupper(substr($name, 0, 3)),
            'description' => null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
