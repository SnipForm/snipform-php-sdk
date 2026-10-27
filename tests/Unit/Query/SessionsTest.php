<?php

namespace SnipForm\Tests\Unit\Query;

use PHPUnit\Framework\TestCase;
use SnipForm\Tests\Support\FakeApi;

/**
 * The sessions endpoint sizes its pages by `limit` - the one paginated V2
 * endpoint that does not use `per_page`. Sent under the wrong key, the
 * server ignores it and every page holds its default 20.
 */
class SessionsTest extends TestCase
{
    private function pageOf(int $limit, int $page, int $lastPage): array
    {
        return [
            'data' => [['id' => 'sess_'.$page]],
            'current_page' => $page,
            'last_page' => $lastPage,
            'per_page' => $limit,
            'total' => $limit * $lastPage,
        ];
    }

    public function test_the_page_size_is_sent_as_limit(): void
    {
        $api = FakeApi::make()->willReturn($this->pageOf(10, 2, 3));

        $api->client->signals()->last7Days()->where('country', 'US')->sessions(10)->page(2);

        $this->assertSame('/v2/property/signals/sessions', $api->lastRequest()->getUri()->getPath());
        $this->assertSame([
            'period' => 'last_7',
            'clauses' => [['id' => 'country', 'op' => 'equals', 'value' => 'US']],
            'limit' => 10,
            'page' => 2,
        ], $api->lastJson());
    }

    public function test_the_default_page_size_is_fifty(): void
    {
        $api = FakeApi::make()->willReturn($this->pageOf(50, 1, 1));

        $api->client->signals()->sessions()->first();

        $this->assertSame(50, $api->lastJson()['limit']);
        $this->assertArrayNotHasKey('per_page', $api->lastJson());
    }
}
