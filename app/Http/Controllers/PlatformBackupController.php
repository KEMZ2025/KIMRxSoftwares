<?php

namespace App\Http\Controllers;

use App\Models\PlatformBackup;
use App\Support\AuditTrail;
use App\Support\PlatformBackupService;
use Illuminate\Http\Request;
use RuntimeException;

class PlatformBackupController extends Controller
{
    public function __construct(
        protected PlatformBackupService $backupService,
        protected AuditTrail $auditTrail,
    ) {
    }

    public function index(Request $request)
    {
        $this->backupService->syncCatalogFromDisk();
        $context = $this->platformWorkspaceContext($request);
        $backups = PlatformBackup::query()
            ->with(['creator', 'restorer'])
            ->latest('created_at')
            ->paginate(12)
            ->withQueryString();

        return view('admin.platform.backups.index', [
            ...$context,
            'backups' => $backups,
            'totalBackups' => PlatformBackup::query()->count(),
            'readyBackups' => PlatformBackup::query()->where('status', PlatformBackup::STATUS_READY)->count(),
            'restoredBackups' => PlatformBackup::query()->where('status', PlatformBackup::STATUS_RESTORED)->count(),
            'missingBackups' => PlatformBackup::query()->where('status', PlatformBackup::STATUS_MISSING)->count(),
            'latestBackup' => PlatformBackup::query()->latest('created_at')->first(),
            'totalBackupBytes' => (int) PlatformBackup::query()->sum('total_size_bytes'),
            'automationEnabled' => (bool) config('backup.platform.auto_enabled', false),
            'automationTime' => (string) config('backup.platform.auto_time', '02:00'),
            'retentionCount' => max(1, (int) config('backup.platform.retention_count', 14)),
            'skipRecentMinutes' => max(0, (int) config('backup.platform.skip_if_recent_minutes', 240)),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $backup = $this->backupService->createFullBackup(
                $request->user(),
                $validated['notes'] ?? null
            );
        } catch (RuntimeException $exception) {
            return redirect()
                ->back()
                ->withErrors(['backup' => $exception->getMessage()]);
        }

        $this->auditTrail->recordSafely(
            $request->user(),
            'platform.backup.created',
            'Platform Owner',
            'Create Full Backup',
            'Created a full platform backup named ' . $backup->filename . '.',
            [
                'subject' => $backup,
                'subject_label' => $backup->filename,
                'client_id' => null,
                'branch_id' => null,
                'new_values' => [
                    'filename' => $backup->filename,
                    'backup_type' => $backup->backup_type,
                    'status' => $backup->status,
                    'total_size_bytes' => $backup->total_size_bytes,
                    'database_tables_count' => $backup->database_tables_count,
                    'database_rows_count' => $backup->database_rows_count,
                    'storage_files_count' => $backup->storage_files_count,
                    'storage_bytes' => $backup->storage_bytes,
                ],
            ]
        );

        return redirect()
            ->route('admin.platform.backups.show', $backup)
            ->with('success', 'Full platform backup created successfully.');
    }

    public function show(Request $request, PlatformBackup $backup)
    {
        $context = $this->platformWorkspaceContext($request);

        try {
            $manifest = $this->backupService->readManifest($backup);
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('admin.platform.backups.index')
                ->withErrors(['backup' => $exception->getMessage()]);
        }

        return view('admin.platform.backups.show', [
            ...$context,
            'backup' => $backup,
            'manifest' => $manifest,
        ]);
    }

    public function download(PlatformBackup $backup)
    {
        abort_unless($backup->fileExists(), 404, 'Backup file not found.');

        return response()->download($backup->absolutePath(), $backup->filename);
    }

    public function restore(Request $request, PlatformBackup $backup)
    {
        $validated = $request->validate([
            'restore_confirmation' => ['required', 'string'],
            'create_safety_backup' => ['nullable', 'boolean'],
        ]);

        if (trim((string) $validated['restore_confirmation']) !== $backup->filename) {
            return redirect()
                ->back()
                ->withErrors([
                    'restore_confirmation' => 'Type the exact backup filename before a full restore can start.',
                ]);
        }

        try {
            $this->backupService->restoreFullBackup(
                $backup,
                $request->user(),
                $request->boolean('create_safety_backup', true)
            );
        } catch (RuntimeException $exception) {
            return redirect()
                ->back()
                ->withErrors(['backup' => $exception->getMessage()]);
        }

        $restoredBackup = PlatformBackup::query()->where('filename', $backup->filename)->first();

        $this->auditTrail->recordSafely(
            $request->user(),
            'platform.backup.restored',
            'Platform Owner',
            'Restore Full Backup',
            'Restored the full platform from backup ' . $backup->filename . '.',
            [
                'subject' => $restoredBackup,
                'subject_label' => $backup->filename,
                'client_id' => null,
                'branch_id' => null,
                'new_values' => [
                    'filename' => $backup->filename,
                    'status' => PlatformBackup::STATUS_RESTORED,
                    'restored_at' => now()->toIso8601String(),
                ],
            ]
        );

        return redirect()
            ->route('admin.platform.backups.index')
            ->with('success', 'Backup restored. If your session or client data changed, refresh and log in again if needed.');
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
