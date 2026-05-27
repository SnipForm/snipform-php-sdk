<?php

namespace SnipForm\Http;

use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Exception\GuzzleException;
use SnipForm\Exceptions\ApiException;
use SnipForm\Exceptions\AuthenticationException;
use SnipForm\Exceptions\SnipFormException;

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
        string $pathPrefix = '/v2/',
        bool $verifySsl = true,
    ) {
        $this->guzzle = new Guzzle([
            'base_uri' => rtrim($baseUrl, '/').'/'.trim($pathPrefix, '/').'/',
            'timeout' => $timeout,
            'verify' => $verifySsl,
            'headers' => [
                'Authorization' => 'Bearer '.$this->token,
                'Accept' => 'application/json',
                'User-Agent' => 'snipform-php-sdk/0.3',
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

    public function delete(string $path): Response
    {
        return $this->send('DELETE', $path, []);
    }

    private function send(string $method, string $path, array $options): Response
    {
        try {
            $raw = $this->guzzle->request($method, ltrim($path, '/'), $options);
        } catch (GuzzleException $e) {
            throw new SnipFormException('HTTP transport failed: '.$e->getMessage(), 0, $e);
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
