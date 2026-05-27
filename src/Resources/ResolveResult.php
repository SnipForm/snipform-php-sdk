<?php

namespace SnipForm\Resources;

/**
 * Result of $client->session()->resolve($request) — the answer to
 * "which SnipForm session belongs to this visitor?".
 *
 * If $resolved is false, this visitor hasn't been tracked yet today on
 * this property and $sessionId will be null. The $sid is returned either
 * way as the deterministic fingerprint computed for the visitor.
 */
class ResolveResult
{
    public function __construct(
        public readonly bool $resolved,
        public readonly ?string $sessionId,
        public readonly string $sid,
    ) {}

    public static function fromArray(array $row): self
    {
        return new self(
            resolved: (bool) ($row['resolved'] ?? false),
            sessionId: $row['session_id'] ?? null,
            sid: (string) ($row['sid'] ?? ''),
        );
    }
}
