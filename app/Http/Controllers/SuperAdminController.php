<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Client;
use App\Support\AuditTrail;
use App\Support\PlatformSandboxManager;
use App\Support\PlatformSupportSettings;
use Illuminate\Http\Request;
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
}
