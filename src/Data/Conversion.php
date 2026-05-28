<?php

namespace SnipForm\Data;

/**
 * A conversion definition. Returned by Conversions::find(), Conversions::all(),
 * and after every write (create / update / replaceSteps / publish / toggle).
 *
 * `steps` is empty on list shapes and populated on the detail shape.
 */
class Conversion extends SnipFormDTO
{
    /**
     * @param  array<int, ConversionStep>  $steps
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $description,
        public readonly string $type,
        public readonly string $state,
        public readonly ?float $conversionValue,
        public readonly bool $valueFromEvent,
        public readonly int $stepsCount,
        public readonly ?string $startingFrom,
        public readonly ?string $defaultPeriod,
        public readonly ?string $defaultCycle,
        public readonly array $steps,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    public function isDraft(): bool
    {
        return $this->state === 'draft';
    }

    public function isActive(): bool
    {
        return $this->state === 'active';
    }

    public static function fromArray(array $row): self
    {
        $steps = array_map(ConversionStep::fromArray(...), (array) ($row['steps'] ?? []));

        return new self(
            id: (string) ($row['id'] ?? ''),
            name: (string) ($row['name'] ?? ''),
            description: (string) ($row['description'] ?? ''),
            type: (string) ($row['type'] ?? ''),
            state: (string) ($row['state'] ?? ''),
            conversionValue: isset($row['conversion_value']) ? (float) $row['conversion_value'] : null,
            valueFromEvent: (bool) ($row['value_from_event'] ?? false),
            stepsCount: (int) ($row['steps_count'] ?? count($steps)),
            startingFrom: $row['starting_from'] ?? null,
            defaultPeriod: $row['default_period'] ?? null,
            defaultCycle: $row['default_cycle'] ?? null,
            steps: $steps,
            raw: $row,
        );
    }
}
