<?php

namespace SnipForm\Exceptions;

/**
 * Thrown when the API returns a 4xx/5xx that isn't an auth failure.
 *
 * The exception message surfaces field-level validation errors inline when
 * the API returns Laravel's standard `{message, errors: {field: [msg, ...]}}`
 * shape — so a 422 like "period is invalid" reads at a glance instead of
 * just "API error (422)".
 *
 * Programmatic access:
 *   $e->status   // HTTP status code
 *   $e->errors   // ['field' => ['msg1', 'msg2']] — empty when no validation
 *   $e->body     // full unwrapped response body
 */
class ApiException extends SnipFormException
{
    public function __construct(
        string $message,
        public readonly int $status,
        public readonly array $errors = [],
        public readonly array $body = [],
    ) {
        parent::__construct($message, $status);
    }

    /**
     * Build a human-friendly exception message from the raw API error.
     * If `errors` is non-empty (Laravel validation shape), append the
     * field-level details so the developer sees them in the trace.
     */
    public static function format(?string $message, int $status, array $errors): string
    {
        $base = $message !== null && $message !== ''
            ? $message
            : "API error ({$status})";

        if (empty($errors)) {
            return $base;
        }

        $details = [];
        foreach ($errors as $field => $msgs) {
            $msgs = is_array($msgs) ? $msgs : [(string) $msgs];
            foreach ($msgs as $m) {
                $details[] = "{$field}: {$m}";
            }
        }

        return $base.' — '.implode('; ', $details);
    }
}
