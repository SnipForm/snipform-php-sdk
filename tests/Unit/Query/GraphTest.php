<?php

namespace SnipForm\Tests\Unit\Query;

use PHPUnit\Framework\TestCase;
use SnipForm\Data\GraphPoint;
use SnipForm\Data\GraphResult;
use SnipForm\Tests\Support\FakeApi;

class GraphTest extends TestCase
{
    private function graphPayload(): array
    {
        return ['graph' => [
            'metric' => 'sessions',
            'metricLabel' => 'Sessions',
            'series' => [['label' => 'Mon', 'value' => 12], ['label' => 'Tue', 'value' => 15]],
            'previousSeries' => [['label' => 'Mon', 'value' => 9]],
            'projectedSeries' => [],
        ], 'meta' => ['showing' => 'Last 7 days', 'took_ms' => 12.5]];
    }

    public function test_graph_posts_period_clauses_and_graph_options(): void
    {
        $api = FakeApi::make()->willReturn($this->graphPayload());

        $graph = $api->client->signals()->last7Days()->where('country', 'US')->graph('sessions', 'day', true);

        $this->assertSame('/v2/property/signals/analytics/graph', $api->lastRequest()->getUri()->getPath());
        $this->assertSame([
            'period' => 'last_7',
            'clauses' => [['id' => 'country', 'op' => 'equals', 'value' => 'US']],
            'metric' => 'sessions',
            'interval' => 'day',
            'compare' => true,
        ], $api->lastJson());

        $this->assertInstanceOf(GraphResult::class, $graph);
        $this->assertSame('Sessions', $graph->metricLabel);
        $this->assertCount(2, $graph->series);
        $this->assertInstanceOf(GraphPoint::class, $graph->series[1]);
        $this->assertSame(15.0, $graph->series[1]->value);
        $this->assertCount(1, $graph->previousSeries);
        $this->assertSame('Last 7 days', $graph->showing);
    }

    public function test_graph_omits_interval_when_not_given(): void
    {
        $api = FakeApi::make()->willReturn($this->graphPayload());

        $api->client->signals()->last28Days()->graph();

        $this->assertArrayNotHasKey('interval', $api->lastJson());
        $this->assertSame('sessions', $api->lastJson()['metric']);
    }

    public function test_live_ignores_the_period_but_keeps_clauses(): void
    {
        $api = FakeApi::make()->willReturn($this->graphPayload());

        $api->client->signals()->last28Days()->where('device', 'mobile')->live('views');

        $this->assertSame('/v2/property/signals/analytics/live', $api->lastRequest()->getUri()->getPath());
        $this->assertSame([
            'metric' => 'views',
            'clauses' => [['id' => 'device', 'op' => 'equals', 'value' => 'mobile']],
        ], $api->lastJson());
    }

    public function test_as_raw_returns_the_payload_array(): void
    {
        $api = FakeApi::make()->willReturn($this->graphPayload());

        $raw = $api->client->signals()->last7Days()->asRaw()->graph();

        $this->assertIsArray($raw);
        $this->assertSame('Sessions', $raw['graph']['metricLabel']);
    }
}
