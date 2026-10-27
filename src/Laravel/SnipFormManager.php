<?php

namespace SnipForm\Laravel;

use Illuminate\Http\Request;
use SnipForm\Client;
use SnipForm\Laravel\Dispatch\Write;
use SnipForm\Laravel\Dispatch\WriteDispatcher;
use SnipForm\Resources\Contacts;
use SnipForm\Resources\Session;
use SnipForm\Resources\Signals;

/**
 * The `Snipform` facade root - one place for everything a Laravel backend
 * does with SnipForm, with the visitor's session id resolved for you.
 *
 *   Snipform::event('purchase', 9900, ['order' => 'X-1']);   // custom event
 *   Snipform::revenue(9900, 'USD');                          // acquisition value
 *   Snipform::identify($user);                               // contact
 *   Snipform::signals()->last7Days()->metrics();             // reads
 *
 * Writes resolve the session id through the same four-tier chain the
 * `$request->snipformSessionId()` macro uses (Laravel session stash from
 * the hydrate loader, then header, form field, query string). When nothing
 * resolves the write is skipped and `false` comes back - a JS-disabled
 * visitor is a normal case, not an exception. Use the `*For()` variants
 * when you already hold the session id (an order you stored it against).
 *
 * Dispatch timing (sync / after_response / queue) comes from config.
 */
class SnipFormManager
{
    public function __construct(
        private readonly Client $client,
        private readonly IdentifyManager $identify,
        private readonly WriteDispatcher $dispatcher,
        private readonly array $config,
    ) {}

    // ======================================================================
    // Events
    // ======================================================================

    /**
     * Fire a custom event on the current visitor's session. Returns TRUE when
     * the write was dispatched, FALSE when no session id could be resolved.
     */
    public function event(string $name, mixed $value = null, array $meta = [], ?Request $request = null): bool
    {
        $sessionId = $this->sessionId($request);
        if (! $sessionId) {
            return false;
        }

        return $this->eventFor($sessionId, $name, $value, $meta);
    }

    /** Fire a custom event on a session id you already hold. */
    public function eventFor(string $sessionId, string $name, mixed $value = null, array $meta = []): bool
    {
        $payload = ['session_id' => $sessionId, 'name' => $name];
        if ($value !== null) {
            $payload['value'] = $value;
        }
        if ($meta) {
            $payload['meta'] = $meta;
        }

        $this->send(Write::EVENT, $payload);

        return true;
    }

    // ======================================================================
    // Acquisition
    // ======================================================================

    /**
     * Patch acquisition data (value / cost in integer cents, currency, tags)
     * onto the current visitor's session.
     *
     * @param  array{cost?: int, value?: int, currency_code?: string, tags?: array<string>}  $attributes
     */
    public function acquisition(array $attributes, ?Request $request = null): bool
    {
        $sessionId = $this->sessionId($request);
        if (! $sessionId) {
            return false;
        }

        return $this->acquisitionFor($sessionId, $attributes);
    }

    /** @param  array{cost?: int, value?: int, currency_code?: string, tags?: array<string>}  $attributes */
    public function acquisitionFor(string $sessionId, array $attributes): bool
    {
        $this->send(Write::ACQUISITION, ['session_id' => $sessionId, ...$attributes]);

        return true;
    }

    /** Shorthand for the most common acquisition write - the session's value, in cents. */
    public function revenue(int $cents, ?string $currencyCode = null, ?Request $request = null): bool
    {
        $attributes = ['value' => $cents];
        if ($currencyCode) {
            $attributes['currency_code'] = $currencyCode;
        }

        return $this->acquisition($attributes, $request);
    }

    // ======================================================================
    // Identify (delegates to IdentifyManager - dedup + its own dispatch mode)
    // ======================================================================

    /** Identify a user-shaped subject: an Identifiable model or a raw payload array. */
    public function identify(mixed $subject, ?object $request = null): bool
    {
        return $this->identify->user($subject, $request);
    }

    public function user(mixed $subject, ?object $request = null): bool
    {
        return $this->identify->user($subject, $request);
    }

    public function auth(?string $guard = null, ?object $request = null): bool
    {
        return $this->identify->auth($guard, $request);
    }

    public function payload(array $payload, ?object $request = null): bool
    {
        return $this->identify->payload($payload, $request);
    }

    // ======================================================================
    // Session id + resources
    // ======================================================================

    /** The current visitor's session id via the four-tier chain, or null. */
    public function sessionId(?Request $request = null): ?string
    {
        return $this->identify->sessionId($request);
    }

    public function contacts(): Contacts
    {
        return $this->client->contacts();
    }

    public function session(): Session
    {
        return $this->client->session();
    }

    public function signals(): Signals
    {
        return $this->client->signals();
    }

    public function client(): Client
    {
        return $this->client;
    }

    // ======================================================================
    // Internals
    // ======================================================================

    private function send(Write $write, array $payload): void
    {
        $this->dispatcher->dispatch(
            $this->config['events']['dispatch'] ?? WriteDispatcher::AFTER_RESPONSE,
            $write,
            $payload,
            $this->config['queue_name'] ?? null,
        );
    }
}
