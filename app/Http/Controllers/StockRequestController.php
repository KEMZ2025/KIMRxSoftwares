<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Product;
use App\Models\StockRequest;
use App\Support\AuditTrail;
use App\Support\StockRequestBook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StockRequestController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(StockRequestBook::canView($request->user()), 403);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(array_keys(StockRequestBook::LABELS))],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('from'), 'after_or_equal:from')],
            'view' => ['nullable', Rule::in(['requests', 'procurement'])],
            'key' => ['nullable', 'regex:/^[a-f0-9]{64}$/'],
        ]);
        $query = StockRequestBook::forUser($request->user())
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('medicine_name', 'like', '%' . trim($search) . '%')
                        ->orWhere('strength', 'like', '%' . trim($search) . '%')
                        ->orWhereHas('product', fn ($q) => $q->where('name', 'like', '%' . trim($search) . '%'));
                });
            })
            ->when($filters['from'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($filters['to'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->when($filters['key'] ?? null, fn ($q, $key) => $q->where('request_key', $key));
        $counts = (clone $query)->selectRaw('display_status, COUNT(*) AS total')
            ->groupBy('display_status')->pluck('total', 'display_status');
        $procurement = ($filters['view'] ?? 'requests') === 'procurement';
        $query->when($filters['status'] ?? null, fn ($q, $status) => $q->where('display_status', $status));
        $rows = $procurement
            ? $query->whereIn('display_status', ['pending', 'ordered'])
                ->selectRaw('request_key, MIN(id) AS id, MIN(product_id) AS product_id, MIN(medicine_name) AS medicine_name, MIN(strength) AS strength, MIN(dosage_form) AS dosage_form, MIN(unit_name) AS unit_name, COUNT(*) AS request_count, SUM(quantity) AS quantity, COUNT(*) - COUNT(quantity) AS unspecified_count, MAX(created_at) AS last_requested')
                ->groupBy('request_key')->orderByDesc('request_count')->orderByDesc('last_requested')->paginate(20)
            : $query->with(['creator:id,name', 'product:id,name,strength'])
                ->orderByDesc('created_at')->orderByDesc('id')->paginate(20);
        $rows->withQueryString();

        return view('stock_requests.index', $this->viewContext($request) + compact('rows', 'counts', 'filters', 'procurement'));
    }

    public function products(Request $request)
    {
        abort_unless(StockRequestBook::canView($request->user()), 403);
        $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $search = trim((string) $request->query('q'));
        if (mb_strlen($search) < 2) {
            return response()->json(['products' => []]);
        }
        $products = Product::query()->with('unit:id,name')
            ->where('client_id', $request->user()->client_id)->where('is_active', true)
            ->where(fn ($q) => $q->where('name', 'like', '%' . $search . '%')->orWhere('strength', 'like', '%' . $search . '%'))
            ->orderBy('name')->limit(15)->get(['id', 'name', 'strength', 'unit_id']);

        return response()->json(['products' => $products->map(fn ($product) => [
            'id' => $product->id, 'name' => $product->name, 'strength' => $product->strength,
            'unit_name' => $product->unit?->name,
        ])]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        abort_unless(StockRequestBook::canRecord($user), 403);
        $values = $request->validate([
            'submission_token' => ['required', 'uuid'],
            'product_id' => ['nullable', 'integer', $this->productRule($request)],
            'medicine_name' => ['required_without:product_id', 'nullable', 'string', 'max:255'],
            'strength' => ['nullable', 'string', 'max:100'],
            'dosage_form' => ['nullable', 'string', 'max:100'],
            'quantity' => ['nullable', 'numeric', 'min:0.01', 'max:99999999.99', 'decimal:0,2'],
            'unit_name' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        $result = DB::transaction(function () use ($values, $user) {
            // Serialize retry tokens per staff member; a retry must not create a second request.
            DB::table('users')->where('id', $user->id)->lockForUpdate()->first();
            $existing = StockRequest::where('client_id', $user->client_id)->where('branch_id', $user->branch_id)
                ->where('submission_token', $values['submission_token'])->first();
            if (!empty($values['product_id'])) {
                $product = Product::with('unit')->where('client_id', $user->client_id)
                    ->where('is_active', true)->findOrFail($values['product_id']);
                $values['medicine_name'] = $product->name;
                $values['strength'] = $product->strength;
                $values['dosage_form'] = null;
                $values['unit_name'] = $product->unit?->name ?: ($values['unit_name'] ?? null);
            }
            if (filled($values['quantity'] ?? null) && blank($values['unit_name'] ?? null)) {
                throw ValidationException::withMessages(['unit_name' => 'Choose a unit when entering quantity.']);
            }
            if ($existing) {
                abort_unless((int) $existing->requested_by_user_id === (int) $user->id, 409);
                if ($existing->request_key !== StockRequest::groupingKey($values)
                    || (float) $existing->quantity !== (float) ($values['quantity'] ?? 0)
                    || (string) $existing->note !== (string) ($values['note'] ?? '')) {
                    throw ValidationException::withMessages(['submission_token' => 'This request was already recorded with different details. Close and refresh before recording another.']);
                }
                return [$existing, false];
            }
            $entry = StockRequest::create($values + [
                'client_id' => $user->client_id, 'branch_id' => $user->branch_id, 'requested_by_user_id' => $user->id,
                'request_key' => StockRequest::groupingKey($values), 'status' => 'pending',
            ]);
            app(AuditTrail::class)->record($user, 'stock_request.created', 'Stock Requests', 'Created',
                'Recorded request for ' . $entry->medicine_name . '.', [
                    'subject' => $entry, 'subject_label' => $entry->medicine_name,
                    'new_values' => $entry->only(['product_id', 'medicine_name', 'strength', 'dosage_form', 'quantity', 'unit_name', 'status', 'note']),
                ]);
            return [$entry, true];
        });

        return response()->json(['message' => 'Request recorded.', 'id' => $result[0]->id], $result[1] ? 201 : 200);
    }

    public function show(Request $request, int $stockRequest)
    {
        abort_unless(StockRequestBook::canView($request->user()), 403);
        $entry = StockRequestBook::forUser($request->user())->with(['creator', 'product.unit'])->findOrFail($stockRequest);
        $history = AuditLog::with('user:id,name')->where('client_id', $request->user()->client_id)
            ->where('branch_id', $request->user()->branch_id)->where('subject_type', StockRequest::class)
            ->where('subject_id', $entry->id)->latest('id')->paginate(15);

        return view('stock_requests.show', $this->viewContext($request) + compact('entry', 'history'));
    }

    public function update(Request $request, int $stockRequest)
    {
        $user = $request->user();
        abort_unless(StockRequestBook::canManage($user), 403);
        $values = $request->validate([
            'status' => ['required', Rule::in(['pending', 'ordered', 'closed'])],
            'product_id' => ['nullable', 'integer', $this->productRule($request)],
            'version' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        DB::transaction(function () use ($user, $values, $stockRequest) {
            $entry = StockRequest::where('client_id', $user->client_id)->where('branch_id', $user->branch_id)
                ->lockForUpdate()->findOrFail($stockRequest);
            if ($entry->version !== (int) $values['version']) {
                throw ValidationException::withMessages(['version' => 'This request was updated by another person. Refresh before saving.']);
            }
            $before = $entry->only(['product_id', 'status', 'unit_name']);
            $entry->status = $values['status'];
            // Linking is explicit; never guess a medicine from similar text.
            if (isset($values['product_id']) && (int) $entry->product_id !== (int) $values['product_id']) {
                $product = Product::with('unit')->where('client_id', $user->client_id)
                    ->where('is_active', true)->findOrFail($values['product_id']);
                if ($entry->quantity !== null && $product->unit?->name
                    && strcasecmp(trim((string) $entry->unit_name), trim($product->unit->name)) !== 0) {
                    throw ValidationException::withMessages(['product_id' => 'The requested unit does not match this product. No unit conversion has been applied.']);
                }
                $entry->product_id = $product->id;
                $entry->unit_name = $product->unit?->name ?: $entry->unit_name;
            }
            $entry->request_key = StockRequest::groupingKey($entry->toArray());
            if (!$entry->isDirty() && blank($values['note'] ?? null)) {
                return;
            }
            $entry->version++;
            $entry->save();
            app(AuditTrail::class)->record($user, 'stock_request.updated', 'Stock Requests', 'Updated',
                'Updated request for ' . $entry->medicine_name . '.', [
                    'subject' => $entry, 'subject_label' => $entry->medicine_name,
                    'reason' => $values['note'] ?? null, 'old_values' => $before,
                    'new_values' => $entry->only(['product_id', 'status', 'unit_name']),
                ]);
        });

        return redirect()->route('stock-requests.show', $stockRequest)->with('success', 'Request updated.');
    }

    private function productRule(Request $request)
    {
        return Rule::exists('products', 'id')->where(fn ($q) => $q->where('client_id', $request->user()->client_id)->where('is_active', true));
    }

    private function viewContext(Request $request): array
    {
        return [
            'clientName' => $request->user()->client?->name, 'branchName' => $request->user()->branch?->name,
            'canRecord' => StockRequestBook::canRecord($request->user()),
            'canManage' => StockRequestBook::canManage($request->user()), 'statusLabels' => StockRequestBook::LABELS,
        ];
    }
}
