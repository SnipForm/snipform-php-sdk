<?php

namespace SnipForm\Tests\Integration;

use SnipForm\Data\GraphResult;
use SnipForm\Data\MetricsResult;
use SnipForm\Exceptions\InvalidPeriodException;

/**
 * Live signals queries against a local SnipForm deployment. Skipped when
 * no test token is set.
 */
class SignalsTest extends IntegrationTestCase
{
    public function test_metrics_for_last_28_days(): void
    {
        $metrics = $this->client->signals()->last28Days()->metrics();

        $this->assertInstanceOf(MetricsResult::class, $metrics);
        $this->assertIsInt($metrics->sessions);
        $this->assertGreaterThanOrEqual(0, $metrics->sessions);
    }

    public function test_metrics_with_clause(): void
    {
        $metrics = $this->client->signals()
            ->last28Days()
            ->where('device', 'mobile')
            ->metrics();

        $this->assertInstanceOf(MetricsResult::class, $metrics);
    }

    public function test_invalid_period_throws_before_http(): void
    {
        $this->expectException(InvalidPeriodException::class);
        $this->client->signals()->period('last_42')->metrics();
    }

    public function test_property_overview(): void
    {
        $property = $this->client->properties()->overview();

        $this->assertNotEmpty($property->id);
        $this->assertNotEmpty($property->name);
    }

    public function test_graph_for_last_7_days(): void
    {
        $graph = $this->client->signals()->last7Days()->graph('sessions');

        $this->assertInstanceOf(GraphResult::class, $graph);
        $this->assertSame('sessions', $graph->metric);
        $this->assertNotEmpty($graph->series);
    }

    public function test_graph_with_compare_returns_a_previous_series(): void
    {
        $graph = $this->client->signals()->last7Days()->graph('views', compare: true);

        $this->assertInstanceOf(GraphResult::class, $graph);
        $this->assertNotEmpty($graph->previousSeries);
    }

    public function test_live_window(): void
    {
        $graph = $this->client->signals()->live();

        $this->assertInstanceOf(GraphResult::class, $graph);
        $this->assertSame('sessions', $graph->metric);
    }
}
