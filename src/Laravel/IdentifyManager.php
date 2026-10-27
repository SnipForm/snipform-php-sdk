<?php

namespace SnipForm\Laravel;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use SnipForm\Client;
use SnipForm\Laravel\Contracts\Identifiable;
use SnipForm\Laravel\Dispatch\Write;
use SnipForm\Laravel\Dispatch\WriteDispatcher;
use SnipForm\Resources\Contacts;

/**
 * Identify pipeline: resolves a SnipForm payload from a user / payload
 * array, merges in the visitor's session id, runs the dedup gate, and hands
 * the write to the dispatcher (sync / after_response / queue per config).
 *
 * Bound as a singleton by `SnipFormServiceProvider`; the `Snipform` facade
 * reaches it through `SnipFormManager`. The Login listener and the identify
 * middleware type-hint it directly.
 */
class IdentifyManager
{
    public function __construct(
        private readonly Client $client,
        private readonly Container $container,
        private readonly WriteDispatcher $dispatcher,
        private readonly array $config,
    ) {}

    // ======================================================================
    // Public surface
    // ======================================================================

    /**
     * Identify a user-shaped argument. Accepts:
     *   - any object implementing `Identifiable` (or carrying a
     *     `snipformPayload()` method via the trait)
     *   - a raw payload array, passed through unchanged
     *
     * Returns TRUE when a write was dispatched, FALSE when the dedup gate
     * short-circuited or no payload could be derived.
     */
    public function user(mixed $subject, ?object $request = null): bool
    {
        $payload = $this->resolvePayload($subject);
        if ($payload === null) {
            return false;
        }

        return $this->fire($payload, $request);
    }

    /**
     * Identify the currently authenticated user, if any. No-op for guests.
     * Pulls the current request off the container automatically so the
     * session id flows through.
     */
    public function auth(?string $guard = null, ?object $request = null): bool
    {
        $user = $this->container->make(AuthFactory::class)->guard($guard)->user();
        if ($user === null) {
            return false;
        }

        $request ??= $this->currentRequest();

        return $this->user($user, $request);
    }

    /**
     * Raw payload pass-through with optional request enrichment. Use when
     * you want to identify someone who isn't the auth user (e.g. a customer
     * record from a webhook).
     */
    public function payload(array $payload, ?object $request = null): bool
    {
        return $this->fire($payload, $request);
    }

    /** Underlying SDK Contacts resource - for find/update/delete/all. */
    public function contacts(): Contacts
    {
        return $this->client->contacts();
    }

    /** Underlying SDK client - escape hatch for everything else. */
    public function client(): Client
    {
        return $this->client;
    }

    /**
     * Resolve the current visitor's SnipForm session id via the macro chain:
     *
     *   1. Laravel session    - stashed by the hydrate loader on a prior request
     *   2. X-SnipForm-Session-Id header - set by the tracker's attachToFetch()
     *   3. snip_session_id form field   - set by attachToForm()
     *   4. snip_session_id query string - manually appended
     *
     * Returns null if none resolve. The session id is canonical only on the
     * browser side - there's no server-to-server fallback. If you need the
     * id and the hydrate loader hasn't fired yet, the answer is null until
     * it does.
     */
    public function sessionId(?Request $request = null): ?string
    {
        $request ??= $this->currentRequest();
        if (! $request) {
            return null;
        }

        if (method_exists($request, 'snipformSessionId')) {
            $id = $request->snipformSessionId();
            if (is_string($id) && $id !== '') {
                return $id;
            }
        }

        return null;
    }

    // ======================================================================
    // Internals
    // ======================================================================

    private function resolvePayload(mixed $subject): ?array
    {
        if ($subject instanceof Identifiable) {
            return $subject->snipformPayload();
        }
        if (is_object($subject) && method_exists($subject, 'snipformPayload')) {
            return $subject->snipformPayload();
        }
        if (is_array($subject)) {
            return $subject;
        }

        return null;
    }

    private function fire(array $payload, ?object $request): bool
    {
        $request ??= $this->currentRequest();

        // Merge the visitor's session id in if the caller didn't supply one -
        // this is what lets a fresh login pick up the sid the hydrate loader
        // stashed on a prior request, no header riding along on the login POST.
        if ($request && ! isset($payload['session_id'])) {
            $sid = $this->sessionId($request instanceof Request ? $request : null);
            if ($sid) {
                $payload['session_id'] = $sid;
            }
        }

        // Dedup runs NOW (one cheap cache add) - only the HTTP call defers.
        if (! $this->client->contacts()->passesDedup($payload)) {
            return false;
        }

        $this->dispatcher->dispatch(
            $this->config['queue'] ?? WriteDispatcher::AFTER_RESPONSE,
            Write::IDENTIFY,
            $payload,
            $this->config['queue_name'] ?? null,
        );

        return true;
    }

    private function currentRequest(): ?object
    {
        if (! $this->container->bound('request')) {
            return null;
        }

        return $this->container->make('request');
    }
}
