<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Client;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Unit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CopyClientCatalogue extends Command
{
    protected $signature = 'client:copy-catalogue {source} {target} {--source-branch=} {--target-branch=} {--execute} {--confirm=}';

    protected $description = 'Copy products and suppliers to an empty client without stock, customers, or history';

    public function handle(): int
    {
        $source = $this->uniqueClient((string) $this->argument('source'));
        $target = $this->uniqueClient((string) $this->argument('target'));

        if (!$source || !$target || $source->id === $target->id) {
            $this->error('Source and target must be two distinct clients with exact, unique names.');
            return self::FAILURE;
        }

        $sourceBranch = $this->uniqueBranch($source, (string) $this->option('source-branch'));
        $targetBranch = $this->uniqueBranch($target, (string) $this->option('target-branch'));
        if (!$sourceBranch || !$targetBranch) {
            $this->error('Each client needs one matching main branch, or specify its branch code.');
            return self::FAILURE;
        }

        $products = Product::query()->where('client_id', $source->id)->where('branch_id', $sourceBranch->id);
        $suppliers = Supplier::query()->where('client_id', $source->id);
        $productCount = (clone $products)->count();
        $supplierCount = (clone $suppliers)->count();

        $this->line("Source: {$source->name} / {$sourceBranch->name}");
        $this->line("Target: {$target->name} / {$targetBranch->name}");
        $this->line("Products: {$productCount}; suppliers: {$supplierCount}");
        $this->line('Only retail prices are copied; purchase and wholesale prices start at zero.');
        $this->line('No stock, batches, customers, purchases, sales, payments, or balances are copied.');

        if ($productCount === 0 || $this->targetHasCatalogue($target)) {
            $this->error('The source has no products or the target already contains catalogue/customer records.');
            return self::FAILURE;
        }

        if (!$this->option('execute')) {
            $this->info('Preview only. No records were changed.');
            return self::SUCCESS;
        }

        if ($this->option('confirm') !== $target->name) {
            $this->error('Pass --confirm with the exact target client name to execute.');
            return self::FAILURE;
        }

        try {
            DB::transaction(function () use ($source, $target, $targetBranch, $products, $suppliers): void {
                if ($this->targetHasCatalogue($target)) {
                    throw new RuntimeException('The destination is no longer empty.');
                }

                $categories = Category::query()->where('client_id', $source->id)->get()->keyBy('id');
                $units = Unit::query()->where('client_id', $source->id)->get()->keyBy('id');
                $categoryIds = [];
                $unitIds = [];

                (clone $suppliers)->chunkById(200, function ($rows) use ($target): void {
                    foreach ($rows as $supplier) {
                        Supplier::query()->create([
                            'client_id' => $target->id,
                            'name' => $supplier->name,
                            'contact_person' => $supplier->contact_person,
                            'phone' => $supplier->phone,
                            'alt_phone' => $supplier->alt_phone,
                            'email' => $supplier->email,
                            'address' => $supplier->address,
                            'notes' => $supplier->notes,
                            'is_active' => $supplier->is_active,
                        ]);
                    }
                });

                (clone $products)->chunkById(200, function ($rows) use (
                    $target, $targetBranch, $categories, $units, &$categoryIds, &$unitIds
                ): void {
                    foreach ($rows as $product) {
                        $categoryId = null;
                        if ($product->category_id) {
                            $category = $categories->get($product->category_id)
                                ?? throw new RuntimeException("Product {$product->id} has a missing category.");
                            $categoryIds[$category->id] ??= Category::query()->create([
                                'client_id' => $target->id, 'name' => $category->name,
                                'description' => $category->description, 'is_active' => $category->is_active,
                            ])->id;
                            $categoryId = $categoryIds[$category->id];
                        }

                        $unitId = null;
                        if ($product->unit_id) {
                            $unit = $units->get($product->unit_id)
                                ?? throw new RuntimeException("Product {$product->id} has a missing unit.");
                            $unitIds[$unit->id] ??= Unit::query()->create([
                                'client_id' => $target->id, 'name' => $unit->name,
                                'description' => $unit->description, 'is_active' => $unit->is_active,
                            ])->id;
                            $unitId = $unitIds[$unit->id];
                        }

                        Product::query()->create([
                            'client_id' => $target->id,
                            'branch_id' => $targetBranch->id,
                            'category_id' => $categoryId,
                            'unit_id' => $unitId,
                            'name' => $product->name,
                            'strength' => $product->strength,
                            'barcode' => $product->barcode,
                            'description' => $product->description,
                            'purchase_price' => 0,
                            'retail_price' => $product->retail_price,
                            'wholesale_price' => 0,
                            'track_batch' => $product->track_batch,
                            'track_expiry' => $product->track_expiry,
                            'expiry_alert_days' => $product->expiry_alert_days,
                            'is_active' => $product->is_active,
                        ]);
                    }
                });
            });
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }

        $this->info("Copied {$productCount} products and {$supplierCount} suppliers to {$target->name}.");
        return self::SUCCESS;
    }

    private function uniqueClient(string $name): ?Client
    {
        $matches = Client::query()->where('name', $name)->limit(2)->get();
        return $matches->count() === 1 ? $matches->first() : null;
    }

    private function uniqueBranch(Client $client, string $code): ?Branch
    {
        $matches = Branch::query()->where('client_id', $client->id)
            ->when($code !== '', fn ($query) => $query->where('code', $code))
            ->when($code === '', fn ($query) => $query->where('is_main', true))
            ->limit(2)->get();
        return $matches->count() === 1 ? $matches->first() : null;
    }

    private function targetHasCatalogue(Client $target): bool
    {
        return Product::query()->where('client_id', $target->id)->exists()
            || Supplier::query()->where('client_id', $target->id)->exists()
            || Category::query()->where('client_id', $target->id)->exists()
            || Unit::query()->where('client_id', $target->id)->exists()
            || Customer::query()->where('client_id', $target->id)->exists();
    }
}
