<?php

namespace Snipform\Resources;

/**
 * Typed value object for a custom event submitted via $client->session()->event().
 */
class Event
{
    public function __construct(
        public readonly string $id,
        public readonly string $sessionId,
        public readonly ?string $type,
        public readonly string $name,
        public readonly ?string $value,
        public readonly array $meta,
        public readonly ?int $createdTs,
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
            sessionId: (string) ($row['session_id'] ?? ''),
            type: $row['type'] ?? null,
            name: (string) ($row['name'] ?? ''),
            value: $row['value'] ?? null,
            meta: (array) ($row['meta'] ?? []),
            createdTs: isset($row['created_ts']) ? (int) $row['created_ts'] : null,
            raw: $row,
        );
    }
}
