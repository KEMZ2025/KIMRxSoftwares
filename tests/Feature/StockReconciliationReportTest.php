<?php

namespace Tests\Feature;

use App\Models\ProductBatch;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockAdjustment;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\AccessControlBootstrapper;
use App\Support\StockReconciliationReport;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StockReconciliationReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconciles_imports_receipts_sales_and_backdated_adjustments_without_double_counting(): void
    {
        $user = $this->context();
        $batch = $this->batch($user, 'Imported', 133);
        $this->movement($batch, 'import_opening_in', 100, 0, '2026-09-01 15:00:00');
        $this->movement($batch, 'purchase_in', 50, 0, '2026-09-10 10:00:00');
        $this->movement($batch, 'purchase_in', 20, 0, '2026-10-01 00:00:00');
        $this->sale($user, $batch, 30, '2026-09-30', 'approved');
        $this->sale($user, $batch, 10, '2026-10-01', 'approved');
        foreach (['pending', 'cancelled', 'proforma'] as $status) {
            $this->sale($user, $batch, 70, '2026-09-20', $status);
        }
        foreach (['increase' => 8, 'decrease' => 5] as $direction => $qty) {
            $adjustment = StockAdjustment::create([
                'client_id' => $user->client_id, 'branch_id' => $user->branch_id,
                'product_id' => $batch->product_id, 'product_batch_id' => $batch->id,
                'direction' => $direction, 'reason' => 'other', 'quantity' => $qty,
                'adjustment_date' => '2026-09-25 10:00:00', 'adjusted_by' => $user->id,
            ]);
            $movement = $this->movement($batch, 'adjustment_' . ($direction === 'increase' ? 'in' : 'out'), $direction === 'increase' ? $qty : 0, $direction === 'decrease' ? $qty : 0, '2026-10-05');
            $movement->update(['reference_type' => 'stock_adjustment', 'reference_id' => $adjustment->id]);
        }
        $otherUser = $this->context();
        $otherBatch = $this->batch($otherUser, 'Other tenant', 900);
        $this->movement($otherBatch, 'import_opening_in', 900, 0, '2026-08-31');
        $report = $this->report($user);
        $row = $report['rows']->sole();
        $this->assertSame(100.0, $row['opening']);
        $this->assertSame(58.0, $row['incoming']);
        $this->assertSame(30.0, $row['sold']);
        $this->assertSame(5.0, $row['other_out']);
        $this->assertSame(123.0, $row['closing']);
        $this->assertSame(1000.0, $row['opening_value']);
        $this->assertSame(1230.0, $row['closing_value']);
        $this->assertSame('', $row['issues']);
        $this->assertSame(133.0, (float) $batch->fresh()->quantity_available);
        $this->assertSame(4.0, (float) $batch->fresh()->reserved_quantity);
    }

    public function test_later_imports_only_move_to_opening_when_explicitly_selected_and_inactive_batches_remain(): void
    {
        $user = $this->context();
        $batch = $this->batch($user, 'Exhausted', 0);
        $batch->update(['is_active' => false, 'reserved_quantity' => 0]);
        $this->movement($batch, 'import_opening_in', 12, 0, '2026-09-03');
        $this->sale($user, $batch, 12, '2026-09-10', 'approved');
        $row = $this->report($user)['rows']->sole();
        $this->assertSame(0.0, $row['opening']);
        $this->assertSame(12.0, $row['incoming']);
        $row = $this->report($user, '2026-09-03')['rows']->sole();
        $this->assertSame(12.0, $row['opening']);
        $this->assertSame(0.0, $row['incoming']);
        $this->assertSame(0.0, $row['closing']);
    }

    public function test_flags_missing_history_zero_costs_and_unlinked_records_instead_of_inventing_stock(): void
    {
        $user = $this->context();
        $batch = $this->batch($user, 'Missing opening', 25);
        $this->movement($batch, 'purchase_in', 0, 0, '2026-09-02')->update(['product_batch_id' => null]);
        $zero = $this->batch($user, 'Zero cost', 3);
        $zero->update(['purchase_price' => 0]);
        $this->movement($zero, 'import_opening_in', 3, 0, '2026-08-31');
        $report = $this->report($user);
        $this->assertSame(2, $report['totals']['review_count']);
        $this->assertSame(1, $report['unlinked']);
        $this->assertStringContainsString('history missing', $report['rows']->firstWhere('batch_id', $batch->id)['issues']);
        $this->assertSame(0.0, $report['totals']['opening_value']);
    }

    public function test_prior_sales_reduce_opening_and_another_branch_is_excluded(): void
    {
        $user = $this->context();
        $batch = $this->batch($user, 'Prior activity', 25);
        $this->movement($batch, 'import_opening_in', 40, 0, '2026-08-30');
        $this->sale($user, $batch, 5, '2026-08-31', 'approved');
        $this->sale($user, $batch, 10, '2026-09-01', 'approved');
        $branch = DB::table('branches')->insertGetId(['client_id' => $user->client_id, 'name' => 'Other branch', 'code' => 'OTHER', 'is_main' => false, 'is_active' => true]);
        $other = User::factory()->create(['client_id' => $user->client_id, 'branch_id' => $branch, 'is_active' => true]);
        $otherBatch = $this->batch($other, 'Other branch product', 500);
        $this->movement($otherBatch, 'import_opening_in', 500, 0, '2026-08-30');
        $report = $this->report($user);
        $row = $report['rows']->sole();
        $this->assertSame(35.0, $row['opening']);
        $this->assertSame(25.0, $row['closing']);
        $this->assertSame(350.0, $report['totals']['opening_value']);
        $this->assertSame(250.0, $report['totals']['closing_value']);
        $this->assertSame(0, $report['unlinked']);
    }

    public function test_screen_print_csv_and_pdf_use_the_same_scoped_report(): void
    {
        $user = $this->context();
        app(AccessControlBootstrapper::class)->ensureForUser($user);
        $batch = $this->batch($user, 'September medicine', 12);
        $this->movement($batch, 'import_opening_in', 12, 0, '2026-09-01');
        $params = ['report' => 'stock_reconciliation', 'period' => 'custom', 'date_from' => '2026-09-01', 'date_to' => '2026-09-30'];
        foreach (['reports.index', 'reports.print'] as $route) {
            $this->actingAs($user)->get(route($route, $params))->assertOk()
                ->assertSee('September medicine')->assertSee('120.00')->assertSee('current recorded batch purchase costs');
        }
        $csv = $this->actingAs($user)->get(route('reports.download', $params + ['format' => 'csv']))->assertOk()->streamedContent();
        $this->assertStringContainsString('September medicine', $csv);
        $this->assertStringContainsString('Opening stock value', $csv);
        $pdf = $this->actingAs($user)->get(route('reports.download', $params + ['format' => 'pdf']))->assertOk();
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $this->actingAs($user)->get(route('reports.index', $params + ['opening_imports_through' => '2026-10-01']))->assertStatus(422);
    }

    private function context(): User
    {
        $client = DB::table('clients')->insertGetId(['name' => 'Reconciliation client', 'business_mode' => 'both', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $branch = DB::table('branches')->insertGetId(['client_id' => $client, 'name' => 'Main', 'code' => 'MAIN', 'is_main' => true, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        return User::factory()->create(['client_id' => $client, 'branch_id' => $branch, 'is_active' => true]);
    }

    private function batch(User $user, string $name, float $available): ProductBatch
    {
        $product = DB::table('products')->insertGetId(['client_id' => $user->client_id, 'branch_id' => $user->branch_id, 'name' => $name, 'purchase_price' => 999, 'retail_price' => 1200, 'wholesale_price' => 1100, 'created_at' => now(), 'updated_at' => now()]);
        return ProductBatch::create(['client_id' => $user->client_id, 'branch_id' => $user->branch_id, 'product_id' => $product, 'batch_number' => 'B-' . $product, 'purchase_price' => 10, 'quantity_received' => $available, 'quantity_available' => $available, 'reserved_quantity' => 4, 'is_active' => true]);
    }

    private function movement(ProductBatch $batch, string $type, float $in, float $out, string $date): StockMovement
    {
        $movement = new StockMovement(['client_id' => $batch->client_id, 'branch_id' => $batch->branch_id, 'product_id' => $batch->product_id, 'product_batch_id' => $batch->id, 'movement_type' => $type, 'quantity_in' => $in, 'quantity_out' => $out, 'balance_after' => 0]);
        $movement->created_at = Carbon::parse($date);
        $movement->save();
        return $movement;
    }

    private function sale(User $user, ProductBatch $batch, float $qty, string $date, string $status): void
    {
        $sale = Sale::create(['client_id' => $user->client_id, 'branch_id' => $user->branch_id, 'served_by' => $user->id, 'invoice_number' => uniqid('REC-'), 'sale_type' => 'retail', 'status' => $status, 'payment_type' => 'cash', 'sale_date' => $date, 'is_active' => true]);
        SaleItem::create(['sale_id' => $sale->id, 'product_id' => $batch->product_id, 'product_batch_id' => $batch->id, 'quantity' => $qty, 'purchase_price' => 10, 'unit_price' => 20, 'total_amount' => $qty * 20]);
    }

    private function report(User $user, string $cutoff = '2026-09-01'): array
    {
        return app(StockReconciliationReport::class)->build($user, Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30'), Carbon::parse($cutoff));
    }
}
