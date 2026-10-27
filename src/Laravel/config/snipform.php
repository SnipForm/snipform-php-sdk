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
    // Hydrate — bridge `window.signals.sessionId` into Laravel's session
    //
    // The SDK ships an inline loader (rendered by `@snipformHydrate`) that
    // delivers the visitor's sessionId to your backend, then auto-stashes
    // it under `snipform_session_id` in Laravel's session. From there it's
    // available via `$request->snipformSessionId()` and `Snipform::sessionId()`.
    //
    // Two modes:
    //
    //   - `cookie` (default): JS writes a transient first-party cookie. The
    //     `HydrateCookieMiddleware` reads it on the next request, copies the
    //     value into the session, and CLEARS the cookie on the response. Zero
    //     extra HTTP calls — the value rides on the user's next nav. Doesn't
    //     compromise the cookieless story: the cookie lives for ONE request
    //     max, then it's gone.
    //
    //   - `post`: JS POSTs the sessionId to a dedicated bind endpoint that
    //     stashes server-side. Heavier (one extra HTTP call) but works in
    //     topologies where cookies are stripped (some corporate proxies).
    // ---------------------------------------------------------------------

    'hydrate' => [
        'enabled' => env('SNIPFORM_HYDRATE_ENABLED', true),

        // 'cookie' or 'post'. Cookie is recommended unless your infra
        // strips or rewrites cookies between browser and Laravel.
        'mode' => env('SNIPFORM_HYDRATE_MODE', 'cookie'),

        // Cookie mode — name of the transient first-party cookie. Generic
        // by default so it doesn't visibly identify Snipform anywhere.
        'cookie_name' => env('SNIPFORM_HYDRATE_COOKIE', 'site_initializer'),

        // Post mode — endpoint the loader POSTs to. Must be same-origin
        // so the Laravel session cookie + CSRF token both travel.
        'bind_url' => env('SNIPFORM_HYDRATE_BIND_URL', '/snipform/bind'),

        // Post mode — middleware applied to the bind route. `web` brings
        // session + CSRF; replace if you want a custom stack.
        'bind_middleware' => ['web'],
    ],

    // ---------------------------------------------------------------------
    // Dispatch - WHEN writes leave the box
    //
    // Every write (`Snipform::event()`, `revenue()`, `acquisition()`,
    // identify) runs in one of three modes:
    //
    //   sync            - inline; the caller waits for the API round-trip
    //   after_response  - after the response is sent (container terminating
    //                     phase). No queue worker needed. Default.
    //   queue           - a real ShouldQueue job on your queue connection -
    //                     survives the request entirely, retries like any job
    // ---------------------------------------------------------------------

    'events' => [
        'dispatch' => env('SNIPFORM_EVENTS_DISPATCH', 'after_response'),
    ],

    // Queue name for any write in 'queue' mode. Null = your default queue.
    'queue_name' => env('SNIPFORM_QUEUE'),

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

        // Dispatch mode for identify writes: sync | after_response | queue.
        // Legacy booleans still work (true = after_response, false = sync).
        // The dedup gate always runs inline; only the HTTP call defers.
        'queue' => env('SNIPFORM_IDENTIFY_QUEUE', 'after_response'),

        // Dedup TTL (seconds). 0 = no dedup; every call hits the API.
        // Default 1h — first request after a user changes their email
        // refreshes the fingerprint and goes through.
        'dedup_ttl' => env('SNIPFORM_IDENTIFY_DEDUP_TTL', 3600),

        // Named cache store for the dedup. Defaults to the app's default
        // store. Set to `redis` / `memcached` if you want isolation.
        'cache_store' => env('SNIPFORM_IDENTIFY_CACHE_STORE'),
    ],
];
