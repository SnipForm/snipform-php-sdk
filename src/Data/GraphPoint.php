<?php

namespace SnipForm\Data;

/**
 * One bucket on an analytics graph - the label is whatever the interval
 * produced ("Mon", "14 Aug", "09:00"), the value is the metric for it.
 */
class GraphPoint extends SnipFormDTO
{
    public function __construct(
        public readonly string $label,
        public readonly float $value,
    ) {}

    public static function fromArray(array $row): self
    {
        return new self(
            label: (string) ($row['label'] ?? ''),
            value: (float) ($row['value'] ?? 0),
        );
    }
}
