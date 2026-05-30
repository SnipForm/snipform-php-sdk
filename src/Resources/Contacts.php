<?php

namespace SnipForm\Resources;

use Closure;
use Generator;
use SnipForm\Concerns\RawAware;
use SnipForm\Data\Contact;
use SnipForm\Data\SessionRow;
use SnipForm\Http\HttpClient;

/**
 * Contacts resource — bridge from anonymous SignalSessions to known people.
 *
 *   // Identify a visitor: find-or-create the Contact, set-once link to the
 *   // current session if one is resolvable.
 *   $contact = $client->contacts()->identify([
 *       'email'   => 'jane@acme.com',
 *       'traits'  => ['first_name' => 'Jane', 'company' => 'Acme'],
 *   ]);
 *
 *   // Read
 *   $contact = $client->contacts()->find($id);
 *   foreach ($client->contacts()->all() as $c) { ... }            // auto-paginated
 *   $client->contacts()->all(['search' => 'jane'])->page(1);      // single page
 *
 *   // Sessions belonging to a contact (cursor-based, auto-walks every page)
 *   foreach ($client->contacts()->sessionsFor($id) as $session) { ... }
 *
 *   // Update / delete
 *   $client->contacts()->update($id, ['first_name' => 'Jane D']);
 *   $client->contacts()->delete($id);                             // GDPR hard delete
 *
 * For identify, the session can come from any of:
 *   1. `session_id`            in the payload
 *   2. `X-Snipform-Session-Id` header (set on the underlying HttpClient by the caller)
 *   3. `ip` + `user_agent`     in the payload — fingerprint-resolved server-side
 *
 * Append `->asRaw()` anywhere on the chain to skip DTO hydration.
 */
class Contacts
{
    use RawAware;

    private const PATH = 'property/contacts';

    public function __construct(
        private readonly HttpClient $http,
        private readonly ?Closure $identifyDedup = null,
        private readonly int $identifyDedupTtl = 3600,
    ) {}

    // ======================================================================
    // Read
    // ======================================================================

    /**
     * @param  array{search?: string, state?: string, lifecycle_stage?: string, per_page?: int}  $filters
     */
    public function all(array $filters = []): PaginatedCollection
    {
        return new PaginatedCollection(
            http: $this->http,
            path: self::PATH,
            payload: $filters,
            factory: Contact::fromArray(...),
            verb: 'GET',
            asRaw: $this->asRaw,
        );
    }

    public function find(string $id): Contact|array
    {
        $row = (array) $this->http->get(self::PATH.'/'.$id)->data('contact');

        return $this->hydrate($row, Contact::fromArray(...));
    }

    /**
     * Walk every session attached to a contact. Cursor-based; the iterator
     * follows `paging.next_cursor` until the server reports no more rows.
     *
     * @return Generator<int, SessionRow|array>
     */
    public function sessionsFor(string $id, int $perPage = 25): Generator
    {
        $cursor = null;

        while (true) {
            $payload = ['per_page' => $perPage];
            if ($cursor !== null) {
                $payload['cursor'] = $cursor;
            }

            $response = $this->http->get(self::PATH.'/'.$id.'/sessions', $payload);
            $rows = (array) ($response->data('sessions') ?? []);

            foreach ($rows as $row) {
                yield $this->asRaw ? $row : SessionRow::fromArray($row);
            }

            $paging = (array) ($response->data('paging') ?? []);
            if (empty($paging['has_more']) || empty($paging['next_cursor'])) {
                break;
            }

            $cursor = (int) $paging['next_cursor'];
        }
    }

    // ======================================================================
    // Write
    // ======================================================================

    /**
     * @param  array{
     *     external_id?: string,
     *     email?: string,
     *     session_id?: string,
     *     ip?: string,
     *     user_agent?: string,
     *     traits?: array{
     *         first_name?: string,
     *         last_name?: string,
     *         phone?: string,
     *         company?: string,
     *         job_title?: string,
     *         website?: string,
     *         country?: string,
     *         city?: string,
     *         lifecycle_stage?: string,
     *         meta?: list<array{key: string, value?: string|null}>,
     *     },
     * }  $payload
     */
    public function identify(array $payload): Contact|array
    {
        $row = (array) $this->http->post(self::PATH.'/identify', $payload)->data('contact');

        return $this->hydrate($row, Contact::fromArray(...));
    }

    /**
     * Idempotent identify with optional client-side dedup. Returns TRUE when
     * a request actually went out, FALSE when the dedup gate short-circuited.
     *
     * Without a dedup gate wired on the Client, this is equivalent to
     * `identify()` but ignoring the response (fire-and-forget).
     *
     * Fingerprint = sha1(session_id + sorted payload), so if the payload
     * changes (email update, new traits) the call fires again.
     */
    public function identifyOnce(array $payload): bool
    {
        if ($this->identifyDedup && ($key = $this->fingerprint($payload)) !== null) {
            $isNew = (bool) ($this->identifyDedup)($key, $this->identifyDedupTtl);
            if (! $isNew) {
                return false;
            }
        }

        $this->http->post(self::PATH.'/identify', $payload);

        return true;
    }

