<?php

namespace SnipForm\Resources;

/**
 * Typed value object for a short-link group.
 */
class LinkGroup
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $description,
        public readonly ?string $purpose,
        public readonly string $state,
        public readonly bool $trackClicks,
        public readonly int $countLinks,
        public readonly int $countClicks,
        public readonly ?string $createdAt,
        private readonly array $raw,
    ) {}

    public function raw(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->raw;
        }

        return $this->raw[$key] ?? null;
    }

    public static function fromArray(array $row): self
    {
        return new self(
            id: (string) ($row['id'] ?? ''),
            name: (string) ($row['name'] ?? ''),
            description: (string) ($row['description'] ?? ''),
            purpose: $row['purpose'] ?? null,
            state: (string) ($row['state'] ?? 'active'),
            trackClicks: (bool) ($row['track_clicks'] ?? false),
            countLinks: (int) ($row['count_links'] ?? 0),
            countClicks: (int) ($row['count_clicks'] ?? 0),
            createdAt: $row['created_at'] ?? null,
            raw: $row,
        );
    }
}
