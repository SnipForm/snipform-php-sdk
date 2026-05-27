<?php

namespace SnipForm\Resources;

/**
 * One time-bucket in a cycles response (a day, week, or month). `delta` is
 * the percent change in rate vs the previous bucket (null on the oldest).
 */
class ConversionCycle
{
    public function __construct(
        public readonly string $label,
        public readonly int $fromTs,
        public readonly int $toTs,
        public readonly string $dateFrom,
        public readonly string $dateTo,
        public readonly bool $isCurrent,
        public readonly int $sessions,
        public readonly int $conversions,
        public readonly float $rate,
        public readonly ?float $value,
        public readonly ?float $delta,
    ) {}

    public static function fromArray(array $row): self
    {
        return new self(
            label: (string) ($row['label'] ?? ''),
            fromTs: (int) ($row['from_ts'] ?? 0),
            toTs: (int) ($row['to_ts'] ?? 0),
            dateFrom: (string) ($row['date_from'] ?? ''),
            dateTo: (string) ($row['date_to'] ?? ''),
            isCurrent: (bool) ($row['is_current'] ?? false),
            sessions: (int) ($row['sessions'] ?? 0),
            conversions: (int) ($row['conversions'] ?? 0),
            rate: (float) ($row['rate'] ?? 0),
            value: isset($row['value']) ? (float) $row['value'] : null,
            delta: isset($row['delta']) ? (float) $row['delta'] : null,
        );
    }
}
