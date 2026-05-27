<?php

namespace SnipForm\Tests\Unit\Resources;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use SnipForm\Http\HttpClient;
use SnipForm\Resources\Clicks;

/**
 * The Clicks filter builder is fluent — verify each method appends the right
 * filter key onto the request payload. We never call ->all() here, so no HTTP
 * request fires; we just introspect the private $filters state.
 */
class ClicksTest extends TestCase
{
    private function clicks(): Clicks
    {
        return new Clicks(new HttpClient('test-token', 'https://example.test'));
    }

    private function filtersOf(Clicks $c): array
    {
        $ref = new ReflectionClass($c);
        $prop = $ref->getProperty('filters');

        return $prop->getValue($c);
    }

    public function test_for_link(): void
    {
        $c = $this->clicks()->forLink('link_123');
        $this->assertSame(['link_id' => 'link_123'], $this->filtersOf($c));
    }

    public function test_for_group(): void
    {
        $c = $this->clicks()->forGroup('group_456');
        $this->assertSame(['group_id' => 'group_456'], $this->filtersOf($c));
    }

    public function test_between_sets_from_and_to(): void
    {
        $c = $this->clicks()->between(1_700_000_000, 1_700_999_999);
        $this->assertSame([
            'from_ts' => 1_700_000_000,
            'to_ts' => 1_700_999_999,
        ], $this->filtersOf($c));
    }

    public function test_users_and_bots_filters(): void
    {
        $this->assertSame(['type' => 'user'], $this->filtersOf($this->clicks()->usersOnly()));
        $this->assertSame(['type' => 'bot'], $this->filtersOf($this->clicks()->botsOnly()));
    }

    public function test_chained_filters_compose(): void
    {
        $c = $this->clicks()
            ->forLink('link_123')
            ->between(1_700_000_000, 1_700_999_999)
            ->usersOnly()
            ->perPage(50);

        $this->assertSame([
            'link_id' => 'link_123',
            'from_ts' => 1_700_000_000,
            'to_ts' => 1_700_999_999,
            'type' => 'user',
            'per_page' => 50,
        ], $this->filtersOf($c));
    }
}
