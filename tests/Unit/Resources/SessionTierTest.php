<?php

namespace SnipForm\Tests\Unit\Resources;

use PHPUnit\Framework\TestCase;
use SnipForm\Data\Event;
use SnipForm\Resources\Session;
use SnipForm\Tests\Support\FakeApi;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session as SymfonySession;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * The server-session stash is tier 1 of the resolution chain - what the
 * hydrate loader fills. Before this, events ignored it and the flagship
 * Laravel flow dead-ended with MissingSessionIdException.
 */
class SessionTierTest extends TestCase
{
    private function requestWithStash(?string $sid): Request
    {
        $request = Request::create('/checkout', 'POST');
        $session = new SymfonySession(new MockArraySessionStorage);
        if ($sid !== null) {
            $session->set(Session::SESSION_STASH_KEY, $sid);
        }
        $request->setSession($session);

        return $request;
    }

    public function test_the_stashed_session_id_wins_over_every_transport(): void
    {
        $request = $this->requestWithStash('from-stash');
        $request->headers->set('X-SnipForm-Session-Id', 'from-header');

        $resource = FakeApi::make()->client->session();

        $this->assertSame('from-stash', $resource->fromRequest($request));
    }

    public function test_an_attached_but_empty_session_falls_through_to_the_header(): void
    {
        $request = $this->requestWithStash(null);
        $request->headers->set('X-SnipForm-Session-Id', 'from-header');

        $this->assertSame('from-header', FakeApi::make()->client->session()->fromRequest($request));
    }

    public function test_stashed_session_id_helper_is_null_without_a_session(): void
    {
        $this->assertNull(Session::stashedSessionId(Request::create('/')));
    }

    public function test_event_from_a_request_posts_the_stashed_id(): void
    {
        $api = FakeApi::make()->willReturn(['event' => [
            'id' => 'ev1', 'session_id' => 'from-stash', 'type' => 'event', 'name' => 'purchase', 'value' => '9900', 'meta' => [], 'created_ts' => 1700000000,
        ]]);

        $event = $api->client->session()->event($this->requestWithStash('from-stash'), ['name' => 'purchase', 'value' => 9900]);

        $this->assertInstanceOf(Event::class, $event);
        $this->assertSame('from-stash', $event->sessionId);
        $this->assertSame('/v2/property/session/event', $api->lastRequest()->getUri()->getPath());
        $this->assertSame(['session_id' => 'from-stash', 'name' => 'purchase', 'value' => 9900], $api->lastJson());
    }
}
