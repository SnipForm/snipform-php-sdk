<?php

namespace SnipForm\Resources;

use InvalidArgumentException;
use SnipForm\Concerns\RawAware;
use SnipForm\Data\Event;
use SnipForm\Exceptions\MissingSessionIdException;
use SnipForm\Http\HttpClient;
use Symfony\Component\HttpFoundation\Request;

/**
 * Session-scoped writes: submit a custom event, patch acquisition metadata.
 *
 * Two ways to say which session to act on:
 *
 *   A) Explicit `session_id` in the payload (always works):
 *      $client->session()->event(['session_id' => $id, 'name' => 'purchase']);
 *
 *   B) Pass the incoming Request - the SDK resolves the id through every
 *      transport the browser side can use (see fromRequest()):
 *      $client->session()->event($request, ['name' => 'purchase']);
 *
 * Either way the session id originates client-side: the JS tracker is the
 * only canonical source. Server-to-server fingerprint resolution was removed
 * - too dependent on the customer's IP-detection chain to trust.
 *
 * Laravel apps get the same thing with less ceremony through the facade:
 * `Snipform::event('purchase', 9900)` resolves the session id itself.
 */
class Session
{
    use RawAware;

    /**
     * Canonical transports the JS tracker + the Laravel hydrate loader use
     * to hand the visitor's session id to the backend. The Laravel layer
     * reads these too - keep them in lockstep.
     */
    public const SESSION_HEADER = 'X-SnipForm-Session-Id';

    public const SESSION_FORM_FIELD = 'snip_session_id';

    /** Server-session key the hydrate loader / bind endpoint stash the id under. */
    public const SESSION_STASH_KEY = 'snipform_session_id';

    public function __construct(private readonly HttpClient $http) {}

    // ======================================================================
    // Event - submit a custom event
    // ======================================================================

    /**
     * Submit a custom event for a session. Pass either:
     *   - a Request (the SDK resolves session_id from it), or
     *   - just the attributes array with an explicit session_id key.
     *
     * @param  Request|array  $requestOrAttributes  Symfony/Laravel Request, or attributes array if calling shorthand
     * @param  array{name: string, value?: mixed, meta?: array}|null  $attributes  required when first arg is a Request
     *
     * @throws MissingSessionIdException
     */
    public function event(Request|array $requestOrAttributes, ?array $attributes = null): Event|array
    {
        $payload = $this->payloadWithSession($requestOrAttributes, $attributes);
        $row = (array) $this->http->post('property/session/event', $payload)->data('event');

        return $this->hydrate($row, Event::fromArray(...));
    }

    // ======================================================================
    // Acquisition - patch acquisition meta onto a session
    // ======================================================================

    /**
     * Patch acquisition metadata. Same signature shape as event() - pass a
     * Request to auto-attach session_id, or pass attributes with an explicit
     * session_id. Money is in integer CENTS.
     *
     * @param  array{cost?: int, value?: int, currency_code?: string, tags?: array<string>}|null  $attributes
     * @return array{id: string, acquisition_meta: array, value: array|null}
     *
     * @throws MissingSessionIdException
     */
    public function acquisition(Request|array $requestOrAttributes, ?array $attributes = null): array
    {
        $payload = $this->payloadWithSession($requestOrAttributes, $attributes);

        return (array) $this->http
            ->post('property/session/acquisition', $payload)
            ->data('session');
    }

    // ======================================================================
    // Public helper - pull session_id from a Request (or return null)
    // ======================================================================

    /**
     * Resolve a SnipForm session_id from a Symfony/Laravel Request. Priority:
     *
     *   1. Server session - stashed by the Laravel hydrate loader (cookie or
     *      POST mode) on a prior request; survives redirects and form posts
     *   2. X-SnipForm-Session-Id header - attached by signals.js attachToFetch()
     *   3. snip_session_id form field - attached by attachToForm()
     *   4. snip_session_id query string - appended by hand
     *
     * Returns null if nothing resolves. Use it when you want to decide
     * yourself how to handle the no-session case instead of letting
     * event()/acquisition() throw.
     */
    public function fromRequest(Request $request): ?string
    {
        $stashed = self::stashedSessionId($request);
        if ($stashed !== null) {
            return $stashed;
        }

        $headerValue = $request->headers->get(self::SESSION_HEADER);
        if ($headerValue) {
            return $headerValue;
        }

        $formValue = $request->request->get(self::SESSION_FORM_FIELD)
            ?? $request->query->get(self::SESSION_FORM_FIELD);

        return $formValue ?: null;
    }

    /**
     * Tier 1 on its own: the id the hydrate loader stashed in the server
     * session, if a session is attached and holds one. Works for Symfony
     * sessions and Laravel's store alike (both expose get()).
     */
    public static function stashedSessionId(Request $request): ?string
    {
        if (! $request->hasSession()) {
            return null;
        }

        $value = $request->getSession()->get(self::SESSION_STASH_KEY);

        return is_string($value) && $value !== '' ? $value : null;
    }

    // ======================================================================
    // Internals
    // ======================================================================

    private function payloadWithSession(Request|array $requestOrAttributes, ?array $attributes): array
    {
        if ($requestOrAttributes instanceof Request) {
            if ($attributes === null) {
                throw new InvalidArgumentException('When passing a Request, the attributes array is required as the second argument.');
            }
            $sessionId = $this->fromRequest($requestOrAttributes);
            if (! $sessionId) {
                throw new MissingSessionIdException;
            }

            return ['session_id' => $sessionId, ...$attributes];
        }

        // Array shorthand - expects session_id key inside.
        if (empty($requestOrAttributes['session_id'])) {
            throw new MissingSessionIdException(
                'session_id is required - either include it in the attributes array or pass a Request as the first argument.',
            );
        }

        return $requestOrAttributes;
    }
}
