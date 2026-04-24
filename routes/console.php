<?php

use App\Support\Compliance\EfrisSyncProcessor;
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

Schedule::command('efris:process --scope=ready --limit=25')
    ->everyMinute()
    ->withoutOverlapping();
