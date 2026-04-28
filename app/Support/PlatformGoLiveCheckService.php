<?php

namespace App\Support;

use App\Models\PlatformBackup;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class PlatformGoLiveCheckService
{
    public const STATUS_PASS = 'pass';
    public const STATUS_WARNING = 'warning';
    public const STATUS_FAIL = 'fail';

    public function run(bool $allowNonProduction = false): array
    {
        $checks = [];

        $this->pushCheck(
            $checks,
            'php_version',
            'PHP Runtime',
            version_compare(PHP_VERSION, '8.2.0', '>=') ? self::STATUS_PASS : self::STATUS_FAIL,
            'Current runtime: PHP ' . PHP_VERSION . '.',
            'Use PHP 8.2 or newer on the production server.'
        );

        $appEnv = (string) config('app.env');
        $this->pushCheck(
            $checks,
            'app_env',
            'Application Environment',
            $appEnv === 'production'
                ? self::STATUS_PASS
                : ($allowNonProduction ? self::STATUS_WARNING : self::STATUS_FAIL),
            'APP_ENV is [' . $appEnv . '].',
            'Set APP_ENV=production before live go-live.'
        );

        $debugEnabled = (bool) config('app.debug');
        $this->pushCheck(
            $checks,
            'app_debug',
            'Debug Mode',
            !$debugEnabled
                ? self::STATUS_PASS
                : ($allowNonProduction ? self::STATUS_WARNING : self::STATUS_FAIL),
            $debugEnabled ? 'APP_DEBUG is enabled.' : 'APP_DEBUG is disabled.',
            'Set APP_DEBUG=false on the live server.'
        );

        $appKey = (string) config('app.key');
        $this->pushCheck(
            $checks,
            'app_key',
            'Application Key',
            $appKey !== '' ? self::STATUS_PASS : self::STATUS_FAIL,
            $appKey !== '' ? 'Application key is configured.' : 'Application key is missing.',
            'Generate and store APP_KEY before production use.'
        );

        $appUrl = (string) config('app.url');
        $host = strtolower((string) parse_url($appUrl, PHP_URL_HOST));
        $scheme = strtolower((string) parse_url($appUrl, PHP_URL_SCHEME));
        $isLocalHost = in_array($host, ['', 'localhost', '127.0.0.1'], true);
        $hasHttps = $scheme === 'https';
        $appUrlStatus = (!$isLocalHost && $hasHttps)
            ? self::STATUS_PASS
            : ($allowNonProduction ? self::STATUS_WARNING : self::STATUS_FAIL);
        $this->pushCheck(
            $checks,
            'app_url',
            'Application URL',
            $appUrlStatus,
            'APP_URL is [' . ($appUrl !== '' ? $appUrl : 'not set') . '].',
            'Use the real HTTPS production domain in APP_URL.'
        );

        $databaseDriver = (string) config('database.default');
        $databaseStatus = $databaseDriver !== 'sqlite'
            ? self::STATUS_PASS
            : ($allowNonProduction ? self::STATUS_WARNING : self::STATUS_FAIL);
        $this->pushCheck(
            $checks,
            'database_driver',
            'Database Driver',
            $databaseStatus,
            'Database driver is [' . $databaseDriver . '].',
            'Use a production-grade database such as MySQL or MariaDB for live deployments.'
        );

        $sessionDriver = (string) config('session.driver');
        $this->pushCheck(
            $checks,
            'session_store',
            'Session Storage',
            $this->sessionStatus($sessionDriver),
            'Session driver is [' . $sessionDriver . '].',
            $sessionDriver === 'database'
                ? 'Run migrations so the sessions table exists.'
                : 'Confirm the selected session driver is appropriate for production.'
        );

        $cacheStore = (string) config('cache.default');
        $this->pushCheck(
            $checks,
            'cache_store',
            'Cache Store',
            $this->cacheStatus($cacheStore, $allowNonProduction),
            'Cache store is [' . $cacheStore . '].',
            $cacheStore === 'database'
                ? 'Run migrations so cache and cache_locks exist.'
                : 'Avoid array cache in production because cached values do not persist.'
        );

        $queueConnection = (string) config('queue.default');
        $this->pushCheck(
            $checks,
            'queue_connection',
            'Queue Connection',
            $this->queueStatus($queueConnection, $allowNonProduction),
            'Queue connection is [' . $queueConnection . '].',
            $queueConnection === 'database'
                ? 'Run migrations so jobs, job_batches, and failed_jobs exist.'
                : 'Use an async worker-backed queue in production.'
        );

        $this->pushCheck(
            $checks,
            'storage_public',
            'Public Storage Link',
            $this->publicStorageStatus(),
            $this->publicStorageMessage(),
            'Run php artisan storage:link during deployment if public file access is broken.'
        );

        $this->pushCheck(
            $checks,
            'storage_writable',
            'Writable Storage Paths',
            $this->storageWritableStatus(),
            'Storage directories are ' . ($this->storageWritableStatus() === self::STATUS_PASS ? 'writable.' : 'not fully writable.'),
            'Ensure storage/app, storage/logs, and bootstrap/cache are writable by the web server.'
        );

        $mailMailer = (string) config('mail.default');
        $mailStatus = in_array($mailMailer, ['log', 'array'], true)
            ? self::STATUS_WARNING
            : self::STATUS_PASS;
        $this->pushCheck(
            $checks,
            'mail_mailer',
            'Mail Delivery',
            $mailStatus,
            'Mail driver is [' . $mailMailer . '].',
            'Use a real mail transport if password resets or support mailouts should reach real users.'
        );

        $logLevel = strtolower((string) config('logging.channels.single.level', config('logging.level', 'debug')));
        $this->pushCheck(
            $checks,
            'log_level',
            'Application Logging',
            $logLevel === 'debug' ? self::STATUS_WARNING : self::STATUS_PASS,
            'Primary log level resolves to [' . $logLevel . '].',
            'Prefer info, notice, or warning in production unless you are debugging a live issue.'
        );

        $secureSessionCookie = (bool) config('session.secure');
        $this->pushCheck(
            $checks,
            'secure_cookie',
            'Secure Session Cookie',
            $secureSessionCookie ? self::STATUS_PASS : self::STATUS_WARNING,
            $secureSessionCookie
                ? 'SESSION_SECURE_COOKIE is enabled.'
                : 'SESSION_SECURE_COOKIE is not enabled.',
            'Enable secure cookies when serving the app through HTTPS.'
        );

        $activeSuperAdminExists = User::query()
            ->where('is_super_admin', true)
            ->where('is_active', true)
            ->exists();
        $this->pushCheck(
            $checks,
            'super_admin',
            'Platform Owner Access',
            $activeSuperAdminExists ? self::STATUS_PASS : self::STATUS_FAIL,
            $activeSuperAdminExists
                ? 'At least one active platform owner account exists.'
                : 'No active platform owner account was found.',
            'Keep at least one active super admin account for platform recovery and package management.'
        );

        $backupStatus = Schema::hasTable('platform_backups')
            ? (PlatformBackup::query()->where('status', PlatformBackup::STATUS_READY)->exists()
                ? self::STATUS_PASS
                : self::STATUS_WARNING)
            : self::STATUS_FAIL;
        $backupMessage = Schema::hasTable('platform_backups')
            ? (PlatformBackup::query()->where('status', PlatformBackup::STATUS_READY)->exists()
                ? 'At least one ready full backup exists in the catalog.'
                : 'No ready full platform backup exists yet.')
            : 'The platform_backups table is missing.';
        $this->pushCheck(
            $checks,
            'backup_readiness',
            'Backup Readiness',
            $backupStatus,
            $backupMessage,
            'Create a full platform backup before deployments, imports, and other risky changes.'
        );

        $this->pushCheck(
            $checks,
            'scheduler_process',
            'Scheduler Process',
            self::STATUS_WARNING,
            'The app cannot confirm your server cron from inside Laravel.',
            'Configure php artisan schedule:run every minute on the live server.'
        );

        $this->pushCheck(
            $checks,
            'queue_worker_process',
            'Queue Worker Process',
            in_array($queueConnection, ['sync', 'null'], true) ? self::STATUS_WARNING : self::STATUS_WARNING,
            'The app cannot confirm a running queue worker from inside Laravel.',
            'Run a persistent queue worker under Supervisor, systemd, Forge, or another process manager.'
        );

        $summary = [
            'passed' => collect($checks)->where('status', self::STATUS_PASS)->count(),
            'warnings' => collect($checks)->where('status', self::STATUS_WARNING)->count(),
            'failed' => collect($checks)->where('status', self::STATUS_FAIL)->count(),
        ];

        return [
            'checked_at' => now(),
            'ready' => $summary['failed'] === 0,
            'checks' => $checks,
            'summary' => $summary,
        ];
    }

    private function sessionStatus(string $driver): string
    {
        if ($driver === 'database') {
            return Schema::hasTable((string) config('session.table', 'sessions'))
                ? self::STATUS_PASS
                : self::STATUS_FAIL;
        }

        if ($driver === 'array') {
            return self::STATUS_WARNING;
        }

        return self::STATUS_PASS;
    }

    private function cacheStatus(string $store, bool $allowNonProduction): string
    {
        if ($store === 'database') {
            $cacheTable = (string) config('cache.stores.database.table', 'cache');
            $lockTable = (string) (config('cache.stores.database.lock_table') ?: 'cache_locks');

            return Schema::hasTable($cacheTable) && Schema::hasTable($lockTable)
                ? self::STATUS_PASS
                : self::STATUS_FAIL;
        }

        if ($store === 'array') {
            return $allowNonProduction ? self::STATUS_WARNING : self::STATUS_FAIL;
        }

        return self::STATUS_PASS;
    }

    private function queueStatus(string $connection, bool $allowNonProduction): string
    {
        if ($connection === 'database') {
            $jobsTable = (string) config('queue.connections.database.table', 'jobs');
            $batchTable = (string) config('queue.batching.table', 'job_batches');
            $failedTable = (string) config('queue.failed.table', 'failed_jobs');

            return Schema::hasTable($jobsTable)
                && Schema::hasTable($batchTable)
                && Schema::hasTable($failedTable)
                    ? self::STATUS_PASS
                    : self::STATUS_FAIL;
        }

        if (in_array($connection, ['sync', 'null'], true)) {
            return $allowNonProduction ? self::STATUS_WARNING : self::STATUS_FAIL;
        }

        return self::STATUS_PASS;
    }

    private function publicStorageStatus(): string
    {
        $path = public_path('storage');

        if (is_link($path)) {
            return self::STATUS_PASS;
        }

        if (is_dir($path) && is_readable($path)) {
            return self::STATUS_PASS;
        }

        return self::STATUS_FAIL;
    }

    private function publicStorageMessage(): string
    {
        $path = public_path('storage');

        if (is_link($path)) {
            return 'public/storage exists as a symbolic link.';
        }

        if (is_dir($path) && is_readable($path)) {
            return 'public/storage exists as a readable directory.';
        }

        return 'public/storage is missing or unreadable.';
    }

    private function storageWritableStatus(): string
    {
        $paths = [
            storage_path('app'),
            storage_path('logs'),
            base_path('bootstrap/cache'),
        ];

        foreach ($paths as $path) {
            if (!is_dir($path) || !is_writable($path)) {
                return self::STATUS_FAIL;
            }
        }

        return self::STATUS_PASS;
    }

    private function pushCheck(array &$checks, string $key, string $label, string $status, string $message, string $action): void
    {
        $checks[] = [
            'key' => $key,
            'label' => $label,
            'status' => $status,
            'message' => $message,
            'action' => $action,
        ];
    }
}
