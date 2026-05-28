<?php

namespace SnipForm\Resources;

use SnipForm\Data\SessionRow;
use SnipForm\Http\HttpClient;

/**
 * Session-typed paginated collection. Thin factory over PaginatedCollection
 * wired for the signals/sessions endpoint, which posts the structured query
 * + period and returns Laravel's native paginator JSON at the root.
 */
class SessionCollection
{
    public static function for(HttpClient $http, array $payload, string $path): PaginatedCollection
    {
        return new PaginatedCollection(
            http: $http,
            path: $path,
            payload: $payload,
            factory: SessionRow::fromArray(...),
            verb: 'POST',
        );
    }
}
