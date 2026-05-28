<?php

namespace SnipForm\Data;

use SnipForm\Http\Response;

/**
 * Typed value object for the analytics-metrics endpoint payload. Headline
 * "current period" values only — use raw() to reach trend data (previous,
 * difference, percent) the API also returns under each metric.
 *
 *   $metrics = $client->signals()->period('last_7')->metrics();
 *   $metrics->sessions;    // int
 *   $metrics->views;       // int
 *   $metrics->bounceRate;  // float (0-100)
 *   $metrics->raw();       // full body
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
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

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
            raw: $response->body,
        );
    }
}
