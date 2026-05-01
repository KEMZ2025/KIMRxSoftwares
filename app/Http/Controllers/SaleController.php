<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Insurer;
use App\Models\InsurancePayment;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Support\AuditTrail;
use App\Support\BatchReservationService;
use App\Support\ClientFeatureAccess;
use App\Support\Compliance\EfrisDocumentManager;
use App\Support\Printing\DocumentBranding;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SaleController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $filters = $this->rememberedSalesFilters($request, $user, 'index');

        $clientName = $user->client?->name ?? 'No Client';
        $branchName = $user->branch?->name ?? 'No Branch';
        $dispensers = $this->salesDispensersForUser($user, isset($filters['served_by']) ? (int) $filters['served_by'] : null);

        $sales = $this->applySalesFilters(
            $this->saleQueryForUser($user)->with(['customer', 'servedByUser']),
            $filters
        )
            ->latest('sale_date')
            ->latest('id')
            ->paginate(10)
            ->appends($this->salesFilterQuery($filters));

        $request->session()->put('sales.return.index', $this->salesRouteUrl('sales.index', $filters, $request->integer('page')));

        return view('sales.index', compact(
            'sales',
            'user',
            'clientName',
            'branchName',
            'filters',
            'dispensers'
        ));
    }

    public function create()
    {
        return $this->renderDraftSaleCreateView('pending');
    }

    public function createProforma()
    {
        return $this->renderDraftSaleCreateView('proforma');
    }

    public function store(Request $request)
    {
        return $this->storeDraftSaleDocument($request, 'pending');
    }

    public function storeProforma(Request $request)
    {
        return $this->storeDraftSaleDocument($request, 'proforma');
    }

    public function edit($sale)
    {
        $user = Auth::user();
        $sale = $this->findScopedSaleForUser($user, $sale, ['items.batch', 'items.product']);

        abort_if($sale->status !== 'pending', 404);

        return $this->renderDraftSaleEditView($sale, $user, 'pending');
    }

    public function editProforma($sale)
    {
        $user = Auth::user();
        $sale = $this->findScopedSaleForUser($user, $sale, ['items.batch', 'items.product']);

        abort_if($sale->status !== 'proforma', 404);

        return $this->renderDraftSaleEditView($sale, $user, 'proforma');
    }

    public function editApproved($sale)
    {
        $user = Auth::user();
        $sale = $this->findScopedSaleForUser($user, $sale, ['items.batch', 'items.product']);

        abort_if($sale->status !== 'approved', 404);

        $products = $this->productQueryForUser($user)->get();
        $customers = $this->customerQueryForUser($user)->get();
        $insurers = $this->insurerQueryForUser($user)->get();
        $saleTypeConfig = $this->saleTypeConfigurationForUser($user, $sale);
        $canManageDiscounts = $this->canManageSaleDiscounts($user);
        $canOverrideSalePrice = $this->canOverrideSalePrice($user);
        $insuranceEnabled = $this->insuranceEnabledForUser($user);

        $clientName = $user->client?->name ?? 'No Client';

        return view('sales.edit_approved', compact(
            'sale',
            'products',
            'customers',
            'insurers',
            'clientName',
            'saleTypeConfig',
            'canManageDiscounts',
            'canOverrideSalePrice',
            'insuranceEnabled'
        ));
    }

    public function show($sale)
    {
        $user = Auth::user();
        $sale = $this->findScopedSaleForUser($user, $sale, [
            'items.product',
            'items.batch',
            'customer',
            'insurer',
            'servedByUser',
            'approvedByUser',
            'cancelledByUser',
            'restoredByUser',
            'payments.receivedByUser',
            'payments.reversals.receivedByUser',
            'payments.originalPayment.receivedByUser',
            'insurancePayments.receivedByUser',
            'insurancePayments.reversals.receivedByUser',
            'insurancePayments.originalPayment.receivedByUser',
            'efrisDocument',
        ]);

        $clientName = $user->client?->name ?? 'No Client';
        $branchName = $user->branch?->name ?? 'No Branch';
        [$backUrl, $backLabel] = $this->salesBackLinkForShow(request(), $sale);
        $restoreStatus = $sale->status === 'cancelled'
            ? $this->resolveRestoreStatus($sale)
            : null;
        $branding = DocumentBranding::forUser($user);
        $printOptions = $this->salePrintOptionsForUser($user, $sale, $branding);
        $efrisEnabled = ClientFeatureAccess::efrisEnabled($user->clientSettingsModel())
            || $sale->efrisDocument !== null;
        $insuranceEnabled = $this->insuranceEnabledForUser($user) || $sale->isInsuranceSale();
        $insurers = $insuranceEnabled ? $this->insurerQueryForUser($user)->get() : collect();

        return view('sales.show', compact('sale', 'user', 'clientName', 'branchName', 'backUrl', 'backLabel', 'restoreStatus', 'printOptions', 'efrisEnabled', 'insuranceEnabled', 'insurers'));
    }

    public function printPos(Request $request, $sale)
    {
        $user = Auth::user();
        $branding = DocumentBranding::forUser($user);
        $sale = $this->findScopedSaleForUser($user, $sale, $this->salePrintRelations());
        $this->ensureSalePrintFormatAllowed($user, $sale, true, $branding);

        return view('prints.sales.pos', $this->salePrintViewData(
            $sale,
            $user,
            true,
            $request->boolean('autoprint', true),
            $branding
        ));
    }

    public function printA4(Request $request, $sale)
    {
        $user = Auth::user();
        $branding = DocumentBranding::forUser($user);
        $sale = $this->findScopedSaleForUser($user, $sale, $this->salePrintRelations());
        $this->ensureSalePrintFormatAllowed($user, $sale, false, $branding);

        return view('prints.sales.a4', $this->salePrintViewData(
            $sale,
            $user,
            false,
            $request->boolean('autoprint', true),
            $branding
        ));
    }

    public function pending()
    {
        $user = Auth::user();

        $clientName = $user->client?->name ?? 'No Client';
        $branchName = $user->branch?->name ?? 'No Branch';

        $sales = $this->saleQueryForUser($user)
            ->with(['customer', 'servedByUser'])
            ->where('status', 'pending')
            ->latest()
            ->paginate(10);

        return view('sales.pending', compact(
            'sales',
            'user',
            'clientName',
            'branchName'
        ));
    }

    public function proforma(Request $request)
    {
        $user = Auth::user();
        $filters = $this->rememberedSalesFilters($request, $user, 'proforma');

        $clientName = $user->client?->name ?? 'No Client';
        $branchName = $user->branch?->name ?? 'No Branch';
        $dispensers = $this->salesDispensersForUser($user, isset($filters['served_by']) ? (int) $filters['served_by'] : null);

        $sales = $this->applySalesFilters(
            $this->saleQueryForUser($user)
                ->with(['customer', 'servedByUser'])
                ->where('status', 'proforma'),
            $filters
        )
            ->latest('sale_date')
            ->latest('id')
            ->paginate(10)
            ->appends($this->salesFilterQuery($filters));

        $request->session()->put('sales.return.proforma', $this->salesRouteUrl('sales.proforma', $filters, $request->integer('page')));

        return view('sales.proforma', compact(
            'sales',
            'user',
            'clientName',
            'branchName',
            'filters',
            'dispensers'
        ));
    }

    public function approved(Request $request)
    {
        $user = Auth::user();
        $filters = $this->rememberedSalesFilters($request, $user, 'approved');

        $clientName = $user->client?->name ?? 'No Client';
        $branchName = $user->branch?->name ?? 'No Branch';
        $dispensers = $this->salesDispensersForUser($user, isset($filters['served_by']) ? (int) $filters['served_by'] : null);

        $sales = $this->applySalesFilters(
            $this->saleQueryForUser($user)
                ->with(['customer', 'servedByUser', 'efrisDocument'])
                ->where('status', 'approved'),
            $filters
        )
            ->latest('sale_date')
            ->latest('id')
            ->paginate(10)
            ->appends($this->salesFilterQuery($filters));

        $request->session()->put('sales.return.approved', $this->salesRouteUrl('sales.approved', $filters, $request->integer('page')));
        $efrisEnabled = ClientFeatureAccess::efrisEnabled($user->clientSettingsModel())
            || $sales->getCollection()->contains(fn (Sale $approvedSale) => $approvedSale->efrisDocument !== null);

        return view('sales.approved', compact(
            'sales',
            'user',
            'clientName',
            'branchName',
            'filters',
            'dispensers',
            'efrisEnabled'
        ));
    }

    public function cancelled(Request $request)
    {
        $user = Auth::user();
        $filters = $this->rememberedSalesFilters($request, $user, 'cancelled');

        $clientName = $user->client?->name ?? 'No Client';
        $branchName = $user->branch?->name ?? 'No Branch';
        $dispensers = $this->salesDispensersForUser($user, isset($filters['served_by']) ? (int) $filters['served_by'] : null);

        $sales = $this->applySalesFilters(
            $this->saleQueryForUser($user)
                ->with(['customer', 'servedByUser', 'cancelledByUser'])
                ->where('status', 'cancelled'),
            $filters
        )
            ->latest('cancelled_at')
            ->latest('sale_date')
            ->latest('id')
            ->paginate(10)
            ->appends($this->salesFilterQuery($filters));

        $request->session()->put('sales.return.cancelled', $this->salesRouteUrl('sales.cancelled', $filters, $request->integer('page')));

        return view('sales.cancelled', compact(
            'sales',
            'user',
            'clientName',
            'branchName',
            'filters',
            'dispensers'
        ));
    }

    public function productSaleBatches($productId)
    {
        $user = Auth::user();

        $batches = ProductBatch::where('product_id', $productId)
            ->where('client_id', $user->client_id)
            ->where('branch_id', $user->branch_id)
            ->where('is_active', true)
            ->orderBy('expiry_date')
            ->get();

        BatchReservationService::syncCollection($batches, $user->client_id, $user->branch_id);

        $batches = $batches
            ->map(function ($batch) {
                $available = (float) $batch->quantity_available;
                $reserved = (float) $batch->reserved_quantity;
                $free = max(0, $available - $reserved);

                return [
                    'id' => $batch->id,
                    'batch_number' => $batch->batch_number,
                    'expiry_date' => $batch->expiry_date ? $batch->expiry_date->format('Y-m-d') : null,
                    'purchase_price' => (float) $batch->purchase_price,
                    'retail_price' => (float) $batch->retail_price,
                    'wholesale_price' => (float) $batch->wholesale_price,
                    'quantity_available' => $available,
                    'reserved_quantity' => $reserved,
                    'free_stock' => $free,
                ];
            });

        return response()->json(['batches' => $batches]);
    }

    public function productSearch(Request $request)
    {
        $user = Auth::user();
        $term = trim((string) $request->get('q', ''));
        $showDispensingPriceGuide = $this->showDispensingPriceGuide($user);

        $rows = ProductBatch::query()
            ->with(['product', 'supplier'])
            ->where('client_id', $user->client_id)
            ->where('branch_id', $user->branch_id)
            ->where('is_active', true)
            ->when($term !== '', function ($q) use ($term) {
                $q->whereHas('product', function ($p) use ($term) {
                    $p->where('name', 'like', '%' . $term . '%');
                });
            })
            ->orderBy('expiry_date')
            ->limit(30)
            ->get();

        BatchReservationService::syncCollection($rows, $user->client_id, $user->branch_id);

        $rows = $rows
            ->map(function ($batch) use ($showDispensingPriceGuide) {
                $available = (float) $batch->quantity_available;
                $reserved = (float) $batch->reserved_quantity;
                $free = max(0, $available - $reserved);

                return [
                    'product_id' => $batch->product_id,
                    'product_name' => $batch->product?->name ?? '',
                    'batch_id' => $batch->id,
                    'batch_number' => $batch->batch_number ?? '',
                    'supplier_name' => $batch->supplier?->name ?? 'N/A',
                    'purchase_price' => (float) $batch->purchase_price,
                    'retail_price' => (float) $batch->retail_price,
                    'wholesale_price' => (float) $batch->wholesale_price,
                    'quantity_available' => $available,
                    'reserved_quantity' => $reserved,
                    'free_stock' => $free,
                    'expiry_date' => $batch->expiry_date ? $batch->expiry_date->format('Y-m-d') : 'N/A',
                    'dispensing_price_guide' => $showDispensingPriceGuide
                        ? ($batch->product?->normalizedDispensingPriceGuide() ?? [])
                        : [],
                ];
            })
            ->values();

        return response()->json($rows);
    }

    public function update(Request $request, $sale)
    {
        return $this->updateDraftSaleDocument($request, $sale, 'pending');
    }

    public function updateProforma(Request $request, $sale)
    {
        return $this->updateDraftSaleDocument($request, $sale, 'proforma');
    }

    public function updateApproved(Request $request, $sale)
    {
        $user = Auth::user();
        $sale = $this->findScopedSaleForUser($user, $sale, ['items', 'customer']);
        $beforeAudit = $this->saleAuditSnapshot($sale);

        abort_if($sale->status !== 'approved', 404);

        $validated = $this->validateSalePayload($request, $user, $sale);
        $this->ensureCustomerPresentWhenRequired($validated);
        $rows = $this->normalizeSaleRows($validated, $user, $sale);

        DB::beginTransaction();

        try {
            $sale = $this->findLockedSaleForUser($user, $sale->id, ['items', 'customer']);
            $previousCreditCustomerId = $this->creditCustomerIdForSale($sale);
            $previousBalanceDue = (float) $sale->balance_due;

            if ($sale->isInsuranceSale() && $this->activeInsuranceRemittancesExist($sale)) {
                throw ValidationException::withMessages([
                    'payment_type' => 'Approved insurance invoices cannot be edited after insurer remittances have been recorded. Reverse the remittances first if the invoice must change.',
                ]);
            }

            $this->releaseExistingSaleStock($sale);

            [$batchMap, $subtotal, $discountTotal] = $this->prepareRequestedBatches($user, $rows, $validated['sale_type']);

            $taxAmount = 0;
            $totalAmount = max(0, $subtotal - $discountTotal + $taxAmount);
            if (($validated['payment_type'] ?? 'cash') === 'insurance') {
                $approvalValidated = $validated;
                $approvalValidated['amount_received'] = (float) ($validated['insurance_patient_copay_amount'] ?? $sale->upfront_amount_paid);
                $financials = $this->approvedSaleFinancials($sale, $approvalValidated, $totalAmount);
                $amountReceived = (float) $financials['amount_received'];
                $amountPaid = (float) $financials['amount_paid'];
                $balanceDue = (float) $financials['balance_due'];
                $paymentMethod = $financials['payment_method'];
            } else {
                $amountReceived = $this->normalizedApprovedAmountReceived($sale);
                $amountPaid = min($amountReceived, $totalAmount);
                $balanceDue = max(0, $totalAmount - $amountPaid);
                $paymentMethod = $this->normalizedApprovedPaymentMethod($sale, $amountPaid);
                $financials = [
                    'insurer_id' => null,
                    'insurance_plan_name' => null,
                    'insurance_member_number' => null,
                    'insurance_card_number' => null,
                    'insurance_authorization_number' => null,
                    'insurance_claim_status' => null,
                    'insurance_status_notes' => null,
                    'insurance_covered_amount' => 0.0,
                    'patient_copay_amount' => 0.0,
                    'insurance_balance_due' => 0.0,
                    'upfront_amount_paid' => $amountPaid,
                ];
            }

            if (!$sale->receipt_number) {
                $sale->receipt_number = 'RCPT-' . str_pad((string) $sale->id, 6, '0', STR_PAD_LEFT);
            }

            $sale->fill([
                'customer_id' => $validated['customer_id'] ?? null,
                'insurer_id' => $financials['insurer_id'],
                'invoice_number' => $validated['invoice_number'],
                'sale_type' => $validated['sale_type'],
                'payment_type' => $validated['payment_type'],
                'insurance_plan_name' => $financials['insurance_plan_name'],
                'insurance_member_number' => $financials['insurance_member_number'],
                'insurance_card_number' => $financials['insurance_card_number'],
                'insurance_authorization_number' => $financials['insurance_authorization_number'],
                'insurance_claim_status' => $financials['insurance_claim_status'],
                'insurance_status_notes' => $financials['insurance_status_notes'],
                'subtotal' => $subtotal,
                'discount_amount' => $discountTotal,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'payment_method' => $paymentMethod,
                'amount_received' => $amountReceived,
                'amount_paid' => $amountPaid,
                'insurance_covered_amount' => $financials['insurance_covered_amount'],
                'patient_copay_amount' => $financials['patient_copay_amount'],
                'insurance_balance_due' => $financials['insurance_balance_due'],
                'upfront_amount_paid' => $financials['upfront_amount_paid'],
                'balance_due' => $balanceDue,
                'sale_date' => $validated['sale_date'],
                'notes' => $validated['notes'] ?? null,
            ]);

            if (($validated['payment_type'] ?? 'cash') === 'insurance') {
                $sale->insurance_claim_status = Sale::CLAIM_DRAFT;
                $sale->insurance_submitted_at = null;
                $sale->insurance_approved_at = null;
                $sale->insurance_rejected_at = null;
                $sale->insurance_paid_at = null;
            } else {
                $sale->insurance_submitted_at = null;
                $sale->insurance_approved_at = null;
                $sale->insurance_rejected_at = null;
                $sale->insurance_paid_at = null;
            }
            $sale->save();

            $this->syncSaleItemsAndStock($sale, $rows, $batchMap, 'deduct');
            $this->reconcileApprovedSaleOutstandingBalance($sale, $previousCreditCustomerId, $previousBalanceDue);

            DB::commit();

            $sale->unsetRelation('customer');
            $sale->unsetRelation('items');
            $sale->load(['customer', 'items']);

            app(AuditTrail::class)->recordSafely(
                $user,
                'sales.approved_sale_updated',
                'Sales',
                'Update Approved Sale',
                'Updated approved sale ' . $this->saleAuditLabel($sale) . '.',
                [
                    'subject' => $sale,
                    'subject_label' => $this->saleAuditLabel($sale),
                    'reason' => $validated['notes'] ?? null,
                    'old_values' => $beforeAudit,
                    'new_values' => $this->saleAuditSnapshot($sale),
                    'context' => [
                        'row_count' => count($rows),
                        'previous_balance_due' => $previousBalanceDue,
                    ],
                ]
            );

            return redirect()
                ->route('sales.show', $sale->id)
                ->with('success', 'Approved sale updated successfully. Existing payment received was preserved and the balance due was recalculated.');
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function approve(Request $request, $sale)
    {
        $user = Auth::user();
        $validated = $request->validate([
            'payment_type' => ['required', Rule::in($this->salePaymentTypesForUser($user))],
            'payment_method' => ['required', 'string', 'max:100'],
            'amount_received' => ['required', 'numeric', 'min:0'],
            'insurer_id' => [
                'nullable',
                Rule::exists('insurers', 'id')->where(function ($query) use ($user) {
                    $query->where('client_id', $user->client_id)
                        ->where('is_active', true);
                }),
            ],
            'insurance_plan_name' => ['nullable', 'string', 'max:255'],
            'insurance_member_number' => ['nullable', 'string', 'max:255'],
            'insurance_card_number' => ['nullable', 'string', 'max:255'],
            'insurance_authorization_number' => ['nullable', 'string', 'max:255'],
            'insurance_covered_amount' => ['nullable', 'numeric', 'min:0'],
            'insurance_patient_copay_amount' => ['nullable', 'numeric', 'min:0'],
            'insurance_status_notes' => ['nullable', 'string'],
        ]);

        $sale = $this->findScopedSaleForUser($user, $sale, ['customer', 'items', 'insurer']);
        $beforeAudit = $this->saleAuditSnapshot($sale);

        if ($sale->status === 'approved') {
            return redirect()
                ->route('sales.show', $sale->id)
                ->with('success', 'Sale is already approved.');
        }

        if ($sale->status !== 'pending') {
            return redirect()
                ->route('sales.show', $sale->id)
                ->withErrors(['amount_received' => 'Only pending sales can be approved.']);
        }

        if ($this->saleRequiresCustomer((string) $sale->sale_type, (string) $validated['payment_type']) && !$sale->customer_id) {
            throw ValidationException::withMessages([
                'payment_type' => 'Select the customer on the pending invoice before approval.',
            ]);
        }

        $totalAmount = (float) $sale->total_amount;
        $financials = $this->approvedSaleFinancials($sale, $validated, $totalAmount);
        $amountReceived = (float) $financials['amount_received'];
        $amountPaid = (float) $financials['amount_paid'];
        $balanceDue = (float) $financials['balance_due'];

        DB::transaction(function () use ($sale, $validated, $financials, $amountReceived, $amountPaid, $balanceDue) {
            foreach ($sale->items as $item) {
                $batch = ProductBatch::query()
                    ->where('id', $item->product_batch_id)
                    ->where('client_id', $sale->client_id)
                    ->where('branch_id', $sale->branch_id)
                    ->lockForUpdate()
                    ->first();

                if (!$batch) {
                    throw new \RuntimeException('Batch not found for one of the sale items.');
                }

                $qty = (float) $item->quantity;

                $batch->quantity_available = max(0, (float) $batch->quantity_available - $qty);
                $batch->reserved_quantity = max(0, (float) $batch->reserved_quantity - $qty);
                $batch->save();
            }

            $sale->payment_type = $validated['payment_type'];
            $sale->payment_method = $financials['payment_method'];
            $sale->amount_received = $amountReceived;
            $sale->amount_paid = $amountPaid;
            $sale->balance_due = $balanceDue;
            $sale->insurer_id = $financials['insurer_id'];
            $sale->insurance_plan_name = $financials['insurance_plan_name'];
            $sale->insurance_member_number = $financials['insurance_member_number'];
            $sale->insurance_card_number = $financials['insurance_card_number'];
            $sale->insurance_authorization_number = $financials['insurance_authorization_number'];
            $sale->insurance_claim_status = $financials['insurance_claim_status'];
            $sale->insurance_status_notes = $financials['insurance_status_notes'];
            $sale->insurance_covered_amount = $financials['insurance_covered_amount'];
            $sale->patient_copay_amount = $financials['patient_copay_amount'];
            $sale->insurance_balance_due = $financials['insurance_balance_due'];
            $sale->upfront_amount_paid = $financials['upfront_amount_paid'];
            $sale->status = 'approved';
            $sale->approved_by = Auth::id();
            $sale->approved_at = now();
            $sale->insurance_submitted_at = null;
            $sale->insurance_approved_at = null;
            $sale->insurance_rejected_at = null;
            $sale->insurance_paid_at = null;

            if (!$sale->receipt_number) {
                $sale->receipt_number = 'RCPT-' . str_pad((string) $sale->id, 6, '0', STR_PAD_LEFT);
            }

            $sale->save();

            if ($sale->payment_type === 'credit' && $sale->customer_id && $balanceDue > 0) {
                $this->adjustCustomerOutstandingBalance((int) $sale->customer_id, $balanceDue);
            }

            EfrisDocumentManager::syncApprovedSale($sale);
        });

        $sale->refresh()->loadMissing(['customer', 'items']);

        app(AuditTrail::class)->recordSafely(
            $user,
            'sales.approved',
            'Sales',
            'Approve Sale',
            'Approved sale ' . $this->saleAuditLabel($sale) . '.',
            [
                'subject' => $sale,
                'subject_label' => $this->saleAuditLabel($sale),
                'old_values' => $beforeAudit,
                'new_values' => $this->saleAuditSnapshot($sale),
                'context' => [
                    'payment_method_requested' => $financials['payment_method'],
                    'amount_received' => $amountReceived,
                ],
            ]
        );

        return redirect()
            ->route('sales.show', $sale->id)
            ->with('success', 'Sale approved successfully.');
    }

    public function convertProformaToPending($sale)
    {
        $user = Auth::user();
        $sale = $this->findScopedSaleForUser($user, $sale, ['items']);

        abort_if($sale->status !== 'proforma', 404);

        DB::transaction(function () use ($sale, $user) {
            $sale = $this->findLockedSaleForUser($user, $sale->id, ['items']);

            if ($sale->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'sale' => 'Add at least one item before converting this proforma invoice to a pending sale.',
                ]);
            }

            $rows = $this->saleRowsFromItems($sale);
            [$batchMap, $subtotal, $discountTotal] = $this->prepareRequestedBatches($user, $rows, $sale->sale_type);

            $taxAmount = 0;
            $totalAmount = max(0, $subtotal - $discountTotal + $taxAmount);

            $sale->fill([
                'status' => 'pending',
                'subtotal' => $subtotal,
                'discount_amount' => $discountTotal,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'amount_paid' => 0,
                'amount_received' => 0,
                'balance_due' => $totalAmount,
                'payment_method' => null,
                'receipt_number' => null,
                'is_active' => true,
            ]);
            $sale->save();

            $this->syncSaleItemsAndStock($sale, $rows, $batchMap, 'reserve');
        });

        return redirect()
            ->route('sales.show', $sale->id)
            ->with('success', 'Proforma invoice converted to a pending sale. Stock is now reserved against the selected batches.');
    }

    public function cancel(Request $request, $sale)
    {
        $user = Auth::user();
        $validated = $request->validate([
            'cancel_reason' => ['required', 'string', 'max:1000'],
        ]);

        $sale = $this->findScopedSaleForUser($user, $sale, ['items', 'customer']);
        $beforeAudit = $this->saleAuditSnapshot($sale);

        if ($sale->status === 'cancelled') {
            return redirect()
                ->route('sales.show', $sale->id)
                ->with('success', 'Sale is already cancelled.');
        }

        DB::transaction(function () use ($sale, $validated, $user) {
            $sale = $this->findLockedSaleForUser($user, $sale->id, ['items', 'customer']);

            if ($sale->status === 'approved' && $this->saleHasActiveCustomerCollections($sale)) {
                throw ValidationException::withMessages([
                    'cancel_reason' => 'Reverse the invoice payments recorded on this sale before cancelling it.',
                ]);
            }

            if ($sale->status === 'approved' && $sale->isInsuranceSale() && $this->activeInsuranceRemittancesExist($sale)) {
                throw ValidationException::withMessages([
                    'cancel_reason' => 'Reverse the insurer remittances recorded on this invoice before cancelling it.',
                ]);
            }

            $statusBeforeCancellation = $sale->status;

            foreach ($sale->items as $item) {
                $batch = ProductBatch::query()
                    ->where('id', $item->product_batch_id)
                    ->where('client_id', $sale->client_id)
                    ->where('branch_id', $sale->branch_id)
                    ->lockForUpdate()
                    ->first();

                if (!$batch) {
                    continue;
                }

                $qty = (float) $item->quantity;

                if ($sale->status === 'pending') {
                    $batch->reserved_quantity = max(0, (float) $batch->reserved_quantity - $qty);
                } elseif ($sale->status === 'approved') {
                    $batch->quantity_available = (float) $batch->quantity_available + $qty;
                    $batch->reserved_quantity = max(0, (float) $batch->reserved_quantity - $qty);
                }

                $batch->save();
            }

            if (
                $statusBeforeCancellation === 'approved' &&
                $sale->payment_type === 'credit' &&
                $sale->customer_id &&
                (float) $sale->balance_due > 0
            ) {
                $this->adjustCustomerOutstandingBalance((int) $sale->customer_id, -1 * (float) $sale->balance_due);
            }

            $sale->status = 'cancelled';
            $sale->is_active = false;
            $sale->cancelled_from_status = $statusBeforeCancellation;
            $sale->cancelled_by = $user->id;
            $sale->cancelled_at = now();
            $sale->cancel_reason = $validated['cancel_reason'];
            $sale->restored_by = null;
            $sale->restored_at = null;
            $sale->restore_reason = null;

            $sale->save();

            if ($statusBeforeCancellation === 'approved') {
                EfrisDocumentManager::markReversalRequired($sale, $validated['cancel_reason']);
            }
        });

        $sale->refresh()->loadMissing(['customer', 'items']);

        app(AuditTrail::class)->recordSafely(
            $user,
            'sales.cancelled',
            'Sales',
            'Cancel Sale',
            'Cancelled sale ' . $this->saleAuditLabel($sale) . '.',
            [
                'subject' => $sale,
                'subject_label' => $this->saleAuditLabel($sale),
                'reason' => $validated['cancel_reason'],
                'old_values' => $beforeAudit,
                'new_values' => $this->saleAuditSnapshot($sale),
                'context' => [
                    'cancelled_from_status' => $sale->cancelled_from_status,
                ],
            ]
        );

        return redirect()
            ->route('sales.show', $sale->id)
            ->with('success', 'Sale cancelled successfully.');
    }

    public function restore(Request $request, $sale)
    {
        $user = Auth::user();
        $validated = $request->validate([
            'restore_reason' => ['required', 'string', 'max:1000'],
        ]);

        $sale = $this->findScopedSaleForUser($user, $sale, ['items', 'customer']);
        $beforeAudit = $this->saleAuditSnapshot($sale);

        if ($sale->status !== 'cancelled') {
            return redirect()
                ->route('sales.show', $sale->id)
                ->with('success', 'Only cancelled sales can be restored.');
        }

        DB::transaction(function () use ($sale, $validated, $user) {
            $sale = $this->findLockedSaleForUser($user, $sale->id, ['items', 'customer']);
            $restoreStatus = $this->resolveRestoreStatus($sale);

            $batchIds = $sale->items
                ->pluck('product_batch_id')
                ->filter()
                ->unique()
                ->values()
                ->all();

            $batches = ProductBatch::query()
                ->whereIn('id', $batchIds)
                ->where('client_id', $sale->client_id)
                ->where('branch_id', $sale->branch_id)
                ->where('is_active', true)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if (in_array($restoreStatus, ['pending', 'approved'], true)) {
                foreach ($sale->items as $item) {
                    $batch = $batches->get($item->product_batch_id);

                    if (!$batch) {
                        throw ValidationException::withMessages([
                            'restore_reason' => 'One of the original batches for this sale is no longer available for restoration.',
                        ]);
                    }

                    $qty = (float) $item->quantity;
                    $freeStock = $this->batchFreeStock($batch);

                    if ($qty > $freeStock) {
                        throw ValidationException::withMessages([
                            'restore_reason' => 'Cannot restore invoice ' . ($sale->invoice_number ?? $sale->id) . ' because batch ' . $batch->batch_number . ' only has ' . number_format($freeStock, 2) . ' free stock left.',
                        ]);
                    }
                }

                foreach ($sale->items as $item) {
                    $batch = $batches->get($item->product_batch_id);
                    $qty = (float) $item->quantity;

                    if ($restoreStatus === 'approved') {
                        $batch->quantity_available = max(0, (float) $batch->quantity_available - $qty);
                    } else {
                        $batch->reserved_quantity = (float) $batch->reserved_quantity + $qty;
                    }

                    $batch->save();
                }
            }

            if (
                $restoreStatus === 'approved' &&
                $sale->payment_type === 'credit' &&
                $sale->customer_id &&
                (float) $sale->balance_due > 0
            ) {
                $this->adjustCustomerOutstandingBalance((int) $sale->customer_id, (float) $sale->balance_due);
            }

            $sale->status = $restoreStatus;
            $sale->is_active = true;
            $sale->restored_by = $user->id;
            $sale->restored_at = now();
            $sale->restore_reason = $validated['restore_reason'];
            $sale->save();

            if ($restoreStatus === 'approved') {
                EfrisDocumentManager::syncApprovedSale($sale);
            }
        });

        $sale->refresh()->loadMissing(['customer', 'items']);

        app(AuditTrail::class)->recordSafely(
            $user,
            'sales.restored',
            'Sales',
            'Restore Sale',
            'Restored sale ' . $this->saleAuditLabel($sale) . '.',
            [
                'subject' => $sale,
                'subject_label' => $this->saleAuditLabel($sale),
                'reason' => $validated['restore_reason'],
                'old_values' => $beforeAudit,
                'new_values' => $this->saleAuditSnapshot($sale),
                'context' => [
                    'restored_status' => $sale->status,
                ],
            ]
        );

        return redirect()
            ->route('sales.show', $sale->id)
            ->with('success', 'Cancelled sale restored successfully.');
    }

    private function saleQueryForUser($user): Builder
    {
        return Sale::query()
            ->where('client_id', $user->client_id)
            ->where('branch_id', $user->branch_id)
            ->operational();
    }

    private function validatedSalesFilters(Request $request, $user): array
    {
        return $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'search' => ['nullable', 'string', 'max:255'],
            'served_by' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(function ($query) use ($user) {
                    $query->where('client_id', $user->client_id)
                        ->where('branch_id', $user->branch_id);
                }),
            ],
        ]);
    }

    private function rememberedSalesFilters(Request $request, $user, string $scope): array
    {
        $sessionKey = 'sales.filters.' . $scope;

        if ($request->boolean('clear_filters')) {
            $request->session()->forget($sessionKey);

            return [];
        }

        $filterKeys = ['date_from', 'date_to', 'search', 'served_by'];
        $hasIncomingFilters = collect($filterKeys)->contains(fn (string $key) => $request->has($key));

        if ($hasIncomingFilters) {
            $filters = $this->normalizeSalesFilters($this->validatedSalesFilters($request, $user));
            $request->session()->put($sessionKey, $filters);

            return $filters;
        }

        $remembered = $request->session()->get($sessionKey, []);

        return is_array($remembered)
            ? $this->normalizeSalesFilters($remembered)
            : [];
    }

    private function normalizeSalesFilters(array $filters): array
    {
        return collect($filters)
            ->only(['date_from', 'date_to', 'search', 'served_by'])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->map(function ($value, $key) {
                if ($key === 'served_by') {
                    return (int) $value;
                }

                if ($key === 'search') {
                    return trim((string) $value);
                }

                return $value;
            })
            ->all();
    }

    private function applySalesFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when(!empty($filters['date_from']), function (Builder $builder) use ($filters) {
                $builder->whereDate('sale_date', '>=', $filters['date_from']);
            })
            ->when(!empty($filters['date_to']), function (Builder $builder) use ($filters) {
                $builder->whereDate('sale_date', '<=', $filters['date_to']);
            })
            ->when(!empty($filters['served_by']), function (Builder $builder) use ($filters) {
                $builder->where('served_by', (int) $filters['served_by']);
            })
            ->when(!empty($filters['search']), function (Builder $builder) use ($filters) {
                $search = trim((string) $filters['search']);

                $builder->where(function (Builder $nested) use ($search) {
                    $nested->where('invoice_number', 'like', '%' . $search . '%')
                        ->orWhere('receipt_number', 'like', '%' . $search . '%')
                        ->orWhereHas('customer', function (Builder $customerQuery) use ($search) {
                            $customerQuery->where('name', 'like', '%' . $search . '%');
                        });
                });
            });
    }

    private function salesFilterQuery(array $filters, ?int $page = null): array
    {
        $query = $this->normalizeSalesFilters($filters);

        if ($page && $page > 1) {
            $query['page'] = $page;
        }

        return $query;
    }

    private function salesRouteUrl(string $routeName, array $filters, ?int $page = null): string
    {
        return route($routeName, $this->salesFilterQuery($filters, $page));
    }

    private function salesBackLinkForShow(Request $request, Sale $sale): array
    {
        $returnTo = (string) $request->query('return_to', '');
        $query = $request->only(['date_from', 'date_to', 'search', 'served_by']);
        $page = $request->integer('page');

        if ($returnTo === 'sales.index') {
            return [$this->salesRouteUrl('sales.index', $query, $page), 'Back to Sales'];
        }

        if ($returnTo === 'sales.approved') {
            return [$this->salesRouteUrl('sales.approved', $query, $page), 'Back to Approved Sales'];
        }

        if ($returnTo === 'sales.cancelled') {
            return [$this->salesRouteUrl('sales.cancelled', $query, $page), 'Back to Cancelled Sales'];
        }

        if ($returnTo === 'sales.proforma') {
            return [$this->salesRouteUrl('sales.proforma', $query, $page), 'Back to Proforma Invoices'];
        }

        if ($sale->status === 'approved') {
            return [
                $request->session()->get('sales.return.approved', route('sales.approved')),
                'Back to Approved Sales',
            ];
        }

        if ($sale->status === 'cancelled') {
            return [
                $request->session()->get('sales.return.cancelled', route('sales.cancelled')),
                'Back to Cancelled Sales',
            ];
        }

        if ($sale->status === 'proforma') {
            return [
                $request->session()->get('sales.return.proforma', route('sales.proforma')),
                'Back to Proforma Invoices',
            ];
        }

        if ($sale->status === 'pending') {
            return [route('sales.pending'), 'Back to Pending Sales'];
        }

        return [
            $request->session()->get('sales.return.index', route('sales.index')),
            'Back to Sales',
        ];
    }

    private function salesDispensersForUser($user, ?int $selectedDispenserId = null)
    {
        return User::query()
            ->where('client_id', $user->client_id)
            ->where('branch_id', $user->branch_id)
            ->where(function (Builder $query) use ($selectedDispenserId) {
                $query->where('is_active', true);

                if ($selectedDispenserId) {
                    $query->orWhere('id', $selectedDispenserId);
                }
            })
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function productQueryForUser($user): Builder
    {
        return Product::query()
            ->where('client_id', $user->client_id)
            ->where('is_active', true)
            ->orderBy('name');
    }

    private function customerQueryForUser($user): Builder
    {
        return Customer::query()
            ->where('client_id', $user->client_id)
            ->when(Schema::hasColumn('customers', 'is_active'), fn (Builder $query) => $query->where('is_active', true))
            ->orderBy('name');
    }

    private function insurerQueryForUser($user): Builder
    {
        return Insurer::query()
            ->where('client_id', $user->client_id)
            ->where('is_active', true)
            ->orderBy('name');
    }

    private function insuranceEnabledForUser($user): bool
    {
        return ClientFeatureAccess::insuranceEnabled($user->clientSettingsModel());
    }

    private function salePaymentTypesForUser($user): array
    {
        $types = ['cash', 'credit'];

        if ($this->insuranceEnabledForUser($user)) {
            $types[] = 'insurance';
        }

        return $types;
    }

    private function findScopedSaleForUser($user, $saleId, array $with = []): Sale
    {
        return $this->saleQueryForUser($user)
            ->with($with)
            ->findOrFail($saleId);
    }

    private function salePrintRelations(): array
    {
        return [
            'items' => fn ($query) => $query->select([
                'id',
                'sale_id',
                'product_id',
                'product_batch_id',
                'quantity',
                'unit_price',
                'total_amount',
            ]),
            'items.product:id,name',
            'items.batch:id,batch_number,expiry_date',
            'customer:id,name,phone,alt_phone,contact_person,address,email',
            'servedByUser:id,name',
            'approvedByUser:id,name',
        ];
    }

    private function salePrintViewData(Sale $sale, $user, bool $isSmallFormat, bool $autoPrint, ?array $branding = null): array
    {
        $branding = $branding ?? DocumentBranding::forUser($user);
        $documentTitle = match ($sale->status) {
            'approved' => 'Sales Receipt',
            'proforma' => 'Proforma Invoice',
            default => 'Pending Sales Invoice',
        };

        $documentBadge = match ($sale->status) {
            'approved' => 'Approved',
            'proforma' => 'Proforma',
            default => 'Pending',
        };

        $paymentMethod = trim((string) ($sale->payment_method ?? ''));
        $paymentMethodLabel = $paymentMethod !== ''
            ? ucwords(str_replace('_', ' ', $paymentMethod))
            : ($sale->status === 'approved' ? 'Not captured' : 'Pending approval');

        $displayItems = $sale->items->map(function (SaleItem $item) {
            $quantity = (float) $item->quantity;
            $lineTotal = (float) $item->total_amount;
            $netUnitPrice = $quantity > 0 ? round($lineTotal / $quantity, 2) : (float) $item->unit_price;

            return [
                'product_name' => $item->product?->name ?? 'Unknown Product',
                'batch_number' => $item->batch?->batch_number ?? 'N/A',
                'expiry_date' => $item->batch?->expiry_date?->format('d M Y') ?? 'N/A',
                'quantity' => $quantity,
                'unit_price' => $netUnitPrice,
                'line_total' => $lineTotal,
            ];
        });

        return [
            'sale' => $sale,
            'branding' => $branding,
            'autoPrint' => $autoPrint,
            'isSmallFormat' => $isSmallFormat,
            'documentTitle' => $documentTitle,
            'documentBadge' => $documentBadge,
            'documentFooter' => $sale->status === 'approved'
                ? ($branding['receipt_footer'] ?: $branding['invoice_footer'])
                : ($branding['invoice_footer'] ?: $branding['receipt_footer']),
            'paymentMethodLabel' => $paymentMethodLabel,
            'displayItems' => $displayItems,
        ];
    }

    private function ensureSalePrintFormatAllowed($user, Sale $sale, bool $isSmallFormat, ?array $branding = null): void
    {
        $allowed = $this->salePrintOptionsForUser($user, $sale, $branding)[$isSmallFormat ? 'small' : 'large'];

        abort_unless($allowed, 404);
    }

    private function salePrintOptionsForUser($user, Sale $sale, ?array $branding = null): array
    {
        $settings = ($branding ?? DocumentBranding::forUser($user))['settings'];

        return match ($sale->status) {
            'approved' => [
                'small' => (bool) $settings->allow_small_receipt,
                'large' => (bool) $settings->allow_large_receipt,
            ],
            'proforma' => [
                'small' => (bool) $settings->allow_small_proforma,
                'large' => (bool) $settings->allow_large_proforma,
            ],
            default => [
                'small' => (bool) $settings->allow_small_invoice,
                'large' => (bool) $settings->allow_large_invoice,
            ],
        };
    }

    private function findLockedSaleForUser($user, $saleId, array $with = []): Sale
    {
        return $this->saleQueryForUser($user)
            ->with($with)
            ->lockForUpdate()
            ->findOrFail($saleId);
    }

    private function saleHasActiveCustomerCollections(Sale $sale): bool
    {
        $payments = $sale->payments()
            ->with('reversals')
            ->whereNull('reversal_of_payment_id')
            ->where('status', 'received')
            ->get();

        foreach ($payments as $payment) {
            if ((float) $payment->available_to_reverse > 0.009) {
                return true;
            }
        }

        return false;
    }

    private function resolveRestoreStatus(Sale $sale): string
    {
        $status = (string) ($sale->cancelled_from_status ?? '');

        if (in_array($status, ['pending', 'approved', 'proforma'], true)) {
            return $status;
        }

        if (
            $sale->receipt_number ||
            (float) $sale->amount_received > 0 ||
            (float) $sale->amount_paid > 0 ||
            ($sale->payment_method !== null && $sale->payment_method !== '')
        ) {
            return 'approved';
        }

        return 'pending';
    }

    private function saleAuditSnapshot(Sale $sale): array
    {
        $sale->loadMissing('customer:id,name', 'insurer:id,name');

        return [
            'status' => $sale->status,
            'invoice_number' => $sale->invoice_number,
            'receipt_number' => $sale->receipt_number,
            'sale_type' => $sale->sale_type,
            'payment_type' => $sale->payment_type,
            'payment_method' => $sale->payment_method,
            'customer_id' => $sale->customer_id,
            'customer_name' => $sale->customer?->name,
            'insurer_id' => $sale->insurer_id,
            'insurer_name' => $sale->insurer?->name,
            'total_amount' => round((float) $sale->total_amount, 2),
            'amount_paid' => round((float) $sale->amount_paid, 2),
            'amount_received' => round((float) $sale->amount_received, 2),
            'balance_due' => round((float) $sale->balance_due, 2),
            'discount_amount' => round((float) $sale->discount_amount, 2),
            'insurance_covered_amount' => round((float) $sale->insurance_covered_amount, 2),
            'patient_copay_amount' => round((float) $sale->patient_copay_amount, 2),
            'insurance_balance_due' => round((float) $sale->insurance_balance_due, 2),
            'upfront_amount_paid' => round((float) $sale->upfront_amount_paid, 2),
            'insurance_claim_status' => $sale->insurance_claim_status,
            'insurance_member_number' => $sale->insurance_member_number,
            'insurance_card_number' => $sale->insurance_card_number,
            'insurance_authorization_number' => $sale->insurance_authorization_number,
            'item_count' => $sale->relationLoaded('items') ? $sale->items->count() : null,
            'sale_date' => $sale->sale_date?->format('Y-m-d H:i:s'),
            'approved_by' => $sale->approved_by,
            'cancelled_from_status' => $sale->cancelled_from_status,
        ];
    }

    private function saleAuditLabel(Sale $sale): string
    {
        if ($sale->invoice_number) {
            return (string) $sale->invoice_number;
        }

        if ($sale->receipt_number) {
            return (string) $sale->receipt_number;
        }

        return 'Sale #' . $sale->id;
    }

    private function renderDraftSaleCreateView(string $documentStatus)
    {
        $user = Auth::user();
        $saleTypeConfig = $this->saleTypeConfigurationForUser($user);
        $canManageDiscounts = $this->canManageSaleDiscounts($user);
        $canOverrideSalePrice = $this->canOverrideSalePrice($user);
        $showDispensingPriceGuide = $this->showDispensingPriceGuide($user);
        $insuranceEnabled = $this->insuranceEnabledForUser($user);

        $products = $this->productQueryForUser($user)->get();
        $customers = $this->customerQueryForUser($user)->get();
        $insurers = $insuranceEnabled ? $this->insurerQueryForUser($user)->get() : collect();

        $clientName = $user->client?->name ?? 'No Client';
        $branchName = $user->branch?->name ?? 'No Branch';

        $nextNumber = Sale::where('client_id', $user->client_id)->count() + 1;
        $retailInvoiceNumber = 'RINV-' . str_pad((string) $nextNumber, 5, '0', STR_PAD_LEFT);
        $wholesaleInvoiceNumber = 'WINV-' . str_pad((string) $nextNumber, 5, '0', STR_PAD_LEFT);
        $proformaInvoiceNumber = 'PINV-' . str_pad((string) $nextNumber, 5, '0', STR_PAD_LEFT);
        $defaultSaleType = $this->resolveDraftDefaultSaleType($saleTypeConfig);

        $allowAddOneLine = true;
        $allowAddFiveLines = true;
        $isProforma = $documentStatus === 'proforma';
        $pageTitle = $isProforma ? 'New Proforma Invoice' : 'New Sale';
        $pageDescription = $this->draftSalePageDescription($documentStatus, $saleTypeConfig['effective_mode']);
        $invoiceNumber = $isProforma
            ? $proformaInvoiceNumber
            : ($defaultSaleType === 'wholesale' ? $wholesaleInvoiceNumber : $retailInvoiceNumber);
        $formAction = $isProforma ? route('sales.proforma.store') : route('sales.store');
        $saveButtonLabel = $isProforma ? 'Save Proforma Invoice' : 'Save Pending Sale';
        $saveModeLabel = $isProforma ? 'Proforma Invoice (No stock reservation)' : 'Pending Sale';

        return view('sales.create', compact(
            'products',
            'customers',
            'clientName',
            'branchName',
            'retailInvoiceNumber',
            'wholesaleInvoiceNumber',
            'proformaInvoiceNumber',
            'invoiceNumber',
            'defaultSaleType',
            'allowAddOneLine',
            'allowAddFiveLines',
            'isProforma',
            'pageTitle',
            'pageDescription',
            'formAction',
            'saveButtonLabel',
            'saveModeLabel',
            'saleTypeConfig',
            'canManageDiscounts',
            'canOverrideSalePrice',
            'showDispensingPriceGuide',
            'insuranceEnabled',
            'insurers'
        ));
    }

    private function renderDraftSaleEditView(Sale $sale, $user, string $documentStatus)
    {
        $saleTypeConfig = $this->saleTypeConfigurationForUser($user, $sale);
        $canManageDiscounts = $this->canManageSaleDiscounts($user);
        $canOverrideSalePrice = $this->canOverrideSalePrice($user);
        $showDispensingPriceGuide = $this->showDispensingPriceGuide($user);
        $insuranceEnabled = $this->insuranceEnabledForUser($user);
        $products = $this->productQueryForUser($user)->get();
        $customers = $this->customerQueryForUser($user)->get();
        $insurers = $insuranceEnabled ? $this->insurerQueryForUser($user)->get() : collect();
        $clientName = $user->client?->name ?? 'No Client';
        $isProforma = $documentStatus === 'proforma';
        $pageTitle = $isProforma ? 'Edit Proforma Invoice' : 'Edit Pending Sale';
        $pageDescription = $isProforma
            ? 'Update a proforma invoice without reserving or deducting stock'
            : 'Continue from where you stopped';
        $updateAction = $isProforma
            ? route('sales.updateProforma', $sale->id)
            : route('sales.update', $sale->id);
        $updateButtonLabel = $isProforma ? 'Update Proforma Invoice' : 'Update Pending Sale';

        return view('sales.edit', compact(
            'sale',
            'products',
            'customers',
            'clientName',
            'isProforma',
            'pageTitle',
            'pageDescription',
            'updateAction',
            'updateButtonLabel',
            'saleTypeConfig',
            'canManageDiscounts',
            'canOverrideSalePrice',
            'showDispensingPriceGuide',
            'insuranceEnabled',
            'insurers'
        ));
    }

    private function storeDraftSaleDocument(Request $request, string $documentStatus)
    {
        $user = Auth::user();
        $validated = $this->validateSalePayload($request, $user);
        $this->ensureCustomerPresentWhenRequired($validated);
        $rows = $this->normalizeSaleRows($validated, $user);

        DB::beginTransaction();

        try {
            [$batchMap, $subtotal, $discountTotal] = $this->prepareRequestedBatches($user, $rows, $validated['sale_type']);

            $taxAmount = 0;
            $totalAmount = max(0, $subtotal - $discountTotal + $taxAmount);
            $insuranceFields = $this->draftInsuranceFields($validated, $totalAmount);

            $sale = Sale::create([
                'client_id' => $user->client_id,
                'branch_id' => $user->branch_id,
                'customer_id' => $validated['customer_id'] ?? null,
                'insurer_id' => $insuranceFields['insurer_id'],
                'served_by' => $user->id,
                'invoice_number' => $validated['invoice_number'],
                'receipt_number' => null,
                'sale_type' => $validated['sale_type'],
                'status' => $documentStatus,
                'payment_type' => $validated['payment_type'],
                'payment_method' => null,
                'insurance_plan_name' => $insuranceFields['insurance_plan_name'],
                'insurance_member_number' => $insuranceFields['insurance_member_number'],
                'insurance_card_number' => $insuranceFields['insurance_card_number'],
                'insurance_authorization_number' => $insuranceFields['insurance_authorization_number'],
                'insurance_claim_status' => $insuranceFields['insurance_claim_status'],
                'insurance_status_notes' => $insuranceFields['insurance_status_notes'],
                'subtotal' => $subtotal,
                'discount_amount' => $discountTotal,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'amount_paid' => 0,
                'amount_received' => 0,
                'insurance_covered_amount' => $insuranceFields['insurance_covered_amount'],
                'patient_copay_amount' => $insuranceFields['patient_copay_amount'],
                'insurance_balance_due' => $insuranceFields['insurance_balance_due'],
                'upfront_amount_paid' => 0,
                'balance_due' => $totalAmount,
                'sale_date' => $validated['sale_date'],
                'notes' => $validated['notes'] ?? null,
                'is_active' => true,
            ]);

            $this->syncSaleItemsAndStock(
                $sale,
                $rows,
                $batchMap,
                $documentStatus === 'pending' ? 'reserve' : 'none'
            );

            DB::commit();

            return redirect()
                ->route($documentStatus === 'proforma' ? 'sales.proforma' : 'sales.pending')
                ->with(
                    'success',
                    $documentStatus === 'proforma'
                        ? 'Proforma invoice saved successfully. Stock was not reserved.'
                        : 'Pending sale saved successfully.'
                );
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function updateDraftSaleDocument(Request $request, $saleId, string $documentStatus)
    {
        $user = Auth::user();
        $sale = $this->findScopedSaleForUser($user, $saleId, ['items']);

        abort_if($sale->status !== $documentStatus, 404);

        $validated = $this->validateSalePayload($request, $user, $sale);
        $this->ensureCustomerPresentWhenRequired($validated);
        $rows = $this->normalizeSaleRows($validated, $user, $sale);

        DB::beginTransaction();

        try {
            $sale = $this->findLockedSaleForUser($user, $sale->id, ['items']);

            if ($documentStatus === 'pending') {
                $this->releaseExistingSaleStock($sale);
            }

            [$batchMap, $subtotal, $discountTotal] = $this->prepareRequestedBatches(
                $user,
                $rows,
                $validated['sale_type'],
                $documentStatus === 'pending' ? $sale->id : null
            );

            $taxAmount = 0;
            $totalAmount = max(0, $subtotal - $discountTotal + $taxAmount);
            $insuranceFields = $this->draftInsuranceFields($validated, $totalAmount);

            $sale->fill([
                'customer_id' => $validated['customer_id'] ?? null,
                'insurer_id' => $insuranceFields['insurer_id'],
                'invoice_number' => $validated['invoice_number'],
                'sale_type' => $validated['sale_type'],
                'payment_type' => $validated['payment_type'],
                'insurance_plan_name' => $insuranceFields['insurance_plan_name'],
                'insurance_member_number' => $insuranceFields['insurance_member_number'],
                'insurance_card_number' => $insuranceFields['insurance_card_number'],
                'insurance_authorization_number' => $insuranceFields['insurance_authorization_number'],
                'insurance_claim_status' => $insuranceFields['insurance_claim_status'],
                'insurance_status_notes' => $insuranceFields['insurance_status_notes'],
                'subtotal' => $subtotal,
                'discount_amount' => $discountTotal,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'amount_paid' => 0,
                'amount_received' => 0,
                'insurance_covered_amount' => $insuranceFields['insurance_covered_amount'],
                'patient_copay_amount' => $insuranceFields['patient_copay_amount'],
                'insurance_balance_due' => $insuranceFields['insurance_balance_due'],
                'upfront_amount_paid' => 0,
                'balance_due' => $totalAmount,
                'sale_date' => $validated['sale_date'],
                'notes' => $validated['notes'] ?? null,
                'payment_method' => null,
                'receipt_number' => $documentStatus === 'proforma' ? null : $sale->receipt_number,
                'status' => $documentStatus,
            ]);
            $sale->save();

            $this->syncSaleItemsAndStock(
                $sale,
                $rows,
                $batchMap,
                $documentStatus === 'pending' ? 'reserve' : 'none'
            );

            DB::commit();

            return redirect()
                ->route('sales.show', $sale->id)
                ->with(
                    'success',
                    $documentStatus === 'proforma'
                        ? 'Proforma invoice updated successfully. Stock remains untouched.'
                        : 'Pending sale updated successfully.'
                );
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function validateSalePayload(Request $request, $user, ?Sale $existingSale = null): array
    {
        $validated = $request->validate([
            'invoice_number' => ['required', 'string', 'max:255'],
            'sale_date' => ['required', 'date'],
            'sale_type' => ['required', 'in:retail,wholesale'],
            'payment_type' => ['required', Rule::in($this->salePaymentTypesForUser($user))],
            'customer_id' => [
                'nullable',
                Rule::exists('customers', 'id')->where(function ($query) use ($user) {
                    $query->where('client_id', $user->client_id);

                    if (Schema::hasColumn('customers', 'is_active')) {
                        $query->where('is_active', true);
                    }
                }),
            ],
            'insurer_id' => [
                'nullable',
                Rule::exists('insurers', 'id')->where(function ($query) use ($user) {
                    $query->where('client_id', $user->client_id)
                        ->where('is_active', true);
                }),
            ],
            'insurance_plan_name' => ['nullable', 'string', 'max:255'],
            'insurance_member_number' => ['nullable', 'string', 'max:255'],
            'insurance_card_number' => ['nullable', 'string', 'max:255'],
            'insurance_authorization_number' => ['nullable', 'string', 'max:255'],
            'insurance_covered_amount' => ['nullable', 'numeric', 'min:0'],
            'insurance_status_notes' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'product_id' => ['required', 'array', 'min:1'],
            'product_id.*' => ['required', 'integer'],
            'product_batch_id' => ['required', 'array', 'min:1'],
            'product_batch_id.*' => [
                'required',
                'integer',
                Rule::exists('product_batches', 'id')->where(function ($query) use ($user) {
                    $query->where('client_id', $user->client_id)
                        ->where('branch_id', $user->branch_id)
                        ->where('is_active', true);
                }),
            ],
            'unit_price' => ['required', 'array', 'min:1'],
            'unit_price.*' => ['required', 'numeric', 'min:0'],
            'quantity' => ['required', 'array', 'min:1'],
            'quantity.*' => ['required', 'numeric', 'gt:0'],
            'discount_amount' => ['nullable', 'array'],
            'discount_amount.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        $this->ensureSaleTypeAllowedForBranch($validated['sale_type'], $user, $existingSale);

        return $validated;
    }

    private function ensureSaleTypeAllowedForBranch(string $saleType, $user, ?Sale $existingSale = null): void
    {
        $saleTypeConfig = $this->saleTypeConfigurationForUser($user, $existingSale);

        if (in_array($saleType, $saleTypeConfig['strict_allowed_sale_types'], true)) {
            return;
        }

        if ($existingSale && $saleType === $existingSale->sale_type) {
            return;
        }

        $allowedLabels = array_map(
            fn (string $type): string => strtolower($saleTypeConfig['sale_type_labels'][$type] ?? $type),
            $saleTypeConfig['strict_allowed_sale_types']
        );

        $allowedText = count($allowedLabels) === 1
            ? $allowedLabels[0]
            : implode(' or ', $allowedLabels);

        throw ValidationException::withMessages([
            'sale_type' => 'This branch is ' . $saleTypeConfig['branch_mode_label'] . '. Only ' . $allowedText . ' transactions are allowed here.',
        ]);
    }

    private function saleTypeConfigurationForUser($user, ?Sale $sale = null): array
    {
        $branch = $user->branch?->loadMissing('client');
        $effectiveMode = $branch?->effectiveBusinessMode()
            ?? ($user->client?->business_mode ?? 'both');

        $saleTypeLabels = $this->saleTypeLabels();
        $strictAllowedSaleTypes = match ($effectiveMode) {
            'retail_only' => ['retail'],
            'wholesale_only' => ['wholesale'],
            default => ['retail', 'wholesale'],
        };

        $displaySaleTypes = $strictAllowedSaleTypes;
        $legacySaleType = null;

        if ($sale && !in_array($sale->sale_type, $strictAllowedSaleTypes, true)) {
            $legacySaleType = $sale->sale_type;
            $displaySaleTypes = array_values(array_unique(array_merge([$legacySaleType], $displaySaleTypes)));
        }

        $saleTypeOptions = [];
        foreach ($displaySaleTypes as $saleType) {
            $label = $saleTypeLabels[$saleType] ?? ucfirst($saleType);

            if ($legacySaleType === $saleType) {
                $label .= ' (Existing)';
            }

            $saleTypeOptions[$saleType] = $label;
        }

        $branchModeLabel = match ($effectiveMode) {
            'retail_only' => 'Retail Only',
            'wholesale_only' => 'Wholesale Only',
            default => 'Retail and Wholesale',
        };

        $saleTypeHint = null;
        if ($legacySaleType !== null) {
            $legacyLabel = strtolower($saleTypeLabels[$legacySaleType] ?? $legacySaleType);
            $saleTypeHint = 'This branch is currently ' . strtolower($branchModeLabel) . '. This invoice still carries its earlier ' . $legacyLabel . ' type from before the branch mode changed.';
        } elseif ($effectiveMode === 'retail_only') {
            $saleTypeHint = 'This branch is Retail Only. New transactions here stay retail.';
        } elseif ($effectiveMode === 'wholesale_only') {
            $saleTypeHint = 'This branch is Wholesale Only. New transactions here stay wholesale.';
        }

        return [
            'effective_mode' => $effectiveMode,
            'branch_mode_label' => $branchModeLabel,
            'strict_allowed_sale_types' => $strictAllowedSaleTypes,
            'sale_type_options' => $saleTypeOptions,
            'sale_type_labels' => $saleTypeLabels,
            'locked_sale_type' => count($displaySaleTypes) === 1 ? $displaySaleTypes[0] : null,
            'legacy_sale_type' => $legacySaleType,
            'sale_type_hint' => $saleTypeHint,
        ];
    }

    private function resolveDraftDefaultSaleType(array $saleTypeConfig): string
    {
        $oldSaleType = session()->getOldInput('sale_type');

        if (is_string($oldSaleType) && array_key_exists($oldSaleType, $saleTypeConfig['sale_type_options'])) {
            return $oldSaleType;
        }

        return $saleTypeConfig['locked_sale_type'] ?? 'retail';
    }

    private function draftSalePageDescription(string $documentStatus, string $effectiveMode): string
    {
        if ($documentStatus === 'proforma') {
            return match ($effectiveMode) {
                'retail_only' => 'Prepare a retail proforma invoice without touching stock or reserved stock',
                'wholesale_only' => 'Prepare a wholesale proforma invoice without touching stock or reserved stock',
                default => 'Prepare a full proforma invoice without touching stock or reserved stock',
            };
        }

        return match ($effectiveMode) {
            'retail_only' => 'Create retail sale for this branch',
            'wholesale_only' => 'Create wholesale sale for this branch',
            default => 'Create retail or wholesale sale',
        };
    }

    private function saleTypeLabels(): array
    {
        return [
            'retail' => 'Retail',
            'wholesale' => 'Wholesale',
        ];
    }

    private function canManageSaleDiscounts($user): bool
    {
        return $user->hasPermission('sales.discount');
    }

    private function canOverrideSalePrice($user): bool
    {
        return $user->hasPermission('sales.price_override');
    }

    private function normalizedApprovedAmountReceived(Sale $sale): float
    {
        return max(
            (float) $sale->amount_received,
            (float) $sale->amount_paid
        );
    }

    private function normalizedApprovedPaymentMethod(Sale $sale, float $amountPaid): ?string
    {
        $currentMethod = trim((string) ($sale->payment_method ?? ''));

        if ($currentMethod !== '') {
            return $currentMethod;
        }

        if ($amountPaid <= 0) {
            return $sale->payment_type === 'insurance' ? 'Insurance' : null;
        }

        return $sale->payment_type === 'cash'
            ? 'Cash'
            : ($sale->payment_type === 'insurance' ? 'Insurance' : 'Credit');
    }

    private function approvedSaleFinancials(Sale $sale, array $validated, float $totalAmount): array
    {
        $amountReceived = round((float) ($validated['amount_received'] ?? 0), 2);

        if (($validated['payment_type'] ?? 'cash') !== 'insurance') {
            $amountPaid = min($amountReceived, $totalAmount);

            return [
                'insurer_id' => null,
                'insurance_plan_name' => null,
                'insurance_member_number' => null,
                'insurance_card_number' => null,
                'insurance_authorization_number' => null,
                'insurance_claim_status' => null,
                'insurance_status_notes' => null,
                'insurance_covered_amount' => 0.0,
                'patient_copay_amount' => 0.0,
                'insurance_balance_due' => 0.0,
                'upfront_amount_paid' => $amountPaid,
                'amount_received' => $amountReceived,
                'amount_paid' => $amountPaid,
                'balance_due' => round(max(0, $totalAmount - $amountPaid), 2),
                'payment_method' => trim((string) ($validated['payment_method'] ?? '')),
            ];
        }

        $insurerId = (int) ($validated['insurer_id'] ?? $sale->insurer_id ?? 0);
        if ($insurerId <= 0) {
            throw ValidationException::withMessages([
                'insurer_id' => 'Select the insurer before approving an insurance invoice.',
            ]);
        }

        $insuranceValidated = $validated;
        $insuranceValidated['insurer_id'] = $insurerId;
        $coveredAmount = $this->normalizedInsuranceCoveredAmount($insuranceValidated, $totalAmount);
        $patientCopay = round(max(0, $totalAmount - $coveredAmount), 2);

        if (abs($amountReceived - $patientCopay) > 0.0001) {
            throw ValidationException::withMessages([
                'amount_received' => 'Insurance approval requires the patient top-up to be received in full. Enter exactly ' . number_format($patientCopay, 2) . '.',
            ]);
        }

        $paymentMethod = $patientCopay > 0
            ? trim((string) ($validated['payment_method'] ?? ''))
            : 'Insurance';

        if ($patientCopay > 0 && $paymentMethod === '') {
            throw ValidationException::withMessages([
                'payment_method' => 'Choose how the patient top-up was received.',
            ]);
        }

        return [
            'insurer_id' => $insurerId,
            'insurance_plan_name' => $this->nullableTrimmed($validated['insurance_plan_name'] ?? $sale->insurance_plan_name),
            'insurance_member_number' => $this->nullableTrimmed($validated['insurance_member_number'] ?? $sale->insurance_member_number),
            'insurance_card_number' => $this->nullableTrimmed($validated['insurance_card_number'] ?? $sale->insurance_card_number),
            'insurance_authorization_number' => $this->nullableTrimmed($validated['insurance_authorization_number'] ?? $sale->insurance_authorization_number),
            'insurance_claim_status' => $sale->insurance_claim_status ?: Sale::CLAIM_DRAFT,
            'insurance_status_notes' => $validated['insurance_status_notes'] ?? $sale->insurance_status_notes,
            'insurance_covered_amount' => $coveredAmount,
            'patient_copay_amount' => $patientCopay,
            'insurance_balance_due' => $coveredAmount,
            'upfront_amount_paid' => $patientCopay,
            'amount_received' => $patientCopay,
            'amount_paid' => $patientCopay,
            'balance_due' => $coveredAmount,
            'payment_method' => $paymentMethod,
        ];
    }

    private function activeInsuranceRemittancesExist(Sale $sale): bool
    {
        return $sale->insurancePayments()
            ->whereNull('reversal_of_payment_id')
            ->exists();
    }

    private function configuredSalePriceForBatch(ProductBatch $batch, string $saleType): float
    {
        return $saleType === 'wholesale'
            ? (float) $batch->wholesale_price
            : (float) $batch->retail_price;
    }

    private function ensureCustomerPresentWhenRequired(array $validated): void
    {
        if (
            $this->saleRequiresCustomer($validated['sale_type'], $validated['payment_type']) &&
            empty($validated['customer_id'])
        ) {
            throw ValidationException::withMessages([
                'customer_id' => 'Customer is required for wholesale, credit, or insurance sales.',
            ]);
        }
    }

    private function saleRequiresCustomer(string $saleType, string $paymentType): bool
    {
        return $saleType === 'wholesale' || in_array($paymentType, ['credit', 'insurance'], true);
    }

    private function draftInsuranceFields(array $validated, float $totalAmount): array
    {
        if (($validated['payment_type'] ?? 'cash') !== 'insurance') {
            return [
                'insurer_id' => null,
                'insurance_plan_name' => null,
                'insurance_member_number' => null,
                'insurance_card_number' => null,
                'insurance_authorization_number' => null,
                'insurance_claim_status' => null,
                'insurance_status_notes' => null,
                'insurance_covered_amount' => 0.0,
                'patient_copay_amount' => 0.0,
                'insurance_balance_due' => 0.0,
            ];
        }

        if (empty($validated['insurer_id'])) {
            throw ValidationException::withMessages([
                'insurer_id' => 'Select the insurer responsible for this invoice.',
            ]);
        }

        $coveredAmount = $this->normalizedInsuranceCoveredAmount($validated, $totalAmount);

        return [
            'insurer_id' => (int) $validated['insurer_id'],
            'insurance_plan_name' => $this->nullableTrimmed($validated['insurance_plan_name'] ?? null),
            'insurance_member_number' => $this->nullableTrimmed($validated['insurance_member_number'] ?? null),
            'insurance_card_number' => $this->nullableTrimmed($validated['insurance_card_number'] ?? null),
            'insurance_authorization_number' => $this->nullableTrimmed($validated['insurance_authorization_number'] ?? null),
            'insurance_claim_status' => Sale::CLAIM_DRAFT,
            'insurance_status_notes' => $validated['insurance_status_notes'] ?? null,
            'insurance_covered_amount' => $coveredAmount,
            'patient_copay_amount' => round(max(0, $totalAmount - $coveredAmount), 2),
            'insurance_balance_due' => $coveredAmount,
        ];
    }

    private function normalizedInsuranceCoveredAmount(array $validated, float $totalAmount): float
    {
        $coveredAmount = round((float) ($validated['insurance_covered_amount'] ?? 0), 2);

        if ($coveredAmount <= 0) {
            throw ValidationException::withMessages([
                'insurance_covered_amount' => 'Insurance covered amount must be greater than zero for an insurance invoice.',
            ]);
        }

        if ($coveredAmount - $totalAmount > 0.0001) {
            throw ValidationException::withMessages([
                'insurance_covered_amount' => 'Insurance covered amount cannot be higher than the invoice total.',
            ]);
        }

        return $coveredAmount;
    }

    private function nullableTrimmed($value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? $value : null;
    }

    private function normalizeSaleRows(array $validated, $user, ?Sale $existingSale = null): array
    {
        $rowCount = count($validated['product_id']);
        $arrayFields = ['product_batch_id', 'unit_price', 'quantity'];

        foreach ($arrayFields as $field) {
            if (count($validated[$field]) !== $rowCount) {
                throw ValidationException::withMessages([
                    $field => 'Sale items are incomplete. Please review all lines and try again.',
                ]);
            }
        }

        $rows = [];

        for ($i = 0; $i < $rowCount; $i++) {
            $rows[] = [
                'product_id' => (int) $validated['product_id'][$i],
                'product_batch_id' => (int) $validated['product_batch_id'][$i],
                'unit_price' => (float) $validated['unit_price'][$i],
                'quantity' => (float) $validated['quantity'][$i],
                'discount_amount' => (float) ($validated['discount_amount'][$i] ?? 0),
            ];
        }

        if ($rows === []) {
            throw ValidationException::withMessages([
                'product_id' => 'At least one sale item is required.',
            ]);
        }

        $this->enforceDiscountPermissions($validated, $rows, $user, $existingSale);

        return $rows;
    }

    private function enforceDiscountPermissions(array $validated, array &$rows, $user, ?Sale $existingSale = null): void
    {
        if ($this->canManageSaleDiscounts($user)) {
            return;
        }

        $existingDiscountsByKey = [];
        if ($existingSale) {
            $existingSale->loadMissing('items');

            foreach ($existingSale->items as $item) {
                $key = $this->saleDiscountMatchKey((int) $item->product_id, (int) $item->product_batch_id);
                $existingDiscountsByKey[$key] ??= [];
                $existingDiscountsByKey[$key][] = (float) $item->discount_amount;
            }
        }

        $hasDiscountInput = array_key_exists('discount_amount', $validated) && is_array($validated['discount_amount']);

        foreach ($rows as $index => &$row) {
            $key = $this->saleDiscountMatchKey((int) $row['product_id'], (int) $row['product_batch_id']);
            $allowedDiscount = 0.0;

            if (!empty($existingDiscountsByKey[$key])) {
                $allowedDiscount = (float) array_shift($existingDiscountsByKey[$key]);
            }

            $submittedDiscount = $hasDiscountInput && array_key_exists($index, $validated['discount_amount'])
                ? (float) $validated['discount_amount'][$index]
                : $allowedDiscount;

            if (abs($submittedDiscount - $allowedDiscount) > 0.0001) {
                throw ValidationException::withMessages([
                    'discount_amount' => 'Only admin or an authorized user can apply or change sale discounts.',
                ]);
            }

            $row['discount_amount'] = $allowedDiscount;
        }
        unset($row);
    }

    private function saleDiscountMatchKey(int $productId, int $productBatchId): string
    {
        return $productId . ':' . $productBatchId;
    }

    private function saleRowsFromItems(Sale $sale): array
    {
        return $sale->items->map(function (SaleItem $item) {
            return [
                'product_id' => (int) $item->product_id,
                'product_batch_id' => (int) $item->product_batch_id,
                'unit_price' => (float) $item->unit_price,
                'quantity' => (float) $item->quantity,
                'discount_amount' => (float) $item->discount_amount,
            ];
        })->values()->all();
    }

    private function prepareRequestedBatches($user, array $rows, string $saleType, ?int $ignoredSaleId = null): array
    {
        $batchIds = collect($rows)
            ->pluck('product_batch_id')
            ->unique()
            ->values()
            ->all();

        $batches = ProductBatch::query()
            ->whereIn('id', $batchIds)
            ->where('client_id', $user->client_id)
            ->where('branch_id', $user->branch_id)
            ->where('is_active', true)
            ->lockForUpdate()
            ->get();

        BatchReservationService::syncCollection(
            $batches,
            $user->client_id,
            $user->branch_id,
            $ignoredSaleId ? [$ignoredSaleId] : []
        );

        $batches = $batches
            ->keyBy('id');

        $availableFreeByBatch = [];
        $batchMap = [];
        $subtotal = 0;
        $discountTotal = 0;

        foreach ($rows as $index => $row) {
            $batch = $batches->get($row['product_batch_id']);

            if (!$batch) {
                throw ValidationException::withMessages([
                    'product_batch_id.' . $index => 'Selected batch was not found for one of the rows.',
                ]);
            }

            if ((int) $batch->product_id !== $row['product_id']) {
                throw ValidationException::withMessages([
                    'product_batch_id.' . $index => 'Selected batch does not belong to the chosen product.',
                ]);
            }

            if (!array_key_exists($batch->id, $availableFreeByBatch)) {
                $availableFreeByBatch[$batch->id] = $this->batchFreeStock($batch);
            }

            if ($row['quantity'] > $availableFreeByBatch[$batch->id]) {
                throw ValidationException::withMessages([
                    'quantity.' . $index => 'Quantity exceeds available free stock for batch ' . $batch->batch_number . '.',
                ]);
            }

            $minimumAllowedPrice = $this->canOverrideSalePrice($user)
                ? (float) $batch->purchase_price
                : $this->configuredSalePriceForBatch($batch, $saleType);

            if ($row['unit_price'] + 0.0001 < $minimumAllowedPrice) {
                $priceLabel = $this->canOverrideSalePrice($user)
                    ? 'purchase price'
                    : ($saleType === 'wholesale' ? 'wholesale selling price' : 'retail selling price');

                throw ValidationException::withMessages([
                    'unit_price.' . $index => 'Row ' . ($index + 1) . ': unit price cannot go below the ' . $priceLabel . ' of batch ' . $batch->batch_number . ' (' . number_format($minimumAllowedPrice, 2) . ').',
                ]);
            }

            $availableFreeByBatch[$batch->id] -= $row['quantity'];

            $lineSubtotal = $row['quantity'] * $row['unit_price'];
            $minimumLineTotal = $row['quantity'] * (float) $batch->purchase_price;
            $lineTotalAfterDiscount = $lineSubtotal - $row['discount_amount'];

            if ($lineTotalAfterDiscount + 0.0001 < $minimumLineTotal) {
                $maximumDiscount = max(0, $lineSubtotal - $minimumLineTotal);

                throw ValidationException::withMessages([
                    'discount_amount.' . $index => 'Row ' . ($index + 1) . ': discount cannot reduce batch ' . $batch->batch_number . ' below its purchase price. Maximum discount for this row is ' . number_format($maximumDiscount, 2) . '.',
                ]);
            }

            $subtotal += $lineSubtotal;
            $discountTotal += $row['discount_amount'];
            $batchMap[$index] = $batch;
        }

        return [$batchMap, $subtotal, $discountTotal];
    }

    private function releaseExistingSaleStock(Sale $sale): void
    {
        if ($sale->items->isEmpty()) {
            return;
        }

        $batchIds = $sale->items
            ->pluck('product_batch_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $batches = ProductBatch::query()
            ->whereIn('id', $batchIds)
            ->where('client_id', $sale->client_id)
            ->where('branch_id', $sale->branch_id)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($sale->items as $item) {
            $batch = $batches->get($item->product_batch_id);

            if (!$batch) {
                throw new \RuntimeException('One of the existing sale batches could not be found for editing.');
            }

            $qty = (float) $item->quantity;

            if ($sale->status === 'approved') {
                $batch->quantity_available = (float) $batch->quantity_available + $qty;
            }

            $batch->reserved_quantity = max(0, (float) $batch->reserved_quantity - $qty);
            $batch->save();
        }
    }

    private function syncSaleItemsAndStock(Sale $sale, array $rows, array $batchMap, string $stockMode): void
    {
        $sale->items()->delete();

        foreach ($rows as $index => $row) {
            $batch = $batchMap[$index];
            $lineSubtotal = $row['quantity'] * $row['unit_price'];
            $lineTotal = max(0, $lineSubtotal - $row['discount_amount']);

            SaleItem::create([
                'sale_id' => $sale->id,
                'product_id' => $row['product_id'],
                'product_batch_id' => $row['product_batch_id'],
                'quantity' => $row['quantity'],
                'purchase_price' => (float) $batch->purchase_price,
                'unit_price' => $row['unit_price'],
                'discount_amount' => $row['discount_amount'],
                'total_amount' => $lineTotal,
            ]);

            if ($stockMode === 'deduct') {
                $batch->quantity_available = max(0, (float) $batch->quantity_available - $row['quantity']);
                $batch->save();
            } elseif ($stockMode === 'reserve') {
                $batch->reserved_quantity = (float) $batch->reserved_quantity + $row['quantity'];
                $batch->save();
            }
        }
    }

    private function reconcileApprovedSaleOutstandingBalance(Sale $sale, ?int $previousCreditCustomerId, float $previousBalanceDue): void
    {
        if ($previousCreditCustomerId && $previousBalanceDue > 0) {
            $this->adjustCustomerOutstandingBalance($previousCreditCustomerId, -1 * $previousBalanceDue);
        }

        $currentCreditCustomerId = $this->creditCustomerIdForSale($sale);

        if ($currentCreditCustomerId && (float) $sale->balance_due > 0) {
            $this->adjustCustomerOutstandingBalance($currentCreditCustomerId, (float) $sale->balance_due);
        }
    }

    private function creditCustomerIdForSale(Sale $sale): ?int
    {
        if ($sale->payment_type !== 'credit' || !$sale->customer_id || (float) $sale->balance_due <= 0) {
            return null;
        }

        return (int) $sale->customer_id;
    }

    private function adjustCustomerOutstandingBalance(int $customerId, float $delta): void
    {
        if (abs($delta) < 0.00001 || !Schema::hasColumn('customers', 'outstanding_balance')) {
            return;
        }

        $customer = Customer::query()
            ->where('id', $customerId)
            ->lockForUpdate()
            ->first();

        if (!$customer) {
            return;
        }

        $currentBalance = (float) ($customer->outstanding_balance ?? 0);
        $customer->outstanding_balance = max(0, $currentBalance + $delta);
        $customer->save();
    }

    private function batchFreeStock(ProductBatch $batch): float
    {
        return max(0, (float) $batch->quantity_available - (float) $batch->reserved_quantity);
    }

    private function showDispensingPriceGuide($user): bool
    {
        return ClientFeatureAccess::dispensingPriceGuideEnabled($user->clientSettingsModel());
    }
}
