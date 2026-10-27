<?php

namespace SnipForm\Tests\Unit\Resources;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use SnipForm\Exceptions\MissingSessionIdException;
use SnipForm\Http\HttpClient;
use SnipForm\Resources\Session;
use Symfony\Component\HttpFoundation\Request;

/**
 * Verify the Session resource extracts session_id correctly from a Request
 * (header → form → query) and composes outbound payloads. No HTTP fires —
 * we invoke the public / private methods directly.
 */
class SessionTest extends TestCase
{
    private function session(): Session
    {
        return new Session(new HttpClient('test-token', 'https://example.test'));
    }

    private function call(Session $session, string $method, mixed ...$args): mixed
    {
        $ref = new ReflectionClass($session);
        $m = $ref->getMethod($method);

        return $m->invoke($session, ...$args);
    }

    // ----------------------------------------------------------------------
    // fromRequest() — session_id bridge from signals.js
    // ----------------------------------------------------------------------

    public function test_from_request_reads_header_first(): void
    {
        $request = Request::create('/path', 'POST', parameters: [
            'snip_session_id' => 'from-form',
        ]);
        $request->headers->set('X-SnipForm-Session-Id', 'from-header');

        $this->assertSame('from-header', $this->session()->fromRequest($request));
    }

    public function test_from_request_falls_back_to_form_field(): void
    {
        $request = Request::create('/path', 'POST', parameters: [
            'snip_session_id' => 'from-form',
        ]);

        $this->assertSame('from-form', $this->session()->fromRequest($request));
    }

    public function test_from_request_falls_back_to_query_param(): void
    {
        $request = Request::create('/path?snip_session_id=from-query');

        $this->assertSame('from-query', $this->session()->fromRequest($request));
    }

    public function test_from_request_returns_null_when_nothing_present(): void
    {
        $request = Request::create('/path');
        $this->assertNull($this->session()->fromRequest($request));
    }

    // ----------------------------------------------------------------------
    // payloadWithSession() — composes the outbound payload from Request/array
    // ----------------------------------------------------------------------

    public function test_payload_with_session_uses_header_session_id(): void
    {
        $request = Request::create('/path', 'POST');
        $request->headers->set('X-SnipForm-Session-Id', 'abc');

        $payload = $this->call($this->session(), 'payloadWithSession', $request, ['name' => 'purchase']);

        $this->assertSame(['session_id' => 'abc', 'name' => 'purchase'], $payload);
    }

    public function test_payload_with_session_uses_array_session_id(): void
    {
        $payload = $this->call($this->session(), 'payloadWithSession', [
            'session_id' => 'abc',
            'name' => 'purchase',
        ], null);

        $this->assertSame(['session_id' => 'abc', 'name' => 'purchase'], $payload);
    }

    public function test_payload_with_session_throws_when_request_has_no_session_id(): void
    {
        $this->expectException(MissingSessionIdException::class);

        $request = Request::create('/path', 'POST');
        $this->call($this->session(), 'payloadWithSession', $request, ['name' => 'purchase']);
    }

    public function test_payload_with_session_throws_when_array_missing_session_id(): void
    {
        $this->expectException(MissingSessionIdException::class);

        $this->call($this->session(), 'payloadWithSession', ['name' => 'purchase'], null);
    }

    public function test_payload_with_session_throws_when_request_passed_without_attrs(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $request = Request::create('/path', 'POST');
        $request->headers->set('X-SnipForm-Session-Id', 'abc');

        $this->call($this->session(), 'payloadWithSession', $request, null);
    }
}
