<?php

namespace SnipForm\Query;

use SnipForm\Http\HttpClient;
use SnipForm\Resources\MetricsResult;
use SnipForm\Resources\PaginatedCollection;
use SnipForm\Resources\SessionCollection;

/**
 * Fluent signals query builder. Collects clauses + a period, then terminal
 * methods (sessions / metrics) post the structured payload to the V2 API.
 *
 *   $client->signals()
 *       ->last28Days()
 *       ->where('country', 'US')
 *       ->orWhere('country', 'CA')
 *       ->whereNot('device', 'mobile')
 *       ->whereStartsWith('entry_path', '/blog')
 *       ->sessions();
 *
 * Field names passed to `where*()` are the public SignalFieldMappingSet
 * ids (e.g. `country`, `device`, `entry_path`, `utm_content`, `views`).
 * The server resolves field/subfield/type from the id, so the wire stays
 * small.
 */
class Builder
{
    /** @var list<Clause> */
    private array $clauses = [];

    private Period $period = Period::LAST_7;

    private ?string $dateFrom = null;

    private ?string $dateTo = null;

    public function __construct(private readonly HttpClient $http) {}

    // ======================================================================
    // Period scoping — typed shorthand (autocompletes in the IDE)
    // ======================================================================

    public function today(): self
    {
        return $this->period(Period::TODAY);
    }

    public function yesterday(): self
    {
        return $this->period(Period::YESTERDAY);
    }

    public function last7Days(): self
    {
        return $this->period(Period::LAST_7);
    }

    public function last28Days(): self
    {
        return $this->period(Period::LAST_28);
    }

    public function monthToDate(): self
    {
        return $this->period(Period::MONTH_TO_DATE);
    }

    public function yearToDate(): self
    {
        return $this->period(Period::YEAR_TO_DATE);
    }

    public function last12Months(): self
    {
        return $this->period(Period::LAST_12_MONTHS);
    }

    /**
     * Set the period from a `Period` case or its string value. Invalid
     * strings throw `InvalidPeriodException` immediately — no round trip.
     */
    public function period(Period|string $period): self
    {
        $this->period = Period::coerce($period);
        $this->dateFrom = null;
        $this->dateTo = null;

        return $this;
    }

    /** Explicit Y-m-d range. Sets the period to CUSTOM. */
    public function between(string $from, string $to): self
    {
        $this->period = Period::CUSTOM;
        $this->dateFrom = $from;
        $this->dateTo = $to;

        return $this;
    }

    // ======================================================================
    // Where (equality, single or multi-value via array)
    // ======================================================================

    public function where(string $id, mixed $value): self
    {
        return $this->push($id, 'equals', $value);
    }

    public function orWhere(string $id, mixed $value): self
    {
        return $this->push($id, 'equals', $value, where: 'or');
    }

    public function whereNot(string $id, mixed $value): self
    {
        return $this->push($id, 'equals', $value, not: true);
    }

    public function orWhereNot(string $id, mixed $value): self
    {
        return $this->push($id, 'equals', $value, where: 'or', not: true);
    }

    // ======================================================================
    // Keyword ops
    // ======================================================================

    public function whereStartsWith(string $id, string $value): self
    {
        return $this->push($id, 'starts_with', $value);
    }

    public function whereContains(string $id, string $value): self
    {
        return $this->push($id, 'contains', $value);
    }

    public function whereRegex(string $id, string $pattern): self
    {
        return $this->push($id, 'regex', $pattern);
    }

    public function whereExists(string $id): self
    {
        return $this->push($id, 'exists', null);
    }

    public function whereNotExists(string $id): self
    {
        return $this->push($id, 'exists', null, not: true);
    }

    // ======================================================================
    // Numeric ops
    // ======================================================================

    public function whereGt(string $id, int|float $value): self
    {
        return $this->push($id, 'gt', $value);
    }

    public function whereGte(string $id, int|float $value): self
    {
        return $this->push($id, 'gte', $value);
    }

    public function whereLt(string $id, int|float $value): self
    {
        return $this->push($id, 'lt', $value);
    }

    public function whereLte(string $id, int|float $value): self
    {
        return $this->push($id, 'lte', $value);
    }

    public function whereBetween(string $id, int|float $min, int|float $max): self
    {
        return $this->push($id, 'between', [$min, $max]);
    }

    // ======================================================================
    // Terminal — sessions (auto-paginated iterator)
    // ======================================================================

    public function sessions(int $perPage = 50): PaginatedCollection
    {
        return SessionCollection::for(
            http: $this->http,
            payload: $this->buildPayload(['per_page' => $perPage]),
            path: 'property/signals/sessions',
        );
    }

    // ======================================================================
    // Terminal — analytics metrics
    // ======================================================================

    public function metrics(bool $withDevices = false): MetricsResult
    {
        $response = $this->http->post(
            'property/signals/analytics/metrics',
            $this->buildPayload(['show_devices' => $withDevices]),
        );

        return MetricsResult::fromResponse($response);
    }

    // ======================================================================
    // Internals
    // ======================================================================

    private function push(string $id, string $op, mixed $value, string $where = 'and', bool $not = false): self
    {
        $this->clauses[] = new Clause($id, $op, $value, $where, $not);

        return $this;
    }

    /**
     * Compose the JSON payload posted to the V2 API.
     *
     * @internal exposed for tests
     */
    public function buildPayload(array $extra = []): array
    {
        $payload = [
            'period' => $this->period->value,
            'clauses' => array_map(fn (Clause $c) => $c->toArray(), $this->clauses),
        ];
        if ($this->dateFrom && $this->dateTo) {
            $payload['date_from'] = $this->dateFrom;
            $payload['date_to'] = $this->dateTo;
        }

        return [...$payload, ...$extra];
    }
}
