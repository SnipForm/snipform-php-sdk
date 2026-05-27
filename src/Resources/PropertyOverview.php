<?php

namespace SnipForm\Resources;

/**
 * Typed value object for the property overview payload.
 *
 *   $property = $client->properties()->overview();
 *   $property->name;          // string
 *   $property->domain;        // string
 *   $property->hasSignals;    // bool
 *   $property->counts;        // ['sessions' => int, 'forms' => int, 'pages' => int, ...]
 *   $property->raw();         // full unwrapped data block
 */
class PropertyOverview extends SnipFormDTO
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $domain,
        public readonly bool $hasSignals,
        public readonly ?string $state,
        public readonly ?string $stateName,
        public readonly array $counts,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            name: (string) ($data['name'] ?? ''),
            domain: (string) ($data['domain'] ?? ''),
            hasSignals: (bool) ($data['has_signals'] ?? false),
            state: isset($data['state']) ? (string) $data['state'] : null,
            stateName: isset($data['state_name']) ? (string) $data['state_name'] : null,
            counts: (array) ($data['counts'] ?? []),
            raw: $data,
        );
    }
}
