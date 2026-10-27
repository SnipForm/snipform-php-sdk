<?php

namespace SnipForm\Laravel\Middleware;

use Closure;
use Illuminate\Http\Request;
use SnipForm\Laravel\Http\Controllers\BindController;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Reads the transient SnipForm sessionId cookie set by `signals.cookieTo()`,
 * copies the value into Laravel's session under `snipform_session_id`, then
 * tells the browser to forget the cookie on the response.
 *
 * One round-trip lifetime — the cookie exists only long enough to deliver
 * its value once. From the customer's perspective Snipform never sets a
 * cookie that persists; it's a baton-handoff transport, not state storage.
 *
 * Auto-registered on the `web` group by `SnipFormServiceProvider` when
 * `snipform.hydrate.mode = 'cookie'` (default). Cookie name is configurable
 * via `snipform.hydrate.cookie_name` (default `site_initializer`).
 */
class HydrateCookieMiddleware
{
    public function __construct(
        private readonly string $cookieName = 'site_initializer',
    ) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $value = $request->cookie($this->cookieName);
        $sid = is_string($value) && $value !== '' ? $value : null;

        if ($sid && $request->hasSession()) {
            $stashed = $request->session()->get(BindController::SESSION_KEY);
            if ($stashed !== $sid) {
                $request->session()->put(BindController::SESSION_KEY, $sid);
            }
        }

        $response = $next($request);

        // Always clear the cookie if it was present. Whether we used the value
        // or not, it has done its job for this request.
        if ($sid && method_exists($response, 'headers')) {
            $response->headers->setCookie(Cookie::create(
                name: $this->cookieName,
                value: '',
                expire: 1,           // distant past
                path: '/',
                secure: $request->isSecure(),
                httpOnly: false,
                sameSite: 'Lax',
            ));
        }

        return $response;
    }
}
