<?php

namespace SnipForm\Resources;

use Closure;
use Countable;
use IteratorAggregate;
use SnipForm\Http\HttpClient;
use SnipForm\Http\Response;
use Traversable;

/**
 * Generic auto-paginating collection. Configured per resource with the endpoint
 * + the dot-paths to find items / total / last_page in the response, plus a
 * factory closure that turns a raw row into the typed value object.
 *
 *   foreach ($client->links()->all() as $link) { ... }   // walks every page
 *   $client->links()->all()->count();                    // total via meta
 *   $client->links()->all()->page(2);                    // single page array
 *   $client->links()->all()->first();                    // terminates after one
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
        private readonly string $itemsPath,
        private readonly string $totalPath,
        private readonly string $lastPagePath,
        private readonly Closure $factory,
        private readonly string $verb = 'GET',
    ) {}

    public function getIterator(): Traversable
    {
        $page = 1;
        while (true) {
            $response = $this->fetchPage($page);
            $rows = (array) ($response->data($this->itemsPath) ?? []);

            foreach ($rows as $row) {
                yield ($this->factory)($row);
            }

            $lastPage = (int) ($response->data($this->lastPagePath) ?? $page);
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
        $response = $this->fetchPage($page);
        $rows = (array) ($response->data($this->itemsPath) ?? []);

        return array_map(fn ($r) => ($this->factory)($r), $rows);
    }

    public function count(): int
    {
        if ($this->total !== null) {
            return $this->total;
        }
        $response = $this->fetchPage(1);

        return $this->total = (int) ($response->data($this->totalPath) ?? 0);
    }

    private function fetchPage(int $page): Response
    {
        $payload = [...$this->payload, 'page' => $page];

        return $this->verb === 'POST'
            ? $this->http->post($this->path, $payload)
            : $this->http->get($this->path, $payload);
    }
}
