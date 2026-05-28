<?php

namespace SnipForm\Query;

use JsonSerializable;
use SnipForm\Concerns\RawAware;
use SnipForm\Data\MetricsResult;
use SnipForm\Exceptions\IncompatibleFieldOperator;
use SnipForm\Http\HttpClient;
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
class Builder implements JsonSerializable
{
    use RawAware;

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

    /**
     * Switch the period to CUSTOM. Either pass both dates inline or chain
     * `->fromDate()` / `->toDate()` afterwards to set them piecemeal.
     *
     *   ->customPeriod('2026-01-01', '2026-01-31')
     *   ->customPeriod()->fromDate('2026-01-01')->toDate('2026-01-31')
     */
    public function customPeriod(?string $dateFrom = null, ?string $dateTo = null): self
    {
        $this->period = Period::CUSTOM;
        if ($dateFrom !== null) {
            $this->dateFrom = $dateFrom;
        }
        if ($dateTo !== null) {
            $this->dateTo = $dateTo;
        }

        return $this;
    }

    /**
     * Set just the from-date on a CUSTOM period. Switches the period to
     * CUSTOM if it isn't already, so callers don't have to remember.
     */
    public function fromDate(string $dateFrom): self
    {
        $this->period = Period::CUSTOM;
        $this->dateFrom = $dateFrom;

        return $this;
    }

    /**
     * Set just the to-date on a CUSTOM period. Switches the period to
     * CUSTOM if it isn't already.
     */
    public function toDate(string $dateTo): self
    {
        $this->period = Period::CUSTOM;
        $this->dateTo = $dateTo;

        return $this;
    }

    // ======================================================================
    // Where (equality, single or multi-value via array)
    // ======================================================================

    public function where(SessionField|string $id, mixed $value): self
    {
        return $this->push($id, 'equals', $value);
    }

    public function orWhere(SessionField|string $id, mixed $value): self
    {
        return $this->push($id, 'equals', $value, where: 'or');
    }

    public function whereNot(SessionField|string $id, mixed $value): self
    {
        return $this->push($id, 'equals', $value, not: true);
    }

    public function orWhereNot(SessionField|string $id, mixed $value): self
    {
        return $this->push($id, 'equals', $value, where: 'or', not: true);
    }

    // ======================================================================
    // Keyword ops
    // ======================================================================

    public function whereStartsWith(SessionField|string $id, string $value): self
    {
        $this->assertKeyword($id, 'starts_with');

        return $this->push($id, 'starts_with', $value);
    }

    public function whereContains(SessionField|string $id, string $value): self
    {
        $this->assertKeyword($id, 'contains');

        return $this->push($id, 'contains', $value);
    }

    public function whereRegex(SessionField|string $id, string $pattern): self
    {
        $this->assertKeyword($id, 'regex');

        return $this->push($id, 'regex', $pattern);
    }

    public function whereExists(SessionField|string $id): self
    {
        return $this->push($id, 'exists', null);
    }

    public function whereNotExists(SessionField|string $id): self
    {
        return $this->push($id, 'exists', null, not: true);
    }

    // ======================================================================
    // Numeric ops
    // ======================================================================

    public function whereGt(SessionField|string $id, int|float $value): self
    {
        $this->assertNumeric($id, 'gt');

        return $this->push($id, 'gt', $value);
    }

    public function whereGte(SessionField|string $id, int|float $value): self
    {
        $this->assertNumeric($id, 'gte');

        return $this->push($id, 'gte', $value);
    }

    public function whereLt(SessionField|string $id, int|float $value): self
    {
        $this->assertNumeric($id, 'lt');

        return $this->push($id, 'lt', $value);
    }

    public function whereLte(SessionField|string $id, int|float $value): self
    {
        $this->assertNumeric($id, 'lte');

        return $this->push($id, 'lte', $value);
    }

    public function whereBetween(SessionField|string $id, int|float $min, int|float $max): self
    {
        $this->assertNumeric($id, 'between');

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
            asRaw: $this->asRaw,
        );
    }

    // ======================================================================
    // Terminal — analytics metrics
    // ======================================================================

    public function metrics(bool $withDevices = false): MetricsResult|array
    {
        $response = $this->http->post(
            'property/signals/analytics/metrics',
            $this->buildPayload(['show_devices' => $withDevices]),
        );

        if ($this->asRaw) {
            return (array) $response->data();
        }

        return MetricsResult::fromResponse($response);
    }

    /**
     * Safety net for `return $client->signals()->...` from a controller —
     * the chain serializes as if `sessions()` were called. To return the
     * headline metrics instead, end the chain with an explicit `->metrics()`.
     */
    public function jsonSerialize(): mixed
    {
        return $this->sessions();
    }

    // ======================================================================
    // Internals
    // ======================================================================

    private function push(SessionField|string $id, string $op, mixed $value, string $where = 'and', bool $not = false): self
    {
        $this->clauses[] = new Clause(
            $id instanceof SessionField ? $id->value : $id,
            $op,
            $value,
            $where,
            $not,
        );

        return $this;
    }

    /**
     * Numeric-only op guard. String IDs pass silently (we can't know their
     * type without a server round-trip); enum cases are validated against
     * their declared `FieldType`.
     */
    private function assertNumeric(SessionField|string $id, string $op): void
    {
        if ($id instanceof SessionField && ! $id->type()->isNumeric()) {
            throw IncompatibleFieldOperator::for($id, $op);
        }
    }

    /**
     * Keyword-only op guard — see assertNumeric().
     */
    private function assertKeyword(SessionField|string $id, string $op): void
    {
        if ($id instanceof SessionField && $id->type() !== FieldType::KEYWORD) {
            throw IncompatibleFieldOperator::for($id, $op);
        }
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
