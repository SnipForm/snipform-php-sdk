<?php

namespace SnipForm\Exceptions;

/**
 * Thrown when the API returns a 4xx/5xx that isn't an auth failure.
 * Use ->errors() / ->body() to introspect the API's response.
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
}
