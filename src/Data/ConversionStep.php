<?php

namespace SnipForm\Data;

/**
 * One step of a conversion funnel.
 */
class ConversionStep
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly int $order,
        public readonly string $triggerType,
        public readonly array $triggerConfig,
        public readonly bool $isRequired,
    ) {}

    public static function fromArray(array $row): self
    {
        return new self(
            id: (string) ($row['id'] ?? ''),
            name: (string) ($row['name'] ?? ''),
            order: (int) ($row['order'] ?? 0),
            triggerType: (string) ($row['trigger_type'] ?? ''),
            triggerConfig: (array) ($row['trigger_config'] ?? []),
            isRequired: (bool) ($row['is_required'] ?? true),
        );
    }
}
