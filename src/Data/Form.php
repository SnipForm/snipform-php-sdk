<?php

namespace SnipForm\Data;

/**
 * Typed value object for a form on the property.
 *
 * `submits` counts the submissions the form accepted. Spam and submissions
 * from blocked countries never add to it; flagged ones (suspicious, kept for
 * review) do.
 */
class Form extends SnipFormDTO
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $state,
        public readonly int $submits,
        public readonly ?string $lastSubmitAt,
        public readonly ?string $createdAt,
    ) {}

    public static function fromArray(array $row): self
    {
        return new self(
            id: (string) ($row['id'] ?? ''),
            name: (string) ($row['name'] ?? ''),
            state: (string) ($row['state'] ?? 'active'),
            submits: (int) ($row['submits'] ?? 0),
            lastSubmitAt: $row['last_submit_at'] ?? null,
            createdAt: $row['created_at'] ?? null,
        );
    }
}
