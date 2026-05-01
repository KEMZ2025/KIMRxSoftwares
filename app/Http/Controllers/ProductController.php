<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use App\Models\Unit;
use App\Models\ProductBatch;
use App\Models\SaleItem;
use App\Support\ClientFeatureAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        $clientName = $user->client?->name ?? 'No Client';
        $branchName = $user->branch?->name ?? 'No Branch';

        $products = Product::with(['category', 'unit'])
            ->where('client_id', $user->client_id)
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim($request->search);

                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('strength', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(10);

        return view('products.index', compact(
            'products',
            'user',
            'clientName',
            'branchName'
        ));
    }

    public function create()
    {
        $user = Auth::user();

        $clientName = $user->client?->name ?? 'No Client';
        $branchName = $user->branch?->name ?? 'No Branch';

        $categories = Category::where('client_id', $user->client_id)->get();
        $units = Unit::where('client_id', $user->client_id)->get();
        $showDispensingPriceGuide = $this->showDispensingPriceGuide($user);

        return view('products.create', compact(
            'categories',
            'units',
            'showDispensingPriceGuide',
            'user',
            'clientName',
            'branchName'
        ));
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required',
            'unit_id' => 'required',
            'retail_price' => 'required|numeric',
            'wholesale_price' => 'required|numeric',
            'expiry_alert_days' => 'nullable|integer|min:1|max:3650',
            'guide_quantity' => 'sometimes|array',
            'guide_quantity.*' => 'nullable|numeric|min:0.01|max:999999.99',
            'guide_label' => 'sometimes|array',
            'guide_label.*' => 'nullable|string|max:80',
            'guide_amount' => 'sometimes|array',
            'guide_amount.*' => 'nullable|numeric|min:0|max:999999999.99',
        ]);

        $trackExpiry = $request->has('track_expiry');
        $expiryAlertDays = $trackExpiry
            ? (int) ($request->input('expiry_alert_days') ?: 90)
            : null;

        $payload = [
            'client_id' => $user->client_id,
            'branch_id' => $user->branch_id,
            'category_id' => $request->category_id,
            'unit_id' => $request->unit_id,
            'name' => $request->name,
            'strength' => $request->strength,
            'barcode' => $request->barcode,
            'description' => $request->description,
            'purchase_price' => 0,
            'retail_price' => $request->retail_price,
            'wholesale_price' => $request->wholesale_price,
            'track_batch' => $request->has('track_batch'),
            'track_expiry' => $trackExpiry,
            'expiry_alert_days' => $expiryAlertDays,
            'is_active' => $request->has('is_active'),
        ];

        if ($request->hasAny(['guide_quantity', 'guide_label', 'guide_amount'])) {
            $payload['dispensing_price_guide'] = $this->normalizedDispensingPriceGuide($request);
        }

        Product::create($payload);

        return redirect()->route('products.index')->with('success', 'Product created successfully');
    }

    public function edit(Product $product)
    {
        $user = Auth::user();

        if ($product->client_id != $user->client_id) {
            abort(403);
        }

        $clientName = $user->client?->name ?? 'No Client';
        $branchName = $user->branch?->name ?? 'No Branch';

        $categories = Category::where('client_id', $user->client_id)->get();
        $units = Unit::where('client_id', $user->client_id)->get();
        $showDispensingPriceGuide = $this->showDispensingPriceGuide($user);
        $latestPurchasePrice = $this->latestPurchasePriceForProduct($product, $user);

        return view('products.edit', compact(
            'product',
            'latestPurchasePrice',
            'categories',
            'units',
            'showDispensingPriceGuide',
            'user',
            'clientName',
            'branchName'
        ));
    }

    public function update(Request $request, Product $product)
    {
        $user = Auth::user();

        if ($product->client_id != $user->client_id) {
            abort(403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required',
            'unit_id' => 'required',
            'retail_price' => 'required|numeric',
            'wholesale_price' => 'required|numeric',
            'expiry_alert_days' => 'nullable|integer|min:1|max:3650',
            'guide_quantity' => 'sometimes|array',
            'guide_quantity.*' => 'nullable|numeric|min:0.01|max:999999.99',
            'guide_label' => 'sometimes|array',
            'guide_label.*' => 'nullable|string|max:80',
            'guide_amount' => 'sometimes|array',
            'guide_amount.*' => 'nullable|numeric|min:0|max:999999999.99',
        ]);

        $latestPurchasePrice = $this->latestPurchasePriceForProduct($product, $user);
        $priceErrors = [];

        if ($latestPurchasePrice > 0 && (float) $request->wholesale_price < $latestPurchasePrice) {
            $priceErrors['wholesale_price'] = 'Wholesale price for ' . $product->name . ' cannot be below the latest purchase price of ' . number_format($latestPurchasePrice, 2) . '.';
        }

        if ($latestPurchasePrice > 0 && (float) $request->retail_price < $latestPurchasePrice) {
            $priceErrors['retail_price'] = 'Retail price for ' . $product->name . ' cannot be below the latest purchase price of ' . number_format($latestPurchasePrice, 2) . '.';
        }

        if (!empty($priceErrors)) {
            return back()->withErrors($priceErrors)->withInput();
        }
        $trackExpiry = $request->has('track_expiry');
        $expiryAlertDays = $trackExpiry
            ? (int) ($request->input('expiry_alert_days') ?: 90)
            : null;

        $payload = [
            'category_id' => $request->category_id,
            'unit_id' => $request->unit_id,
            'name' => $request->name,
            'strength' => $request->strength,
            'barcode' => $request->barcode,
            'description' => $request->description,
            'retail_price' => $request->retail_price,
            'wholesale_price' => $request->wholesale_price,
            'track_batch' => $request->has('track_batch'),
            'track_expiry' => $trackExpiry,
            'expiry_alert_days' => $expiryAlertDays,
            'is_active' => $request->has('is_active'),
        ];

        if ($request->hasAny(['guide_quantity', 'guide_label', 'guide_amount'])) {
            $payload['dispensing_price_guide'] = $this->normalizedDispensingPriceGuide($request);
        }

        $product->update($payload);

        return redirect()->route('products.index')->with('success', 'Product updated successfully');
    }

    public function destroy(Product $product)
    {
        $user = Auth::user();

        if ($product->client_id != $user->client_id) {
            abort(403);
        }

        $product->update([
            'is_active' => false,
        ]);

        return redirect()->route('products.index')->with('success', 'Product deleted');
    }

    public function sources(Product $product)
    {
        $user = Auth::user();

        if ($product->client_id != $user->client_id) {
            abort(403);
        }

        $clientName = $user->client?->name ?? 'No Client';
        $branchName = $user->branch?->name ?? 'No Branch';

        $batches = ProductBatch::with(['supplier'])
            ->where('client_id', $user->client_id)
            ->where('branch_id', $user->branch_id)
            ->where('product_id', $product->id)
            ->latest()
            ->get();

        $salesHistory = SaleItem::with(['sale.customer', 'sale.servedByUser', 'batch'])
            ->whereHas('sale', function ($q) use ($user) {
                $q->where('client_id', $user->client_id);
            })
            ->where('product_id', $product->id)
            ->latest()
            ->get();

        return view('products.sources', compact(
            'product',
            'batches',
            'salesHistory',
            'user',
            'clientName',
            'branchName'
        ));
    }

    private function latestPurchasePriceForProduct(Product $product, $user): float
    {
        $latestPurchasePrice = ProductBatch::where('client_id', $user->client_id)
            ->where('branch_id', $user->branch_id)
            ->where('product_id', $product->id)
            ->latest('created_at')
            ->value('purchase_price');

        return $latestPurchasePrice === null
            ? (float) $product->purchase_price
            : (float) $latestPurchasePrice;
    }
    private function showDispensingPriceGuide($user): bool
    {
        return ClientFeatureAccess::dispensingPriceGuideEnabled($user->clientSettingsModel());
    }

    private function normalizedDispensingPriceGuide(Request $request): array
    {
        $quantities = $request->input('guide_quantity', []);
        $labels = $request->input('guide_label', []);
        $amounts = $request->input('guide_amount', []);

        $rowCount = max(count($quantities), count($labels), count($amounts));
        $guide = [];

        for ($index = 0; $index < $rowCount; $index++) {
            $quantity = (float) ($quantities[$index] ?? 0);
            $label = trim((string) ($labels[$index] ?? ''));
            $amount = (float) ($amounts[$index] ?? 0);

            if ($quantity <= 0 && $label === '' && $amount <= 0) {
                continue;
            }

            if ($quantity <= 0 || $label === '') {
                continue;
            }

            $guide[] = [
                'quantity' => $quantity,
                'label' => $label,
                'amount' => round($amount, 2),
            ];
        }

        return $guide;
    }
}
