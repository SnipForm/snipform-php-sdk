<?php

namespace SnipForm\Laravel\Middleware;

use Closure;
use SnipForm\Resources\Session;
use Symfony\Component\HttpFoundation\Request;

/**
 * Middleware that pulls the visitor's SnipForm session id off the incoming
 * request and stashes it on `$request->attributes` so downstream controllers
 * can reach for it without redoing the lookup.
 *
 * Sources, in priority order:
 *   1. `X-SnipForm-Session-Id` HTTP header (set by signals.js `attachToFetch()`)
 *   2. `snip_session_id` form field (set by `attachToForm()`)
 *   3. `snip_session_id` query string param
 *
 * Wire into Laravel by registering in `app/Http/Kernel.php` (web or api group):
 *
 *   protected $middleware = [
 *       // ...
 *       \SnipForm\Laravel\Middleware\SnipFormSessionMiddleware::class,
 *   ];
 *
 * Reach for it in a controller:
 *
 *   $id = $request->attributes->get('snipform_session_id');
 *
 * Lives in src/Laravel/Middleware/ but takes no Laravel dependency —
 * accepts any Symfony HttpFoundation Request (which Laravel's Request
 * extends).
 */
class SnipFormSessionMiddleware
{
    /** Request-attribute key the resolved id is stored under. */
    public const ATTRIBUTE = 'snipform_session_id';

    public function handle(Request $request, Closure $next): mixed
    {
        $id = $request->headers->get(Session::SESSION_HEADER)
            ?? $request->request->get(Session::SESSION_FORM_FIELD)
            ?? $request->query->get(Session::SESSION_FORM_FIELD);

        if (is_string($id) && $id !== '') {
            $request->attributes->set(self::ATTRIBUTE, $id);
        }

        return $next($request);
    }
}
