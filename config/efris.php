<?php

return [
    'transport' => env('EFRIS_TRANSPORT', 'simulate'),
    'batch_limit' => (int) env('EFRIS_BATCH_LIMIT', 25),
    'timeout_seconds' => (int) env('EFRIS_TIMEOUT_SECONDS', 20),
    'connect_timeout_seconds' => (int) env('EFRIS_CONNECT_TIMEOUT_SECONDS', 10),
];
