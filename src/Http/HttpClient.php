<?php

namespace SnipForm\Http;

use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\HandlerStack;
use SnipForm\Exceptions\ApiException;
use SnipForm\Exceptions\AuthenticationException;
use SnipForm\Exceptions\SnipFormException;
use SnipForm\SnipForm;

/**
 * Internal HTTP client. Owns the Guzzle instance, applies bearer auth, and
 * normalizes responses + errors so the rest of the SDK doesn't see Guzzle.
 */
class HttpClient
{
    private Guzzle $guzzle;

    /**
     * @param  callable|null  $handler  Guzzle handler override - tests inject a
     *                                  MockHandler here so no request leaves the box.
     */
    public function __construct(
        private readonly string $token,
        private readonly string $baseUrl,
        int $timeout = 30,
        string $pathPrefix = '/v2/',
        bool $verifySsl = true,
        ?callable $handler = null,
    ) {
        $options = [
            'base_uri' => rtrim($baseUrl, '/').'/'.trim($pathPrefix, '/').'/',
            'timeout' => $timeout,
            'verify' => $verifySsl,
            'headers' => [
                'Authorization' => 'Bearer '.$this->token,
                'Accept' => 'application/json',
                'User-Agent' => 'snipform-php-sdk/'.SnipForm::VERSION,
            ],
            'http_errors' => false,
        ];
        if ($handler !== null) {
            $options['handler'] = HandlerStack::create($handler);
        }

        $this->guzzle = new Guzzle($options);
    }

    public function get(string $path, array $query = [], array $headers = []): Response
    {
        return $this->send('GET', $path, ['query' => $query, 'headers' => $headers]);
    }

    public function post(string $path, array $payload = [], array $headers = []): Response
    {
        return $this->send('POST', $path, ['json' => $payload, 'headers' => $headers]);
    }

    public function delete(string $path, array $headers = []): Response
    {
        return $this->send('DELETE', $path, ['headers' => $headers]);
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

        // The API wraps every response in {code, status, data: {...}}, so error
        // info (message / errors) hides one level deeper. Look in both places.
        $inner = is_array($body['data'] ?? null) ? $body['data'] : [];
        $message = $body['message'] ?? $inner['message'] ?? null;
        $errors = $inner['errors'] ?? $body['errors'] ?? [];

        if ($status === 401 || $status === 403) {
            throw new AuthenticationException($message ?? 'Unauthenticated', $status);
        }
        if ($status >= 400) {
            throw new ApiException(
                message: ApiException::format($message, $status, $errors),
                status: $status,
                errors: $errors,
                body: $body,
            );
        }

        return new Response($status, $body);
    }
}
