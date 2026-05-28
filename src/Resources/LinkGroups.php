<?php

namespace SnipForm\Resources;

use SnipForm\Concerns\RawAware;
use SnipForm\Data\LinkGroup;
use SnipForm\Http\HttpClient;

/**
 * Link-groups resource. Groups are returned in a single shot from the API
 * (no pagination on the group list), so this is a simple CRUD surface.
 *
 *   $client->linkGroups()->all();              // LinkGroup[]
 *   $client->linkGroups()->find($id);          // LinkGroup
 *   $client->linkGroups()->create([...]);      // LinkGroup
 *   $client->linkGroups()->update($id, [...]); // LinkGroup
 *   $client->linkGroups()->delete($id);        // true
 *
 * Append `->asRaw()` anywhere on the chain to get the underlying API
 * array instead of typed DTOs.
 */
class LinkGroups
{
    use RawAware;

    private const PATH = 'property/link-groups';

    public function __construct(private readonly HttpClient $http) {}

    /** @return array<int, LinkGroup>|array<int, array> */
    public function all(): array
    {
        $rows = (array) ($this->http->get(self::PATH)->data('groups') ?? []);

        return array_map(fn (array $row) => $this->hydrate($row, LinkGroup::fromArray(...)), $rows);
    }

    public function find(string $id): LinkGroup|array
    {
        $row = (array) $this->http->get(self::PATH.'/'.$id)->data('group');

        return $this->hydrate($row, LinkGroup::fromArray(...));
    }

    /**
     * @param  array{name: string, description?: string, purpose?: string, track_clicks?: bool}  $attributes
     */
    public function create(array $attributes): LinkGroup|array
    {
        $row = (array) $this->http->post(self::PATH, $attributes)->data('group');

        return $this->hydrate($row, LinkGroup::fromArray(...));
    }

    /**
     * @param  array{name?: string, description?: string, purpose?: string, track_clicks?: bool, state?: string}  $attributes
     */
    public function update(string $id, array $attributes): LinkGroup|array
    {
        $row = (array) $this->http->post(self::PATH.'/'.$id, $attributes)->data('group');

        return $this->hydrate($row, LinkGroup::fromArray(...));
    }

    public function delete(string $id): bool
    {
        return (bool) $this->http->delete(self::PATH.'/'.$id)->data('deleted');
    }
}
