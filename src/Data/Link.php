<?php

namespace SnipForm\Data;

/**
 * Typed value object for a short link.
 */
class Link extends SnipFormDTO
{
    public function __construct(
        public readonly string $id,
        public readonly string $groupId,
        public readonly string $code,
        public readonly string $shortUrl,
        public readonly string $destinationUrl,
        public readonly ?string $domain,
        public readonly array $utm,
        public readonly bool $isActive,
        public readonly int $clicks,
        public readonly ?int $createdTs,
    ) {}

    public function utm(string $key): ?string
    {
        return $this->utm[$key] ?? null;
    }

    public static function fromArray(array $row): self
    {
        return new self(
            id: (string) ($row['id'] ?? ''),
            groupId: (string) ($row['group_id'] ?? ''),
            code: (string) ($row['code'] ?? ''),
            shortUrl: (string) ($row['short_url'] ?? ''),
            destinationUrl: (string) ($row['destination_url'] ?? ''),
            domain: $row['domain'] ?? null,
            utm: (array) ($row['utm'] ?? []),
            isActive: (bool) ($row['is_active'] ?? false),
            clicks: (int) ($row['clicks'] ?? 0),
            createdTs: isset($row['created_ts']) ? (int) $row['created_ts'] : null,
        );
    }
}
