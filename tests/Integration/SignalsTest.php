<?php

namespace SnipForm\Tests\Integration;

use SnipForm\Resources\MetricsResult;

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
        $this->expectException(\SnipForm\Exceptions\InvalidPeriodException::class);
        $this->client->signals()->period('last_42')->metrics();
    }

    public function test_property_overview(): void
    {
        $property = $this->client->properties()->overview();

        $this->assertNotEmpty($property->id);
        $this->assertNotEmpty($property->name);
    }
}
