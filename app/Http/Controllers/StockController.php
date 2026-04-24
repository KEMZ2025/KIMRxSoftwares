<?php

namespace App\Http\Controllers;

use App\Models\ProductBatch;
use App\Models\StockAdjustment;
use App\Models\StockMovement;
use App\Support\AuditTrail;
use App\Support\BatchReservationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StockController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $clientName = $user->client?->name ?? 'No Client';
        $branchName = $user->branch?->name ?? 'No Branch';
        $search = trim((string) $request->get('search', ''));

        BatchReservationService::syncForBranch($user->client_id, $user->branch_id);

        $query = $this->batchQueryForUser($user)
            ->with(['product.unit', 'supplier', 'purchaseItem.purchase'])
            ->when($search !== '', function (Builder $batchQuery) use ($search) {
                $batchQuery->where(function (Builder $innerQuery) use ($search) {
                    $innerQuery->where('batch_number', 'like', '%' . $search . '%')
                        ->orWhereHas('product', function (Builder $productQuery) use ($search) {
                            $productQuery->where('name', 'like', '%' . $search . '%')
                                ->orWhere('barcode', 'like', '%' . $search . '%')
                                ->orWhere('strength', 'like', '%' . $search . '%');
                        })
                        ->orWhereHas('supplier', function (Builder $supplierQuery) use ($search) {
                            $supplierQuery->where('name', 'like', '%' . $search . '%');
                        })
                        ->orWhereHas('purchaseItem.purchase', function (Builder $purchaseQuery) use ($search) {
                            $purchaseQuery->where('invoice_number', 'like', '%' . $search . '%');
                        });
                });
            });

        $summaryQuery = $this->batchQueryForUser($user);
        $batchCount = (clone $summaryQuery)->count();
        $availableStock = (float) (clone $summaryQuery)->sum('quantity_available');
        $reservedStock = (float) (clone $summaryQuery)->sum('reserved_quantity');
        $freeStock = max(0, $availableStock - $reservedStock);
        $expiringSoonCount = (clone $summaryQuery)
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<=', now()->addDays(90)->toDateString())
            ->whereDate('expiry_date', '>=', now()->toDateString())
            ->count();

        $batches = $query
            ->orderByRaw('CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('expiry_date')
            ->orderByDesc('id')
            ->paginate(12, ['*'], 'batches')
            ->withQueryString();

        $adjustments = StockAdjustment::query()
            ->with(['product', 'batch', 'purchase', 'adjustedByUser'])
            ->where('client_id', $user->client_id)
            ->where('branch_id', $user->branch_id)
            ->latest('adjustment_date')
            ->paginate(10, ['*'], 'adjustments')
            ->withQueryString();

        return view('stock.index', compact(
            'batches',
            'adjustments',
            'batchCount',
            'availableStock',
            'reservedStock',
            'freeStock',
            'expiringSoonCount',
            'search',
            'clientName',
            'branchName'
        ));
    }

    public function createAdjustment($batch)
    {
        $user = Auth::user();
        $clientName = $user->client?->name ?? 'No Client';
        $branchName = $user->branch?->name ?? 'No Branch';
        $batch = $this->findScopedBatchForUser($user, $batch, [
            'product.unit',
            'supplier',
            'purchaseItem.purchase',
            'stockAdjustments.adjustedByUser',
        ]);
        BatchReservationService::syncSingle($batch);

        $directionOptions = self::directionOptions();
        $reasonOptions = self::reasonOptions();
        $freeStock = $this->batchFreeStock($batch);

        return view('stock.adjust', compact(
            'batch',
            'directionOptions',
            'reasonOptions',
            'freeStock',
            'clientName',
            'branchName'
        ));
    }

    public function storeAdjustment(Request $request, $batch)
    {
        $user = Auth::user();
        $batch = $this->findScopedBatchForUser($user, $batch, [
            'purchaseItem.purchase',
            'product',
        ]);
        $adjustment = null;
        BatchReservationService::syncSingle($batch);

        $validated = $request->validate([
            'direction' => ['required', Rule::in(array_keys(self::directionOptions()))],
            'reason' => ['required', Rule::in(array_keys(self::reasonOptions()))],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'adjustment_date' => ['required', 'date'],
            'note' => ['nullable', 'string'],
        ]);

        if ($validated['reason'] === 'other' && blank($validated['note'] ?? null)) {
            throw ValidationException::withMessages([
                'note' => 'Please enter a note when the adjustment reason is Other.',
            ]);
        }

        DB::beginTransaction();

        try {
            $batch = $this->findLockedBatchForUser($user, $batch->id, ['purchaseItem.purchase', 'product']);
            BatchReservationService::syncSingle($batch);
            $quantity = (float) $validated['quantity'];
            $direction = $validated['direction'];
            $freeStockBefore = $this->batchFreeStock($batch);

            if ($direction === 'decrease' && $quantity > $freeStockBefore + 0.0001) {
                throw ValidationException::withMessages([
                    'quantity' => 'Decrease quantity cannot be greater than the free stock on this batch. Reserved stock is protected.',
                ]);
            }

            $receivedBefore = (float) $batch->quantity_received;
            $availableBefore = (float) $batch->quantity_available;
            $reservedBefore = (float) $batch->reserved_quantity;

            if ($direction === 'increase') {
                $batch->quantity_received = $receivedBefore + $quantity;
                $batch->quantity_available = $availableBefore + $quantity;
            } else {
                $batch->quantity_received = max(0, $receivedBefore - $quantity);
                $batch->quantity_available = max(0, $availableBefore - $quantity);
            }

            $batch->save();

            $adjustment = StockAdjustment::create([
                'client_id' => $batch->client_id,
                'branch_id' => $batch->branch_id,
                'product_id' => $batch->product_id,
                'product_batch_id' => $batch->id,
                'purchase_id' => $batch->purchaseItem?->purchase_id,
                'direction' => $direction,
                'reason' => $validated['reason'],
                'quantity' => $quantity,
                'quantity_received_before' => $receivedBefore,
                'quantity_received_after' => (float) $batch->quantity_received,
                'quantity_available_before' => $availableBefore,
                'quantity_available_after' => (float) $batch->quantity_available,
                'reserved_quantity_before' => $reservedBefore,
                'reserved_quantity_after' => (float) $batch->reserved_quantity,
                'note' => $validated['note'] ?? null,
                'adjusted_by' => $user->id,
                'adjustment_date' => $validated['adjustment_date'],
            ]);

            StockMovement::create([
                'client_id' => $batch->client_id,
                'branch_id' => $batch->branch_id,
                'product_id' => $batch->product_id,
                'product_batch_id' => $batch->id,
                'movement_type' => $direction === 'increase' ? 'adjustment_in' : 'adjustment_out',
                'reference_type' => 'stock_adjustment',
                'reference_id' => $adjustment->id,
                'quantity_in' => $direction === 'increase' ? $quantity : 0,
                'quantity_out' => $direction === 'decrease' ? $quantity : 0,
                'balance_after' => (float) $batch->quantity_available,
                'note' => $this->buildAdjustmentNote($batch, $validated['reason'], $validated['note'] ?? null),
                'created_by' => $user->id,
            ]);

            DB::commit();

            app(AuditTrail::class)->recordSafely(
                $user,
                'stock.adjustment_recorded',
                'Stock',
                'Record Stock Adjustment',
                ucfirst($direction) . ' stock for batch ' . $batch->batch_number . '.',
                [
                    'subject' => $batch,
                    'subject_label' => $batch->batch_number,
                    'reason' => $validated['reason'] === 'other'
                        ? ($validated['note'] ?? 'Other')
                        : self::reasonOptions()[$validated['reason']],
                    'old_values' => [
                        'quantity_received' => round($receivedBefore, 2),
                        'quantity_available' => round($availableBefore, 2),
                        'reserved_quantity' => round($reservedBefore, 2),
                    ],
                    'new_values' => [
                        'quantity_received' => round((float) $batch->quantity_received, 2),
                        'quantity_available' => round((float) $batch->quantity_available, 2),
                        'reserved_quantity' => round((float) $batch->reserved_quantity, 2),
                    ],
                    'context' => [
                        'stock_adjustment_id' => $adjustment?->id,
                        'direction' => $direction,
                        'quantity' => round($quantity, 2),
                        'reason_code' => $validated['reason'],
                        'adjustment_date' => $validated['adjustment_date'],
                        'product_name' => $batch->product?->name,
                    ],
                ]
            );

            return redirect()
                ->route('stock.index')
                ->with('success', 'Stock adjustment saved for batch ' . $batch->batch_number . '.');
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public static function directionOptions(): array
    {
        return [
            'increase' => 'Increase Stock',
            'decrease' => 'Decrease Stock',
        ];
    }

    public static function reasonOptions(): array
    {
        return [
            'count_gain' => 'Count Gain',
            'found_stock' => 'Found Stock',
            'supplier_return' => 'Supplier Return',
            'customer_return' => 'Customer Return',
            'count_loss' => 'Count Loss',
            'damaged' => 'Damaged',
            'expired' => 'Expired',
            'theft_loss' => 'Theft / Loss',
            'sample_use' => 'Sample / Internal Use',
            'other' => 'Other',
        ];
    }

    private function batchQueryForUser($user): Builder
    {
        return ProductBatch::query()
            ->where('client_id', $user->client_id)
            ->where('branch_id', $user->branch_id)
            ->where('is_active', true);
    }

    private function findScopedBatchForUser($user, $batchId, array $with = []): ProductBatch
    {
        return $this->batchQueryForUser($user)
            ->with($with)
            ->findOrFail($batchId);
    }

    private function findLockedBatchForUser($user, $batchId, array $with = []): ProductBatch
    {
        return $this->batchQueryForUser($user)
            ->with($with)
            ->lockForUpdate()
            ->findOrFail($batchId);
    }

    private function batchFreeStock(ProductBatch $batch): float
    {
        return max(0, (float) $batch->quantity_available - (float) $batch->reserved_quantity);
    }

    private function buildAdjustmentNote(ProductBatch $batch, string $reason, ?string $note = null): string
    {
        $base = 'Stock adjustment for batch ' . $batch->batch_number . ' (' . $batch->product?->name . ') reason: ' . ucwords(str_replace('_', ' ', $reason)) . '.';

        if ($note) {
            return $base . ' ' . $note;
        }

        return $base;
    }
}
