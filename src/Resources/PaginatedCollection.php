<?php

namespace SnipForm\Resources;

use Closure;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use SnipForm\Data\Page;
use SnipForm\Http\HttpClient;
use SnipForm\Http\Response;
use Traversable;

/**
 * Generic auto-paginating collection. Consumes Laravel's native paginator
 * JSON shape (`data`, `current_page`, `last_page`, `total`, `next_page_url`,
 * `per_page`, ...).
 *
 *   foreach ($client->links()->all() as $link) { ... }    // walks every page
 *   $client->links()->all()->count();                     // total via meta
 *   $client->links()->all()->page(2);                     // → Page (items + meta + ->next()/->prev())
 *   $client->links()->all()->first();                     // first item across all pages
 *
 * Pass `payloadPath` for endpoints that nest the paginator under a key —
 * e.g. `'sessions'` for an endpoint returning `{sessions: {data: [...]}}`.
 */
class PaginatedCollection implements Countable, IteratorAggregate, JsonSerializable
{
    private ?int $total = null;

    /**
     * @param  Closure(array): object  $factory  fn (array $row) => Item value object.
     *                                            Skipped when $asRaw is true; rows yield as plain arrays.
     */
    public function __construct(
        private readonly HttpClient $http,
        private readonly string $path,
        private readonly array $payload,
        private readonly Closure $factory,
        private readonly string $verb = 'GET',
        private readonly ?string $payloadPath = null,
        private readonly bool $asRaw = false,
    ) {}

    public function getIterator(): Traversable
    {
        $page = 1;
        while (true) {
            $body = $this->pageBody($this->fetchPage($page));
            $rows = (array) ($body['data'] ?? []);

            foreach ($rows as $row) {
                yield $this->asRaw ? $row : ($this->factory)($row);
            }

            $lastPage = (int) ($body['last_page'] ?? $page);
            if ($page >= $lastPage || empty($rows)) {
                break;
            }
            $page++;
        }
    }

    /** First match (or null) — terminates after one row. */
    public function first(): mixed
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

    /**
     * Fetch a specific page. Returns a {@see Page} object carrying items
     * plus the full Laravel paginator meta and navigation helpers
     * (`->next()`, `->prev()`, `->first()`, `->last()`).
     *
     * Back-compat: Page is iterable + countable + array-accessible, so
     * existing `foreach`/`count`/`$page[0]` usage on the return value of
     * `->page(N)` still works.
     */
    public function page(int $page): Page
    {
        $body = $this->pageBody($this->fetchPage($page));
        $rows = (array) ($body['data'] ?? []);
        $items = $this->asRaw
            ? $rows
            : array_map(fn ($r) => ($this->factory)($r), $rows);

        return new Page(
            items: $items,
            currentPage: (int) ($body['current_page'] ?? $page),
            lastPage: (int) ($body['last_page'] ?? 1),
            total: (int) ($body['total'] ?? count($items)),
            perPage: (int) ($body['per_page'] ?? count($items)),
            from: isset($body['from']) ? (int) $body['from'] : null,
            to: isset($body['to']) ? (int) $body['to'] : null,
            nextPageUrl: $body['next_page_url'] ?? null,
            prevPageUrl: $body['prev_page_url'] ?? null,
            firstPageUrl: $body['first_page_url'] ?? null,
            lastPageUrl: $body['last_page_url'] ?? null,
            raw: $body,
            collection: $this,
        );
    }

    public function count(): int
    {
        if ($this->total !== null) {
            return $this->total;
        }
        $body = $this->pageBody($this->fetchPage(1));

        return $this->total = (int) ($body['total'] ?? 0);
    }

    /**
     * Serialize as Laravel's pagination JSON shape for page 1 — items
     * (hydrated via the factory unless `asRaw` is set) plus the paginator
     * meta. Lets a Laravel controller `return $client->signals()->sessions();`
     * directly without iterating.
     *
     * For a specific page, return the Page directly: `return $coll->page(2);`
     */
    public function jsonSerialize(): array
    {
        return $this->page(1)->jsonSerialize();
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
            return $response->data();
        }

        return (array) ($response->data($this->payloadPath) ?? []);
    }
}
