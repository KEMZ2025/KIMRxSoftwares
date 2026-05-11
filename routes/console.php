<?php

use App\Support\Compliance\EfrisSyncProcessor;
use App\Support\PlatformBackupService;
use App\Support\PlatformGoLiveCheckService;
use App\Support\PlatformPostDeploySmokeTestService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Symfony\Component\Console\Command\Command;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('efris:process {--client=} {--scope=ready} {--limit=25}', function (EfrisSyncProcessor $processor) {
    $scope = (string) $this->option('scope');

    if (!in_array($scope, ['ready', 'failed', 'all'], true)) {
        $this->error('The scope must be one of: ready, failed, all.');

        return Command::FAILURE;
    }

    $limit = max(1, (int) $this->option('limit'));
    $clientId = $this->option('client');

    $summary = $clientId
        ? $processor->processClient((int) $clientId, $scope, $limit)
        : $processor->processAll($scope, $limit);

    $this->info(
        'EFRIS queue processed '
        . $summary['processed']
        . ' document(s): '
        . $summary['accepted']
        . ' accepted, '
        . $summary['submitted']
        . ' submitted, '
        . $summary['failed']
        . ' failed.'
    );

    return Command::SUCCESS;
})->purpose('Process queued URA / EFRIS documents');

Artisan::command('platform:go-live-check {--allow-non-production}', function (PlatformGoLiveCheckService $service) {
    $allowNonProduction = $this->option('allow-non-production');
    $result = $service->run((bool) $allowNonProduction);

    $rows = collect($result['checks'])
        ->map(function (array $check) {
            return [
                $check['label'],
                strtoupper((string) $check['status']),
                $check['message'],
                $check['action'],
            ];
        })
        ->all();

    $this->table(['Check', 'Status', 'Current State', 'Action'], $rows);

    $summary = $result['summary'];
    $this->line('Checked at: ' . $result['checked_at']->format('Y-m-d H:i:s'));
    $this->line('Passed: ' . $summary['passed'] . ' | Warnings: ' . $summary['warnings'] . ' | Failed: ' . $summary['failed']);

    if ($summary['failed'] > 0) {
        $this->error('Go-live readiness is blocked by one or more failed checks.');

        return Command::FAILURE;
    }

    if ($summary['warnings'] > 0) {
        $this->warn('Go-live readiness passed with warnings. Review the warning actions before production rollout.');

        return Command::SUCCESS;
    }

    $this->info('Go-live readiness passed with no blocking issues.');

    return Command::SUCCESS;
})->purpose('Review production go-live readiness for the KIM Rx platform');

Artisan::command('platform:backup:auto {--keep=} {--force-run}', function (PlatformBackupService $service) {
    $autoEnabled = (bool) config('backup.platform.auto_enabled', false);

    if (!$autoEnabled && !$this->option('force-run')) {
        $this->warn('Automatic platform backups are disabled. Set PLATFORM_BACKUPS_AUTO_ENABLED=true or use --force-run.');

        return Command::SUCCESS;
    }

    $keep = max(1, (int) ($this->option('keep') ?: config('backup.platform.retention_count', 14)));
    $skipRecentMinutes = max(0, (int) config('backup.platform.skip_if_recent_minutes', 240));
    $latestBackup = $service->latestReadyBackup();

    if (
        !$this->option('force-run')
        && $latestBackup
        && $latestBackup->created_at
        && $latestBackup->created_at->greaterThanOrEqualTo(now()->subMinutes($skipRecentMinutes))
    ) {
        $this->info(
            'Skipped automatic platform backup because a recent archive already exists from '
            . $latestBackup->created_at->format('Y-m-d H:i:s')
            . '.'
        );

        $pruned = $service->pruneFullBackupRetention($keep);

        if (($pruned['deleted_records'] ?? 0) > 0) {
            $this->line('Retention cleanup removed ' . $pruned['deleted_records'] . ' old backup record(s).');
        }

        return Command::SUCCESS;
    }

    $backup = $service->createFullBackup(
        null,
        'Automatic scheduled platform backup'
    );

    $pruned = $service->pruneFullBackupRetention($keep);

    $this->info('Created automatic platform backup ' . $backup->filename . '.');
    $this->line('Retention policy keeps the latest ' . $keep . ' backup(s).');

    if (($pruned['deleted_records'] ?? 0) > 0) {
        $this->line('Removed ' . $pruned['deleted_records'] . ' old backup record(s) and ' . $pruned['deleted_files'] . ' archive file(s).');
    }

    return Command::SUCCESS;
})->purpose('Run the automatic full platform backup and enforce retention');

