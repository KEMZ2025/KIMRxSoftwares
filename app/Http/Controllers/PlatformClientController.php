<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Client;
use App\Models\ClientSetting;
use App\Support\AccessControlBootstrapper;
use App\Support\AuditTrail;
use App\Support\ClientFeatureAccess;
use App\Support\ClientPackagePresetCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PlatformClientController extends Controller
{
    public function __construct(
        protected AccessControlBootstrapper $bootstrapper,
    ) {
    }

    public function index(Request $request)
    {
        $context = $this->platformWorkspaceContext($request);

        $query = Client::query()
            ->where('is_platform_sandbox', false)
            ->withCount([
                'branches',
                'branches as active_branches_count' => fn ($builder) => $builder->where('is_active', true),
                'users as active_users_count' => fn ($builder) => $builder
                    ->where('is_super_admin', false)
                    ->where('is_active', true),
            ])
            ->orderBy('name');

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%')
                    ->orWhere('phone', 'like', '%' . $search . '%');
            });
        }

        if ($clientType = trim((string) $request->query('client_type'))) {
            $query->where('client_type', $clientType);
        }

        if ($subscriptionStatus = trim((string) $request->query('subscription_status'))) {
            $query->where('subscription_status', $subscriptionStatus);
        }

        if ($packagePreset = trim((string) $request->query('package_preset'))) {
            $query->where('package_preset', $packagePreset);
        }

        if ($renewalWindow = trim((string) $request->query('renewal_window'))) {
            $today = now()->startOfDay();

            match ($renewalWindow) {
                'due_7' => $query->whereBetween('subscription_ends_at', [$today->toDateString(), $today->copy()->addDays(7)->toDateString()]),
                'due_30' => $query->whereBetween('subscription_ends_at', [$today->toDateString(), $today->copy()->addDays(30)->toDateString()]),
                'expired' => $query->whereDate('subscription_ends_at', '<', $today->toDateString()),
                'no_date' => $query->whereNull('subscription_ends_at'),
                default => null,
            };
        }

        $clients = $query->paginate(12)->withQueryString();
        $today = now()->startOfDay();

        return view('admin.platform.clients.index', [
            ...$context,
            'clients' => $clients,
            'clientTypes' => Client::clientTypeOptions(),
            'subscriptionStatuses' => Client::subscriptionStatusOptions(),
            'packagePresets' => Client::packagePresetOptions(),
            'totalClients' => Client::query()->where('is_platform_sandbox', false)->count(),
            'payingClients' => Client::query()->where('is_platform_sandbox', false)->where('client_type', Client::TYPE_PAYING)->count(),
            'trialClients' => Client::query()->where('is_platform_sandbox', false)->where('client_type', Client::TYPE_TRIAL)->count(),
            'demoClients' => Client::query()->where('is_platform_sandbox', false)->where('client_type', Client::TYPE_DEMO)->count(),
            'attentionClients' => Client::query()
                ->where('is_platform_sandbox', false)
                ->whereIn('subscription_status', [Client::STATUS_OVERDUE, Client::STATUS_SUSPENDED])
                ->count(),
            'activeClients' => Client::query()->where('is_platform_sandbox', false)->where('is_active', true)->count(),
            'totalBranches' => Branch::query()
                ->whereHas('client', fn ($builder) => $builder->where('is_platform_sandbox', false))
                ->count(),
            'seatLimitedClients' => Client::query()
                ->where('is_platform_sandbox', false)
                ->whereNotNull('active_user_limit')
                ->count(),
            'dueSoonClients' => Client::query()
                ->where('is_platform_sandbox', false)
                ->whereBetween('subscription_ends_at', [$today->toDateString(), $today->copy()->addDays(7)->toDateString()])
                ->count(),
            'expiredRenewals' => Client::query()
                ->where('is_platform_sandbox', false)
                ->whereDate('subscription_ends_at', '<', $today->toDateString())
                ->count(),
            'clientsWithoutRenewalDate' => Client::query()
                ->where('is_platform_sandbox', false)
                ->whereNull('subscription_ends_at')
                ->count(),
        ]);
    }

    public function create(Request $request)
    {
        $context = $this->platformWorkspaceContext($request);

        return view('admin.platform.clients.create', [
            ...$context,
            'businessModes' => $this->businessModes(),
            'packagePresets' => ClientPackagePresetCatalog::definitions(),
            'clientTypes' => Client::clientTypeOptions(),
            'subscriptionStatuses' => Client::subscriptionStatusOptions(),
            'initialBranchBusinessModes' => $this->branchBusinessModes('both'),
            'moduleOptions' => ClientFeatureAccess::moduleDefinitions(),
            'accountingFeatureOptions' => ClientFeatureAccess::accountingFeatureDefinitions(),
            'featureValues' => ClientFeatureAccess::defaultSettingValues(),
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'business_mode' => ['required', Rule::in(array_keys($this->businessModes()))],
            'package_preset' => ['nullable', Rule::in(array_keys(Client::packagePresetOptions()))],
            'client_type' => ['required', Rule::in(array_keys(Client::clientTypeOptions()))],
            'subscription_status' => ['required', Rule::in(array_keys(Client::subscriptionStatusOptions()))],
            'active_user_limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'subscription_ends_at' => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],
            'initial_branch_name' => ['required', 'string', 'max:255'],
            'initial_branch_code' => ['nullable', 'string', 'max:50'],
            'initial_branch_phone' => ['nullable', 'string', 'max:50'],
            'initial_branch_email' => ['nullable', 'email', 'max:255'],
            'initial_branch_address' => ['nullable', 'string'],
            'initial_branch_business_mode' => ['nullable', Rule::in(array_keys($this->branchBusinessModes((string) $request->input('business_mode', 'both'))))],
            'initial_branch_is_active' => ['nullable', 'boolean'],
        ] + $this->featureValidationRules());

        $lifecycle = $this->normalizedLifecycleValues($request, $validated);
        $settingsPayload = $this->featureSettingsPayload($request, $validated['business_mode']);

        [$client, $branch] = DB::transaction(function () use ($request, $validated, $settingsPayload, $lifecycle) {
            $client = Client::query()->create([
                'name' => $validated['name'],
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'address' => $validated['address'] ?? null,
                'business_mode' => $validated['business_mode'],
                'package_preset' => $validated['package_preset'] ?? null,
                'client_type' => $validated['client_type'],
                'subscription_status' => $lifecycle['subscription_status'],
                'active_user_limit' => $validated['active_user_limit'] ?? ($this->presetSeatLimit($validated['package_preset'] ?? null)),
                'subscription_ends_at' => $lifecycle['subscription_ends_at'],
                'is_active' => $lifecycle['is_active'],
            ]);

            $branch = Branch::query()->create([
                'client_id' => $client->id,
                'name' => $validated['initial_branch_name'],
                'code' => $validated['initial_branch_code'] ?: null,
                'phone' => $validated['initial_branch_phone'] ?? null,
                'email' => $validated['initial_branch_email'] ?? null,
                'address' => $validated['initial_branch_address'] ?? null,
                'business_mode' => $validated['initial_branch_business_mode'] ?? 'inherit',
                'is_main' => true,
                'is_active' => $request->boolean('initial_branch_is_active', true),
            ]);

            $this->bootstrapper->ensureForClient($client->id);

            ClientSetting::query()->updateOrCreate(
                ['client_id' => $client->id],
                $settingsPayload
            );

            return [$client, $branch];
        });

        $settings = ClientSetting::query()->where('client_id', $client->id)->first();

        app(AuditTrail::class)->recordSafely(
            $user,
            'platform.client_created',
            'Platform',
            'Create Client',
            'Created client ' . $client->name . ' with the first branch.',
            [
                'subject' => $client,
                'subject_label' => $client->name,
                'client_id' => $client->id,
                'branch_id' => $branch->id,
                'new_values' => $this->auditClientSnapshot($client, $settings),
                'context' => [
                    'initial_branch' => $this->auditBranchSnapshot($branch),
                ],
            ]
        );

        return redirect()
            ->route('admin.platform.branches.index', $client)
            ->with('success', 'Client created successfully. The first main branch and default roles are ready.');
    }

    public function edit(Request $request, Client $client)
    {
        $this->ensureManageableClient($client);
        $context = $this->platformWorkspaceContext($request);
        $settings = ClientSetting::query()->firstOrCreate(
            ['client_id' => $client->id],
            ['business_mode' => $client->business_mode] + ClientFeatureAccess::defaultSettingValues()
        );

        return view('admin.platform.clients.edit', [
            ...$context,
            'managedClient' => $client,
            'businessModes' => $this->businessModes(),
            'packagePresets' => ClientPackagePresetCatalog::definitions(),
            'clientTypes' => Client::clientTypeOptions(),
            'subscriptionStatuses' => Client::subscriptionStatusOptions(),
            'moduleOptions' => ClientFeatureAccess::moduleDefinitions(),
            'accountingFeatureOptions' => ClientFeatureAccess::accountingFeatureDefinitions(),
            'featureValues' => ClientFeatureAccess::valuesFromSettings($settings),
        ]);
    }

    public function update(Request $request, Client $client)
    {
        $this->ensureManageableClient($client);
        $user = $request->user();
        $settings = ClientSetting::query()->firstOrCreate(
            ['client_id' => $client->id],
            ['business_mode' => $client->business_mode] + ClientFeatureAccess::defaultSettingValues()
        );
        $beforeAudit = $this->auditClientSnapshot($client, $settings);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'business_mode' => ['required', Rule::in(array_keys($this->businessModes()))],
            'package_preset' => ['nullable', Rule::in(array_keys(Client::packagePresetOptions()))],
            'client_type' => ['required', Rule::in(array_keys(Client::clientTypeOptions()))],
            'subscription_status' => ['required', Rule::in(array_keys(Client::subscriptionStatusOptions()))],
            'active_user_limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'subscription_ends_at' => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],
        ] + $this->featureValidationRules());

        $invalidBranchCount = $client->branches()
            ->whereNotIn('business_mode', array_keys($this->branchBusinessModes($validated['business_mode'])))
            ->count();

        if ($invalidBranchCount > 0) {
            return back()
                ->withInput()
                ->withErrors([
                    'business_mode' => 'This client still has branch business modes that do not fit the selected client mode. Update those branches first.',
                ]);
        }

        $lifecycle = $this->normalizedLifecycleValues($request, $validated);
        $client->update([
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'business_mode' => $validated['business_mode'],
            'package_preset' => $validated['package_preset'] ?? null,
            'client_type' => $validated['client_type'],
            'subscription_status' => $lifecycle['subscription_status'],
            'active_user_limit' => $validated['active_user_limit'] ?? ($this->presetSeatLimit($validated['package_preset'] ?? null)),
            'subscription_ends_at' => $lifecycle['subscription_ends_at'],
            'is_active' => $lifecycle['is_active'],
        ]);

        ClientSetting::query()->updateOrCreate(
            ['client_id' => $client->id],
            $this->featureSettingsPayload($request, $validated['business_mode'])
        );

        $client->refresh();
        $settings->refresh();

        app(AuditTrail::class)->recordSafely(
            $user,
            'platform.client_updated',
            'Platform',
            'Update Client',
            'Updated client package and access settings for ' . $client->name . '.',
            [
                'subject' => $client,
                'subject_label' => $client->name,
                'client_id' => $client->id,
                'old_values' => $beforeAudit,
                'new_values' => $this->auditClientSnapshot($client, $settings),
            ]
        );

        return redirect()
            ->route('admin.platform.clients.index')
            ->with('success', 'Client updated successfully.');
    }

    public function updateSubscription(Request $request, Client $client)
    {
        $this->ensureManageableClient($client);
        $user = $request->user();
        $settings = ClientSetting::query()->firstOrCreate(
            ['client_id' => $client->id],
            ['business_mode' => $client->business_mode] + ClientFeatureAccess::defaultSettingValues()
        );
        $beforeAudit = $this->auditClientSnapshot($client, $settings);

        $validated = $request->validate([
            'subscription_status' => ['required', Rule::in(array_keys(Client::subscriptionStatusOptions()))],
            'subscription_ends_at' => ['nullable', 'date'],
            'clear_subscription_end' => ['nullable', 'boolean'],
            'sync_access' => ['nullable', 'boolean'],
        ]);

        $updates = [
            'subscription_status' => $validated['subscription_status'],
        ];

        if ($request->boolean('clear_subscription_end')) {
            $updates['subscription_ends_at'] = null;
        } elseif (array_key_exists('subscription_ends_at', $validated)) {
            $updates['subscription_ends_at'] = $validated['subscription_ends_at'] ?: $client->subscription_ends_at;
        }

        if ($validated['subscription_status'] === Client::STATUS_SUSPENDED) {
            $updates['is_active'] = false;
        } elseif ($request->boolean('sync_access')) {
            $updates['is_active'] = true;
        }

        if (($client->client_type === Client::TYPE_TRIAL) && empty($updates['subscription_ends_at']) && !$request->boolean('clear_subscription_end')) {
            $updates['subscription_ends_at'] = now()->addDays(14)->toDateString();
        }

        $client->update($updates);
        $client->refresh();
        $settings->refresh();

        app(AuditTrail::class)->recordSafely(
            $user,
            'platform.client_subscription_updated',
            'Platform',
            'Update Subscription Workflow',
            'Updated subscription workflow for ' . $client->name . '.',
            [
                'subject' => $client,
                'subject_label' => $client->name,
                'client_id' => $client->id,
                'old_values' => $beforeAudit,
                'new_values' => $this->auditClientSnapshot($client, $settings),
            ]
        );

        return redirect()
            ->back()
            ->with('success', 'Subscription workflow updated successfully.');
    }

    public function branches(Request $request, Client $client)
    {
        $this->ensureManageableClient($client);
        $context = $this->platformWorkspaceContext($request);
        $branches = $client->branches()
            ->orderByDesc('is_main')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.platform.branches.index', [
            ...$context,
            'managedClient' => $client,
            'branches' => $branches,
        ]);
    }

    public function createBranch(Request $request, Client $client)
    {
        $this->ensureManageableClient($client);
        $context = $this->platformWorkspaceContext($request);

        return view('admin.platform.branches.create', [
            ...$context,
            'managedClient' => $client,
            'branchBusinessModes' => $this->branchBusinessModes($client->business_mode),
        ]);
    }

    public function storeBranch(Request $request, Client $client)
    {
        $this->ensureManageableClient($client);
        $user = $request->user();
        $createdBranch = null;
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('branches', 'code')->where(fn ($query) => $query->where('client_id', $client->id)),
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'business_mode' => ['nullable', Rule::in(array_keys($this->branchBusinessModes($client->business_mode)))],
            'is_main' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($request, $client, $validated, &$createdBranch) {
            $isMain = $request->boolean('is_main', !$client->branches()->exists());

            if ($isMain) {
                $client->branches()->update(['is_main' => false]);
            }

            $createdBranch = Branch::query()->create([
                'client_id' => $client->id,
                'name' => $validated['name'],
                'code' => $validated['code'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'email' => $validated['email'] ?? null,
                'address' => $validated['address'] ?? null,
                'business_mode' => $validated['business_mode'] ?? 'inherit',
                'is_main' => $isMain,
                'is_active' => $request->boolean('is_active', true),
            ]);
        });

        app(AuditTrail::class)->recordSafely(
            $user,
            'platform.branch_created',
            'Platform',
            'Create Branch',
            'Created branch ' . $createdBranch->name . ' for client ' . $client->name . '.',
            [
                'subject' => $createdBranch,
                'subject_label' => $createdBranch->name,
                'client_id' => $client->id,
                'branch_id' => $createdBranch->id,
                'new_values' => $this->auditBranchSnapshot($createdBranch),
            ]
        );

        return redirect()
            ->route('admin.platform.branches.index', $client)
            ->with('success', 'Branch created successfully.');
    }

    public function editBranch(Request $request, Client $client, Branch $branch)
    {
        $this->ensureManageableClient($client);
        $this->ensureClientOwnsBranch($client, $branch);
        $context = $this->platformWorkspaceContext($request);

        return view('admin.platform.branches.edit', [
            ...$context,
            'managedClient' => $client,
            'managedBranch' => $branch,
            'branchBusinessModes' => $this->branchBusinessModes($client->business_mode),
        ]);
    }

    public function updateBranch(Request $request, Client $client, Branch $branch)
    {
        $this->ensureManageableClient($client);
        $this->ensureClientOwnsBranch($client, $branch);
        $user = $request->user();
        $beforeAudit = $this->auditBranchSnapshot($branch);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('branches', 'code')
                    ->ignore($branch->id)
                    ->where(fn ($query) => $query->where('client_id', $client->id)),
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'business_mode' => ['nullable', Rule::in(array_keys($this->branchBusinessModes($client->business_mode)))],
            'is_main' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($request, $client, $branch, $validated) {
            $isMain = $request->boolean('is_main', false);

            if ($isMain) {
                $client->branches()->where('id', '!=', $branch->id)->update(['is_main' => false]);
            }

            if (!$isMain && !$client->branches()->where('id', '!=', $branch->id)->where('is_main', true)->exists()) {
                $isMain = true;
            }

            $branch->update([
                'name' => $validated['name'],
                'code' => $validated['code'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'email' => $validated['email'] ?? null,
                'address' => $validated['address'] ?? null,
                'business_mode' => $validated['business_mode'] ?? 'inherit',
                'is_main' => $isMain,
                'is_active' => $request->boolean('is_active', true),
            ]);
        });

        $branch->refresh();

        app(AuditTrail::class)->recordSafely(
            $user,
            'platform.branch_updated',
            'Platform',
            'Update Branch',
            'Updated branch ' . $branch->name . ' for client ' . $client->name . '.',
            [
                'subject' => $branch,
                'subject_label' => $branch->name,
                'client_id' => $client->id,
                'branch_id' => $branch->id,
                'old_values' => $beforeAudit,
                'new_values' => $this->auditBranchSnapshot($branch),
            ]
        );

        return redirect()
            ->route('admin.platform.branches.index', $client)
            ->with('success', 'Branch updated successfully.');
    }

    protected function businessModes(): array
    {
        return [
            'retail_only' => 'Retail Only',
            'wholesale_only' => 'Wholesale Only',
            'both' => 'Retail and Wholesale',
        ];
    }

    protected function branchBusinessModes(string $clientBusinessMode): array
    {
        $allModes = ['inherit' => 'Inherit Client Mode'] + $this->businessModes();

        return match ($clientBusinessMode) {
            'retail_only' => array_intersect_key($allModes, array_flip(['inherit', 'retail_only'])),
            'wholesale_only' => array_intersect_key($allModes, array_flip(['inherit', 'wholesale_only'])),
            default => $allModes,
        };
    }

    protected function ensureClientOwnsBranch(Client $client, Branch $branch): void
    {
        abort_unless((int) $branch->client_id === (int) $client->id, 404);
    }

    protected function ensureManageableClient(Client $client): void
    {
        abort_if($client->isPlatformSandbox(), 403, 'The platform sandbox is managed automatically.');
    }

    protected function auditClientSnapshot(Client $client, ?ClientSetting $settings = null): array
    {
        $settings ??= ClientSetting::query()->firstOrCreate(
            ['client_id' => $client->id],
            ['business_mode' => $client->business_mode] + ClientFeatureAccess::defaultSettingValues()
        );

        $featureValues = ClientFeatureAccess::valuesFromSettings($settings);
        $enabledModules = collect(ClientFeatureAccess::moduleDefinitions())
            ->filter(fn (array $definition) => (bool) ($featureValues[$definition['field']] ?? false))
            ->pluck('label')
            ->values()
            ->all();
        $enabledAccountingFeatures = collect(ClientFeatureAccess::accountingFeatureDefinitions())
            ->filter(fn (array $definition) => (bool) ($featureValues[$definition['field']] ?? false))
            ->pluck('label')
            ->values()
            ->all();

        return [
            'name' => $client->name,
            'email' => $client->email,
            'phone' => $client->phone,
            'address' => $client->address,
            'business_mode' => $client->business_mode,
            'package_preset' => $client->package_preset,
            'client_type' => $client->client_type,
            'subscription_status' => $client->subscription_status,
            'active_user_limit' => $client->active_user_limit,
            'subscription_ends_at' => optional($client->subscription_ends_at)?->toDateString(),
            'active_users_count' => array_key_exists('active_users_count', $client->getAttributes())
                ? (int) $client->getAttribute('active_users_count')
                : $client->activeManagedUsersCount(),
            'is_active' => (bool) $client->is_active,
            'enabled_modules' => $enabledModules,
            'enabled_accounting_features' => $enabledAccountingFeatures,
            'feature_values' => $featureValues,
        ];
    }

    protected function auditBranchSnapshot(Branch $branch): array
    {
        return [
            'name' => $branch->name,
            'code' => $branch->code,
            'phone' => $branch->phone,
            'email' => $branch->email,
            'address' => $branch->address,
            'business_mode' => $branch->business_mode,
            'is_main' => (bool) $branch->is_main,
            'is_active' => (bool) $branch->is_active,
        ];
    }

    protected function platformWorkspaceContext(Request $request): array
    {
        $user = $request->user();
        $hasTenantContext = $user?->isSuperAdmin()
            ? $user->hasSelectedActingContext()
            : true;

        return [
            'user' => $user,
            'clientName' => $hasTenantContext
                ? (optional($user?->client)->name ?? 'N/A')
                : 'Owner Workspace',
            'branchName' => $hasTenantContext
                ? (optional($user?->branch)->name ?? 'N/A')
                : 'No client selected',
        ];
    }

    protected function featureValidationRules(): array
    {
        return collect(ClientFeatureAccess::settingFields())
            ->mapWithKeys(fn (string $field) => [$field => ['nullable', 'boolean']])
            ->all();
    }

    protected function featureSettingsPayload(Request $request, string $businessMode): array
    {
        $payload = ['business_mode' => $businessMode];

        foreach (ClientFeatureAccess::defaultSettingValues() as $field => $default) {
            $payload[$field] = $request->boolean($field, $default);
        }

        return $payload;
    }

    protected function normalizedLifecycleValues(Request $request, array $validated): array
    {
        $subscriptionStatus = $validated['subscription_status'];
        $isActive = $request->boolean('is_active', true);
        $subscriptionEndsAt = $validated['subscription_ends_at'] ?? null;

        if ($validated['client_type'] === Client::TYPE_TRIAL && empty($subscriptionEndsAt)) {
            $subscriptionEndsAt = now()->addDays(14)->toDateString();
        }

        if ($subscriptionStatus === Client::STATUS_SUSPENDED) {
            $isActive = false;
        }

        return [
            'subscription_status' => $subscriptionStatus,
            'subscription_ends_at' => $subscriptionEndsAt,
            'is_active' => $isActive,
        ];
    }

    protected function presetSeatLimit(?string $packagePreset): ?int
    {
        $preset = ClientPackagePresetCatalog::preset($packagePreset);

        return $preset['active_user_limit'] ?? null;
    }
}
