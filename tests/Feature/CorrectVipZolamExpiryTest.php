<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ProductBatch;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Support\AuditTrail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CorrectVipZolamExpiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_then_correction_changes_only_the_target_date_and_is_repeatable(): void
    {
        $batch = $this->batch();
        $other = $this->batch('ELOHIM DRUGSHOP');
        $before = $batch->getAttributes();
        $this->artisan('stock:correct-vip-zolam-expiry')->assertSuccessful();
        $this->assertSame('2026-10-01', $batch->fresh()->expiry_date->toDateString());
        $this->assertDatabaseCount('audit_logs', 0);
        $this->execute()->assertSuccessful();
        $this->assertSame('2026-10-30', $batch->fresh()->expiry_date->toDateString());
        foreach (['quantity_received', 'quantity_available', 'reserved_quantity', 'purchase_price', 'retail_price', 'wholesale_price', 'is_active'] as $key) {
            $this->assertEquals($before[$key], $batch->fresh()->getAttributes()[$key]);
        }
        $this->assertSame('2026-10-01', $other->fresh()->expiry_date->toDateString());
        $log = AuditLog::sole();
        $this->assertSame('2026-10-01', $log->old_values['expiry_date']);
        $this->assertSame('2026-10-30', $log->new_values['expiry_date']);
        $this->execute()->assertSuccessful();
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_mismatched_date_ambiguous_batch_and_missing_confirmation_are_rejected(): void
    {
        $batch = $this->batch();
        $this->artisan('stock:correct-vip-zolam-expiry', ['--execute' => true])->assertFailed();
        $batch->update(['expiry_date' => '2026-09-30']);
        $this->execute()->assertFailed();
        $this->assertSame('2026-09-30', $batch->fresh()->expiry_date->toDateString());
        $batch->update(['expiry_date' => '2026-10-01']);
        $batch->replicate()->save();
        $this->execute()->assertFailed();
        $this->assertSame('2026-10-01', $batch->fresh()->expiry_date->toDateString());
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_linked_purchase_expiry_is_corrected_without_changing_costs(): void
    {
        $batch = $this->batch();
        $supplier = DB::table('suppliers')->insertGetId(['client_id' => $batch->client_id, 'name' => 'Supplier']);
        $purchase = Purchase::create(['client_id' => $batch->client_id, 'branch_id' => $batch->branch_id, 'supplier_id' => $supplier, 'invoice_number' => 'ZOLAM-TEST', 'purchase_date' => '2026-09-01', 'total_amount' => 68970]);
        $item = PurchaseItem::create(['purchase_id' => $purchase->id, 'product_id' => $batch->product_id, 'batch_number' => '243400', 'expiry_date' => '2026-10-01', 'quantity' => 121, 'unit_cost' => 570, 'total_cost' => 68970]);
        $batch->update(['purchase_item_id' => $item->id]);
        $this->execute()->assertSuccessful();
        $this->assertSame('2026-10-30', $item->fresh()->expiry_date->toDateString());
        $this->assertEquals(68970, $item->fresh()->total_cost);
        $this->assertEquals(68970, $purchase->fresh()->total_amount);
    }

    public function test_audit_failure_rolls_back_the_date_change(): void
    {
        $batch = $this->batch();
        $this->mock(AuditTrail::class)->shouldReceive('record')->once()->andThrow(new \RuntimeException('Audit unavailable'));
        $this->execute()->assertFailed();
        $this->assertSame('2026-10-01', $batch->fresh()->expiry_date->toDateString());
    }

    private function execute()
    {
        return $this->artisan('stock:correct-vip-zolam-expiry', ['--execute' => true, '--confirm' => 'VIP PHARMACY']);
    }

    private function batch(string $clientName = 'VIP PHARMACY'): ProductBatch
    {
        $client = DB::table('clients')->insertGetId(['name' => $clientName, 'business_mode' => 'both', 'is_active' => true]);
        $branch = DB::table('branches')->insertGetId(['client_id' => $client, 'name' => 'Main Branch', 'code' => 'MAIN', 'is_main' => true, 'is_active' => true]);
        $product = DB::table('products')->insertGetId(['client_id' => $client, 'branch_id' => $branch, 'name' => 'Zolam 0.25mg tabs 30s']);
        return ProductBatch::create(['client_id' => $client, 'branch_id' => $branch, 'product_id' => $product, 'batch_number' => '243400', 'expiry_date' => '2026-10-01', 'quantity_received' => 121, 'quantity_available' => 111, 'reserved_quantity' => 2, 'purchase_price' => 570, 'retail_price' => 700, 'wholesale_price' => 625, 'is_active' => true]);
    }
}
