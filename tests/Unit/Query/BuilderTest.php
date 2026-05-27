<?php

namespace SnipForm\Tests\Unit\Query;

use PHPUnit\Framework\TestCase;
use SnipForm\Exceptions\InvalidPeriodException;
use SnipForm\Http\HttpClient;
use SnipForm\Query\Builder;
use SnipForm\Query\Period;

/**
 * BuilderTest covers the structured-clause + typed-period wire format.
 * We never call a terminal so no HTTP fires — buildPayload() is the unit
 * under test.
 */
class BuilderTest extends TestCase
{
    private function builder(): Builder
    {
        return new Builder(new HttpClient('test-token', 'https://example.test'));
    }

    // ----------------------------------------------------------------------
    // Period
    // ----------------------------------------------------------------------

    public function test_empty_builder_defaults_to_last_7(): void
    {
        $payload = $this->builder()->buildPayload();
        $this->assertSame('last_7', $payload['period']);
        $this->assertSame([], $payload['clauses']);
    }

    public function test_typed_period_shorthand_methods(): void
    {
        $cases = [
            'today' => $this->builder()->today(),
            'yesterday' => $this->builder()->yesterday(),
            'last_7' => $this->builder()->last7Days(),
            'last_28' => $this->builder()->last28Days(),
            'month_to_date' => $this->builder()->monthToDate(),
            'year_to_date' => $this->builder()->yearToDate(),
            'last_12_months' => $this->builder()->last12Months(),
        ];

        foreach ($cases as $expected => $builder) {
            $this->assertSame($expected, $builder->buildPayload()['period']);
        }
    }

    public function test_period_accepts_enum_case(): void
    {
        $payload = $this->builder()->period(Period::LAST_28)->buildPayload();
        $this->assertSame('last_28', $payload['period']);
    }

    public function test_period_accepts_string_value(): void
    {
        $payload = $this->builder()->period('last_28')->buildPayload();
        $this->assertSame('last_28', $payload['period']);
    }

    public function test_period_with_invalid_string_throws(): void
    {
        $this->expectException(InvalidPeriodException::class);
        $this->expectExceptionMessage('Invalid period `last_42`');
        $this->builder()->period('last_42');
    }

    public function test_between_sets_custom_dates(): void
    {
        $payload = $this->builder()->between('2026-01-01', '2026-01-31')->buildPayload();
        $this->assertSame('custom', $payload['period']);
        $this->assertSame('2026-01-01', $payload['date_from']);
        $this->assertSame('2026-01-31', $payload['date_to']);
    }

    // ----------------------------------------------------------------------
    // Clauses
    // ----------------------------------------------------------------------

    public function test_where_emits_structured_clause(): void
    {
        $payload = $this->builder()->where('country', 'US')->buildPayload();
        $this->assertSame([
            ['id' => 'country', 'op' => 'equals', 'value' => 'US'],
        ], $payload['clauses']);
    }

    public function test_array_value_passes_through_unchanged(): void
    {
        $payload = $this->builder()->where('device', ['mobile', 'tablet'])->buildPayload();
        $this->assertSame([
            ['id' => 'device', 'op' => 'equals', 'value' => ['mobile', 'tablet']],
        ], $payload['clauses']);
    }

    public function test_or_clause_carries_where_or(): void
    {
        $payload = $this->builder()
            ->where('country', 'US')
            ->orWhere('country', 'CA')
            ->buildPayload();

        $this->assertSame([
            ['id' => 'country', 'op' => 'equals', 'value' => 'US'],
            ['id' => 'country', 'op' => 'equals', 'value' => 'CA', 'where' => 'or'],
        ], $payload['clauses']);
    }

    public function test_not_clause_carries_not_true(): void
    {
        $payload = $this->builder()
            ->whereNot('device', 'mobile')
            ->orWhereNot('source', 'direct')
            ->buildPayload();

        $this->assertSame([
            ['id' => 'device', 'op' => 'equals', 'value' => 'mobile', 'not' => true],
            ['id' => 'source', 'op' => 'equals', 'value' => 'direct', 'where' => 'or', 'not' => true],
        ], $payload['clauses']);
    }

    public function test_keyword_ops(): void
    {
        $payload = $this->builder()
            ->whereStartsWith('entry_path', '/blog')
            ->whereContains('entry_title', 'welcome')
            ->whereRegex('source', 'goog.*')
            ->buildPayload();

        $this->assertSame([
            ['id' => 'entry_path', 'op' => 'starts_with', 'value' => '/blog'],
            ['id' => 'entry_title', 'op' => 'contains', 'value' => 'welcome'],
            ['id' => 'source', 'op' => 'regex', 'value' => 'goog.*'],
        ], $payload['clauses']);
    }

    public function test_numeric_ops(): void
    {
        $payload = $this->builder()
            ->whereGt('views', 5)
            ->whereGte('time_on_site', 60)
            ->whereLt('views', 100)
            ->whereLte('avg_max_scroll', 50)
            ->whereBetween('views', 3, 10)
            ->buildPayload();

        $this->assertSame([
            ['id' => 'views', 'op' => 'gt', 'value' => 5],
            ['id' => 'time_on_site', 'op' => 'gte', 'value' => 60],
            ['id' => 'views', 'op' => 'lt', 'value' => 100],
            ['id' => 'avg_max_scroll', 'op' => 'lte', 'value' => 50],
            ['id' => 'views', 'op' => 'between', 'value' => [3, 10]],
        ], $payload['clauses']);
    }

    public function test_exists_and_not_exists(): void
    {
        $payload = $this->builder()
            ->whereExists('bounced')
            ->whereNotExists('utm_source')
            ->buildPayload();

        $this->assertSame([
            ['id' => 'bounced', 'op' => 'exists', 'value' => null],
            ['id' => 'utm_source', 'op' => 'exists', 'value' => null, 'not' => true],
        ], $payload['clauses']);
    }
}
