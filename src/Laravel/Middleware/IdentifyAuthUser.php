<?php

namespace SnipForm\Laravel\Middleware;

use Closure;
use SnipForm\Laravel\IdentifyManager;
use Symfony\Component\HttpFoundation\Request;

/**
 * Per-request identify for the authenticated user. Use this when there is
 * no `Login` event to listen to — token-auth APIs, SPAs that authenticate
 * via a stateful session cookie without going through the form login flow,
 * etc.
 *
 * Wire it onto an auth-protected route group:
 *
 *   Route::middleware(['auth:sanctum', 'snipform.identify'])->group(...);
 *
 * The alias is registered by `SnipFormServiceProvider`. Cheap to apply on
 * every request because the manager's dedup gate short-circuits repeats —
 * one identify per (user, payload) per TTL hits the API.
 */
class IdentifyAuthUser
{
    public function __construct(private readonly IdentifyManager $manager) {}

    public function handle(Request $request, Closure $next, ?string $guard = null): mixed
    {
        $this->manager->auth($guard, $request);

        return $next($request);
    }
}
