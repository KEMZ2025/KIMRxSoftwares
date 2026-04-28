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

Schedule::command('efris:process --scope=ready --limit=25')
    ->everyMinute()
    ->withoutOverlapping();

if ((bool) config('backup.platform.auto_enabled', false)) {
    Schedule::command('platform:backup:auto')
        ->dailyAt((string) config('backup.platform.auto_time', '02:00'))
        ->timezone((string) config('app.timezone', 'UTC'))
        ->withoutOverlapping();
}
