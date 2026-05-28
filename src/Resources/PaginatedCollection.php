<?php

namespace SnipForm\Resources;

use Closure;
use Countable;
use IteratorAggregate;
use SnipForm\Http\HttpClient;
use SnipForm\Http\Response;
use Traversable;

/**
 * Generic auto-paginating collection. Consumes Laravel's native paginator
 * JSON shape (`data`, `current_page`, `last_page`, `total`, `next_page_url`,
 * `per_page`, ...).
 *
 *   foreach ($client->links()->all() as $link) { ... }   // walks every page
 *   $client->links()->all()->count();                    // total via meta
 *   $client->links()->all()->page(2);                    // single page array
 *   $client->links()->all()->first();                    // terminates after one
 *
 * Pass `payloadPath` for endpoints that nest the paginator under a key —
 * e.g. `'sessions'` for an endpoint returning `{sessions: {data: [...]}}`.
 */
class PaginatedCollection implements Countable, IteratorAggregate
{
    private ?int $total = null;

    /**
     * @param  Closure(array): object  $factory  fn (array $row) => Item value object
     */
    public function __construct(
        private readonly HttpClient $http,
        private readonly string $path,
        private readonly array $payload,
        private readonly Closure $factory,
        private readonly string $verb = 'GET',
        private readonly ?string $payloadPath = null,
    ) {}

    public function getIterator(): Traversable
    {
        $page = 1;
        while (true) {
            $body = $this->pageBody($this->fetchPage($page));
            $rows = (array) ($body['data'] ?? []);

            foreach ($rows as $row) {
                yield ($this->factory)($row);
            }

            $lastPage = (int) ($body['last_page'] ?? $page);
            if ($page >= $lastPage || empty($rows)) {
                break;
            }
            $page++;
        }
    }

    /** First match (or null) — terminates after one row. */
    public function first(): ?object
    {
        foreach ($this as $item) {
            return $item;
        }

        return null;
    }

    /** Materialize all rows into an array. Use with caution on large result sets. */
    public function all(): array
    {
        return iterator_to_array($this, preserve_keys: false);
    }

    /** Single page as an array of typed items — no auto-pagination. */
    public function page(int $page): array
    {
        $body = $this->pageBody($this->fetchPage($page));
        $rows = (array) ($body['data'] ?? []);

        return array_map(fn ($r) => ($this->factory)($r), $rows);
    }

    public function count(): int
    {
        if ($this->total !== null) {
            return $this->total;
        }
        $body = $this->pageBody($this->fetchPage(1));

        return $this->total = (int) ($body['total'] ?? 0);
    }

    private function fetchPage(int $page): Response
    {
        $payload = [...$this->payload, 'page' => $page];

        return $this->verb === 'POST'
            ? $this->http->post($this->path, $payload)
            : $this->http->get($this->path, $payload);
    }

    /**
     * Unwrap the paginator's JSON block from the response. When `payloadPath`
     * is null, the paginator is the response body itself; otherwise it sits
     * nested under that key.
     */
    private function pageBody(Response $response): array
    {
        if ($this->payloadPath === null) {
            return $response->all();
        }

        return (array) ($response->data($this->payloadPath) ?? []);
    }
}
