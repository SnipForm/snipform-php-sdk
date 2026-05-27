<?php

namespace SnipForm\Exceptions;

use SnipForm\Query\Period;

/**
 * Raised when a string passed to `period()` doesn't match any case of the
 * Period enum. Thrown SDK-side before the HTTP call so the developer gets
 * a useful stack trace and a list of valid values, rather than a 500 from
 * the server.
 */
class InvalidPeriodException extends SnipFormException
{
    public static function for(string $given): self
    {
        $allowed = implode(', ', array_map(fn (Period $c) => $c->value, Period::cases()));

        return new self("Invalid period `$given`. Allowed: $allowed.");
    }
}
