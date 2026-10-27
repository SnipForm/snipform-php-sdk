<?php

namespace SnipForm\Tests\Unit\Laravel;

use Illuminate\Contracts\Foundation\Application;
use PHPUnit\Framework\TestCase;
use SnipForm\Client;
use SnipForm\Laravel\Dispatch\WriteDispatcher;
use SnipForm\Laravel\IdentifyManager;
use SnipForm\Laravel\SnipFormManager;
use SnipForm\Resources\Contacts;
use SnipForm\Resources\Session;
use SnipForm\Resources\Signals;
use SnipForm\Tests\Support\FakeApi;

/**
 * The facade root, exercised without a Laravel app: a mock container with no
 * bound request (so nothing resolves a session id) plus a sync dispatcher,
 * which makes every write observable on the FakeApi.
 */
class SnipFormManagerTest extends TestCase
{
    private FakeApi $api;

    private function manager(array $config = []): SnipFormManager
    {
        $this->api = FakeApi::make();

        $app = $this->createMock(Application::class);
        $app->method('bound')->with('request')->willReturn(false);
        $app->method('make')->with(Client::class)->willReturn($this->api->client);

        $dispatcher = new WriteDispatcher($app);
        $identify = new IdentifyManager($this->api->client, $app, $dispatcher, ['queue' => 'sync']);

        return new SnipFormManager($this->api->client, $identify, $dispatcher, [
            'events' => ['dispatch' => 'sync'],
            ...$config,
        ]);
    }

    public function test_event_without_a_resolvable_session_is_skipped_not_thrown(): void
    {
        $manager = $this->manager();

        $this->assertFalse($manager->event('purchase', 9900));
        $this->assertFalse($this->api->hasSent());
    }

    public function test_event_for_a_known_session_posts_name_value_and_meta(): void
    {
        $manager = $this->manager();
        $this->api->willReturn(['event' => ['id' => 'e1']]);

        $this->assertTrue($manager->eventFor('sess_1', 'purchase', 9900, ['order' => 'X-1']));

        $this->assertSame('/v2/property/session/event', $this->api->lastRequest()->getUri()->getPath());
        $this->assertSame([
            'session_id' => 'sess_1',
            'name' => 'purchase',
            'value' => 9900,
            'meta' => ['order' => 'X-1'],
        ], $this->api->lastJson());
    }

    public function test_event_for_omits_empty_value_and_meta(): void
    {
        $manager = $this->manager();
        $this->api->willReturn(['event' => ['id' => 'e1']]);

        $manager->eventFor('sess_1', 'signup');

        $this->assertSame(['session_id' => 'sess_1', 'name' => 'signup'], $this->api->lastJson());
    }

    public function test_revenue_is_an_acquisition_value_in_cents(): void
    {
        $manager = $this->manager();
        $this->api->willReturn(['session' => ['id' => 'sess_1']]);

        $this->assertTrue($manager->acquisitionFor('sess_1', ['value' => 9900, 'currency_code' => 'USD']));
        $this->assertSame('/v2/property/session/acquisition', $this->api->lastRequest()->getUri()->getPath());
        $this->assertSame(['session_id' => 'sess_1', 'value' => 9900, 'currency_code' => 'USD'], $this->api->lastJson());

        $this->assertFalse($manager->revenue(9900, 'USD'));   // no resolvable session on this mock container
    }

    public function test_identify_delegates_to_the_identify_pipeline(): void
    {
        $manager = $this->manager();
        $this->api->willReturn(['contact' => ['id' => 'c1']]);

        $this->assertTrue($manager->identify(['email' => 'jane@acme.com']));
        $this->assertSame('/v2/property/contacts/identify', $this->api->lastRequest()->getUri()->getPath());
    }

    public function test_resources_are_reachable_from_the_facade_root(): void
    {
        $manager = $this->manager();

        $this->assertSame($this->api->client, $manager->client());
        $this->assertInstanceOf(Signals::class, $manager->signals());
        $this->assertInstanceOf(Contacts::class, $manager->contacts());
        $this->assertInstanceOf(Session::class, $manager->session());
    }
}
