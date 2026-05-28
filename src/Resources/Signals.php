<?php

namespace SnipForm\Resources;

use SnipForm\Http\HttpClient;
use SnipForm\Query\Builder;

/**
 * Signals resource — entry to the session/analytics endpoints.
 *
 *   $client->signals()
 *       ->last28Days()
 *       ->where('country', 'US')
 *       ->sessions();
 *
 * `Signals` IS a `Builder` — every call to `$client->signals()` returns a
 * fresh instance, so each starting chain has its own clauses + period.
 * Inheriting (rather than holding a Builder + magic __call) gives the IDE
 * full autocomplete over the fluent surface.
 */
class Signals extends Builder
{
    public function __construct(HttpClient $http)
    {
        parent::__construct($http);
    }

    /**
     * Back-compat — older code calls `->query()` to start a chain. The
     * Signals resource itself is already a builder, so this just returns
     * `$this`. Prefer chaining directly: `$client->signals()->where(...)`.
     *
     * @deprecated since 0.0.4 — call methods on `signals()` directly.
     */
    public function query(): self
    {
        return $this;
    }
}