Artisan::command('platform:post-deploy-smoke-test', function (PlatformPostDeploySmokeTestService $service) {
    $result = $service->run();

    $rows = collect($result['checks'])
        ->map(function (array $check) {
            return [
                $check['label'],
                strtoupper((string) $check['status']),
                $check['message'],
                $check['action'],
            ];
        })
        ->all();

    $this->table(['Check', 'Status', 'Current State', 'Action'], $rows);

    $summary = $result['summary'];
    $this->line('Checked at: ' . $result['checked_at']->format('Y-m-d H:i:s'));
    $this->line('Passed: ' . $summary['passed'] . ' | Warnings: ' . $summary['warnings'] . ' | Failed: ' . $summary['failed']);

    if ($summary['failed'] > 0) {
        $this->error('Post-deploy smoke test found one or more blocking failures.');

        return Command::FAILURE;
    }

    if ($summary['warnings'] > 0) {
        $this->warn('Post-deploy smoke test passed with warnings. Review the warning actions before opening the update to users.');

        return Command::SUCCESS;
    }

    $this->info('Post-deploy smoke test passed with no blocking issues.');

    return Command::SUCCESS;
})->purpose('Run a post-deploy smoke test against critical KIM Rx owner and tenant screens');


Artisan::command('sales:reopen-partial-cash {--invoice=} {--id=} {--dry-run}', function () {
    $invoice = trim((string) $this->option('invoice'));
    $id = $this->option('id');

    $baseQuery = \App\Models\Sale::query()
        ->with(['customer', 'items'])
        ->where('status', 'approved')
        ->where('payment_type', 'cash')
        ->where('balance_due', '>', 0);

    if ($invoice === '' && !$id) {
        $sales = (clone $baseQuery)
            ->latest('approved_at')
            ->limit(25)
            ->get();

        if ($sales->isEmpty()) {
            $this->info('No approved cash sales with unpaid balances were found.');

            return Command::SUCCESS;
        }

        $this->table(
            ['ID', 'Invoice', 'Receipt', 'Customer', 'Total', 'Received', 'Balance', 'Approved At'],
            $sales->map(function (\App\Models\Sale $sale) {
                return [
                    $sale->id,
                    $sale->invoice_number,
                    $sale->receipt_number ?: 'N/A',
                    $sale->customer?->name ?? 'Walk-in / N/A',
                    number_format((float) $sale->total_amount, 2),
                    number_format((float) $sale->amount_received, 2),
                    number_format((float) $sale->balance_due, 2),
                    $sale->approved_at ? $sale->approved_at->format('Y-m-d H:i:s') : 'N/A',
                ];
            })->all()
        );

        $this->warn('To reopen one sale, run: php artisan sales:reopen-partial-cash --invoice=INVOICE_NUMBER');

        return Command::SUCCESS;
    }

    $saleQuery = clone $baseQuery;

    if ($id) {
        $saleQuery->whereKey((int) $id);
    }

    if ($invoice !== '') {
        $saleQuery->where('invoice_number', $invoice);
    }

    $sale = $saleQuery->first();

    if (!$sale) {
        $this->error('No matching approved cash sale with an unpaid balance was found.');

        return Command::FAILURE;
    }

    $activeCollections = \App\Models\Payment::query()
        ->where('sale_id', $sale->id)
        ->where('status', 'received')
        ->count();

    if ($activeCollections > 0) {
        $this->error('This sale already has customer collection payments recorded. Reverse those payments before reopening the sale.');

        return Command::FAILURE;
    }

    $efrisDocument = \App\Models\EfrisDocument::query()
        ->where('sale_id', $sale->id)
        ->first();

    if (
        $efrisDocument
        && (
            in_array((string) $efrisDocument->status, ['submitted', 'accepted'], true)
            || $efrisDocument->submitted_at
            || $efrisDocument->accepted_at
        )
    ) {
        $this->error('This sale already has a submitted/accepted EFRIS document. Do not reopen it from this command; cancel/reverse through the normal audit flow.');

        return Command::FAILURE;
    }

    $this->table(
        ['ID', 'Invoice', 'Receipt', 'Customer', 'Total', 'Received', 'Balance'],
        [[
            $sale->id,
            $sale->invoice_number,
            $sale->receipt_number ?: 'N/A',
            $sale->customer?->name ?? 'Walk-in / N/A',
            number_format((float) $sale->total_amount, 2),
            number_format((float) $sale->amount_received, 2),
            number_format((float) $sale->balance_due, 2),
        ]]
    );

    if ($this->option('dry-run')) {
        $this->warn('Dry run only. No data was changed.');

        return Command::SUCCESS;
    }

    \Illuminate\Support\Facades\DB::transaction(function () use ($sale) {
        $lockedSale = \App\Models\Sale::query()
            ->with(['items'])
            ->whereKey($sale->id)
            ->lockForUpdate()
            ->firstOrFail();

        if (
            $lockedSale->status !== 'approved'
            || $lockedSale->payment_type !== 'cash'
            || (float) $lockedSale->balance_due <= 0
        ) {
            throw new \RuntimeException('Sale is no longer an approved partial cash sale.');
        }

        foreach ($lockedSale->items as $item) {
            $batch = \App\Models\ProductBatch::query()
                ->where('id', $item->product_batch_id)
                ->where('client_id', $lockedSale->client_id)
                ->where('branch_id', $lockedSale->branch_id)
                ->lockForUpdate()
                ->first();

            if (!$batch) {
                throw new \RuntimeException('A batch for one of the sale items could not be found.');
            }

            $qty = (float) $item->quantity;
            $batch->quantity_available = (float) $batch->quantity_available + $qty;
            $batch->reserved_quantity = (float) $batch->reserved_quantity + $qty;
            $batch->save();
        }

        $lockedSale->forceFill([
            'status' => 'pending',
            'approved_by' => null,
            'approved_at' => null,
            'receipt_number' => null,
            'payment_method' => null,
            'amount_received' => 0,
            'amount_paid' => 0,
            'upfront_amount_paid' => 0,
            'balance_due' => (float) $lockedSale->total_amount,
            'insurance_claim_status' => null,
            'insurance_submitted_at' => null,
            'insurance_approved_at' => null,
            'insurance_rejected_at' => null,
            'insurance_paid_at' => null,
        ])->save();

        \App\Models\EfrisDocument::query()
            ->where('sale_id', $lockedSale->id)
            ->whereNull('submitted_at')
            ->whereNull('accepted_at')
            ->delete();
    });

    $this->info('Sale reopened to Pending. Stock has been restored to reserved status, and the wrong partial cash payment was cleared.');
    $this->line('Open the pending sale and approve it again with the correct full cash amount, or change Payment Type to Credit if part remains unpaid.');

    return Command::SUCCESS;
})->purpose('Reopen an approved cash sale that was wrongly approved with an unpaid balance');
Schedule::command('efris:process --scope=ready --limit=25')
    ->everyMinute()
    ->withoutOverlapping();

if ((bool) config('backup.platform.auto_enabled', false)) {
    Schedule::command('platform:backup:auto')
        ->dailyAt((string) config('backup.platform.auto_time', '02:00'))
        ->timezone((string) config('app.timezone', 'UTC'))
        ->withoutOverlapping();
}
