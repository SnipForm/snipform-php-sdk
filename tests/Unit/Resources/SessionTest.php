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
 * Verify the Session resource extracts visitor fingerprint values correctly
 * from a Symfony/Laravel Request and validates plain-array inputs. No HTTP
 * fires — we invoke the private extractor / validator directly.
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

    public function test_extracts_ip_ua_lang_from_symfony_request(): void
    {
        $request = Request::create('/path', 'POST', server: [
            'REMOTE_ADDR' => '203.0.113.42',
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Test)',
            'HTTP_ACCEPT_LANGUAGE' => 'en-US,en;q=0.9',
        ]);

        $payload = $this->call($this->session(), 'extractFromRequest', $request);

        $this->assertSame('203.0.113.42', $payload['ip']);
        $this->assertSame('Mozilla/5.0 (Test)', $payload['user_agent']);
        $this->assertSame('en-US,en;q=0.9', $payload['lang']);
    }

    public function test_payload_keys_always_present_for_request_input(): void
    {
        // Symfony's Request::create() sets some HTTP defaults of its own
        // (e.g. HTTP_USER_AGENT = 'Symfony') — the test that matters is the
        // SDK always emits ip/user_agent/lang keys, never nulls.
        $request = Request::create('/path', 'POST', server: ['REMOTE_ADDR' => '203.0.113.42']);

        $payload = $this->call($this->session(), 'extractFromRequest', $request);

        $this->assertArrayHasKey('ip', $payload);
        $this->assertArrayHasKey('user_agent', $payload);
        $this->assertArrayHasKey('lang', $payload);
        $this->assertSame('203.0.113.42', $payload['ip']);
        $this->assertIsString($payload['user_agent']);
        $this->assertIsString($payload['lang']);
    }

    public function test_validates_array_input_passes_through(): void
    {
        $payload = $this->call($this->session(), 'validateArrayInput', [
            'ip' => '1.2.3.4',
            'user_agent' => 'curl/8.0',
            'lang' => 'fr',
        ]);

        $this->assertSame([
            'ip' => '1.2.3.4',
            'user_agent' => 'curl/8.0',
            'lang' => 'fr',
        ], $payload);
    }

    public function test_validates_array_input_defaults_lang(): void
    {
        $payload = $this->call($this->session(), 'validateArrayInput', [
            'ip' => '1.2.3.4',
            'user_agent' => 'curl/8.0',
        ]);

        $this->assertSame('', $payload['lang']);
    }

    public function test_validates_array_input_throws_when_ip_missing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('resolve() requires `ip` and `user_agent`');

        $this->call($this->session(), 'validateArrayInput', [
            'user_agent' => 'curl/8.0',
        ]);
    }

    public function test_validates_array_input_throws_when_ua_missing(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->call($this->session(), 'validateArrayInput', [
            'ip' => '1.2.3.4',
        ]);
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
