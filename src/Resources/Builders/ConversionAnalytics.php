<?php

namespace SnipForm\Resources\Builders;

use SnipForm\Concerns\RawAware;
use SnipForm\Data\ConversionCycle;
use SnipForm\Data\ConversionSegment;
use SnipForm\Data\ConversionSummary;
use SnipForm\Http\HttpClient;

/**
 * Fluent reads for one conversion. Built via `$client->conversions()->for($id)`.
 * Chain `->between()` / `->filter()` / `->period()` to set the window, then
 * call a terminal: `->summary()`, `->segments(...)`, `->cycles(...)`,
 * `->sessionsAt(...)`.
 *
 *   $client->conversions()->for($id)
 *       ->between(strtotime('-30 days'), time())
 *       ->filter(['channel_category' => 'paid_search'])
 *       ->summary();
 */
class ConversionAnalytics
{
    use RawAware;

    private ?int $from = null;

    private ?int $to = null;

    private ?array $filter = null;

    public function __construct(
        private readonly HttpClient $http,
        private readonly string $conversionId,
    ) {}

    private function base(): string
    {
        return 'property/conversions/'.$this->conversionId;
    }

    // ----------------------------------------------------------------------
    // Window setters
    // ----------------------------------------------------------------------

    public function between(int $fromTs, int $toTs): self
    {
        $this->from = $fromTs;
        $this->to = $toTs;

        return $this;
    }

    public function since(int $fromTs): self
    {
        $this->from = $fromTs;
        $this->to = time();

        return $this;
    }

    public function filter(array $filter): self
    {
        $this->filter = $filter;

        return $this;
    }

    // ----------------------------------------------------------------------
    // Terminals
    // ----------------------------------------------------------------------

    public function summary(): ConversionSummary|array
    {
        $body = $this->http->post($this->base().'/summary', $this->windowPayload())->data();

        return $this->hydrate($body, ConversionSummary::fromArray(...));
    }

    /**
     * Slice the funnel by a flat dimension (channel_category, source_id,
     * utm_medium, request_country, request_device, entry_path, etc.).
     *
     * @return array<int, ConversionSegment>|array<int, array>
     */
    public function segments(string $dimension): array
    {
        $rows = (array) ($this->http->post(
            $this->base().'/segments',
            $this->windowPayload(['dimension' => $dimension])
        )->data('segments') ?? []);

        return array_map(fn (array $row) => $this->hydrate($row, ConversionSegment::fromArray(...)), $rows);
    }

    /**
     * Slice by a custom tag key (anything you've set via `signals.tag()` on the
     * tracker side, e.g. `campaign_phase`, `experiment_arm`).
     *
     * @return array<int, ConversionSegment>|array<int, array>
     */
    public function segmentsByTag(string $tagKey): array
    {
        $rows = (array) ($this->http->post(
            $this->base().'/segments',
            $this->windowPayload(['tag_key' => $tagKey])
        )->data('segments') ?? []);

        return array_map(fn (array $row) => $this->hydrate($row, ConversionSegment::fromArray(...)), $rows);
    }

    /**
     * @param  string  $interval  day | week | month
     * @return array{cycles: array<int, ConversionCycle>|array<int, array>, has_more: bool, page: int, interval: string}
     */
    public function cycles(string $interval, int $page = 0, int $perPage = 6): array
    {
        $body = $this->http->post($this->base().'/cycles', [
            'interval' => $interval,
            'page' => $page,
            'per_page' => $perPage,
            'filter' => $this->filter,
        ])->data();

        return [
            'cycles' => array_map(
                fn (array $row) => $this->hydrate($row, ConversionCycle::fromArray(...)),
                (array) ($body['cycles'] ?? []),
            ),
            'has_more' => (bool) ($body['has_more'] ?? false),
            'page' => (int) ($body['page'] ?? 0),
            'interval' => (string) ($body['interval'] ?? $interval),
        ];
    }

    /**
     * Sessions that reached a given funnel step. Pass `null` to get fully
     * converted sessions (every step applied).
     *
     * Returns the raw body — sessions are plain arrays since they vary by
     * what fields we surface and downstream callers usually map them anyway.
     *
     * @return array{sessions: array<int, array>, page: int, per_page: int, total: int, has_more: bool}
     */
    public function sessionsAt(?string $stepId = null, int $page = 1, int $perPage = 25): array
    {
        $body = $this->http->post($this->base().'/sessions', [
            ...$this->windowPayload(),
            'step_id' => $stepId,
            'page' => $page,
            'per_page' => $perPage,
        ])->data();

        return [
            'sessions' => (array) ($body['sessions'] ?? []),
            'page' => (int) ($body['page'] ?? 1),
            'per_page' => (int) ($body['per_page'] ?? $perPage),
            'total' => (int) ($body['total'] ?? 0),
            'has_more' => (bool) ($body['has_more'] ?? false),
        ];
    }

    // ----------------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------------

    private function windowPayload(array $extra = []): array
    {
        $body = $extra;
        if ($this->from !== null) {
            $body['from'] = $this->from;
        }
        if ($this->to !== null) {
            $body['to'] = $this->to;
        }
        if ($this->filter !== null) {
            $body['filter'] = $this->filter;
        }

        return $body;
    }
}
