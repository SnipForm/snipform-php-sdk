<?php

/*
 * SnipForm SDK — Laravel config.
 *
 * Publish with:
 *
 *   php artisan vendor:publish --tag=snipform-config
 *
 * Or just set the env vars below; the SDK falls back to these defaults if
 * the file isn't published.
 */
return [

    // ---------------------------------------------------------------------
    // Auth
    // ---------------------------------------------------------------------

    'token' => env('SNIPFORM_TOKEN'),
    'base_url' => env('SNIPFORM_BASE_URL'),
    'path_prefix' => env('SNIPFORM_PATH_PREFIX'),
    'timeout' => env('SNIPFORM_TIMEOUT', 30),
    'verify_ssl' => env('SNIPFORM_VERIFY_SSL', true),

    // ---------------------------------------------------------------------
    // Identify
    //
    // Powers `Snipform::identify(...)`, the `Identifiable` model trait, the
    // `Illuminate\Auth\Events\Login` listener, and the per-request middleware.
    // All cheap calls go through the dedup gate so the SnipForm API only
    // sees one call per (session, payload) per TTL.
    // ---------------------------------------------------------------------

    'identify' => [

        // Listen for Auth's Login event and identify the user automatically.
        // Disable if you want to be the sole driver via the facade.
        'on_login' => env('SNIPFORM_IDENTIFY_ON_LOGIN', true),

        // Run identify calls after the response is sent so the user-facing
        // request returns instantly. Backed by Laravel's queue::afterResponse,
        // no queue worker needed.
        'queue' => env('SNIPFORM_IDENTIFY_QUEUE', true),

        // Dedup TTL (seconds). 0 = no dedup; every call hits the API.
        // Default 24h — first request after a user changes their email
        // refreshes the fingerprint and goes through.
        'dedup_ttl' => env('SNIPFORM_IDENTIFY_DEDUP_TTL', 86400),

        // Named cache store for the dedup. Defaults to the app's default
        // store. Set to `redis` / `memcached` if you want isolation.
        'cache_store' => env('SNIPFORM_IDENTIFY_CACHE_STORE'),
    ],
];
