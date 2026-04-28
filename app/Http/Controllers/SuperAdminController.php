<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Client;
use App\Models\PlatformBackup;
use App\Support\AuditTrail;
use App\Support\ClientPackagePresetCatalog;
use App\Support\PlatformSandboxManager;
use App\Support\PlatformSupportSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class SuperAdminController extends Controller
{
    protected const SESSION_KEY = 'super_admin.context';

    public function __construct(
        protected PlatformSandboxManager $sandboxManager,
        protected PlatformSupportSettings $supportSettings,
        protected AuditTrail $auditTrail,
    ) {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        [$sandboxClient, $sandboxBranch] = $this->sandboxManager->ensure();

        $allClients = Client::query()
            ->with(['branches' => fn ($query) => $query
                ->where('is_active', true)
                ->orderByDesc('is_main')
                ->orderBy('name')])
            ->withCount([
                'users as active_users_count' => fn ($query) => $query
                    ->where('is_super_admin', false)
                    ->where('is_active', true),
            ])
            ->orderBy('name')
            ->get();

        $clients = $allClients
            ->reject(fn (Client $client) => $client->isPlatformSandbox())
            ->values();

        $storedContext = $request->session()->get(self::SESSION_KEY, []);
        $selectedClient = $allClients->firstWhere('id', (int) ($storedContext['client_id'] ?? 0));
        $selectedBranch = $selectedClient?->branches->firstWhere('id', (int) ($storedContext['branch_id'] ?? 0))
            ?? ($selectedClient ? $selectedClient->branches->first() : null);
        $hasTenantContext = (bool) ($selectedClient && $selectedBranch);
        $branchMap = $clients->mapWithKeys(fn ($client) => [
            $client->id => $client->branches->map(fn ($branch) => [
                'id' => $branch->id,
                'name' => $branch->name,
                'is_main' => (bool) $branch->is_main,
            ])->values()->all(),
        ])->all();

        $payingClients = $clients->where('client_type', Client::TYPE_PAYING)->count();
        $trialClients = $clients->where('client_type', Client::TYPE_TRIAL)->count();
        $demoClients = $clients->where('client_type', Client::TYPE_DEMO)->count();
        $internalClients = $clients->where('client_type', Client::TYPE_INTERNAL)->count();

        $subscriptionSummary = [
            Client::STATUS_ACTIVE => $clients->where('subscription_status', Client::STATUS_ACTIVE)->count(),
            Client::STATUS_GRACE => $clients->where('subscription_status', Client::STATUS_GRACE)->count(),
            Client::STATUS_OVERDUE => $clients->where('subscription_status', Client::STATUS_OVERDUE)->count(),
            Client::STATUS_SUSPENDED => $clients->where('subscription_status', Client::STATUS_SUSPENDED)->count(),
        ];

        $renewalsDueSoon = $clients->filter(fn (Client $client) => $client->subscriptionEndsSoon(7))->count();
        $expiredRenewals = $clients->filter(fn (Client $client) => $client->subscriptionExpired())->count();
        $clientsMissingRenewalDate = $clients->filter(fn (Client $client) => in_array($client->client_type, [Client::TYPE_PAYING, Client::TYPE_TRIAL], true) && !$client->subscription_ends_at)->count();

        $activeSeatsUsed = (int) $clients->sum(fn (Client $client) => (int) $client->active_users_count);
        $seatCapacity = (int) $clients->sum(fn (Client $client) => $client->hasUnlimitedActiveUsers() ? 0 : (int) $client->active_user_limit);
        $remainingSeats = (int) $clients->sum(function (Client $client) {
            $remaining = $client->remainingActiveUserSeats((int) $client->active_users_count);

            return $remaining ?? 0;
        });
        $seatLimitedClients = $clients->filter(fn (Client $client) => !$client->hasUnlimitedActiveUsers())->count();
        $clientsAtSeatLimit = $clients->filter(fn (Client $client) => $client->activeUserLimitReached((int) $client->active_users_count))->count();

        $packageMix = collect(ClientPackagePresetCatalog::options())
            ->map(function (string $label, string $preset) use ($clients) {
                return [
                    'key' => $preset,
                    'label' => $label,
                    'count' => $clients->where('package_preset', $preset)->count(),
                ];
            })
            ->values();

        $customPackageCount = $clients->filter(fn (Client $client) => !ClientPackagePresetCatalog::exists($client->package_preset))->count();
        if ($customPackageCount > 0) {
            $packageMix->push([
                'key' => 'custom',
                'label' => 'Custom',
                'count' => $customPackageCount,
            ]);
        }

        $attentionClients = $clients
            ->map(function (Client $client) {
                $alerts = [];

                if ($client->subscription_status === Client::STATUS_SUSPENDED) {
                    $alerts[] = 'Suspended';
                } elseif ($client->subscription_status === Client::STATUS_OVERDUE) {
                    $alerts[] = 'Overdue';
                } elseif ($client->subscription_status === Client::STATUS_GRACE) {
                    $alerts[] = 'Grace period';
                }

                if ($client->subscriptionExpired()) {
                    $alerts[] = 'Renewal expired';
                } elseif ($client->subscriptionEndsSoon(7)) {
                    $alerts[] = 'Renewal due in 7 days';
                }

                if ($client->activeUserLimitReached((int) $client->active_users_count)) {
                    $alerts[] = 'Seat limit reached';
                }

                if (!$client->subscription_ends_at && in_array($client->client_type, [Client::TYPE_PAYING, Client::TYPE_TRIAL], true)) {
                    $alerts[] = 'Missing renewal date';
                }

                return [
                    'client' => $client,
                    'alerts' => $alerts,
                    'priority' => $this->clientAttentionPriority($client, $alerts),
                ];
            })
            ->filter(fn (array $row) => !empty($row['alerts']))
            ->sortByDesc('priority')
            ->take(6)
            ->values();

        $backupSummary = [
            'available' => Schema::hasTable('platform_backups'),
            'ready_count' => 0,
            'restored_count' => 0,
            'missing_count' => 0,
            'latest' => null,
        ];

        if ($backupSummary['available']) {
            $backupSummary['ready_count'] = PlatformBackup::query()->where('status', PlatformBackup::STATUS_READY)->count();
            $backupSummary['restored_count'] = PlatformBackup::query()->where('status', PlatformBackup::STATUS_RESTORED)->count();
            $backupSummary['missing_count'] = PlatformBackup::query()->where('status', PlatformBackup::STATUS_MISSING)->count();
            $backupSummary['latest'] = PlatformBackup::query()->latest('created_at')->first();
        }

        return view('admin.platform.context', [
            'user' => $user,
            'clientName' => $hasTenantContext ? $selectedClient->name : 'Owner Workspace',
            'branchName' => $hasTenantContext ? $selectedBranch->name : 'No client selected',
            'clients' => $clients,
            'sandboxClient' => $sandboxClient,
            'sandboxBranch' => $sandboxBranch,
            'selectedClient' => $selectedClient,
            'selectedBranch' => $selectedBranch,
            'hasTenantContext' => $hasTenantContext,
            'clientCount' => $clients->count(),
            'branchCount' => $clients->sum(fn ($client) => $client->branches->count()),
            'branchMap' => $branchMap,
            'payingClients' => $payingClients,
            'trialClients' => $trialClients,
            'demoClients' => $demoClients,
            'internalClients' => $internalClients,
            'subscriptionSummary' => $subscriptionSummary,
            'renewalsDueSoon' => $renewalsDueSoon,
            'expiredRenewals' => $expiredRenewals,
            'clientsMissingRenewalDate' => $clientsMissingRenewalDate,
            'activeSeatsUsed' => $activeSeatsUsed,
            'seatCapacity' => $seatCapacity,
            'remainingSeats' => $remainingSeats,
            'seatLimitedClients' => $seatLimitedClients,
            'clientsAtSeatLimit' => $clientsAtSeatLimit,
            'packageMix' => $packageMix,
            'attentionClients' => $attentionClients,
            'backupSummary' => $backupSummary,
            'supportSettings' => $this->supportSettings->resolved(),
            'supportSettingsUpdatedAt' => $this->supportSettings->record()?->updated_at,
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'client_id' => [
                'required',
                Rule::exists('clients', 'id'),
            ],
            'branch_id' => [
                'required',
                Rule::exists('branches', 'id')->where(fn ($query) => $query
                    ->where('client_id', (int) $request->input('client_id'))
                    ->where('is_active', true)),
            ],
        ]);

        $request->session()->put(self::SESSION_KEY, [
            'client_id' => (int) $validated['client_id'],
            'branch_id' => (int) $validated['branch_id'],
        ]);

        return redirect()
            ->route('admin.platform.index')
            ->with('success', 'Platform context updated. You are now working inside the selected client and branch.');
    }

    public function clear(Request $request)
    {
        $request->session()->forget(self::SESSION_KEY);

        return redirect()
            ->route('admin.platform.index')
            ->with('success', 'Client context cleared. You are now back in the platform owner workspace.');
    }

    public function useSandbox(Request $request)
    {
        [$sandboxClient, $sandboxBranch] = $this->sandboxManager->ensure();

        $request->session()->put(self::SESSION_KEY, [
            'client_id' => (int) $sandboxClient->id,
            'branch_id' => (int) $sandboxBranch->id,
        ]);

        return redirect()
            ->route('admin.platform.index')
            ->with('success', 'Sandbox context is active. You can now test features without using a paying client.');
    }

    public function updateSupport(Request $request)
    {
        $validated = $request->validate([
            'company_name' => ['nullable', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone_primary' => ['nullable', 'string', 'max:50'],
            'phone_secondary' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'whatsapp' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'url', 'max:255'],
            'hours' => ['nullable', 'string', 'max:255'],
            'response_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $before = $this->supportSettings->resolved();
        $record = $this->supportSettings->save($validated);
        $after = $this->supportSettings->resolved();

        $this->auditTrail->recordSafely(
            $request->user(),
            'platform.support.updated',
            'Platform Owner',
            'Update Support Contacts',
            'Updated the global support contacts shown on pharmacy support screens.',
            [
                'subject' => $record,
                'subject_label' => 'Platform Support Settings',
                'client_id' => null,
                'branch_id' => null,
                'old_values' => $before,
                'new_values' => $after,
            ]
        );

        return redirect()
            ->route('admin.platform.index')
            ->with('success', 'Support contacts updated. All client support screens will now use the new details.');
    }

    protected function clientAttentionPriority(Client $client, array $alerts): int
    {
        $priority = 0;

        if ($client->subscription_status === Client::STATUS_SUSPENDED) {
            $priority += 100;
        } elseif ($client->subscription_status === Client::STATUS_OVERDUE) {
            $priority += 80;
        } elseif ($client->subscription_status === Client::STATUS_GRACE) {
            $priority += 60;
        }

        if ($client->subscriptionExpired()) {
            $priority += 50;
        } elseif ($client->subscriptionEndsSoon(7)) {
            $priority += 30;
        }

        if ($client->activeUserLimitReached((int) $client->active_users_count)) {
            $priority += 20;
        }

        if (in_array('Missing renewal date', $alerts, true)) {
            $priority += 10;
        }

        return $priority;
    }
}
