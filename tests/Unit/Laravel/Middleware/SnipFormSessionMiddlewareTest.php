<?php

namespace SnipForm\Tests\Unit\Laravel\Middleware;

use PHPUnit\Framework\TestCase;
use SnipForm\Laravel\Middleware\SnipFormSessionMiddleware;
use Symfony\Component\HttpFoundation\Request;

/**
 * The middleware accepts a Symfony Request (Laravel's Request extends it),
 * so we can exercise it without standing up a Laravel app. Each test
 * builds a Request, runs it through `handle()`, and asserts the request
 * attribute landed where we expect.
 */
class SnipFormSessionMiddlewareTest extends TestCase
{
    public function test_picks_session_id_from_header(): void
    {
        $request = Request::create('/');
        $request->headers->set('X-SnipForm-Session-Id', 'sess_abc123');

        $passed = null;
        (new SnipFormSessionMiddleware)->handle($request, function ($r) use (&$passed) {
            $passed = $r;

            return 'OK';
        });

        $this->assertSame('sess_abc123', $passed->attributes->get(SnipFormSessionMiddleware::ATTRIBUTE));
    }

    public function test_picks_session_id_from_form_field(): void
    {
        $request = Request::create('/checkout', 'POST', ['snip_session_id' => 'sess_form']);

        (new SnipFormSessionMiddleware)->handle($request, fn ($r) => $r);

        $this->assertSame('sess_form', $request->attributes->get(SnipFormSessionMiddleware::ATTRIBUTE));
    }

    public function test_picks_session_id_from_query_string(): void
    {
        $request = Request::create('/?snip_session_id=sess_q');

        (new SnipFormSessionMiddleware)->handle($request, fn ($r) => $r);

        $this->assertSame('sess_q', $request->attributes->get(SnipFormSessionMiddleware::ATTRIBUTE));
    }

    public function test_header_wins_over_form_and_query(): void
    {
        $request = Request::create('/?snip_session_id=sess_q', 'POST', ['snip_session_id' => 'sess_form']);
        $request->headers->set('X-SnipForm-Session-Id', 'sess_header');

        (new SnipFormSessionMiddleware)->handle($request, fn ($r) => $r);

        $this->assertSame('sess_header', $request->attributes->get(SnipFormSessionMiddleware::ATTRIBUTE));
    }

    public function test_no_session_id_leaves_attribute_unset(): void
    {
        $request = Request::create('/');

        (new SnipFormSessionMiddleware)->handle($request, fn ($r) => $r);

        $this->assertFalse($request->attributes->has(SnipFormSessionMiddleware::ATTRIBUTE));
    }

    public function test_empty_string_is_treated_as_missing(): void
    {
        $request = Request::create('/');
        $request->headers->set('X-SnipForm-Session-Id', '');

        (new SnipFormSessionMiddleware)->handle($request, fn ($r) => $r);

        $this->assertFalse($request->attributes->has(SnipFormSessionMiddleware::ATTRIBUTE));
    }

    public function test_handle_returns_whatever_next_returns(): void
    {
        $request = Request::create('/');
        $result = (new SnipFormSessionMiddleware)->handle($request, fn () => 'PAYLOAD');

        $this->assertSame('PAYLOAD', $result);
    }
}
