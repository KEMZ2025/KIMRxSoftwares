<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $clientName = $user->client?->name ?? 'No Client';
        $branchName = $user->branch?->name ?? 'No Branch';

        $query = $this->customerQueryForUser($user);

        if ($request->filled('search')) {
            $search = trim((string) $request->search);

            $query->where(function (Builder $customerQuery) use ($search) {
                $customerQuery->where('name', 'like', '%' . $search . '%')
                    ->orWhere('contact_person', 'like', '%' . $search . '%')
                    ->orWhere('phone', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%')
                    ->orWhere('address', 'like', '%' . $search . '%');
            });
        }

        $customerCount = (clone $query)->count();
        $totalCreditLimit = (float) (clone $query)->sum('credit_limit');
        $totalOutstanding = (float) (clone $query)->sum('outstanding_balance');

        $customers = $query
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $totalRemainingCredit = max(0, $totalCreditLimit - $totalOutstanding);

        return view('customers.index', compact(
            'customers',
            'customerCount',
            'totalCreditLimit',
            'totalOutstanding',
            'totalRemainingCredit',
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

        return view('customers.create', compact(
            'user',
            'clientName',
            'branchName'
        ));
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        $validated = $this->validateCustomer($request, $user);

        Customer::create([
            'client_id' => $user->client_id,
            'name' => $validated['name'],
            'contact_person' => $validated['contact_person'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'alt_phone' => $validated['alt_phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'address' => $validated['address'] ?? null,
            'credit_limit' => $validated['credit_limit'] ?? 0,
            'outstanding_balance' => 0,
            'notes' => $validated['notes'] ?? null,
            'is_active' => true,
        ]);

        return redirect()
            ->route('customers.index')
            ->with('success', 'Customer added successfully.');
    }

    public function show(Request $request, $customer)
    {
        $user = Auth::user();
        $customer = $this->findScopedCustomerForUser($user, $customer);

        $clientName = $user->client?->name ?? 'No Client';
        $branchName = $user->branch?->name ?? 'No Branch';
        $search = trim((string) $request->get('search', ''));

        $baseSalesQuery = $this->approvedSalesQueryForCustomer($customer)
            ->with(['items.product', 'payments.receivedByUser'])
            ->when($search !== '', function (Builder $saleQuery) use ($search) {
                $saleQuery->where(function (Builder $invoiceQuery) use ($search) {
                    $invoiceQuery->where('invoice_number', 'like', '%' . $search . '%')
                        ->orWhere('receipt_number', 'like', '%' . $search . '%')
                        ->orWhereHas('items.product', function (Builder $productQuery) use ($search) {
                            $productQuery->where('name', 'like', '%' . $search . '%');
                        });
                });
            });

        $salesQuery = (clone $baseSalesQuery);

        $basePaymentsQuery = Payment::query()
            ->with(['sale', 'receivedByUser', 'originalPayment.receivedByUser', 'reversals.receivedByUser'])
            ->where('client_id', $user->client_id)
            ->where('customer_id', $customer->id)
            ->where(function (Builder $paymentQuery) {
                $paymentQuery->where('status', 'received')
                    ->orWhereNotNull('reversal_of_payment_id');
            });

        $paymentsQuery = (clone $basePaymentsQuery)
            ->when($search !== '', function (Builder $paymentQuery) use ($search) {
                $paymentQuery->where(function (Builder $innerQuery) use ($search) {
                    $innerQuery->where('reference_number', 'like', '%' . $search . '%')
                        ->orWhere('payment_method', 'like', '%' . $search . '%')
                        ->orWhereHas('sale', function (Builder $saleQuery) use ($search) {
                            $saleQuery->where('invoice_number', 'like', '%' . $search . '%')
                                ->orWhere('receipt_number', 'like', '%' . $search . '%');
                        });
                });
            });

        $invoiceCount = (clone $this->approvedSalesQueryForCustomer($customer))->count();
        $outstandingInvoiceCount = (clone $this->approvedSalesQueryForCustomer($customer))->where('balance_due', '>', 0)->count();
        $totalInvoiced = (float) (clone $this->approvedSalesQueryForCustomer($customer))->sum('total_amount');
        $totalCollected = (float) (clone $this->approvedSalesQueryForCustomer($customer))->sum('amount_paid');
        $outstandingBalance = (float) (clone $this->approvedSalesQueryForCustomer($customer))->sum('balance_due');

        $this->syncCustomerOutstandingBalance($customer, $outstandingBalance);
        $remainingCredit = max(0, (float) $customer->credit_limit - $outstandingBalance);

        $sales = $salesQuery
            ->latest('sale_date')
            ->paginate(10, ['*'], 'invoices')
            ->withQueryString();

        $payments = $paymentsQuery
            ->latest('payment_date')
            ->paginate(10, ['*'], 'payments')
            ->withQueryString();

        return view('customers.show', compact(
            'customer',
            'sales',
            'payments',
            'invoiceCount',
            'outstandingInvoiceCount',
            'totalInvoiced',
            'totalCollected',
            'outstandingBalance',
            'remainingCredit',
            'user',
            'clientName',
            'branchName',
            'search'
        ));
    }

    public function edit($customer)
    {
        $user = Auth::user();
        $customer = $this->findScopedCustomerForUser($user, $customer);

        $clientName = $user->client?->name ?? 'No Client';
        $branchName = $user->branch?->name ?? 'No Branch';

        return view('customers.edit', compact(
            'customer',
            'user',
            'clientName',
            'branchName'
        ));
    }

    public function update(Request $request, $customer)
    {
        $user = Auth::user();
        $customer = $this->findScopedCustomerForUser($user, $customer);
        $validated = $this->validateCustomer($request, $user, $customer->id);

        $customer->update([
            'name' => $validated['name'],
            'contact_person' => $validated['contact_person'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'alt_phone' => $validated['alt_phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'address' => $validated['address'] ?? null,
            'credit_limit' => $validated['credit_limit'] ?? 0,
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()
            ->route('customers.show', $customer->id)
            ->with('success', 'Customer updated successfully.');
    }

    public function destroy($customer)
    {
        $user = Auth::user();
        $customer = $this->findScopedCustomerForUser($user, $customer);

        $hasLinkedSales = Sale::query()
            ->where('client_id', $user->client_id)
            ->where('customer_id', $customer->id)
            ->exists();

        $hasLinkedPayments = Payment::query()
            ->where('client_id', $user->client_id)
            ->where('customer_id', $customer->id)
            ->exists();

        if ($hasLinkedSales || $hasLinkedPayments || (float) $customer->outstanding_balance > 0) {
            $customer->is_active = false;
            $customer->save();

            return redirect()
                ->route('customers.index')
                ->with('success', 'Customer was deactivated instead of deleted because there is linked invoice or payment history.');
        }

        $customer->delete();

        return redirect()
            ->route('customers.index')
            ->with('success', 'Customer deleted successfully.');
    }

    private function customerQueryForUser($user): Builder
    {
        return Customer::query()
            ->where('client_id', $user->client_id)
            ->where('is_active', true);
    }

    private function findScopedCustomerForUser($user, $customerId): Customer
    {
        return Customer::query()
            ->where('client_id', $user->client_id)
            ->findOrFail($customerId);
    }

    private function validateCustomer(Request $request, $user, ?int $customerId = null): array
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
                Rule::unique('customers', 'email')
                    ->where(fn ($query) => $query->where('client_id', $user->client_id))
                    ->ignore($customerId),
            ],
            'address' => ['nullable', 'string'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    private function approvedSalesQueryForCustomer(Customer $customer): Builder
    {
        return Sale::query()
            ->where('client_id', $customer->client_id)
            ->where('customer_id', $customer->id)
            ->where('status', 'approved')
            ->where('is_active', true);
    }

    private function syncCustomerOutstandingBalance(Customer $customer, ?float $liveOutstandingBalance = null): void
    {
        $liveOutstandingBalance ??= (float) (clone $this->approvedSalesQueryForCustomer($customer))->sum('balance_due');

        if (abs((float) $customer->outstanding_balance - $liveOutstandingBalance) > 0.009) {
            $customer->outstanding_balance = $liveOutstandingBalance;
            $customer->save();
            return;
        }

        $customer->outstanding_balance = $liveOutstandingBalance;
    }
}
