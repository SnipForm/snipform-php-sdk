<?php

namespace Snipform\Resources;

use Snipform\Http\Response;

/**
 * Typed value object for the analytics-metrics endpoint payload.
 *
 *   $metrics = $client->signals()->period('last_7')->metrics();
 *   $metrics->sessions;   // int
 *   $metrics->views;      // int
 *   $metrics->bounceRate; // float
 *   $metrics->raw();      // full body for anything we haven't typed
 */
class MetricsResult
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
        private readonly array $raw,
    ) {}

    public function raw(): array
    {
        return $this->raw;
    }

    public static function fromResponse(Response $response): self
    {
        $analytics = (array) ($response->data('analytics') ?? []);
        $period = $analytics['period_metrics']['data']['summary']['current'] ?? [];

        return new self(
            sessions: (int) ($period['sessions'] ?? 0),
            views: (int) ($period['views'] ?? 0),
            viewsPerSession: (float) ($period['views_session'] ?? 0),
            bounceRate: (float) ($period['bounce'] ?? 0),
            duration: (int) ($period['duration'] ?? 0),
            avgScroll: (float) ($period['scroll'] ?? 0),
            showing: $response->data('meta.showing'),
            tookMs: (float) ($response->data('meta.took_ms') ?? 0),
            raw: $response->body,
        );
    }
}
