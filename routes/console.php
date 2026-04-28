<?php

use App\Support\Compliance\EfrisSyncProcessor;
use App\Support\PlatformGoLiveCheckService;
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

Schedule::command('efris:process --scope=ready --limit=25')
    ->everyMinute()
    ->withoutOverlapping();
