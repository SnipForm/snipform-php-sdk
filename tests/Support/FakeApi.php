<?php

namespace SnipForm\Tests\Support;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use LogicException;
use Psr\Http\Message\RequestInterface;
use SnipForm\Client;
use Throwable;

/**
 * A Client whose HTTP never leaves the box. Queue responses in the order
 * the code under test will request them, then inspect what was sent.
 *
 *   $api = FakeApi::make()->willReturn(['event' => ['id' => 'e1']]);
 *   $api->client->session()->event([...]);
 *   $api->lastJson();                            // decoded request body
 *   $api->lastRequest()->getUri()->getPath();    // '/v2/property/session/event'
 *
 * An unqueued request throws from the MockHandler ("Mock queue is empty"),
 * so "this must not call the API" tests fail loudly by themselves.
 */
class FakeApi
{
    public readonly Client $client;

    private readonly MockHandler $handler;

    private function __construct(string $token, array $options)
    {
        $this->handler = new MockHandler([]);
        $this->client = new Client($token, ['handler' => $this->handler, ...$options]);
    }

    public static function make(string $token = 'test-token', array $options = []): self
    {
        return new self($token, ['base_url' => 'https://api.example.test', ...$options]);
    }

    /** Queue a response wrapped in the API envelope ({code, status, data}). */
    public function willReturn(array $data, int $status = 200): self
    {
        $this->handler->append(new Response($status, ['Content-Type' => 'application/json'], json_encode([
            'code' => $status,
            'status' => $status < 400 ? 'OK' : 'ERROR',
            'data' => $data,
        ])));

        return $this;
    }

    /** Queue a raw response - for error shapes the envelope doesn't cover. */
    public function willRespond(int $status, array $body = []): self
    {
        $this->handler->append(new Response($status, ['Content-Type' => 'application/json'], json_encode($body)));

        return $this;
    }

    public function willFail(Throwable $exception): self
    {
        $this->handler->append($exception);

        return $this;
    }

    public function hasSent(): bool
    {
        return $this->handler->getLastRequest() !== null;
    }

    public function lastRequest(): RequestInterface
    {
        $request = $this->handler->getLastRequest();
        if (! $request) {
            throw new LogicException('No request was sent');
        }

        return $request;
    }

    public function lastJson(): array
    {
        return (array) json_decode((string) $this->lastRequest()->getBody(), true);
    }
}
