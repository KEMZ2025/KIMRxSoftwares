<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $clientName = $user->client?->name ?? 'No Client';
        $branchName = $user->branch?->name ?? 'No Branch';

        $query = $this->supplierQueryForUser($user);

        if ($request->filled('search')) {
            $search = trim((string) $request->search);

            $query->where(function (Builder $supplierQuery) use ($search) {
                $supplierQuery->where('name', 'like', '%' . $search . '%')
                    ->orWhere('contact_person', 'like', '%' . $search . '%')
                    ->orWhere('phone', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%')
                    ->orWhere('address', 'like', '%' . $search . '%');
            });
        }

        $supplierCount = (clone $query)->count();
        $branchPurchases = $this->branchPurchaseQuery($user);
        $suppliersOwed = (clone $branchPurchases)->where('balance_due', '>', 0)->distinct('supplier_id')->count('supplier_id');
        $totalOutstanding = (float) (clone $branchPurchases)->sum('balance_due');
        $totalPaid = (float) (clone $branchPurchases)->sum('amount_paid');

        $suppliers = $query
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('suppliers.index', compact(
            'suppliers',
            'supplierCount',
            'suppliersOwed',
            'totalOutstanding',
            'totalPaid',
            'user',
            'clientName',
            'branchName'
        ));
    }

    public function statement(Request $request)
    {
        $user = Auth::user();
        $clientName = $user->client?->name ?? 'No Client';
        $branchName = $user->branch?->name ?? 'No Branch';
        $search = trim((string) $request->get('search', ''));

        $query = $this->supplierQueryForUser($user)
            ->when($search !== '', function (Builder $supplierQuery) use ($search) {
                $supplierQuery->where(function (Builder $innerQuery) use ($search) {
                    $innerQuery->where('name', 'like', '%' . $search . '%')
                        ->orWhere('contact_person', 'like', '%' . $search . '%')
                        ->orWhere('phone', 'like', '%' . $search . '%');
                });
            })
            ->withCount([
                'purchases as total_invoices' => function (Builder $purchaseQuery) use ($user) {
                    $purchaseQuery->where('branch_id', $user->branch_id)
                        ->where('is_active', true);
                },
                'purchases as outstanding_invoices' => function (Builder $purchaseQuery) use ($user) {
                    $purchaseQuery->where('branch_id', $user->branch_id)
                        ->where('is_active', true)
                        ->where('balance_due', '>', 0);
                },
            ])
            ->withSum([
                'purchases as total_purchases' => function (Builder $purchaseQuery) use ($user) {
                    $purchaseQuery->where('branch_id', $user->branch_id)
                        ->where('is_active', true);
                },
            ], 'total_amount')
            ->withSum([
                'purchases as total_paid' => function (Builder $purchaseQuery) use ($user) {
                    $purchaseQuery->where('branch_id', $user->branch_id)
                        ->where('is_active', true);
                },
            ], 'amount_paid')
            ->withSum([
                'purchases as total_balance' => function (Builder $purchaseQuery) use ($user) {
                    $purchaseQuery->where('branch_id', $user->branch_id)
                        ->where('is_active', true);
                },
            ], 'balance_due')
            ->withMax([
                'purchases as last_purchase_date' => function (Builder $purchaseQuery) use ($user) {
                    $purchaseQuery->where('branch_id', $user->branch_id)
                        ->where('is_active', true);
                },
            ], 'purchase_date');

        $supplierCount = (clone $query)->count();
        $suppliersWithBalance = (clone $query)
            ->get()
            ->filter(fn (Supplier $supplier) => (float) ($supplier->total_balance ?? 0) > 0)
            ->count();
        $statements = $query
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        $branchPurchases = $this->branchPurchaseQuery($user);
        $totalOutstanding = (float) (clone $branchPurchases)->sum('balance_due');
        $totalPaid = (float) (clone $branchPurchases)->sum('amount_paid');
        $totalInvoiced = (float) (clone $branchPurchases)->sum('total_amount');

        return view('suppliers.statement', compact(
            'statements',
            'supplierCount',
            'suppliersWithBalance',
            'totalOutstanding',
            'totalPaid',
            'totalInvoiced',
            'search',
            'user',
            'clientName',
            'branchName'
        ));
    }

    public function show(Request $request, $supplier)
    {
        $user = Auth::user();
        $supplier = $this->findScopedSupplierForUser($user, $supplier);

        $clientName = $user->client?->name ?? 'No Client';
        $branchName = $user->branch?->name ?? 'No Branch';
        $search = trim((string) $request->get('search', ''));

        $basePurchasesQuery = $this->purchaseQueryForSupplier($supplier, $user)
            ->with(['items.product', 'supplierPayments.paidByUser'])
            ->when($search !== '', function (Builder $purchaseQuery) use ($search) {
                $purchaseQuery->where(function (Builder $invoiceQuery) use ($search) {
                    $invoiceQuery->where('invoice_number', 'like', '%' . $search . '%')
                        ->orWhereHas('items.product', function (Builder $productQuery) use ($search) {
                            $productQuery->where('name', 'like', '%' . $search . '%');
                        });
                });
            });

        $purchasesQuery = clone $basePurchasesQuery;

        $basePaymentsQuery = $this->supplierPaymentQueryForSupplier($supplier, $user)
            ->with(['purchase', 'paidByUser'])
            ->when($search !== '', function (Builder $paymentQuery) use ($search) {
                $paymentQuery->where(function (Builder $innerQuery) use ($search) {
                    $innerQuery->where('reference_number', 'like', '%' . $search . '%')
                        ->orWhere('payment_method', 'like', '%' . $search . '%')
                        ->orWhereHas('purchase', function (Builder $purchaseQuery) use ($search) {
                            $purchaseQuery->where('invoice_number', 'like', '%' . $search . '%');
                        });
                });
            });

        $paymentsQuery = clone $basePaymentsQuery;

        $supplierPurchasesQuery = $this->purchaseQueryForSupplier($supplier, $user);
        $invoiceCount = (clone $supplierPurchasesQuery)->count();
        $outstandingInvoiceCount = (clone $supplierPurchasesQuery)->where('balance_due', '>', 0)->count();
        $totalInvoiced = (float) (clone $supplierPurchasesQuery)->sum('total_amount');
        $totalPaid = (float) (clone $supplierPurchasesQuery)->sum('amount_paid');
        $outstandingBalance = (float) (clone $supplierPurchasesQuery)->sum('balance_due');
        $paymentCount = (clone $this->supplierPaymentQueryForSupplier($supplier, $user))->count();

        $purchases = $purchasesQuery
            ->latest('purchase_date')
            ->paginate(10, ['*'], 'invoices')
            ->withQueryString();

        $payments = $paymentsQuery
            ->latest('payment_date')
            ->paginate(10, ['*'], 'payments')
            ->withQueryString();

        return view('suppliers.show', compact(
            'supplier',
            'purchases',
            'payments',
            'invoiceCount',
            'outstandingInvoiceCount',
            'totalInvoiced',
            'totalPaid',
            'outstandingBalance',
            'paymentCount',
            'user',
            'clientName',
            'branchName',
            'search'
        ));
    }

    public function create()
    {
        $user = Auth::user();
        $clientName = $user->client?->name ?? 'No Client';
        $branchName = $user->branch?->name ?? 'No Branch';

        return view('suppliers.create', compact(
            'user',
            'clientName',
            'branchName'
        ));
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        $validated = $this->validateSupplier($request, $user);

        Supplier::create([
            'client_id' => $user->client_id,
            'name' => $validated['name'],
            'contact_person' => $validated['contact_person'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'alt_phone' => $validated['alt_phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'address' => $validated['address'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()
            ->route('suppliers.index')
            ->with('success', 'Supplier added successfully.');
    }

    public function edit($supplier)
    {
        $user = Auth::user();
        $supplier = $this->findScopedSupplierForUser($user, $supplier);

        $clientName = $user->client?->name ?? 'No Client';
        $branchName = $user->branch?->name ?? 'No Branch';

        return view('suppliers.edit', compact(
            'supplier',
            'user',
            'clientName',
            'branchName'
        ));
    }

    public function update(Request $request, $supplier)
    {
        $user = Auth::user();
        $supplier = $this->findScopedSupplierForUser($user, $supplier);
        $validated = $this->validateSupplier($request, $user, $supplier->id);

        $supplier->update([
            'name' => $validated['name'],
            'contact_person' => $validated['contact_person'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'alt_phone' => $validated['alt_phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'address' => $validated['address'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()
            ->route('suppliers.show', $supplier->id)
            ->with('success', 'Supplier updated successfully.');
    }

    public function destroy($supplier)
    {
        $user = Auth::user();
        $supplier = $this->findScopedSupplierForUser($user, $supplier);

        $hasLinkedPurchases = Purchase::query()
            ->where('client_id', $user->client_id)
            ->where('supplier_id', $supplier->id)
            ->exists();

        $hasLinkedPayments = SupplierPayment::query()
            ->where('client_id', $user->client_id)
            ->where('supplier_id', $supplier->id)
            ->exists();

        if ($hasLinkedPurchases || $hasLinkedPayments) {
            $supplier->is_active = false;
            $supplier->save();

            return redirect()
                ->route('suppliers.index')
                ->with('success', 'Supplier was deactivated instead of deleted because there is linked invoice or payment history.');
        }

        $supplier->delete();

        return redirect()
            ->route('suppliers.index')
            ->with('success', 'Supplier deleted successfully.');
    }

    private function supplierQueryForUser($user): Builder
    {
        return Supplier::query()
            ->where('client_id', $user->client_id);
    }

    private function findScopedSupplierForUser($user, $supplierId): Supplier
    {
        return $this->supplierQueryForUser($user)->findOrFail($supplierId);
    }

    private function branchPurchaseQuery($user): Builder
    {
        return Purchase::query()
            ->where('client_id', $user->client_id)
            ->where('branch_id', $user->branch_id)
            ->whereNotNull('supplier_id')
            ->where('is_active', true);
    }

    private function purchaseQueryForSupplier(Supplier $supplier, $user): Builder
    {
        return Purchase::query()
            ->where('client_id', $supplier->client_id)
            ->where('branch_id', $user->branch_id)
            ->where('supplier_id', $supplier->id)
            ->where('is_active', true);
    }

    private function supplierPaymentQueryForSupplier(Supplier $supplier, $user): Builder
    {
        return SupplierPayment::query()
            ->where('client_id', $supplier->client_id)
            ->where('branch_id', $user->branch_id)
            ->where('supplier_id', $supplier->id);
    }

    private function validateSupplier(Request $request, $user, ?int $supplierId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'alt_phone' => ['nullable', 'string', 'max:50'],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('suppliers', 'email')
                    ->where(fn ($query) => $query->where('client_id', $user->client_id))
                    ->ignore($supplierId),
            ],
            'address' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }
}