    /**
     * Pull `X-Snipform-Session-Id`, client IP, and User-Agent off an inbound
     * Laravel / Symfony / PSR-7 request and call `identifyOnce()` with them
     * merged in. The browser-side tracker auto-attaches the session-id
     * header to same-origin fetch/XHR via `signals.attachToFetch()`, so this
     * gives you a one-liner inside any authenticated controller / middleware:
     *
     *   public function login(Request $request, \SnipForm\Client $sf)
     *   {
     *       $user = auth()->user();
     *
     *       $sf->contacts()->identifyFromRequest($request, [
     *           'external_id' => (string) $user->id,
     *           'email'       => $user->email,
     *           'traits'      => [
     *               'first_name' => $user->first_name,
     *               'last_name'  => $user->last_name,
     *           ],
     *       ]);
     *   }
     *
     * Wire dedup on the Client (one liner in a service provider) and the
     * same auth'd user firing this endpoint a hundred times only hits the
     * SnipForm API once per cache TTL — or again immediately if their
     * traits change.
     */
    public function identifyFromRequest(object $request, array $payload): bool
    {
        $sessionId = $this->extractHeader($request, 'X-Snipform-Session-Id');
        if ($sessionId !== null && ! isset($payload['session_id'])) {
            $payload['session_id'] = $sessionId;
        }

        if (! isset($payload['ip']) && ($ip = $this->extractIp($request)) !== null) {
            $payload['ip'] = $ip;
        }

        if (! isset($payload['user_agent']) && ($ua = $this->extractHeader($request, 'User-Agent')) !== null) {
            $payload['user_agent'] = $ua;
        }

        return $this->identifyOnce($payload);
    }

    /**
     * @param  array{
     *     external_id?: string|null,
     *     email?: string|null,
     *     first_name?: string|null,
     *     last_name?: string|null,
     *     phone?: string|null,
     *     company?: string|null,
     *     job_title?: string|null,
     *     website?: string|null,
     *     country?: string|null,
     *     city?: string|null,
     *     lifecycle_stage?: string|null,
     *     state?: string,
     *     meta?: list<array{key: string, value?: string|null}>,
     * }  $traits
     */
    public function update(string $id, array $traits): Contact|array
    {
        $row = (array) $this->http->post(self::PATH.'/'.$id, $traits)->data('contact');

        return $this->hydrate($row, Contact::fromArray(...));
    }

    public function delete(string $id): bool
    {
        return (bool) $this->http->delete(self::PATH.'/'.$id)->data('deleted');
    }

    // ======================================================================
    // Internals
    // ======================================================================

    /**
     * Build a stable cache key from the identify payload. NULL when there's
     * nothing to anchor on — in which case dedup is skipped and the call
     * always fires.
     */
    private function fingerprint(array $payload): ?string
    {
        $anchor = $payload['session_id']
            ?? $payload['external_id']
            ?? $payload['email']
            ?? null;

        if (! $anchor) {
            return null;
        }

        // Sort to make ordering irrelevant; nested arrays sort by key too.
        $normalized = $this->sortRecursive($payload);

        return 'snipform:identify:'.sha1((string) json_encode($normalized));
    }

    private function sortRecursive(array $input): array
    {
        ksort($input);
        foreach ($input as $k => $v) {
            if (is_array($v)) {
                $input[$k] = $this->sortRecursive($v);
            }
        }

        return $input;
    }

    /**
     * Read a header from any of the common Request shapes:
     *   - Laravel (Illuminate\Http\Request): `->header($name)`
     *   - Symfony (HttpFoundation\Request):  `->headers->get($name)`
     *   - PSR-7   (ServerRequestInterface):  `->getHeaderLine($name)`
     */
    private function extractHeader(object $r, string $name): ?string
    {
        if (method_exists($r, 'header')) {
            $value = $r->header($name);
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }
        if (isset($r->headers) && is_object($r->headers) && method_exists($r->headers, 'get')) {
            $value = $r->headers->get($name);
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }
        if (method_exists($r, 'getHeaderLine')) {
            $value = $r->getHeaderLine($name);
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function extractIp(object $r): ?string
    {
        if (method_exists($r, 'ip')) {
            $ip = $r->ip();              // Laravel
            if (is_string($ip) && $ip !== '') {
                return $ip;
            }
        }
        if (method_exists($r, 'getClientIp')) {
            $ip = $r->getClientIp();     // Symfony
            if (is_string($ip) && $ip !== '') {
                return $ip;
            }
        }
        if (method_exists($r, 'getServerParams')) {
            $params = $r->getServerParams();  // PSR-7
            $ip = $params['REMOTE_ADDR'] ?? null;
            if (is_string($ip) && $ip !== '') {
                return $ip;
            }
        }

        return null;
    }
}
