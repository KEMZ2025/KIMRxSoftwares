<?php

namespace Tests\Feature;

use App\Models\ClientSetting;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\User;
use App\Support\AccessControlBootstrapper;
use App\Support\ClientFeatureAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PosDispensingPriceGuideTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_create_screen_shows_dispensing_price_guide_panel_when_enabled(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext();
        app(AccessControlBootstrapper::class)->ensureForUser($user);
        $this->setDispensingPriceGuideEnabled($clientId, true);
        $user = $user->fresh();

        $categoryId = $this->createCategory($clientId, 'Analgesics');
        $unitId = $this->createUnit($clientId, 'Packet');
        $productId = $this->createProduct($clientId, $branchId, $categoryId, $unitId, 'Panadol', [
            'dispensing_price_guide' => [
                ['quantity' => 1, 'label' => 'strip', 'amount' => 1500],
            ],
        ]);
        $supplierId = $this->createSupplier($clientId, 'Guide Supplier');
        $this->createBatch($clientId, $branchId, $productId, $supplierId, [
            'batch_number' => 'PAN-GUIDE-001',
        ]);

        $this->actingAs($user)
            ->get(route('sales.create'))
            ->assertOk()
            ->assertSee('Dispensing Price Guide')
            ->assertSee('Quick quote guide for', false);
    }

    public function test_product_search_returns_dispensing_price_guide_when_enabled(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext();
        app(AccessControlBootstrapper::class)->ensureForUser($user);
        $this->setDispensingPriceGuideEnabled($clientId, true);
        $user = $user->fresh();

        $categoryId = $this->createCategory($clientId, 'Analgesics');
        $unitId = $this->createUnit($clientId, 'Packet');
        $productId = $this->createProduct($clientId, $branchId, $categoryId, $unitId, 'Panadol Extra', [
            'dispensing_price_guide' => [
                ['quantity' => 1, 'label' => 'strip', 'amount' => 1500],
                ['quantity' => 5, 'label' => 'packets', 'amount' => 32000],
            ],
        ]);
        $supplierId = $this->createSupplier($clientId, 'Guide Supplier');
        $this->createBatch($clientId, $branchId, $productId, $supplierId, [
            'batch_number' => 'PAN-SEARCH-001',
        ]);

        $this->actingAs($user)
            ->getJson(route('sales.productSearch', ['q' => 'Panadol']))
            ->assertOk()
            ->assertJsonPath('0.product_name', 'Panadol Extra')
            ->assertJsonPath('0.dispensing_price_guide.0.label', 'strip')
            ->assertJsonPath('0.dispensing_price_guide.1.quantity', 5);
    }

    public function test_product_search_hides_dispensing_price_guide_when_module_is_disabled(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext();
        app(AccessControlBootstrapper::class)->ensureForUser($user);
        $this->setDispensingPriceGuideEnabled($clientId, false);
        $user = $user->fresh();

        $categoryId = $this->createCategory($clientId, 'Analgesics');
        $unitId = $this->createUnit($clientId, 'Packet');
        $productId = $this->createProduct($clientId, $branchId, $categoryId, $unitId, 'Panadol Hidden Guide', [
            'dispensing_price_guide' => [
                ['quantity' => 1, 'label' => 'strip', 'amount' => 1500],
            ],
        ]);
        $supplierId = $this->createSupplier($clientId, 'Guide Supplier');
        $this->createBatch($clientId, $branchId, $productId, $supplierId, [
            'batch_number' => 'PAN-HIDDEN-001',
        ]);

        $this->actingAs($user)
            ->get(route('sales.create'))
            ->assertOk()
            ->assertDontSee('Dispensing Price Guide');

        $this->actingAs($user)
            ->getJson(route('sales.productSearch', ['q' => 'Panadol']))
            ->assertOk()
            ->assertJsonPath('0.product_name', 'Panadol Hidden Guide')
            ->assertJsonPath('0.dispensing_price_guide', []);
    }

    private function createUserContext(): array
    {
        $clientId = $this->createClient('KimRx POS Guide Client');
        $branchId = $this->createBranch($clientId, 'Main Branch');

        $user = User::factory()->create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'is_active' => true,
        ]);

        return [$user, $clientId, $branchId];
    }

    private function setDispensingPriceGuideEnabled(int $clientId, bool $enabled): void
    {
        ClientSetting::query()->updateOrCreate(
            ['client_id' => $clientId],
            array_merge(
                ['business_mode' => 'both'],
                ClientFeatureAccess::defaultSettingValues(),
                [
                'dispensing_price_guide_enabled' => $enabled,
                ]
            )
        );
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

    private function createProduct(int $clientId, int $branchId, int $categoryId, int $unitId, string $name, array $attributes = []): int
    {
        return Product::query()->create(array_merge([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'category_id' => $categoryId,
            'unit_id' => $unitId,
            'name' => $name,
            'purchase_price' => 10,
            'retail_price' => 15,
            'wholesale_price' => 13,
            'track_batch' => true,
            'track_expiry' => false,
            'expiry_alert_days' => null,
            'is_active' => true,
        ], $attributes))->id;
    }

    private function createSupplier(int $clientId, string $name): int
    {
        return DB::table('suppliers')->insertGetId([
            'client_id' => $clientId,
            'name' => $name,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createBatch(int $clientId, int $branchId, int $productId, int $supplierId, array $attributes = []): ProductBatch
    {
        return ProductBatch::query()->create(array_merge([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'product_id' => $productId,
            'supplier_id' => $supplierId,
            'batch_number' => 'BATCH-001',
            'expiry_date' => '2027-01-01',
            'purchase_price' => 10,
            'retail_price' => 15,
            'wholesale_price' => 13,
            'quantity_received' => 20,
            'quantity_available' => 20,
            'reserved_quantity' => 0,
            'is_active' => true,
        ], $attributes));
    }
}
