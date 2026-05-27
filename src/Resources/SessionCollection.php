<?php

namespace SnipForm\Resources;

use SnipForm\Http\HttpClient;

/**
 * Session-typed paginated collection. Thin factory over PaginatedCollection
 * wired with the session endpoint's nested Laravel-paginator shape.
 */
class SessionCollection
{
    public static function for(HttpClient $http, array $payload, string $path): PaginatedCollection
    {
        return new PaginatedCollection(
            http: $http,
            path: $path,
            payload: $payload,
            itemsPath: 'sessions.data',
            totalPath: 'sessions.total',
            lastPagePath: 'sessions.last_page',
            factory: SessionRow::fromArray(...),
            verb: 'POST',
        );
    }
}
