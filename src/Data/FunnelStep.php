<?php

namespace SnipForm\Data;

/**
 * One step inside a funnel response. `dropOff` is the percent of sessions
 * lost from the previous step (0 on the entry step). `isConversion` marks
 * the final step that defines the conversion event itself.
 */
class FunnelStep
{
    public function __construct(
        public readonly string $stepId,
        public readonly string $name,
        public readonly int $order,
        public readonly int $count,
        public readonly float $dropOff,
        public readonly bool $isConversion,
        public readonly ?string $triggerLabel,
        public readonly ?string $triggerSummary,
    ) {}

    public static function fromArray(array $row): self
    {
        return new self(
            stepId: (string) ($row['step_id'] ?? ''),
            name: (string) ($row['name'] ?? ''),
            order: (int) ($row['order'] ?? 0),
            count: (int) ($row['count'] ?? 0),
            dropOff: (float) ($row['drop_off'] ?? 0),
            isConversion: (bool) ($row['is_conversion'] ?? false),
            triggerLabel: $row['trigger_label'] ?? null,
            triggerSummary: $row['trigger_summary'] ?? null,
        );
    }
}
