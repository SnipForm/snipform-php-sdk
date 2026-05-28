<?php

namespace SnipForm\Resources;

use SnipForm\Concerns\RawAware;
use SnipForm\Data\Click;
use SnipForm\Http\HttpClient;

/**
 * Short-link clicks resource. Read-only — clicks are recorded server-side
 * from short-link redirects.
 *
 *   foreach ($client->clicks()->forLink($id)->all() as $click) { ... }
 *
 *   $client->clicks()
 *       ->forGroup($groupId)
 *       ->between($from, $to)
 *       ->usersOnly()
 *       ->all();
 *
 *   $client->clicks()->find($clickId);
 */
class Clicks
{
    use RawAware;

    private const PATH = 'property/clicks';

    /** @var array<string, mixed> */
    private array $filters = [];

    public function __construct(private readonly HttpClient $http) {}

    public function forLink(string $shortLinkId): self
    {
        $this->filters['link_id'] = $shortLinkId;

        return $this;
    }

    public function forGroup(string $shortLinkGroupId): self
    {
        $this->filters['group_id'] = $shortLinkGroupId;

        return $this;
    }

    /** Both as unix timestamps. */
    public function between(int $fromTs, int $toTs): self
    {
        $this->filters['from_ts'] = $fromTs;
        $this->filters['to_ts'] = $toTs;

        return $this;
    }

    public function since(int $fromTs): self
    {
        $this->filters['from_ts'] = $fromTs;

        return $this;
    }

    public function usersOnly(): self
    {
        $this->filters['type'] = 'user';

        return $this;
    }

    public function botsOnly(): self
    {
        $this->filters['type'] = 'bot';

        return $this;
    }

    public function perPage(int $n): self
    {
        $this->filters['per_page'] = $n;

        return $this;
    }

    public function all(): PaginatedCollection
    {
        return new PaginatedCollection(
            http: $this->http,
            path: self::PATH,
            payload: $this->filters,
            factory: Click::fromArray(...),
            verb: 'GET',
            asRaw: $this->asRaw,
        );
    }

    public function find(string $id): Click|array
    {
        $row = (array) $this->http->get(self::PATH.'/'.$id)->data('click');

        return $this->hydrate($row, Click::fromArray(...));
    }
}
