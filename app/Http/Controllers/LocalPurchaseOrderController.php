<?php

namespace App\Http\Controllers;

use App\Models\LocalPurchaseOrder;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LocalPurchaseOrderController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = LocalPurchaseOrder::query()
            ->with('supplier')
            ->where('client_id', $user->client_id)
            ->where('branch_id', $user->branch_id);

        $search = is_string($request->query('q')) ? trim($request->query('q')) : '';
        $status = is_string($request->query('status')) ? $request->query('status') : '';
        $dateFrom = is_string($request->query('date_from')) ? $request->query('date_from') : '';
        $dateTo = is_string($request->query('date_to')) ? $request->query('date_to') : '';
        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('order_number', 'like', '%' . $search . '%')
                    ->orWhere('supplier_name', 'like', '%' . $search . '%');
            });
        }
        if (in_array($status, ['draft', 'issued', 'cancelled'], true)) {
            $query->where('status', $status);
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $query->whereDate('order_date', '>=', $dateFrom);
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $query->whereDate('order_date', '<=', $dateTo);
        }

        return view('lpos.index', [
            'orders' => $query->latest('id')->paginate(20)->withQueryString(),
            'search' => $search,
            'status' => $status,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'user' => $user,
        ]);
    }

    public function create(Request $request)
    {
        return $this->form($request);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $validated = $this->validateOrder($request);
        $prepared = $this->prepareOrder($validated, $user->client_id);

        $order = DB::transaction(function () use ($user, $validated, $prepared) {
            $order = LocalPurchaseOrder::query()->firstOrCreate(
                [
                    'client_id' => $user->client_id,
                    'branch_id' => $user->branch_id,
                    'submission_token' => $validated['submission_token'],
                ],
                [
                    'supplier_id' => $prepared['supplier']->id,
                    'supplier_name' => $prepared['supplier']->name,
                    'supplier_phone' => $prepared['supplier']->phone,
                    'supplier_address' => $prepared['supplier']->address,
                    'order_date' => $validated['order_date'],
                    'expected_delivery_date' => $validated['expected_delivery_date'] ?? null,
                    'delivery_address' => $validated['delivery_address'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                    'status' => 'draft',
                    'subtotal' => $prepared['subtotal'],
                    'discount_amount' => $prepared['discount'],
                    'tax_amount' => $prepared['tax'],
                    'total_amount' => $prepared['total'],
                    'created_by' => $user->id,
                ]
            );

            if ($order->wasRecentlyCreated) {
                $order->update(['order_number' => 'LPO-' . str_pad((string) $order->id, 6, '0', STR_PAD_LEFT)]);
                $order->items()->createMany($prepared['items']);
            }

            return $order;
        });

        return redirect()->route('lpos.show', $order)->with('success', 'LPO draft saved.');
    }

    public function show(Request $request, LocalPurchaseOrder $lpo)
    {
        $this->ensureAccess($lpo, $request);
        $lpo->load(['items', 'client', 'branch', 'createdByUser', 'issuedByUser']);

        return view('lpos.show', ['order' => $lpo, 'user' => $request->user()]);
    }

    public function edit(Request $request, LocalPurchaseOrder $lpo)
    {
        $this->ensureAccess($lpo, $request);
        abort_unless($lpo->status === 'draft', 409, 'Only draft LPOs can be edited.');

        return $this->form($request, $lpo->load('items'));
    }

    public function update(Request $request, LocalPurchaseOrder $lpo)
    {
        $this->ensureAccess($lpo, $request);
        $validated = $this->validateOrder($request, false);
        $prepared = $this->prepareOrder($validated, $request->user()->client_id);

        DB::transaction(function () use ($lpo, $validated, $prepared) {
            $locked = LocalPurchaseOrder::query()->lockForUpdate()->findOrFail($lpo->id);
            if ($locked->status !== 'draft' || $locked->version !== (int) $validated['version']) {
                throw ValidationException::withMessages(['version' => 'This LPO changed in another tab. Reload it before editing.']);
            }

            $locked->update([
                'supplier_id' => $prepared['supplier']->id,
                'supplier_name' => $prepared['supplier']->name,
                'supplier_phone' => $prepared['supplier']->phone,
                'supplier_address' => $prepared['supplier']->address,
                'order_date' => $validated['order_date'],
                'expected_delivery_date' => $validated['expected_delivery_date'] ?? null,
                'delivery_address' => $validated['delivery_address'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'subtotal' => $prepared['subtotal'],
                'discount_amount' => $prepared['discount'],
                'tax_amount' => $prepared['tax'],
                'total_amount' => $prepared['total'],
                'version' => $locked->version + 1,
            ]);
            $locked->items()->delete();
            $locked->items()->createMany($prepared['items']);
        });

        return redirect()->route('lpos.show', $lpo)->with('success', 'LPO draft updated.');
    }

    public function issue(Request $request, LocalPurchaseOrder $lpo)
    {
        $this->ensureAccess($lpo, $request);
        $validated = $request->validate(['version' => ['required', 'integer', 'min:1']]);
        DB::transaction(function () use ($lpo, $request, $validated) {
            $locked = LocalPurchaseOrder::query()->lockForUpdate()->findOrFail($lpo->id);
            if ($locked->version !== (int) $validated['version']) {
                throw ValidationException::withMessages(['version' => 'This LPO changed in another tab. Reload it before issuing.']);
            }
            if ($locked->status !== 'draft' || !$locked->items()->exists()) {
                throw ValidationException::withMessages(['status' => 'Only a draft with items can be issued.']);
            }
            $locked->update([
                'status' => 'issued',
                'issued_by' => $request->user()->id,
                'issued_at' => now(),
                'version' => $locked->version + 1,
            ]);
        });

        return redirect()->route('lpos.show', $lpo)->with('success', 'LPO issued.');
    }

    public function cancel(Request $request, LocalPurchaseOrder $lpo)
    {
        $this->ensureAccess($lpo, $request);
        $validated = $request->validate([
            'version' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($lpo, $request, $validated) {
            $locked = LocalPurchaseOrder::query()->lockForUpdate()->findOrFail($lpo->id);
            if ($locked->version !== (int) $validated['version']) {
                throw ValidationException::withMessages(['version' => 'This LPO changed in another tab. Reload it before cancelling.']);
            }
            if (!in_array($locked->status, ['draft', 'issued'], true)) {
                throw ValidationException::withMessages(['status' => 'This LPO is already cancelled.']);
            }
            $locked->update([
                'status' => 'cancelled',
                'cancelled_by' => $request->user()->id,
                'cancelled_at' => now(),
                'cancellation_reason' => $validated['reason'],
                'version' => $locked->version + 1,
            ]);
        });

        return redirect()->route('lpos.show', $lpo)->with('success', 'LPO cancelled.');
    }

    public function print(Request $request, LocalPurchaseOrder $lpo)
    {
        $this->ensureAccess($lpo, $request);
        $lpo->load(['items', 'client', 'branch', 'createdByUser', 'issuedByUser']);

        return view('lpos.print', ['order' => $lpo]);
    }

    private function form(Request $request, ?LocalPurchaseOrder $order = null)
    {
        $user = $request->user();
        return view('lpos.form', [
            'order' => $order,
            'user' => $user,
            'submissionToken' => (string) Str::uuid(),
            'suppliers' => Supplier::query()->where('client_id', $user->client_id)->where('is_active', true)->orderBy('name')->get(),
            'products' => Product::query()->with('unit')->where('client_id', $user->client_id)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'unit_id', 'purchase_price']),
        ]);
    }

    private function validateOrder(Request $request, bool $creating = true): array
    {
        $clientId = $request->user()->client_id;
        $rules = [
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')->where('client_id', $clientId)],
            'order_date' => ['required', 'date'],
            'expected_delivery_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'delivery_address' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'discount_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999', 'decimal:0,2'],
            'tax_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999', 'decimal:0,2'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['nullable', 'integer', Rule::exists('products', 'id')->where('client_id', $clientId)],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.unit_name' => ['nullable', 'string', 'max:80'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:99999.99', 'decimal:0,2'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:999999.99', 'decimal:0,2'],
        ];
        if ($creating) {
            $rules['submission_token'] = ['required', 'uuid'];
        } else {
            $rules['version'] = ['required', 'integer', 'min:1'];
        }

        return $request->validate($rules);
    }

    private function prepareOrder(array $validated, int $clientId): array
    {
        $supplier = Supplier::query()->where('client_id', $clientId)->findOrFail($validated['supplier_id']);
        $productIds = collect($validated['items'])->pluck('product_id')->filter()->unique()->all();
        $products = Product::query()->with('unit')->where('client_id', $clientId)->whereIn('id', $productIds)->get()->keyBy('id');
        $lines = [];
        $errors = [];
        $subtotalCents = 0;

        foreach ($validated['items'] as $position => $item) {
            $product = $products->get($item['product_id'] ?? null);
            $description = trim((string) ($item['description'] ?? ''));
            if ($description === '' && $product) {
                $description = $product->name;
            }
            if ($description === '') {
                $errors["items.$position.description"] = 'Select a product or enter an item description.';
                continue;
            }

            $quantity = (float) $item['quantity'];
            $unitPrice = (float) $item['unit_price'];
            $lineCents = (int) round($quantity * $unitPrice * 100);
            $subtotalCents += $lineCents;
            $lines[] = [
                'product_id' => $product?->id,
                'line_number' => count($lines) + 1,
                'description' => $description,
                'unit_name' => trim((string) ($item['unit_name'] ?? '')) ?: $product?->unit?->name,
                'quantity' => number_format($quantity, 2, '.', ''),
                'unit_price' => number_format($unitPrice, 2, '.', ''),
                'line_total' => number_format($lineCents / 100, 2, '.', ''),
            ];
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $discountCents = (int) round(((float) ($validated['discount_amount'] ?? 0)) * 100);
        $taxCents = (int) round(((float) ($validated['tax_amount'] ?? 0)) * 100);
        if ($discountCents > $subtotalCents) {
            throw ValidationException::withMessages(['discount_amount' => 'Discount cannot exceed the item subtotal.']);
        }
        $totalCents = $subtotalCents - $discountCents + $taxCents;

        return [
            'supplier' => $supplier,
            'items' => $lines,
            'subtotal' => number_format($subtotalCents / 100, 2, '.', ''),
            'discount' => number_format($discountCents / 100, 2, '.', ''),
            'tax' => number_format($taxCents / 100, 2, '.', ''),
            'total' => number_format($totalCents / 100, 2, '.', ''),
        ];
    }

    private function ensureAccess(LocalPurchaseOrder $lpo, Request $request): void
    {
        $user = $request->user();
        abort_unless($lpo->client_id === $user->client_id && $lpo->branch_id === $user->branch_id, 404);
    }
}
