<?php

return [

    /*
    |--------------------------------------------------------------------------
    | API key
    |--------------------------------------------------------------------------
    |
    | Create a key in the Stedi portal under Settings > API Keys. Test keys
    | (test_...) work with mock eligibility checks and claims; production keys
    | reach real payers. Use Stedi::withApiKey() to switch keys at runtime.
    |
    */

    'api_key' => env('STEDI_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | HTTP options
    |--------------------------------------------------------------------------
    |
    | timeout: seconds to wait for a response. Real-time eligibility and claim
    | status checks wait on the payer, so keep it generous.
    |
    | retries: how many extra attempts a request gets. Requests are retried
    | on 429 (honouring Retry-After), and on 5xx answers and connection errors
    | when repeating them is safe: reads, and writes sent with an idempotency
    | key (claim submissions get one automatically).
    |
    | retry_delay: base delay in milliseconds between attempts; it doubles on
    | every attempt.
    |
    | retry_max_delay: the longest wait between attempts in milliseconds, also
    | when Stedi asks for more with Retry-After. Keep it short when requests
    | run inside a web request.
    |
    */

    'timeout' => (int) env('STEDI_TIMEOUT', 60),

    'retries' => (int) env('STEDI_RETRIES', 2),

    'retry_delay' => (int) env('STEDI_RETRY_DELAY', 500),

    'retry_max_delay' => (int) env('STEDI_RETRY_MAX_DELAY', 10000),

    /*
    |--------------------------------------------------------------------------
    | Extra headers and API endpoints
    |--------------------------------------------------------------------------
    |
    | headers: sent with every request.
    |
    | endpoints: override the base URL of an API ("healthcare", "claims",
    | "manager", "payers", "enrollment", "events" or "core"), for example to
    | go through a proxy.
    |
    */

    'headers' => [],

    'endpoints' => [],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | log: the lowest level to log: "debug" (every request with its status and
    | duration), "info" (retries), "warning" (failed requests), "error" (server
    | and connection errors) or null to log nothing. Bodies, query strings and
    | the API key are never logged, since they can hold patient data.
    |
    | log_channel: the Laravel log channel to write to; null uses the default.
    |
    */

    'log' => env('STEDI_LOG'),

    'log_channel' => env('STEDI_LOG_CHANNEL'),

    /*
    |--------------------------------------------------------------------------
    | Webhooks (event destinations)
    |--------------------------------------------------------------------------
    |
    | path: where Stedi delivers events; null disables the route. Point your
    | event destination at https://your-app.com/{path}.
    |
    | secret: the destination's signing secret (whsec_...). While rotating,
    | list the old and new secrets separated by a comma.
    |
    | tolerance: seconds a delivery's timestamp may differ from now.
    |
    | deduplicate: acknowledge redelivered events (same webhook-id) without
    | firing WebhookReceived again. Uses your default cache store, and its
    | locks when the store supports them, so concurrent deliveries of the same
    | event are handled once.
    |
    | middleware: middleware for the webhook route.
    |
    */

    'webhook' => [
        'path' => env('STEDI_WEBHOOK_PATH', 'stedi/webhook'),
        'secret' => env('STEDI_WEBHOOK_SECRET'),
        'tolerance' => (int) env('STEDI_WEBHOOK_TOLERANCE', 300),
        'deduplicate' => true,
        'middleware' => [],
    ],

];
