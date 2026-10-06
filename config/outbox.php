<?php

return [
    // Use a "Sending only" key from Developers in the Outbox Stack dashboard.
    'api_key' => env('OUTBOX_API_KEY'),

    'base_url' => env('OUTBOX_BASE_URL', 'https://outboxstack.app'),

    // Seconds per attempt, and retries after timeouts, 429 and 5xx (safe: every send is idempotent).
    'timeout' => env('OUTBOX_TIMEOUT', 10),
    'max_retries' => env('OUTBOX_MAX_RETRIES', 2),

    'webhook' => [
        // The route is registered only when a signing secret is set.
        'secret' => env('OUTBOX_WEBHOOK_SECRET'),
        'path' => env('OUTBOX_WEBHOOK_PATH', 'outbox/webhook'),
        'tolerance' => 300,
        // No CSRF here: requests are authenticated by their signature.
        'middleware' => [],
    ],
];
