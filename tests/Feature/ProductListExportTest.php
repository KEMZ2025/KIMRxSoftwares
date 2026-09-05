<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Support\AccessControlBootstrapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductListExportTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_pdf_download_handles_a_multi_page_product_catalogue(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext('Large PDF Client');
        app(AccessControlBootstrapper::class)->ensureForUser($user);
        $categoryId = $this->createCategory($clientId, 'General');
        $unitId = $this->createUnit($clientId, 'Packet', 'PKT');

        foreach (range(1, 100) as $number) {
            $this->createProduct(
                $clientId,
                $branchId,
                $categoryId,
                $unitId,
                'Large Catalogue Product ' . str_pad((string) $number, 3, '0', STR_PAD_LEFT),
                true
            );
        }

        $response = $this->actingAs($user)->get(route('products.index', ['format' => 'pdf']));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $this->assertGreaterThan(1000, strlen($response->getContent()));
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
