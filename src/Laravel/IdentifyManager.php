<?php

namespace SnipForm\Laravel;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Container\Container;
use SnipForm\Client;
use SnipForm\Laravel\Contracts\Identifiable;
use SnipForm\Resources\Contacts;

/**
 * Central place the facade + listener + middleware all funnel through.
 * Resolves a SnipForm payload from a user / payload array, honors the
 * `identify.queue` config (afterResponse vs inline), and delegates to the
 * SDK's `identifyOnce()` so dedup kicks in.
 *
 * Bound as a singleton by `SnipFormServiceProvider`. Reach it via the
 * `Snipform` facade or type-hint `IdentifyManager` directly.
 */
class IdentifyManager
{
    public function __construct(
        private readonly Client $client,
        private readonly Container $container,
        private readonly array $config,
    ) {}

    // ======================================================================
    // Public surface — what the facade exposes
    // ======================================================================

    /**
     * Identify a user-shaped argument. Accepts:
     *   - any object implementing `Identifiable` (or carrying a
     *     `snipformPayload()` method via the trait)
     *   - a raw payload array, passed through unchanged
     *
     * Returns TRUE when an API call went out, FALSE when the dedup gate
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
     * Pulls the current request off the container automatically so
     * `session_id`, IP, and UA all flow through.
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

    /** Underlying SDK Contacts resource — for find/update/delete/all. */
    public function contacts(): Contacts
    {
        return $this->client->contacts();
    }

    /** Underlying SDK client — escape hatch for everything else. */
    public function client(): Client
    {
        return $this->client;
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
        $resource = $this->client->contacts();

        $call = fn (): bool => $request !== null
            ? $resource->identifyFromRequest($request, $payload)
            : $resource->identifyOnce($payload);

        if (! empty($this->config['queue'])) {
            // `terminating()` runs after the response is sent — same window
            // as queue::afterResponse without requiring a closure dispatcher
            // or job class. Cheapest possible deferral.
            $this->container->terminating(static function () use ($call): void {
                $call();
            });

            return true;
        }

        return $call();
    }

    private function currentRequest(): ?object
    {
        if (! $this->container->bound('request')) {
            return null;
        }

        return $this->container->make('request');
    }
}
