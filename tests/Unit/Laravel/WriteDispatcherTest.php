<?php

namespace SnipForm\Tests\Unit\Laravel;

use Illuminate\Contracts\Bus\Dispatcher as BusDispatcher;
use Illuminate\Contracts\Foundation\Application;
use PHPUnit\Framework\TestCase;
use SnipForm\Client;
use SnipForm\Laravel\Dispatch\Write;
use SnipForm\Laravel\Dispatch\WriteDispatcher;
use SnipForm\Laravel\Jobs\SignalWriteJob;
use SnipForm\Tests\Support\FakeApi;

/**
 * The dispatcher only needs the Application contract, so a mock stands in
 * for Laravel: `make(Client::class)` hands back a FakeApi client,
 * `terminating()` records the deferred callback, and a mock bus records
 * what was queued.
 */
class WriteDispatcherTest extends TestCase
{
    public function test_legacy_booleans_and_unknown_values_normalize(): void
    {
        $this->assertSame('after_response', WriteDispatcher::normalize(true));
        $this->assertSame('sync', WriteDispatcher::normalize(false));
        $this->assertSame('sync', WriteDispatcher::normalize(null));
        $this->assertSame('queue', WriteDispatcher::normalize('queue'));
        $this->assertSame('sync', WriteDispatcher::normalize('yolo'));
    }

    public function test_sync_mode_runs_the_write_immediately(): void
    {
        $api = FakeApi::make()->willReturn(['event' => ['id' => 'e1']]);
        $app = $this->createMock(Application::class);
        $app->method('make')->with(Client::class)->willReturn($api->client);

        (new WriteDispatcher($app))->dispatch('sync', Write::EVENT, ['session_id' => 's1', 'name' => 'purchase']);

        $this->assertSame('/v2/property/session/event', $api->lastRequest()->getUri()->getPath());
        $this->assertSame(['session_id' => 's1', 'name' => 'purchase'], $api->lastJson());
    }

    public function test_after_response_mode_defers_until_terminating(): void
    {
        $api = FakeApi::make()->willReturn(['session' => ['id' => 's1']]);
        $deferred = null;
        $app = $this->createMock(Application::class);
        $app->method('make')->with(Client::class)->willReturn($api->client);
        $app->method('terminating')->willReturnCallback(function (callable $callback) use (&$deferred, $app) {
            $deferred = $callback;

            return $app;
        });

        (new WriteDispatcher($app))->dispatch('after_response', Write::ACQUISITION, ['session_id' => 's1', 'value' => 9900]);

        $this->assertFalse($api->hasSent());
        $this->assertNotNull($deferred);

        $deferred();

        $this->assertSame('/v2/property/session/acquisition', $api->lastRequest()->getUri()->getPath());
    }

    public function test_queue_mode_pushes_a_job_onto_the_bus_with_the_queue_name(): void
    {
        $queued = null;
        $bus = $this->createMock(BusDispatcher::class);
        $bus->method('dispatch')->willReturnCallback(function (object $job) use (&$queued) {
            $queued = $job;

            return null;
        });
        $app = $this->createMock(Application::class);
        $app->method('make')->with(BusDispatcher::class)->willReturn($bus);

        (new WriteDispatcher($app))->dispatch('queue', Write::IDENTIFY, ['email' => 'jane@acme.com'], 'snipform');

        $this->assertInstanceOf(SignalWriteJob::class, $queued);
        $this->assertSame(Write::IDENTIFY, $queued->write);
        $this->assertSame(['email' => 'jane@acme.com'], $queued->payload);
        $this->assertSame('snipform', $queued->queue);
    }

    public function test_a_queued_job_runs_the_right_sdk_call_when_handled(): void
    {
        $api = FakeApi::make()->willReturn(['contact' => ['id' => 'c1']]);

        (new SignalWriteJob(Write::IDENTIFY, ['email' => 'jane@acme.com']))->handle($api->client);

        $this->assertSame('/v2/property/contacts/identify', $api->lastRequest()->getUri()->getPath());
    }
}
