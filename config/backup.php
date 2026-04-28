<?php

return [
    'platform' => [
        'auto_enabled' => filter_var(env('PLATFORM_BACKUPS_AUTO_ENABLED', true), FILTER_VALIDATE_BOOL),
        'auto_time' => env('PLATFORM_BACKUPS_AUTO_TIME', '02:00'),
        'retention_count' => max(1, (int) env('PLATFORM_BACKUPS_RETENTION_COUNT', 14)),
        'skip_if_recent_minutes' => max(0, (int) env('PLATFORM_BACKUPS_SKIP_IF_RECENT_MINUTES', 240)),
    ],
];
