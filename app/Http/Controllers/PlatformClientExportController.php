<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientExport;
use App\Support\AuditTrail;
use App\Support\ClientExportService;
use Illuminate\Http\Request;
use RuntimeException;

class PlatformClientExportController extends Controller
{
    public function __construct(
        protected ClientExportService $exportService,
        protected AuditTrail $auditTrail,
    ) {
    }

    public function index(Request $request)
    {
        $this->exportService->syncCatalogFromDisk();
        $context = $this->platformWorkspaceContext($request);
        $exports = ClientExport::query()
            ->with(['client', 'creator'])
            ->latest('created_at')
            ->paginate(12)
            ->withQueryString();

        return view('admin.platform.client-exports.index', [
            ...$context,
            'exports' => $exports,
            'clients' => Client::query()
                ->orderBy('name')
                ->get(['id', 'name', 'client_type', 'subscription_status', 'is_active', 'is_platform_sandbox']),
            'totalExports' => ClientExport::query()->count(),
            'readyExports' => ClientExport::query()->where('status', ClientExport::STATUS_READY)->count(),
            'missingExports' => ClientExport::query()->where('status', ClientExport::STATUS_MISSING)->count(),
            'coveredClients' => ClientExport::query()->distinct('client_id')->whereNotNull('client_id')->count('client_id'),
            'latestExport' => ClientExport::query()->latest('created_at')->first(),
            'totalExportBytes' => (int) ClientExport::query()->sum('total_size_bytes'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $client = Client::query()->findOrFail($validated['client_id']);

        try {
            $clientExport = $this->exportService->createClientExport(
                $client,
                $request->user(),
                $validated['notes'] ?? null
            );
        } catch (RuntimeException $exception) {
            return redirect()
                ->back()
                ->withErrors(['client_export' => $exception->getMessage()]);
        }

        $this->auditTrail->recordSafely(
            $request->user(),
            'platform.client_export.created',
            'Platform Owner',
            'Create Client Export',
            'Created a per-client export archive for ' . $client->name . ' named ' . $clientExport->filename . '.',
            [
                'subject' => $clientExport,
                'subject_label' => $clientExport->filename,
                'client_id' => $client->id,
                'branch_id' => null,
                'new_values' => [
                    'client_id' => $client->id,
                    'client_name' => $client->name,
                    'filename' => $clientExport->filename,
                    'export_type' => $clientExport->export_type,
                    'status' => $clientExport->status,
                    'total_size_bytes' => $clientExport->total_size_bytes,
                    'database_tables_count' => $clientExport->database_tables_count,
                    'database_rows_count' => $clientExport->database_rows_count,
                    'storage_files_count' => $clientExport->storage_files_count,
                    'storage_bytes' => $clientExport->storage_bytes,
                ],
            ]
        );

        return redirect()
            ->route('admin.platform.client-exports.show', $clientExport)
            ->with('success', 'Client export created successfully.');
    }

    public function show(Request $request, ClientExport $clientExport)
    {
        $context = $this->platformWorkspaceContext($request);

        try {
            $manifest = $this->exportService->readManifest($clientExport);
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('admin.platform.client-exports.index')
                ->withErrors(['client_export' => $exception->getMessage()]);
        }

        return view('admin.platform.client-exports.show', [
            ...$context,
            'clientExport' => $clientExport,
            'manifest' => $manifest,
        ]);
    }

    public function download(ClientExport $clientExport)
    {
        abort_unless($clientExport->fileExists(), 404, 'Client export file not found.');

        return response()->download($clientExport->absolutePath(), $clientExport->filename);
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
}
