<?php

namespace SnipForm\Data;

use ArrayAccess;
use BadMethodCallException;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use SnipForm\Resources\PaginatedCollection;
use Traversable;

/**
 * One page of a `PaginatedCollection` result, returned by `->page($n)`.
 * Carries the page's items plus the full Laravel paginator meta, with
 * convenience navigators (`next()`, `prev()`, `first()`, `last()`) that
 * fetch the related page on demand.
 *
 *   $page = $snipform->signals()->sessions(20)->page(2);
 *
 *   $page->items;           // SessionRow[] (or array[] when asRaw)
 *   $page->currentPage;     // 2
 *   $page->lastPage;        // 5
 *   $page->total;           // 230
 *   $page->hasMore();       // true
 *   $page->next();          // → Page 3 (one HTTP call)
 *
 *   foreach ($page as $row) { ... }    // iterates items
 *   count($page);                       // count of items on THIS page
 *   $page[0];                           // first item
 *
 *   return $page;                       // Laravel pagination JSON for THIS page
 */
class Page implements ArrayAccess, Countable, IteratorAggregate, JsonSerializable
{
    /**
     * @param  array<int, mixed>  $items  hydrated typed items (or arrays in asRaw mode)
     * @param  array  $raw  full paginator JSON body — used as the JSON
     *                      serialization base (data replaced with $items)
     */
    public function __construct(
        public readonly array $items,
        public readonly int $currentPage,
        public readonly int $lastPage,
        public readonly int $total,
        public readonly int $perPage,
        public readonly ?int $from,
        public readonly ?int $to,
        public readonly ?string $nextPageUrl,
        public readonly ?string $prevPageUrl,
        public readonly ?string $firstPageUrl,
        public readonly ?string $lastPageUrl,
        private readonly array $raw,
        private readonly PaginatedCollection $collection,
    ) {}

    // ----------------------------------------------------------------------
    // Predicates
    // ----------------------------------------------------------------------

    public function hasMore(): bool
    {
        return $this->currentPage < $this->lastPage;
    }

    public function isFirstPage(): bool
    {
        return $this->currentPage <= 1;
    }

    public function isLastPage(): bool
    {
        return $this->currentPage >= $this->lastPage;
    }

    // ----------------------------------------------------------------------
    // Navigation — each follows by re-fetching from the parent collection
    // ----------------------------------------------------------------------

    public function next(): ?self
    {
        return $this->hasMore() ? $this->collection->page($this->currentPage + 1) : null;
    }

    public function prev(): ?self
    {
        return $this->currentPage > 1 ? $this->collection->page($this->currentPage - 1) : null;
    }

    public function first(): self
    {
        return $this->isFirstPage() ? $this : $this->collection->page(1);
    }

    public function last(): self
    {
        return $this->isLastPage() ? $this : $this->collection->page($this->lastPage);
    }

    /**
     * Follow one of the paginator URLs returned by the API
     * (`nextPageUrl`, `prevPageUrl`, `firstPageUrl`, `lastPageUrl`, or any
     * URL from a Laravel-paginator `links` array). Pulls the `page` query
     * param out of the URL and fetches that page.
     *
     * Returns null when `$url` is empty, malformed, or carries no `page`
     * parameter — so a Laravel `links` row whose url is null (the gap dots
     * between page numbers) can be passed straight through.
     *
     *   foreach ($page->raw()['links'] ?? [] as $link) {
     *       if (! $link['active']) {
     *           $other = $page->pageLink($link['url']);
     *       }
     *   }
     */
    public function pageLink(?string $url): ?self
    {
        if (! $url) {
            return null;
        }

        $query = parse_url($url, PHP_URL_QUERY);
        if (! is_string($query)) {
            return null;
        }

        parse_str($query, $params);
        $n = (int) ($params['page'] ?? 0);

        return $n > 0 ? $this->collection->page($n) : null;
    }

    /**
     * The full paginator JSON body as the API returned it. Useful when you
     * want fields the Page doesn't surface directly — e.g. Laravel's
     * `links` array for rendering numbered page links in a UI.
     */
    public function raw(): array
    {
        return $this->raw;
    }

    // ----------------------------------------------------------------------
    // IteratorAggregate / Countable / ArrayAccess — make Page behave like
    // the bare items array so existing `foreach ($coll->page(2) as $x)` and
    // `count($coll->page(2))` keep working after the return-type change.
    // ----------------------------------------------------------------------

    public function getIterator(): Traversable
    {
        yield from $this->items;
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->items[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->items[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new BadMethodCallException('Page is immutable.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new BadMethodCallException('Page is immutable.');
    }

    // ----------------------------------------------------------------------
    // JsonSerializable — return Laravel pagination shape for THIS page.
    // ----------------------------------------------------------------------

    public function jsonSerialize(): array
    {
        return [...$this->raw, 'data' => $this->items];
    }
}
