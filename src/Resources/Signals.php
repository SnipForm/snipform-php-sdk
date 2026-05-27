<?php

namespace SnipForm\Resources;

use SnipForm\Http\HttpClient;
use SnipForm\Query\Builder;

/**
 * Signals resource — entry to the session/analytics endpoints.
 *
 *   $client->signals()
 *       ->period('last_30')
 *       ->where('country', 'US')
 *       ->sessions();
 *
 * The resource is a thin spawner: every fluent call lives on Query\Builder.
 */
class Signals
{
    public function __construct(private readonly HttpClient $http) {}

    /**
     * Start a new query. Each call returns a fresh builder so you can hold
     * multiple in flight without state leaking between them.
     */
    public function query(): Builder
    {
        return new Builder($this->http);
    }

    // ----------------------------------------------------------------------
    // Pass-throughs — let `$client->signals()->where(...)` chain without
    // requiring users to call ->query() explicitly.
    // ----------------------------------------------------------------------

    public function __call(string $method, array $args): mixed
    {
        return $this->query()->{$method}(...$args);
    }
}
