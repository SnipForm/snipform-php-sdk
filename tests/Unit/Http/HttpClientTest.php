<?php

namespace SnipForm\Tests\Unit\Http;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request as PsrRequest;
use PHPUnit\Framework\TestCase;
use SnipForm\Exceptions\ApiException;
use SnipForm\Exceptions\AuthenticationException;
use SnipForm\Exceptions\SnipFormException;
use SnipForm\SnipForm;
use SnipForm\Tests\Support\FakeApi;

/**
 * The one place Guzzle is touched. Pins the status -> exception mapping and
 * the envelope unwrapping that every resource relies on.
 */
class HttpClientTest extends TestCase
{
    public function test_success_unwraps_the_data_envelope(): void
    {
        $api = FakeApi::make()->willReturn(['property' => ['id' => 'p1', 'name' => 'Acme']]);

        $response = $api->client->http->get('property/overview');

        $this->assertSame(200, $response->status);
        $this->assertSame('Acme', $response->data('property.name'));
        $this->assertSame(['id' => 'p1', 'name' => 'Acme'], $response->data('property'));
    }

    public function test_every_request_carries_bearer_auth_and_the_versioned_user_agent(): void
    {
        $api = FakeApi::make('tok_123')->willReturn([]);

        $api->client->http->get('property/overview');
        $request = $api->lastRequest();

        $this->assertSame('Bearer tok_123', $request->getHeaderLine('Authorization'));
        $this->assertSame('snipform-php-sdk/'.SnipForm::VERSION, $request->getHeaderLine('User-Agent'));
        $this->assertSame('/v2/property/overview', $request->getUri()->getPath());
    }

    public function test_401_and_403_become_authentication_exceptions(): void
    {
        $api = FakeApi::make()->willRespond(401, ['message' => 'Unauthenticated.']);

        $this->expectException(AuthenticationException::class);
        $api->client->http->get('property/overview');
    }

    public function test_422_becomes_an_api_exception_carrying_field_errors(): void
    {
        $api = FakeApi::make()->willRespond(422, [
            'message' => 'The given data was invalid.',
            'errors' => ['period' => ['The selected period is invalid.']],
        ]);

        try {
            $api->client->http->post('property/signals/analytics/metrics', ['period' => 'last_42']);
            $this->fail('expected ApiException');
        } catch (ApiException $e) {
            $this->assertSame(422, $e->status);
            $this->assertSame(['period' => ['The selected period is invalid.']], $e->errors);
            $this->assertStringContainsString('period: The selected period is invalid.', $e->getMessage());
        }
    }

    public function test_errors_nested_inside_the_data_envelope_are_found_too(): void
    {
        $api = FakeApi::make()->willReturn(['message' => 'session_id missing', 'errors' => []], 422);

        try {
            $api->client->http->post('property/session/event', []);
            $this->fail('expected ApiException');
        } catch (ApiException $e) {
            $this->assertSame('session_id missing', $e->getMessage());
        }
    }

    public function test_transport_failures_become_snipform_exceptions(): void
    {
        $api = FakeApi::make()->willFail(new ConnectException('refused', new PsrRequest('GET', '/')));

        $this->expectException(SnipFormException::class);
        $this->expectExceptionMessage('HTTP transport failed');
        $api->client->http->get('property/overview');
    }
}
