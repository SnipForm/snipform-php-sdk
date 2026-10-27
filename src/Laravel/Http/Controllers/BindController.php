<?php

namespace SnipForm\Laravel\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use SnipForm\Resources\Session;

/**
 * Receives the visitor's SnipForm session id from the hydrate loader and
 * stashes it into Laravel's session so every subsequent request in the
 * visit has it available — no header, no form field, no query string.
 *
 * Route is auto-registered by `SnipFormServiceProvider` at `POST /snipform/bind`
 * (configurable). CSRF-protected via the standard `<meta name="csrf-token">`
 * pattern — the loader script reads the token and forwards it.
 *
 * Idempotent: same session id can be re-posted any number of times. The
 * controller stops short of re-stashing identical values to avoid touching
 * the session driver more than necessary.
 */
class BindController
{
    /** Session storage key the SDK reads from elsewhere - one constant, defined on the core Session resource. */
    public const SESSION_KEY = Session::SESSION_STASH_KEY;

    /** Loose sanity check on the session id — Snipform issues short alphanum + a few separators. */
    private const ID_PATTERN = '/^[A-Za-z0-9_:.-]{8,128}$/';

    public function __invoke(Request $request): Response
    {
        $id = (string) $request->input('session_id', '');

        if ($id === '' || ! preg_match(self::ID_PATTERN, $id)) {
            return new Response('', 422);
        }

        if (! $request->hasSession()) {
            // No session driver — nowhere to stash. Tell the loader silently;
            // the SDK macro will fall through to other resolution paths.
            return new Response('', 204);
        }

        $current = $request->session()->get(self::SESSION_KEY);
        if ($current !== $id) {
            $request->session()->put(self::SESSION_KEY, $id);
        }

        return new Response('', 204);
    }
}
