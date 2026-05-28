<?php

namespace SnipForm\Resources;

use SnipForm\Concerns\RawAware;
use SnipForm\Data\Link;
use SnipForm\Http\HttpClient;

/**
 * Short-links resource. Paginated list + full CRUD.
 *
 *   foreach ($client->links()->all() as $link) { ... }     // auto-paginates
 *   $client->links()->all(['group_id' => '...'])->page(1); // single page
 *   $client->links()->all()->count();                      // total
 *
 *   $link = $client->links()->find($id);
 *   $link = $client->links()->create(['group_id' => ..., 'destination_url' => ..., 'domain' => ...]);
 *   $link = $client->links()->update($id, ['destination_url' => 'https://...']);
 *   $client->links()->delete($id);
 *
 * Append `->asRaw()` to get arrays back instead of `Link` DTOs (works for
 * `all()`, `find()`, `create()`, `update()`).
 */
class Links
{
    use RawAware;

    private const PATH = 'property/links';

    public function __construct(private readonly HttpClient $http) {}

    /**
     * @param  array{group_id?: string, per_page?: int}  $filters
     */
    public function all(array $filters = []): PaginatedCollection
    {
        return new PaginatedCollection(
            http: $this->http,
            path: self::PATH,
            payload: $filters,
            factory: Link::fromArray(...),
            verb: 'GET',
            asRaw: $this->asRaw,
        );
    }

    public function find(string $id): Link|array
    {
        $row = (array) $this->http->get(self::PATH.'/'.$id)->data('link');

        return $this->hydrate($row, Link::fromArray(...));
    }

    /**
     * @param  array{group_id: string, destination_url: string, domain: string, utm?: array<string,string>}  $attributes
     */
    public function create(array $attributes): Link|array
    {
        $row = (array) $this->http->post(self::PATH, $attributes)->data('link');

        return $this->hydrate($row, Link::fromArray(...));
    }

    /**
     * @param  array{destination_url?: string, domain?: string, utm?: array<string,string>, is_active?: bool}  $attributes
     */
    public function update(string $id, array $attributes): Link|array
    {
        $row = (array) $this->http->post(self::PATH.'/'.$id, $attributes)->data('link');

        return $this->hydrate($row, Link::fromArray(...));
    }

    public function delete(string $id): bool
    {
        return (bool) $this->http->delete(self::PATH.'/'.$id)->data('deleted');
    }
}
