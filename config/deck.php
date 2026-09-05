<?php

/*
|--------------------------------------------------------------------------
| Deck core (kernel) configuration
|--------------------------------------------------------------------------
|
| These keys belong to deck/core (the queue-instrumentation kernel) and are
| merged under the shared `deck.*` config namespace. deck/deck ships the full
| published config file; deck/core and deck/cloud only merge their own slices
| so each package works standalone. All keys here are disjoint from the cloud
| and dashboard slices, so the shallow merge never clobbers.
|
*/

return [

    'project' => env('DECK_PROJECT', env('APP_NAME', 'laravel')),

    'environment' => env('DECK_ENVIRONMENT', env('APP_ENV', 'production')),

    'cancel_ttl_seconds' => (int) env('DECK_CANCEL_TTL_SECONDS', 86_400),

    'cancel_cache_store' => env('DECK_CANCEL_CACHE_STORE'),

    'progress_ttl_seconds' => (int) env('DECK_PROGRESS_TTL_SECONDS', 86_400),

    'progress_cache_store' => env('DECK_PROGRESS_CACHE_STORE'),

    'timing_terminal_ttl_seconds' => (int) env('DECK_TIMING_TERMINAL_TTL_SECONDS', 300),

    'block_release_seconds' => (int) env('DECK_BLOCK_RELEASE_SECONDS', 60),

    'block_cache_store' => env('DECK_BLOCK_CACHE_STORE'),

    'block_manual_ttl_seconds' => (int) env('DECK_BLOCK_MANUAL_TTL_SECONDS', 31_536_000),

    'block_reason_max_length' => (int) env('DECK_BLOCK_REASON_MAX_LENGTH', 500),

    // Queue pause flags (connection:queue). Falls back to the block/cancel
    // store so all control flags live together. A pause is held until resumed
    // or until this TTL elapses (a year by default) — it never expires quietly
    // mid-incident.
    'pause_cache_store' => env('DECK_PAUSE_CACHE_STORE'),
    'pause_ttl_seconds' => (int) env('DECK_PAUSE_TTL_SECONDS', 31_536_000),

    'defer_side_effects' => (bool) env('DECK_DEFER_SIDE_EFFECTS', true),

    'store_context' => (bool) env('DECK_STORE_CONTEXT', false),

    'log_recording_failures' => (bool) env('DECK_LOG_RECORDING_FAILURES', true),

    'exception_trace_bytes' => (int) env('DECK_EXCEPTION_TRACE_BYTES', 65_536),

    'dispatch_groups' => [
        'enabled' => (bool) env('DECK_DISPATCH_GROUPS_ENABLED', true),
        'request_middleware' => (bool) env('DECK_DISPATCH_GROUPS_REQUEST_MIDDLEWARE', true),
        'lineage' => (bool) env('DECK_DISPATCH_GROUPS_LINEAGE', true),
        'artisan' => (bool) env('DECK_DISPATCH_GROUPS_ARTISAN', false),
        'request_id_header' => env('DECK_DISPATCH_GROUPS_REQUEST_ID_HEADER', 'X-Request-Id'),
        'request_id_attribute' => env('DECK_DISPATCH_GROUPS_REQUEST_ID_ATTRIBUTE', 'request_id'),
    ],

    'lifecycle' => [
        'enabled' => (bool) env('DECK_LIFECYCLE_ENABLED', true),
        'origin_http' => (bool) env('DECK_LIFECYCLE_ORIGIN_HTTP', true),
        'origin_artisan' => (bool) env('DECK_LIFECYCLE_ORIGIN_ARTISAN', true),
        'parent_job' => (bool) env('DECK_LIFECYCLE_PARENT_JOB', true),
        'wait_analytics' => (bool) env('DECK_LIFECYCLE_WAIT_ANALYTICS', true),
    ],

];
