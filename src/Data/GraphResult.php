<?php

namespace SnipForm\Data;

use SnipForm\Http\Response;

/**
 * Typed value object for the analytics graph endpoints (`graph()` + `live()`).
 *
 *   $graph = $client->signals()->last28Days()->graph('sessions');
 *   $graph->series;            // GraphPoint[] - the current period, one per bucket
 *   $graph->previousSeries;    // GraphPoint[] - only when compare=true
 *   $graph->projectedSeries;   // GraphPoint[] - dotted tail for the still-running bucket
 *
 * `live()` returns the same shape for the live window.
 */
class GraphResult extends SnipFormDTO
{
    /**
     * @param  GraphPoint[]  $series
     * @param  GraphPoint[]  $previousSeries
     * @param  GraphPoint[]  $projectedSeries
     */
    public function __construct(
        public readonly string $metric,
        public readonly string $metricLabel,
        public readonly array $series,
        public readonly array $previousSeries,
        public readonly array $projectedSeries,
        public readonly ?string $showing,
        public readonly float $tookMs,
    ) {}

    public static function fromResponse(Response $response): self
    {
        $graph = (array) ($response->data('graph') ?? []);

        return new self(
            metric: (string) ($graph['metric'] ?? ''),
            metricLabel: (string) ($graph['metricLabel'] ?? ''),
            series: self::points($graph['series'] ?? []),
            previousSeries: self::points($graph['previousSeries'] ?? []),
            projectedSeries: self::points($graph['projectedSeries'] ?? []),
            showing: $response->data('meta.showing'),
            tookMs: (float) ($response->data('meta.took_ms') ?? 0),
        );
    }

    /** @return GraphPoint[] */
    private static function points(mixed $rows): array
    {
        $points = [];
        foreach ((array) $rows as $row) {
            if (is_array($row)) {
                $points[] = GraphPoint::fromArray($row);
            }
        }

        return $points;
    }
}
