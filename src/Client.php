<?php

namespace SnipForm;

use Closure;
use SnipForm\Http\HttpClient;
use SnipForm\Resources\Attribution;
use SnipForm\Resources\Clicks;
use SnipForm\Resources\Contacts;
use SnipForm\Resources\Conversions;
use SnipForm\Resources\LinkGroups;
use SnipForm\Resources\Links;
use SnipForm\Resources\Properties;
use SnipForm\Resources\Session;
use SnipForm\Resources\Signals;

/**
 * Top-level SnipForm client. Holds auth + HTTP, exposes resource sub-clients.
 */
class Client
{
    private const DEFAULT_BASE_URL = 'https://api.snipform.io';

    public readonly HttpClient $http;

    /**
     * Optional dedup gate for `identifyOnce()` / `identifyFromRequest()`.
     * Signature: `fn (string $fingerprintKey, int $ttl): bool` — return true
     * when the fingerprint is NEW (call should go out), false when it's
     * already been seen (call should be skipped). Use your cache's atomic
     * add-if-missing primitive (`Cache::add` in Laravel, SETNX in Redis)
     * so concurrent requests don't double-fire.
     */
    private ?Closure $identifyDedup = null;

    private int $identifyDedupTtl = 86400; // 24h default

    public function __construct(string $token, array $options = [])
    {
        $this->http = new HttpClient(
            token: $token,
            baseUrl: $options['base_url'] ?? self::DEFAULT_BASE_URL,
            timeout: $options['timeout'] ?? 30,
            pathPrefix: $options['path_prefix'] ?? '/v2/',
            verifySsl: $options['verify_ssl'] ?? true,
        );
    }

    /**
     * Wire a dedup gate for `identifyOnce()` / `identifyFromRequest()`.
     * Without it, every call goes over the wire.
     *
     *   // Laravel — one liner
     *   $client->withIdentifyDedup(
     *       fn ($key, $ttl) => \Illuminate\Support\Facades\Cache::add($key, true, $ttl),
     *   );
     *
     *   // Redis — atomic SETNX
     *   $client->withIdentifyDedup(
     *       fn ($key, $ttl) => (bool) $redis->set($key, 1, ['NX', 'EX' => $ttl]),
     *   );
     *
     * The closure receives `($fingerprintKey, $ttl)` and must return TRUE when
     * the key is NEW (i.e. should fire), FALSE when it's already cached.
     *
     * Optional `$ttl` overrides the default (24h). The closure is invoked
     * with this TTL on every call so a long-lived client can change it via
     * config without reconstructing.
     */
    public function withIdentifyDedup(Closure $gate, ?int $ttl = null): self
    {
        $this->identifyDedup = $gate;
        if ($ttl !== null) {
            $this->identifyDedupTtl = $ttl;
        }

        return $this;
    }

    /** @internal */
    public function identifyDedup(): ?Closure
    {
        return $this->identifyDedup;
    }

    /** @internal */
    public function identifyDedupTtl(): int
    {
        return $this->identifyDedupTtl;
    }

    public function properties(): Properties
    {
        return new Properties($this->http);
    }

    public function signals(): Signals
    {
        return new Signals($this->http);
    }

    public function session(): Session
    {
        return new Session($this->http);
    }

    public function linkGroups(): LinkGroups
    {
        return new LinkGroups($this->http);
    }

    public function links(): Links
    {
        return new Links($this->http);
    }

    public function clicks(): Clicks
    {
        return new Clicks($this->http);
    }

    public function conversions(): Conversions
    {
        return new Conversions($this->http);
    }

    public function attribution(): Attribution
    {
        return new Attribution($this->http);
    }

    public function contacts(): Contacts
    {
        return new Contacts($this->http, $this->identifyDedup, $this->identifyDedupTtl);
    }
}
