<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Client;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Support\AuditTrail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class CorrectVipZolamExpiry extends Command
{
    protected $signature = 'stock:correct-vip-zolam-expiry
        {--execute : Apply the verified packaging-date correction}
        {--confirm= : Exact client name required to execute}';

    protected $description = 'Correct VIP Main Branch Zolam batch 243400 expiry to the confirmed packaging date of 30 October 2026';

    public function handle(AuditTrail $audit): int
    {
        if ($this->option('execute') && $this->option('confirm') !== 'VIP PHARMACY') {
            $this->error('Pass --confirm="VIP PHARMACY" to execute. Nothing was changed.');
            return self::FAILURE;
        }

        try {
            return DB::transaction(function () use ($audit): int {
                $client = $this->exactlyOne(Client::query()->whereRaw('LOWER(TRIM(name)) = ?', ['vip pharmacy'])->lockForUpdate()->get(), 'VIP PHARMACY client');
                $branch = $this->exactlyOne(Branch::query()->where('client_id', $client->id)->whereRaw('LOWER(TRIM(name)) = ?', ['main branch'])->lockForUpdate()->get(), 'VIP Main Branch');
                $product = $this->exactlyOne(Product::query()->where('client_id', $client->id)->where('branch_id', $branch->id)
                    ->whereRaw('LOWER(TRIM(name)) = ?', ['zolam 0.25mg tabs 30s'])->lockForUpdate()->get(), 'Zolam 0.25mg tabs 30s product');
                $batch = $this->exactlyOne(ProductBatch::query()->where('client_id', $client->id)->where('branch_id', $branch->id)
                    ->where('product_id', $product->id)->where('batch_number', '243400')->lockForUpdate()->get(), 'Zolam batch 243400');

                $oldDate = $batch->expiry_date?->toDateString();
                $this->checkDate($oldDate, 'Batch');
                $item = null;
                if ($batch->purchase_item_id) {
                    $item = PurchaseItem::query()->whereKey($batch->purchase_item_id)->lockForUpdate()->first();
                    $purchase = $item ? Purchase::query()->whereKey($item->purchase_id)->where('client_id', $client->id)->where('branch_id', $branch->id)->first() : null;
                    if (!$item || !$purchase || (int) $item->product_id !== (int) $product->id || $item->batch_number !== '243400') {
                        throw new RuntimeException('Linked purchase item does not match this VIP product and batch.');
                    }
                    if (ProductBatch::query()->where('purchase_item_id', $item->id)->where('id', '!=', $batch->id)->exists()) {
                        throw new RuntimeException('The purchase item is shared with another batch; manual review is required.');
                    }
                    $this->checkDate($item->expiry_date?->toDateString(), 'Purchase item');
                }

                $this->table(['Client / Branch', 'Product', 'Batch ID / Number', 'Current expiry', 'Correct expiry'], [[
                    'VIP PHARMACY / Main Branch', $product->name, $batch->id . ' / 243400', $oldDate, '2026-10-30',
                ]]);
                $this->line('Basis: user confirmed physical packaging expiry as 30/10/2026.');
                $this->line('Quantities, reservations, prices, payments and expiry safeguards stay unchanged.');
                if (!$this->option('execute')) {
                    $this->info('Preview only. No records were changed.');
                    return self::SUCCESS;
                }
                if ($oldDate === '2026-10-30' && (!$item || $item->expiry_date?->toDateString() === '2026-10-30')) {
                    $this->info('Already corrected. No records were changed.');
                    return self::SUCCESS;
                }

                $oldItemDate = $item?->expiry_date?->toDateString();
                $batch->update(['expiry_date' => '2026-10-30']);
                if ($item) {
                    $item->update(['expiry_date' => '2026-10-30']);
                }
                // Audit is part of the transaction: a logging failure rolls back the correction.
                $audit->record(null, 'stock.expiry_corrected', 'Stock', 'Correct Expiry Date',
                    'Corrected VIP Zolam batch 243400 expiry to 30 October 2026.', [
                        'subject' => $batch, 'subject_label' => $product->name . ' / 243400',
                        'reason' => 'Data-entry correction: user confirmed physical packaging expiry as 30/10/2026.',
                        'old_values' => ['expiry_date' => $oldDate, 'purchase_item_expiry_date' => $oldItemDate],
                        'new_values' => ['expiry_date' => '2026-10-30', 'purchase_item_expiry_date' => $item ? '2026-10-30' : null],
                        'context' => ['command' => 'stock:correct-vip-zolam-expiry', 'purchase_item_id' => $item?->id, 'execution_source' => 'VPS console'],
                    ]);
                $this->info('Corrected and audited: VIP Zolam batch 243400 now expires on 2026-10-30.');
                return self::SUCCESS;
            });
        } catch (Throwable $exception) {
            $this->error('Correction stopped; no changes saved. ' . $exception->getMessage());
            return self::FAILURE;
        }
    }

    private function exactlyOne($records, string $label)
    {
        if ($records->count() !== 1) {
            throw new RuntimeException('Expected exactly one ' . $label . '; found ' . $records->count() . '.');
        }
        return $records->first();
    }

    private function checkDate(?string $date, string $label): void
    {
        if (!in_array($date, ['2026-10-01', '2026-10-30'], true)) {
            throw new RuntimeException($label . ' expiry differs from the expected 2026-10-01 or already-corrected 2026-10-30.');
        }
    }
}
