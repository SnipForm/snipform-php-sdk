<?php

namespace SnipForm\Data;

use SnipForm\Http\Response;

/**
 * Typed value object for the analytics-metrics endpoint payload. Headline
 * "current period" values only — use `->asRaw()` on the builder to reach
 * the trend data (previous, difference, percent) the API returns under
 * each metric.
 *
 *   $metrics = $client->signals()->last7Days()->metrics();
 *   $metrics->sessions;    // int
 *   $metrics->views;       // int
 *   $metrics->bounceRate;  // float (0-100)
 *
 *   $raw = $client->signals()->last7Days()->asRaw()->metrics();
 *   $raw['analytics']['period_metrics']['summary']['sessions']['previous'];
 */
class MetricsResult extends SnipFormDTO
{
    public function __construct(
        public readonly int $sessions,
        public readonly int $views,
        public readonly float $viewsPerSession,
        public readonly float $bounceRate,
        public readonly int $duration,
        public readonly float $avgScroll,
        public readonly ?string $showing,
        public readonly float $tookMs,
    ) {}

    public static function fromResponse(Response $response): self
    {
        $summary = (array) ($response->data('analytics.period_metrics.summary') ?? []);

        return new self(
            sessions: (int) ($summary['sessions']['current'] ?? 0),
            views: (int) ($summary['views']['current'] ?? 0),
            viewsPerSession: (float) ($summary['views_session']['current'] ?? 0),
            // API returns bounce as a 0-1 fraction; normalize to 0-100 to match field semantics.
            bounceRate: ((float) ($summary['bounce']['current'] ?? 0)) * 100,
            duration: (int) ($summary['duration']['current'] ?? 0),
            avgScroll: (float) ($summary['scroll']['current'] ?? 0),
            showing: $response->data('meta.showing'),
            tookMs: (float) ($response->data('meta.took_ms') ?? 0),
        );
    }
}
