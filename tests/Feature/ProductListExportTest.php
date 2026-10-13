<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\User;
use App\Support\AccessControlBootstrapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductListExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_preserves_each_tabs_list_page_and_search_after_save_or_back(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext('Return Client');
        app(AccessControlBootstrapper::class)->ensureForUser($user);
        $categoryId = $this->createCategory($clientId, 'Tablets');
        $unitId = $this->createUnit($clientId, 'Packet', 'PKT');
        for ($i = 1; $i <= 11; $i++) {
            $product = $this->createProduct($clientId, $branchId, $categoryId, $unitId, sprintf('Return %02d', $i), true);
        }
        $query = ['page' => 2, 'search' => 'Return'];
        $editUrl = route('products.edit', array_merge(['product' => $product->id], $query));
        $this->actingAs($user)->get(route('products.index', $query))->assertOk()->assertSee($editUrl);
        $updateUrl = route('products.update', array_merge(['product' => $product->id], $query));
        $this->get($editUrl)->assertOk()->assertSee($updateUrl)->assertSee(route('products.index', $query));
        // Opening another tab must not overwrite the first tab's return destination.
        $this->get(route('products.edit', ['product' => $product->id, 'page' => 10]))->assertOk();
        $payload = ['name' => $product->name, 'category_id' => $categoryId, 'unit_id' => $unitId, 'retail_price' => 500, 'wholesale_price' => 400, 'is_active' => 1];
        $this->from($editUrl)->put($updateUrl, array_merge($payload, ['name' => '']))->assertSessionHasErrors('name')->assertRedirect($editUrl);
        $this->put($updateUrl, $payload)->assertRedirect(route('products.index', $query));
        $this->assertEquals(500, $product->fresh()->retail_price);
        $this->put(route('products.update', ['product' => $product->id, 'page' => 10]), $payload)->assertRedirect(route('products.index', ['page' => 10]));
        $this->put(route('products.update', $product), $payload)->assertRedirect(route('products.index'));
        $this->put(route('products.update', ['product' => $product->id, 'page' => -1, 'return_url' => 'https://example.com', 'format' => 'pdf']), $payload)->assertRedirect(route('products.index'));
    }

    public function test_product_pages_are_alphabetical_and_stable_for_shared_import_timestamps(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext('Alphabetical Client');
        app(AccessControlBootstrapper::class)->ensureForUser($user);
        $categoryId = $this->createCategory($clientId, 'Tablets');
        $unitId = $this->createUnit($clientId, 'Packet', 'PKT');
        $expected = [];
        foreach (['Zinc', 'aspirin', ' Beta', 'beta', 'Amoxicillin', 'Cetirizine', 'Doxycycline', 'Erythromycin', 'Fluconazole', 'Ibuprofen', 'Metformin', 'Paracetamol', 'Warfarin'] as $name) {
            $product = $this->createProduct($clientId, $branchId, $categoryId, $unitId, $name, true);
            $product->update(['created_at' => '2026-09-01 00:00:00']);
            $expected[] = ['id' => $product->id, 'name' => strtolower(trim($name))];
        }
        // Identical names span page boundaries and must use a unique tie-breaker.
        for ($i = 0; $i < 12; $i++) {
            $product = $this->createProduct($clientId, $branchId, $categoryId, $unitId, 'Beta', true);
            $product->update(['created_at' => '2026-09-01 00:00:00']);
            $expected[] = ['id' => $product->id, 'name' => 'beta'];
        }
        usort($expected, fn ($a, $b) => strcmp($a['name'], $b['name']) ?: ($a['id'] <=> $b['id']));
        $actual = [];
        for ($page = 1; $page <= 3; $page++) {
            $response = $this->actingAs($user)->get(route('products.index', ['page' => $page]));
            $response->assertOk();
            $products = $response->viewData('products');
            $this->assertSame(25, $products->total());
            $ids = $products->getCollection()->pluck('id')->all();
            $this->assertSame(array_slice(array_column($expected, 'id'), ($page - 1) * 10, 10), $ids);
            $actual = array_merge($actual, $ids);
        }
        $this->assertCount(25, array_unique($actual));
        $this->assertSame(array_column($expected, 'id'), $actual);
        $this->assertDatabaseCount('products', 25);
    }

    public function test_product_search_stays_alphabetical_and_excludes_other_clients(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext('Search Client');
        app(AccessControlBootstrapper::class)->ensureForUser($user);
        $categoryId = $this->createCategory($clientId, 'Tablets');
        $unitId = $this->createUnit($clientId, 'Packet', 'PKT');
        $zinc = $this->createProduct($clientId, $branchId, $categoryId, $unitId, 'Zinc tabs', true);
        $aspirin = $this->createProduct($clientId, $branchId, $categoryId, $unitId, 'Aspirin tabs', true);
        $this->createProduct($clientId, $branchId, $categoryId, $unitId, 'Unrelated', true);
        [, $otherClientId, $otherBranchId] = $this->createUserContext('Other Search Client');
        $this->createProduct($otherClientId, $otherBranchId, $this->createCategory($otherClientId, 'Other'), $this->createUnit($otherClientId, 'Box', 'BOX'), 'Aspirin tabs', true);
        $response = $this->actingAs($user)->get(route('products.index', ['search' => 'tabs']));
        $response->assertOk();
        $this->assertSame([$aspirin->id, $zinc->id], $response->viewData('products')->getCollection()->pluck('id')->all());
        $response = $this->get(route('products.index', ['search' => 'Aspirin']));
        $response->assertOk();
        $this->assertSame([$aspirin->id], $response->viewData('products')->getCollection()->pluck('id')->all());
    }

    public function test_csv_download_contains_the_entire_client_product_list_without_prices(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext('Export Client');
        app(AccessControlBootstrapper::class)->ensureForUser($user);
        $categoryId = $this->createCategory($clientId, 'Tablets');
        $unitId = $this->createUnit($clientId, 'Packet', 'PKT');

        $this->createProduct($clientId, $branchId, $categoryId, $unitId, 'Export Alpha', true);
        $this->createProduct($clientId, $branchId, $categoryId, $unitId, 'Export Inactive', false);

        [, $otherClientId, $otherBranchId] = $this->createUserContext('Other Client');
        $otherCategoryId = $this->createCategory($otherClientId, 'Other');
        $otherUnitId = $this->createUnit($otherClientId, 'Box', 'BOX');
        $this->createProduct($otherClientId, $otherBranchId, $otherCategoryId, $otherUnitId, 'Private Other Product', true);

        $response = $this->actingAs($user)->get(route('products.index', [
            'format' => 'csv',
            'search' => 'no-match',
        ]));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));

        $csv = $response->streamedContent();
        $this->assertStringContainsString('Export Alpha', $csv);
        $this->assertStringContainsString('Export Inactive', $csv);
        $this->assertStringContainsString('Inactive', $csv);
        $this->assertStringNotContainsString('Private Other Product', $csv);
        $this->assertStringNotContainsString('Purchase Price', $csv);
        $this->assertStringNotContainsString('Retail Price', $csv);
        $this->assertStringNotContainsString('Wholesale Price', $csv);
        $this->assertStringNotContainsString('987654.32', $csv);
    }

    public function test_pdf_download_is_available_and_product_screen_shows_both_download_actions(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext('PDF Client');
        app(AccessControlBootstrapper::class)->ensureForUser($user);
        $categoryId = $this->createCategory($clientId, 'Capsules');
        $unitId = $this->createUnit($clientId, 'Box', 'BOX');
        $this->createProduct($clientId, $branchId, $categoryId, $unitId, 'PDF Product', true);

        $this->actingAs($user)
            ->get(route('products.index'))
            ->assertOk()
            ->assertSee('Download PDF')
            ->assertSee('Download CSV');

        $response = $this->actingAs($user)->get(route('products.index', ['format' => 'pdf']));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('product-list-', (string) $response->headers->get('content-disposition'));
    }

    public function test_product_list_and_sources_show_distinct_configured_selling_prices(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext('Price Display Client');
        app(AccessControlBootstrapper::class)->ensureForUser($user);
        $categoryId = $this->createCategory($clientId, 'Tablets');
        $unitId = $this->createUnit($clientId, 'Packet', 'PKT');
        $product = $this->createProduct(
            $clientId,
            $branchId,
            $categoryId,
            $unitId,
            'Split Price Product',
            true
        );

        ProductBatch::query()->create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'product_id' => $product->id,
            'batch_number' => 'COPIED-PRICE-001',
            'expiry_date' => now()->addYear()->toDateString(),
            'purchase_price' => 400,
            'retail_price' => 500,
            'wholesale_price' => 500,
            'quantity_received' => 20,
            'quantity_available' => 20,
            'reserved_quantity' => 0,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('products.index', ['search' => 'Split Price Product']))
            ->assertOk()
            ->assertSeeInOrder(['1,234,567.89', '1,111,111.11']);

        $this->actingAs($user)
            ->get(route('products.sources', $product))
            ->assertOk()
            ->assertSeeInOrder(['1,234,567.89', '1,111,111.11']);
    }

    public function test_pdf_download_handles_a_vip_sized_product_catalogue_within_256_mb(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext('Large PDF Client');
        app(AccessControlBootstrapper::class)->ensureForUser($user);
        $categoryId = $this->createCategory($clientId, 'General');
        $unitId = $this->createUnit($clientId, 'Packet', 'PKT');

        $now = now();
        foreach (array_chunk(range(1, 1800), 200) as $numbers) {
            DB::table('products')->insert(array_map(fn (int $number) => [
                'client_id' => $clientId,
                'branch_id' => $branchId,
                'category_id' => $categoryId,
                'unit_id' => $unitId,
                'name' => 'Large Catalogue Product ' . str_pad((string) $number, 4, '0', STR_PAD_LEFT),
                'strength' => '500mg',
                'barcode' => 'LARGE-' . $number,
                'purchase_price' => 987654.32,
                'retail_price' => 1234567.89,
                'wholesale_price' => 1111111.11,
                'track_batch' => true,
                'track_expiry' => true,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ], $numbers));
        }

        $originalMemoryLimit = ini_get('memory_limit');
        ini_set('memory_limit', '256M');

        try {
            $response = $this->actingAs($user)->get(route('products.index', ['format' => 'pdf']));
        } finally {
            ini_set('memory_limit', (string) $originalMemoryLimit);
        }

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $this->assertGreaterThan(5000, strlen($response->getContent()));
    }

    private function createUserContext(string $clientName): array
    {
        $clientId = DB::table('clients')->insertGetId([
            'name' => $clientName,
            'business_mode' => 'both',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $branchId = DB::table('branches')->insertGetId([
            'client_id' => $clientId,
            'name' => 'Main Branch',
            'code' => 'MAIN',
            'is_main' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $user = User::factory()->create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'is_active' => true,
        ]);

        return [$user, $clientId, $branchId];
    }

    private function createCategory(int $clientId, string $name): int
    {
        return DB::table('categories')->insertGetId([
            'client_id' => $clientId,
            'name' => $name,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createUnit(int $clientId, string $name, string $shortName): int
    {
        return DB::table('units')->insertGetId([
            'client_id' => $clientId,
            'name' => $name,
            'short_name' => $shortName,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createProduct(
        int $clientId,
        int $branchId,
        int $categoryId,
        int $unitId,
        string $name,
        bool $active
    ): Product {
        return Product::query()->create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'category_id' => $categoryId,
            'unit_id' => $unitId,
            'name' => $name,
            'strength' => '500mg',
            'barcode' => 'BAR-' . $clientId . '-' . $name,
            'purchase_price' => 987654.32,
            'retail_price' => 1234567.89,
            'wholesale_price' => 1111111.11,
            'track_batch' => true,
            'track_expiry' => true,
            'is_active' => $active,
        ]);
    }
}
