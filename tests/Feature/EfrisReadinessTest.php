<?php

namespace Tests\Feature;

use App\Models\ClientSetting;
use App\Models\EfrisDocument;
use App\Models\ProductBatch;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Support\AccessControlBootstrapper;
use App\Support\ClientFeatureAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EfrisReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_sale_creates_ready_efris_document_when_module_is_enabled(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext(true);
        app(AccessControlBootstrapper::class)->ensureForUser($user);

        $sale = $this->createPendingSaleWithBatch($user->id, $clientId, $branchId, 'EFRIS-READY-001');

        $response = $this->actingAs($user)->post(route('sales.approve', $sale), [
            'payment_type' => 'cash',
            'payment_method' => 'Cash',
            'amount_received' => 40,
        ]);

        $response->assertRedirect(route('sales.show', $sale));

        $sale->refresh();

        $this->assertDatabaseHas('efris_documents', [
            'sale_id' => $sale->id,
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'status' => 'ready',
            'next_action' => 'submit_sale',
            'document_kind' => 'receipt',
            'reference_number' => $sale->receipt_number,
        ]);

        $document = EfrisDocument::query()->where('sale_id', $sale->id)->firstOrFail();
        $this->assertSame('sandbox', $document->environment);
        $this->assertSame('VIP Pharmacy', $document->payload_snapshot['business_name']);
        $this->assertCount(1, $document->payload_snapshot['items']);
    }

    public function test_approved_sale_skips_efris_document_when_module_is_disabled(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext(false);
        app(AccessControlBootstrapper::class)->ensureForUser($user);

        $sale = $this->createPendingSaleWithBatch($user->id, $clientId, $branchId, 'EFRIS-OFF-001');

        $response = $this->actingAs($user)->post(route('sales.approve', $sale), [
            'payment_type' => 'cash',
            'payment_method' => 'Cash',
            'amount_received' => 40,
        ]);

        $response->assertRedirect(route('sales.show', $sale));

        $this->assertDatabaseMissing('efris_documents', [
            'sale_id' => $sale->id,
        ]);
    }

    public function test_cancelling_an_approved_sale_marks_efris_reversal_as_required(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext(true);
        app(AccessControlBootstrapper::class)->ensureForUser($user);

        $sale = $this->createPendingSaleWithBatch($user->id, $clientId, $branchId, 'EFRIS-REV-001');

        $this->actingAs($user)->post(route('sales.approve', $sale), [
            'payment_type' => 'cash',
            'payment_method' => 'Cash',
            'amount_received' => 40,
        ])->assertRedirect(route('sales.show', $sale));

        $response = $this->actingAs($user)->post(route('sales.cancel', $sale), [
            'cancel_reason' => 'Customer asked for immediate cancellation.',
        ]);

        $response->assertRedirect(route('sales.show', $sale));

        $this->assertDatabaseHas('efris_documents', [
            'sale_id' => $sale->id,
            'status' => 'ready',
            'next_action' => 'submit_reversal',
        ]);
    }

    public function test_manual_processing_accepts_ready_efris_documents(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext(true);
        app(AccessControlBootstrapper::class)->ensureForUser($user);

        $sale = $this->createPendingSaleWithBatch($user->id, $clientId, $branchId, 'EFRIS-PROC-001');

        $this->actingAs($user)->post(route('sales.approve', $sale), [
            'payment_type' => 'cash',
            'payment_method' => 'Cash',
            'amount_received' => 40,
        ])->assertRedirect(route('sales.show', $sale));

        $response = $this->actingAs($user)->post(route('settings.efris.process'), [
            'scope' => 'ready',
        ]);

        $response->assertRedirect(route('settings.index'));

        $this->assertDatabaseHas('efris_documents', [
            'sale_id' => $sale->id,
            'status' => 'accepted',
            'next_action' => 'complete',
            'attempt_count' => 1,
        ]);

        $document = EfrisDocument::query()->where('sale_id', $sale->id)->firstOrFail();
        $this->assertSame('simulate', $document->response_snapshot['transport']);
        $this->assertSame('submit_sale', $document->response_snapshot['action']);
    }

    public function test_manual_processing_marks_document_failed_when_required_efris_fields_are_missing(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext(true, [
            'efris_tin' => null,
            'tax_number' => null,
            'efris_branch_code' => null,
        ]);
        app(AccessControlBootstrapper::class)->ensureForUser($user);

        $sale = $this->createPendingSaleWithBatch($user->id, $clientId, $branchId, 'EFRIS-FAIL-001');

        $this->actingAs($user)->post(route('sales.approve', $sale), [
            'payment_type' => 'cash',
            'payment_method' => 'Cash',
            'amount_received' => 40,
        ])->assertRedirect(route('sales.show', $sale));

        $response = $this->actingAs($user)->post(route('settings.efris.process'), [
            'scope' => 'ready',
        ]);

        $response->assertRedirect(route('settings.index'));

        $document = EfrisDocument::query()->where('sale_id', $sale->id)->firstOrFail();

        $this->assertSame('failed', $document->status);
        $this->assertSame('submit_sale', $document->next_action);
        $this->assertStringContainsString('URA TIN', (string) $document->last_error_message);
        $this->assertStringContainsString('branch code', strtolower((string) $document->last_error_message));
    }

    public function test_manual_processing_accepts_queued_reversal_documents(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext(true);
        app(AccessControlBootstrapper::class)->ensureForUser($user);

        $sale = $this->createPendingSaleWithBatch($user->id, $clientId, $branchId, 'EFRIS-REV-PROC-001');

        $this->actingAs($user)->post(route('sales.approve', $sale), [
            'payment_type' => 'cash',
            'payment_method' => 'Cash',
            'amount_received' => 40,
        ])->assertRedirect(route('sales.show', $sale));

        $this->actingAs($user)->post(route('sales.cancel', $sale), [
            'cancel_reason' => 'Customer cancellation after approval.',
        ])->assertRedirect(route('sales.show', $sale));

        $response = $this->actingAs($user)->post(route('settings.efris.process'), [
            'scope' => 'ready',
        ]);

        $response->assertRedirect(route('settings.index'));

        $document = EfrisDocument::query()->where('sale_id', $sale->id)->firstOrFail();

        $this->assertSame('accepted', $document->status);
        $this->assertSame('complete', $document->next_action);
        $this->assertSame('submit_reversal', $document->response_snapshot['action']);
    }

    public function test_http_transport_processes_sale_document_through_auth_and_submission_endpoints(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext(true, [
            'efris_transport_mode' => 'http',
            'efris_auth_url' => 'https://ura-uat.example.com/auth',
            'efris_submission_url' => 'https://ura-uat.example.com/sales',
            'efris_reversal_url' => 'https://ura-uat.example.com/reversals',
            'efris_username' => 'vip-user',
            'efris_password' => 'vip-pass',
            'efris_client_id' => 'vip-client',
            'efris_client_secret' => 'vip-secret',
        ]);
        app(AccessControlBootstrapper::class)->ensureForUser($user);

        Http::fake([
            'https://ura-uat.example.com/auth' => Http::response([
                'access_token' => 'uat-token-123',
            ], 200),
            'https://ura-uat.example.com/sales' => Http::response([
                'message' => 'Accepted by UAT gateway.',
                'reference_number' => 'URA-REF-001',
            ], 200),
        ]);

        $sale = $this->createPendingSaleWithBatch($user->id, $clientId, $branchId, 'EFRIS-HTTP-001');

        $this->actingAs($user)->post(route('sales.approve', $sale), [
            'payment_type' => 'cash',
            'payment_method' => 'Cash',
            'amount_received' => 40,
        ])->assertRedirect(route('sales.show', $sale));

        $this->actingAs($user)->post(route('settings.efris.process'), [
            'scope' => 'ready',
        ])->assertRedirect(route('settings.index'));

        $document = EfrisDocument::query()->where('sale_id', $sale->id)->firstOrFail();

        $this->assertSame('accepted', $document->status);
        $this->assertSame('complete', $document->next_action);
        $this->assertSame('http', $document->response_snapshot['transport']);
        $this->assertSame('URA-REF-001', $document->response_snapshot['tracking_number']);

        Http::assertSentCount(2);
        Http::assertSent(function ($request) {
            return $request->url() === 'https://ura-uat.example.com/auth'
                && $request['username'] === 'vip-user'
                && $request['client_id'] === 'vip-client';
        });
        Http::assertSent(function ($request) {
            return $request->url() === 'https://ura-uat.example.com/sales'
                && $request->hasHeader('Authorization', 'Bearer uat-token-123')
                && $request['connector_action'] === 'submit_sale';
        });
    }

    public function test_http_transport_processes_reversal_document_through_reversal_endpoint(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext(true, [
            'efris_transport_mode' => 'http',
            'efris_auth_url' => 'https://ura-uat.example.com/auth',
            'efris_submission_url' => 'https://ura-uat.example.com/sales',
            'efris_reversal_url' => 'https://ura-uat.example.com/reversals',
            'efris_username' => 'vip-user',
            'efris_password' => 'vip-pass',
            'efris_client_id' => 'vip-client',
            'efris_client_secret' => 'vip-secret',
        ]);
        app(AccessControlBootstrapper::class)->ensureForUser($user);

        Http::fake([
            'https://ura-uat.example.com/auth' => Http::response([
                'access_token' => 'uat-token-123',
            ], 200),
            'https://ura-uat.example.com/reversals' => Http::response([
                'message' => 'Reversal accepted.',
                'tracking_number' => 'URA-REV-001',
            ], 200),
        ]);

        $sale = $this->createPendingSaleWithBatch($user->id, $clientId, $branchId, 'EFRIS-HTTP-REV-001');

        $this->actingAs($user)->post(route('sales.approve', $sale), [
            'payment_type' => 'cash',
            'payment_method' => 'Cash',
            'amount_received' => 40,
        ])->assertRedirect(route('sales.show', $sale));

        $this->actingAs($user)->post(route('sales.cancel', $sale), [
            'cancel_reason' => 'Testing the URA reversal connector.',
        ])->assertRedirect(route('sales.show', $sale));

        $this->actingAs($user)->post(route('settings.efris.process'), [
            'scope' => 'ready',
        ])->assertRedirect(route('settings.index'));

        $document = EfrisDocument::query()->where('sale_id', $sale->id)->firstOrFail();

        $this->assertSame('accepted', $document->status);
        $this->assertSame('complete', $document->next_action);
        $this->assertSame('submit_reversal', $document->response_snapshot['action']);
        $this->assertSame('URA-REV-001', $document->response_snapshot['tracking_number']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://ura-uat.example.com/reversals'
                && $request['connector_action'] === 'submit_reversal';
        });
    }

    private function createUserContext(bool $efrisEnabled, array $settingOverrides = []): array
    {
        $clientId = DB::table('clients')->insertGetId([
            'name' => 'VIP Pharmacy',
            'business_mode' => 'both',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $branchId = DB::table('branches')->insertGetId([
            'client_id' => $clientId,
            'name' => 'Main Branch',
            'code' => 'MAIN',
            'business_mode' => 'inherit',
            'is_main' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        ClientSetting::create(array_replace(
            ['client_id' => $clientId, 'business_mode' => 'both'],
            ClientFeatureAccess::defaultSettingValues(),
            [
                'efris_enabled' => $efrisEnabled,
                'efris_environment' => 'sandbox',
                'efris_transport_mode' => 'simulate',
                'efris_tin' => '101202303',
                'efris_legal_name' => 'VIP Pharmacy Limited',
                'efris_business_name' => 'VIP Pharmacy',
                'efris_branch_code' => 'VIP-MAIN',
                'efris_device_serial' => 'DEVICE-001',
                'currency_symbol' => 'UGX',
                'tax_label' => 'TIN',
                'tax_number' => '101202303',
            ],
            $settingOverrides
        ));

        $user = User::factory()->create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'is_active' => true,
        ]);

        return [$user, $clientId, $branchId];
    }

    private function createPendingSaleWithBatch(int $userId, int $clientId, int $branchId, string $invoiceNumber): Sale
    {
        $supplierId = DB::table('suppliers')->insertGetId([
            'client_id' => $clientId,
            'name' => 'EFRIS Supplier',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $productId = $this->createProduct($clientId, $branchId, 'Panadol Tabs');

        $batch = ProductBatch::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'product_id' => $productId,
            'supplier_id' => $supplierId,
            'batch_number' => $invoiceNumber . '-BATCH',
            'purchase_price' => 10,
            'retail_price' => 20,
            'wholesale_price' => 18,
            'expiry_date' => now()->addMonths(6)->toDateString(),
            'quantity_received' => 10,
            'quantity_available' => 10,
            'reserved_quantity' => 2,
            'is_active' => true,
        ]);

        $sale = Sale::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'customer_id' => null,
            'served_by' => $userId,
            'invoice_number' => $invoiceNumber,
            'receipt_number' => null,
            'sale_type' => 'retail',
            'status' => 'pending',
            'payment_type' => 'cash',
            'payment_method' => null,
            'subtotal' => 40,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 40,
            'amount_paid' => 0,
            'amount_received' => 0,
            'balance_due' => 40,
            'sale_date' => now()->toDateString(),
            'notes' => 'EFRIS readiness test sale.',
            'is_active' => true,
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $productId,
            'product_batch_id' => $batch->id,
            'quantity' => 2,
            'purchase_price' => 10,
            'unit_price' => 20,
            'discount_amount' => 0,
            'total_amount' => 40,
        ]);

        return $sale;
    }

    private function createProduct(int $clientId, int $branchId, string $name): int
    {
        $data = [
            'name' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('products', 'client_id')) {
            $data['client_id'] = $clientId;
        }

        if (Schema::hasColumn('products', 'branch_id')) {
            $data['branch_id'] = $branchId;
        }

        if (Schema::hasColumn('products', 'purchase_price')) {
            $data['purchase_price'] = 10;
        }

        if (Schema::hasColumn('products', 'retail_price')) {
            $data['retail_price'] = 20;
        }

        if (Schema::hasColumn('products', 'wholesale_price')) {
            $data['wholesale_price'] = 18;
        }

        if (Schema::hasColumn('products', 'track_batch')) {
            $data['track_batch'] = true;
        }

        if (Schema::hasColumn('products', 'track_expiry')) {
            $data['track_expiry'] = true;
        }

        if (Schema::hasColumn('products', 'is_active')) {
            $data['is_active'] = true;
        }

        return DB::table('products')->insertGetId($data);
    }
}
