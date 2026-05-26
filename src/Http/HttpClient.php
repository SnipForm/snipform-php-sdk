<?php

namespace Snipform\Http;

use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Exception\GuzzleException;
use Snipform\Exceptions\ApiException;
use Snipform\Exceptions\AuthenticationException;
use Snipform\Exceptions\SnipformException;

/**
 * Internal HTTP client. Owns the Guzzle instance, applies bearer auth, and
 * normalizes responses + errors so the rest of the SDK doesn't see Guzzle.
 */
class HttpClient
{
    private Guzzle $guzzle;

    public function __construct(
        private readonly string $token,
        private readonly string $baseUrl,
        int $timeout = 30,
    ) {
        $this->guzzle = new Guzzle([
            'base_uri' => rtrim($baseUrl, '/').'/api/v2/',
            'timeout' => $timeout,
            'headers' => [
                'Authorization' => 'Bearer '.$this->token,
                'Accept' => 'application/json',
                'User-Agent' => 'snipform-php-sdk/0.1',
            ],
            'http_errors' => false,
        ]);
    }

    public function get(string $path, array $query = []): Response
    {
        return $this->send('GET', $path, ['query' => $query]);
    }

    public function post(string $path, array $payload = []): Response
    {
        return $this->send('POST', $path, ['json' => $payload]);
    }

    public function patch(string $path, array $payload = []): Response
    {
        return $this->send('PATCH', $path, ['json' => $payload]);
    }

    public function delete(string $path): Response
    {
        return $this->send('DELETE', $path, []);
    }

    private function send(string $method, string $path, array $options): Response
    {
        try {
            $raw = $this->guzzle->request($method, ltrim($path, '/'), $options);
        } catch (GuzzleException $e) {
            throw new SnipformException('HTTP transport failed: '.$e->getMessage(), 0, $e);
        }

        $status = $raw->getStatusCode();
        $body = json_decode((string) $raw->getBody(), true) ?? [];

        if ($status === 401 || $status === 403) {
            throw new AuthenticationException($body['message'] ?? 'Unauthenticated', $status);
        }
        if ($status >= 400) {
            throw new ApiException(
                message: $body['message'] ?? "API error ({$status})",
                status: $status,
                errors: $body['errors'] ?? [],
                body: $body,
            );
        }

        return new Response($status, $body);
    }
}
