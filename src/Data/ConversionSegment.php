<?php

namespace SnipForm\Data;

/**
 * One row in a segments response — a slice of the funnel by a dimension or
 * tag value. `value` is the raw key (can be array for merged source rows);
 * `label` is the display string.
 */
class ConversionSegment
{
    public function __construct(
        public readonly mixed $value,
        public readonly string $label,
        public readonly int $sessions,
        public readonly int $converted,
        public readonly float $rate,
        public readonly ?string $icon,
        public readonly ?string $favicon,
    ) {}

    public static function fromArray(array $row): self
    {
        return new self(
            value: $row['value'] ?? null,
            label: (string) ($row['label'] ?? ''),
            sessions: (int) ($row['sessions'] ?? 0),
            converted: (int) ($row['converted'] ?? 0),
            rate: (float) ($row['rate'] ?? 0),
            icon: $row['icon'] ?? null,
            favicon: $row['favicon'] ?? null,
        );
    }
}
