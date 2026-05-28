<?php

namespace SnipForm\Data;

/**
 * Typed value object for a custom event submitted via $client->session()->event().
 */
class Event extends SnipFormDTO
{
    public function __construct(
        public readonly string $id,
        public readonly string $sessionId,
        public readonly ?string $type,
        public readonly string $name,
        public readonly ?string $value,
        public readonly array $meta,
        public readonly ?int $createdTs,
        array $raw = [],
    ) {
        parent::__construct($raw);
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
