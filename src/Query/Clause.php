<?php

namespace SnipForm\Query;

/**
 * Immutable representation of a single filter clause. Lives in memory only —
 * the public wire format is the URL-DSL string produced by ClauseSerializer.
 */
class Clause
{
    public function __construct(
        public readonly string $field,
        public readonly string $op,
        public readonly mixed $value,
        public readonly bool $or = false,
        public readonly bool $not = false,
    ) {}
}
