<?php

namespace SnipForm\Laravel\View;

use Illuminate\Http\Request;
use SnipForm\Laravel\Http\Controllers\BindController;

/**
 * Renders the inline `<script>` tag that bridges `window.signals.sessionId`
 * (set by the JS tracker) into the customer's Laravel session.
 *
 * Two modes — picked via `snipform.hydrate.mode`:
 *
 *  - `'cookie'` (default) — emits `signals.cookieTo(name)`. JS writes the
 *    sessionId to a transient first-party cookie; `HydrateCookieMiddleware`
 *    reads + stashes + clears it on the next request. Zero extra HTTP calls;
 *    the value rides on the user's next legitimate nav.
 *
 *  - `'post'` — emits `signals.bindTo(url)`. JS POSTs the sessionId to a
 *    dedicated bind endpoint that stashes it server-side. Heavier (one extra
 *    HTTP call per visit) but works in topologies where cookies are stripped.
 *
 * Both modes carry a `skipIfMatch` value baked in by the server — the current
 * Laravel-session-stashed sessionId — so a page reload after a successful
 * stash is a no-op (no cookie write, no POST).
 *
 * The Blade directive `@snipformHydrate` echoes whatever `renderForRequest()`
 * returns. Inline is intentional — the script is tiny and ships with the
 * page render rather than as a separate cached asset.
 */
class HydrateLoader
{
    public const MODE_COOKIE = 'cookie';

    public const MODE_POST = 'post';

    /**
     * Render the loader for the current Laravel request — pulls the
     * currently-stashed sessionId (if any) so the loader compares and
     * skips when it matches.
     *
     * @param  array{mode?: string, cookie_name?: string, bind_url?: string}  $config
     */
    public static function renderForRequest(Request $request, array $config = []): string
    {
        $stashed = null;
        if ($request->hasSession()) {
            $value = $request->session()->get(BindController::SESSION_KEY);
            if (is_string($value) && $value !== '') {
                $stashed = $value;
            }
        }

        $mode = $config['mode'] ?? self::MODE_COOKIE;
        $stashedJs = json_encode($stashed ?? '', JSON_UNESCAPED_SLASHES);

        if ($mode === self::MODE_POST) {
            $url = htmlspecialchars($config['bind_url'] ?? '/snipform/bind', ENT_QUOTES, 'UTF-8');
            $call = "window.signals.bindTo(\"$url\",{skipIfMatch:$stashedJs})";
            $guard = 'window.signals&&window.signals.bindTo';
        } else {
            $name = htmlspecialchars($config['cookie_name'] ?? 'site_initializer', ENT_QUOTES, 'UTF-8');
            $call = "window.signals.cookieTo(\"$name\",{skipIfMatch:$stashedJs})";
            $guard = 'window.signals&&window.signals.cookieTo';
        }

        // 50ms poll until the tracker has loaded + exposed the helper.
        // Handles the case where the directive renders before the tracker
        // script tag has executed (rare but defended against).
        return '<script>(function check(){if('.$guard.'){'.$call.';}else{setTimeout(check,50);}})();</script>';
    }

    /**
     * Session-storage key the bind endpoint + cookie middleware both stash to.
     * Re-exported here so callers don't have to import BindController just to
     * read the constant.
     */
    public static function sessionKey(): string
    {
        return BindController::SESSION_KEY;
    }
}
