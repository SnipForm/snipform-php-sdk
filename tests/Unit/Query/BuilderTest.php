<?php

namespace SnipForm\Tests\Unit\Query;

use PHPUnit\Framework\TestCase;
use SnipForm\Http\HttpClient;
use SnipForm\Query\Builder;

class BuilderTest extends TestCase
{
    private function builder(): Builder
    {
        // Build with a real HttpClient — we only inspect buildPayload(), no requests fire.
        return new Builder(new HttpClient('test-token', 'https://example.test'));
    }

    public function test_empty_builder_defaults_to_last_7(): void
    {
        $payload = $this->builder()->buildPayload();
        $this->assertSame('last_7', $payload['period']);
        $this->assertSame([], $payload['query']);
    }

    public function test_period_sets_named_period(): void
    {
        $payload = $this->builder()->period('last_30')->buildPayload();
        $this->assertSame('last_30', $payload['period']);
        $this->assertArrayNotHasKey('date_from', $payload);
    }

    public function test_between_sets_custom_dates(): void
    {
        $payload = $this->builder()->between('2026-01-01', '2026-01-31')->buildPayload();
        $this->assertSame('custom', $payload['period']);
        $this->assertSame('2026-01-01', $payload['date_from']);
        $this->assertSame('2026-01-31', $payload['date_to']);
    }

    public function test_where_serializes_equality(): void
    {
        $payload = $this->builder()->where('country', 'US')->buildPayload();
        $this->assertSame(['country:US'], $payload['query']);
    }

    public function test_array_value_becomes_multi(): void
    {
        $payload = $this->builder()->where('device', ['mobile', 'tablet'])->buildPayload();
        $this->assertSame(['device:mobile,tablet'], $payload['query']);
    }

    public function test_or_and_not_prefixes(): void
    {
        $payload = $this->builder()
            ->where('country', 'US')
            ->orWhere('country', 'CA')
            ->whereNot('device', 'mobile')
            ->orWhereNot('source', 'direct')
            ->buildPayload();

        $this->assertSame([
            'country:US',
            'or_country:CA',
            'not_device:mobile',
            'or_not_source:direct',
        ], $payload['query']);
    }

    public function test_keyword_ops(): void
    {
        $payload = $this->builder()
            ->whereStartsWith('entry_path', '/blog')
            ->whereContains('entry_title', 'welcome')
            ->whereRegex('source', 'goog.*')
            ->buildPayload();

        $this->assertSame([
            'entry_path:/blog*',
            'entry_title:*welcome*',
            'source:/goog.*/',
        ], $payload['query']);
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
            'views:>5',
            'time_on_site:>=60',
            'views:<100',
            'avg_max_scroll:<=50',
            'views:[3 TO 10]',
        ], $payload['query']);
    }

    public function test_exists_and_not_exists(): void
    {
        $payload = $this->builder()
            ->whereExists('bounced')
            ->whereNotExists('utm_source')
            ->buildPayload();

        $this->assertSame(['bounced', 'not_utm_source'], $payload['query']);
    }

    public function test_raw_passes_string_through(): void
    {
        $payload = $this->builder()
            ->where('country', 'US')
            ->raw('tags_key:fbclid', 'tags_value:abc')
            ->buildPayload();

        $this->assertSame([
            'country:US',
            'tags_key:fbclid',
            'tags_value:abc',
        ], $payload['query']);
    }

    public function test_values_with_commas_get_quoted(): void
    {
        $payload = $this->builder()
            ->where('utm_campaign', ['summer,2026', 'normal'])
            ->buildPayload();

        $this->assertSame(['utm_campaign:"summer,2026",normal'], $payload['query']);
    }
}
