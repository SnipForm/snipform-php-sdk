<?php

namespace Snipform\Http;

/**
 * Thin wrapper over a decoded API response. The Snipform API ships as:
 *
 *   { code, status, data: { inputs, analytics|sessions|..., meta, options } }
 *
 * Resources reach into `data()` for the payload and `meta()` for the envelope.
 */
class Response
{
    public function __construct(
        public readonly int $status,
        public readonly array $body,
    ) {}

    /**
     * The decoded `data` block. Some endpoints nest further (e.g. data.analytics);
     * pass a dot-path to drill in.
     */
    public function data(?string $path = null): mixed
    {
        $data = $this->body['data'] ?? [];
        if ($path === null) {
            return $data;
        }

        return $this->dig($data, $path);
    }

    public function meta(?string $key = null): mixed
    {
        $meta = $this->data('meta') ?? [];
        if ($key === null) {
            return $meta;
        }

        return $meta[$key] ?? null;
    }

    private function dig(array $data, string $path): mixed
    {
        foreach (explode('.', $path) as $segment) {
            if (! is_array($data) || ! array_key_exists($segment, $data)) {
                return null;
            }
            $data = $data[$segment];
        }

        return $data;
    }
}
