<?php

namespace SnipForm\Resources;

/**
 * Result of $client->conversions()->for($id)->summary().
 *
 * `funnel` is an array of FunnelStep — the per-step counts and drop-offs over
 * the window. `value` is null when no monetary value is associated with the
 * conversion definition.
 */
class ConversionSummary
{
    /**
     * @param  array<int, FunnelStep>  $funnel
     */
    public function __construct(
        public readonly int $sessions,
        public readonly int $conversions,
        public readonly float $rate,
        public readonly ?float $value,
        public readonly int $windowFrom,
        public readonly int $windowTo,
        public readonly array $funnel,
        private readonly array $raw,
    ) {}

    public function raw(?string $key = null): mixed
    {
        return $key === null ? $this->raw : ($this->raw[$key] ?? null);
    }

    public static function fromArray(array $row): self
    {
        $summary = (array) ($row['summary'] ?? []);
        $window = (array) ($row['window'] ?? []);
        $funnel = array_map(FunnelStep::fromArray(...), (array) ($row['funnel'] ?? []));

        return new self(
            sessions: (int) ($summary['sessions'] ?? 0),
            conversions: (int) ($summary['conversions'] ?? 0),
            rate: (float) ($summary['rate'] ?? 0),
            value: isset($summary['value']) ? (float) $summary['value'] : null,
            windowFrom: (int) ($window['from'] ?? 0),
            windowTo: (int) ($window['to'] ?? 0),
            funnel: $funnel,
            raw: $row,
        );
    }
}
