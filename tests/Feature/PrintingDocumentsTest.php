<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\ProductBatch;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Support\AccessControlBootstrapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PrintingDocumentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_approving_sale_preserves_dispenser_and_records_approver(): void
    {
        [$dispenser, $clientId, $branchId] = $this->createUserContext();
        app(AccessControlBootstrapper::class)->ensureForUser($dispenser);
        $this->enableClientPrinting($clientId);

        $approver = User::factory()->create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'is_active' => true,
        ]);
        app(AccessControlBootstrapper::class)->ensureForUser($approver);
        $this->assignAdminRole($approver, $clientId);

        $supplierId = $this->createSupplier($clientId, 'Approval Supplier');
        $productId = $this->createProduct($clientId, $branchId, 'Approval Drug');

        $batch = ProductBatch::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'product_id' => $productId,
            'supplier_id' => $supplierId,
            'batch_number' => 'APP-001',
            'expiry_date' => now()->addYear()->toDateString(),
            'purchase_price' => 10,
            'retail_price' => 25,
            'wholesale_price' => 20,
            'quantity_received' => 20,
            'quantity_available' => 20,
            'reserved_quantity' => 2,
            'is_active' => true,
        ]);

        $sale = Sale::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'served_by' => $dispenser->id,
            'invoice_number' => 'APPROVE-PRINT-001',
            'sale_type' => 'retail',
            'status' => 'pending',
            'payment_type' => 'cash',
            'subtotal' => 50,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 50,
            'amount_paid' => 0,
            'amount_received' => 0,
            'balance_due' => 50,
            'sale_date' => now()->toDateString(),
            'is_active' => true,
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $productId,
            'product_batch_id' => $batch->id,
            'quantity' => 2,
            'purchase_price' => 10,
            'unit_price' => 25,
            'discount_amount' => 0,
            'total_amount' => 50,
        ]);

        $response = $this->actingAs($approver)->post(route('sales.approve', $sale), [
            'payment_type' => 'cash',
            'payment_method' => 'Cash',
            'amount_received' => 50,
        ]);

        $response->assertRedirect(route('sales.show', $sale));

        $this->assertDatabaseHas('sales', [
            'id' => $sale->id,
            'served_by' => $dispenser->id,
            'approved_by' => $approver->id,
            'status' => 'approved',
        ]);

        $this->assertNotNull(Sale::query()->findOrFail($sale->id)->approved_at);
    }

    public function test_sale_print_views_include_batch_and_approval_details_without_discount_line(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext();
        app(AccessControlBootstrapper::class)->ensureForUser($user);
        $this->enableClientPrinting($clientId);

        $approver = User::factory()->create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'is_active' => true,
        ]);
        app(AccessControlBootstrapper::class)->ensureForUser($approver);

        $supplierId = $this->createSupplier($clientId, 'Print Supplier');
        $productId = $this->createProduct($clientId, $branchId, 'Printed Drug');

        $batch = ProductBatch::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'product_id' => $productId,
            'supplier_id' => $supplierId,
            'batch_number' => 'PRINT-001',
            'expiry_date' => '2027-06-30',
            'purchase_price' => 10,
            'retail_price' => 20,
            'wholesale_price' => 18,
            'quantity_received' => 10,
            'quantity_available' => 8,
            'reserved_quantity' => 0,
            'is_active' => true,
        ]);

        $sale = Sale::create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'served_by' => $user->id,
            'approved_by' => $approver->id,
            'invoice_number' => 'PRINT-SALE-001',
            'receipt_number' => 'RCPT-PRINT-001',
            'sale_type' => 'retail',
            'status' => 'approved',
            'payment_type' => 'cash',
            'payment_method' => 'Cash',
            'subtotal' => 40,
            'discount_amount' => 5,
            'tax_amount' => 0,
            'total_amount' => 35,
            'amount_paid' => 35,
            'amount_received' => 40,
            'balance_due' => 0,
            'sale_date' => now()->toDateString(),
            'approved_at' => now(),
            'is_active' => true,
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $productId,
            'product_batch_id' => $batch->id,
            'quantity' => 2,
            'purchase_price' => 10,
            'unit_price' => 20,
            'discount_amount' => 5,
            'total_amount' => 35,
        ]);

        $a4 = $this->actingAs($user)->get(route('sales.print.a4', ['sale' => $sale->id, 'autoprint' => 0]));
        $a4->assertOk();
        $a4->assertSee('Sales Receipt');
        $a4->assertSee('PRINT-001');
        $a4->assertSee('30 Jun 2027');
        $a4->assertSee('Printed At');
        $a4->assertSee('Change');
        $a4->assertSee($user->name);
        $a4->assertSee($approver->name);
        $a4->assertDontSee('Discount');

        $pos = $this->actingAs($user)->get(route('sales.print.pos', ['sale' => $sale->id, 'autoprint' => 0]));
        $pos->assertOk();
        $pos->assertSee('PRINT-001');
        $pos->assertSee('Approved By');
        $pos->assertSee('Printed At');
        $pos->assertSee('Change');
        $pos->assertDontSee('Discount');
    }

    public function test_report_and_accounting_print_and_download_routes_render(): void
    {
        [$user] = $this->createUserContext();
        app(AccessControlBootstrapper::class)->ensureForUser($user);

        $this->actingAs($user)
            ->get(route('reports.print', ['autoprint' => 0]))
            ->assertOk()
            ->assertSee('Performance Reports');

        $this->actingAs($user)
            ->get(route('reports.download'))
            ->assertOk();

        $reportsPdf = $this->actingAs($user)->get(route('reports.download', ['format' => 'pdf']));
        $reportsPdf->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $reportsPdf->headers->get('content-type'));
        $this->assertStringContainsString('.pdf', (string) $reportsPdf->headers->get('content-disposition'));

        $this->actingAs($user)
            ->get(route('accounting.trial-balance.print', ['autoprint' => 0]))
            ->assertOk()
            ->assertSee('Trial Balance');

        $this->actingAs($user)
            ->get(route('accounting.trial-balance.download'))
            ->assertOk();

        $trialBalancePdf = $this->actingAs($user)->get(route('accounting.trial-balance.download', ['format' => 'pdf']));
        $trialBalancePdf->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $trialBalancePdf->headers->get('content-type'));
        $this->assertStringContainsString('.pdf', (string) $trialBalancePdf->headers->get('content-disposition'));
    }

    private function createUserContext(): array
    {
        $clientId = $this->createClient('Print Client');
        $branchId = $this->createBranch($clientId, 'Main Branch');

        $user = User::factory()->create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'is_active' => true,
        ]);

        return [$user, $clientId, $branchId];
    }

    private function enableClientPrinting(int $clientId): void
    {
        DB::table('client_settings')->updateOrInsert(
            ['client_id' => $clientId],
            [
                'business_mode' => 'both',
                'allow_small_receipt' => true,
                'allow_small_invoice' => true,
                'allow_large_receipt' => true,
                'allow_large_invoice' => true,
                'allow_small_proforma' => true,
                'allow_large_proforma' => true,
                'show_logo_on_print' => true,
                'show_branch_contacts_on_print' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    private function assignAdminRole(User $user, int $clientId): void
    {
        $role = Role::query()
            ->where('client_id', $clientId)
            ->where('name', 'Admin')
            ->first();

        if (!$role) {
            return;
        }

        DB::table('user_roles')->updateOrInsert([
            'user_id' => $user->id,
            'role_id' => $role->id,
        ], [
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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
