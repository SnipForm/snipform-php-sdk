<?php

namespace SnipForm\Query;

use SnipForm\Http\HttpClient;
use SnipForm\Resources\MetricsResult;
use SnipForm\Resources\PaginatedCollection;
use SnipForm\Resources\SessionCollection;

/**
 * Fluent query builder. Collects clauses + period scoping; terminal methods
 * (sessions / metrics / graph / count) serialize and POST to the V2 API.
 *
 *   $client->signals()
 *       ->period('last_30')
 *       ->where('country', 'US')
 *       ->orWhere('country', 'CA')
 *       ->whereNot('device', 'mobile')
 *       ->whereStartsWith('entry_path', '/blog')
 *       ->sessions();
 */
class Builder
{
    /** @var list<Clause> */
    private array $clauses = [];

    private ?string $period = null;

    private ?string $dateFrom = null;

    private ?string $dateTo = null;

    public function __construct(private readonly HttpClient $http) {}

    // ======================================================================
    // Period scoping
    // ======================================================================

    /** Named period: last_7, last_30, last_90, today, yesterday, … */
    public function period(string $period): self
    {
        $this->period = $period;
        $this->dateFrom = null;
        $this->dateTo = null;

        return $this;
    }

    /** Explicit date range (Y-m-d). Sets period to 'custom'. */
    public function between(string $from, string $to): self
    {
        $this->period = 'custom';
        $this->dateFrom = $from;
        $this->dateTo = $to;

        return $this;
    }

    // ======================================================================
    // Where (equality, single or multi-value via array)
    // ======================================================================

    public function where(string $field, mixed $value): self
    {
        return $this->push($field, 'equals', $value);
    }

    public function orWhere(string $field, mixed $value): self
    {
        return $this->push($field, 'equals', $value, or: true);
    }

    public function whereNot(string $field, mixed $value): self
    {
        return $this->push($field, 'equals', $value, not: true);
    }

    public function orWhereNot(string $field, mixed $value): self
    {
        return $this->push($field, 'equals', $value, or: true, not: true);
    }

    // ======================================================================
    // Keyword ops
    // ======================================================================

    public function whereStartsWith(string $field, string $value): self
    {
        return $this->push($field, 'starts_with', $value);
    }

    public function whereContains(string $field, string $value): self
    {
        return $this->push($field, 'contains', $value);
    }

    public function whereRegex(string $field, string $pattern): self
    {
        return $this->push($field, 'regex', $pattern);
    }

    public function whereExists(string $field): self
    {
        return $this->push($field, 'exists', null);
    }

    public function whereNotExists(string $field): self
    {
        return $this->push($field, 'exists', null, not: true);
    }

    // ======================================================================
    // Numeric ops
    // ======================================================================

    public function whereGt(string $field, int|float $value): self
    {
        return $this->push($field, 'gt', $value);
    }

    public function whereGte(string $field, int|float $value): self
    {
        return $this->push($field, 'gte', $value);
    }

    public function whereLt(string $field, int|float $value): self
    {
        return $this->push($field, 'lt', $value);
    }

    public function whereLte(string $field, int|float $value): self
    {
        return $this->push($field, 'lte', $value);
    }

    public function whereBetween(string $field, int|float $min, int|float $max): self
    {
        return $this->push($field, 'between', [$min, $max]);
    }

    // ======================================================================
    // Raw clause escape hatch (advanced)
    // ======================================================================

    /**
     * Append a clause already in the URL-DSL string form. Useful for nested
     * subfields (`tags_key:fbclid`) or anything outside the fluent surface.
     */
    public function raw(string ...$dslStrings): self
    {
        foreach ($dslStrings as $s) {
            $this->clauses[] = new Clause(field: '__raw', op: 'raw', value: $s);
        }

        return $this;
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

    private function push(string $field, string $op, mixed $value, bool $or = false, bool $not = false): self
    {
        $this->clauses[] = new Clause($field, $op, $value, $or, $not);

        return $this;
    }

    /**
     * Compose the JSON payload posted to the V2 API. The `query` array carries
     * serialized DSL strings; period + dates wrap it.
     *
     * @internal exposed for tests
     */
    public function buildPayload(array $extra = []): array
    {
        $payload = [
            'period' => $this->period ?? 'last_7',
            'query' => array_map(
                fn (Clause $c) => $c->op === 'raw' ? (string) $c->value : ClauseSerializer::serialize($c),
                $this->clauses,
            ),
        ];
        if ($this->dateFrom && $this->dateTo) {
            $payload['date_from'] = $this->dateFrom;
            $payload['date_to'] = $this->dateTo;
        }

        return [...$payload, ...$extra];
    }
}
