<?php

namespace SnipForm\Query;

/**
 * The data-type bucket a field maps to in the SnipForm index.
 *
 *  - KEYWORD : string fields. Support equals / contains / starts_with /
 *              regex / exists. NOT gt/lt/between.
 *  - INT     : integer numbers. Support equals / gt / gte / lt / lte /
 *              between / exists.
 *  - FLOAT   : floating-point numbers. Same ops as INT.
 *  - BOOL    : true/false. Support equals / exists.
 *
 * Used by `SessionField::type()` so the Builder can refuse a numeric op
 * (`whereBetween`, `whereGt`, ...) on a keyword field — and vice versa —
 * before the request leaves the SDK.
 */
enum FieldType: string
{
    case KEYWORD = 'keyword';
    case INT = 'int';
    case FLOAT = 'float';
    case BOOL = 'bool';

    public function isNumeric(): bool
    {
        return $this === self::INT || $this === self::FLOAT;
    }
}
