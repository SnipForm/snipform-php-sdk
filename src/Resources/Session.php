<?php

namespace SnipForm\Resources;

use InvalidArgumentException;
use SnipForm\Concerns\RawAware;
use SnipForm\Data\Event;
use SnipForm\Data\ResolveResult;
use SnipForm\Exceptions\MissingSessionIdException;
use SnipForm\Http\HttpClient;
use Symfony\Component\HttpFoundation\Request;

/**
 * Session-scoped actions: resolve a visitor's session by fingerprint, submit
 * a custom event, patch acquisition metadata.
 *
 * Two ways to identify which session to act on:
 *
 *   A) Explicit `session_id` in the payload (always works):
 *      $client->session()->event(['session_id' => $id, 'name' => 'purchase']);
 *
 *   B) Pass the incoming Request — the SDK pulls session_id from the
 *      X-SnipForm-Session-Id header or the snip_session_id form field that
 *      signals.js sets via attachToFetch() / attachToForms():
 *      $client->session()->event($request, ['name' => 'purchase']);
 */
class Session
{
    use RawAware;

    private const SESSION_HEADER = 'X-SnipForm-Session-Id';

    private const SESSION_FORM_FIELD = 'snip_session_id';

    public function __construct(private readonly HttpClient $http) {}

    // ======================================================================
    // Resolve — derive session_id from a visitor's fingerprint
    // ======================================================================

    /**
     * Look up the visitor's SignalSession by their fingerprint. Accepts a
     * Symfony/Laravel Request (ip / UA / lang extracted automatically) or
     * a plain array with those keys.
     *
     * @param  Request|array{ip: string, user_agent: string, lang?: string}  $input
     */
    public function resolve(Request|array $input): ResolveResult|array
    {
        $payload = $input instanceof Request
            ? $this->extractFromRequest($input)
            : $this->validateArrayInput($input);

        $row = (array) $this->http->post('property/session/resolve', $payload)->data();

        return $this->hydrate($row, ResolveResult::fromArray(...));
    }

    // ======================================================================
    // Event — submit a custom event
    // ======================================================================

    /**
     * Submit a custom event for a session. Pass either:
     *   - a Request (SDK extracts session_id from X-SnipForm-Session-Id or snip_session_id), or
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
    // Acquisition — patch acquisition meta onto a session
    // ======================================================================

    /**
     * Patch acquisition metadata. Same signature shape as event() — pass a
     * Request to auto-attach session_id, or pass attributes with an explicit
     * session_id.
     *
     * @param  array{cost?: int, value?: int, currency_code?: string, tags?: array<string>}|null  $attributes
     *
     * @return array{id: string, acquisition_meta: array}
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
    // Public helper — pull session_id from a Request (or return null)
    // ======================================================================

    /**
     * Extract a SnipForm session_id from a Symfony/Laravel Request:
     *   1. X-SnipForm-Session-Id header (attached by signals.js attachToFetch)
     *   2. snip_session_id body/form field (attached by attachToForms)
     *
     * Returns null if neither is present. Use this when you want to decide
     * yourself how to handle the no-session case, instead of letting
     * event()/acquisition() throw.
     */
    public function fromRequest(Request $request): ?string
    {
        $headerValue = $request->headers->get(self::SESSION_HEADER);
        if ($headerValue) {
            return $headerValue;
        }

        $formValue = $request->request->get(self::SESSION_FORM_FIELD)
            ?? $request->query->get(self::SESSION_FORM_FIELD);

        return $formValue ?: null;
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

        // Array shorthand — expects session_id key inside.
        if (empty($requestOrAttributes['session_id'])) {
            throw new MissingSessionIdException(
                'session_id is required — either include it in the attributes array or pass a Request as the first argument.',
            );
        }

        return $requestOrAttributes;
    }

    private function extractFromRequest(Request $request): array
    {
        return [
            'ip' => $request->getClientIp() ?? '',
            'user_agent' => (string) ($request->headers->get('User-Agent') ?? ''),
            'lang' => (string) ($request->headers->get('Accept-Language') ?? ''),
        ];
    }

    private function validateArrayInput(array $input): array
    {
        if (empty($input['ip']) || empty($input['user_agent'])) {
            throw new InvalidArgumentException(
                'resolve() requires `ip` and `user_agent` — pass a Symfony/Laravel Request or an array with those keys',
            );
        }

        return [
            'ip' => (string) $input['ip'],
            'user_agent' => (string) $input['user_agent'],
            'lang' => (string) ($input['lang'] ?? ''),
        ];
    }
}
