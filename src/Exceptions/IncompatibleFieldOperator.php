<?php

namespace SnipForm\Exceptions;

use SnipForm\Query\FieldType;
use SnipForm\Query\SessionField;

/**
 * Thrown SDK-side when a Builder operator is called against a field whose
 * type can't take that op — e.g. `whereBetween(SessionField::COUNTRY, 0, 10)`
 * (between on a keyword) or `whereStartsWith(SessionField::VIEWS, '3')`
 * (string-prefix on a numeric).
 *
 * The error message names the field, its type, the op that failed, and
 * lists the valid ops for that type — so the fix is obvious.
 *
 * Pass-through stays open: callers using bare strings (`where('country', 'US')`)
 * skip this validation because the SDK doesn't know the field's type
 * without an enum case.
 */
class IncompatibleFieldOperator extends SnipFormException
{
    public static function for(SessionField $field, string $op): self
    {
        $type = $field->type();
        $valid = self::validOpsFor($type);

        return new self(sprintf(
            "Operator `%s` is not valid for field `%s` (type: %s). Valid ops: %s.",
            $op,
            $field->value,
            $type->value,
            implode(', ', $valid),
        ));
    }

    /**
     * Catalog of valid ops per field type — used for the helpful error message.
     */
    private static function validOpsFor(FieldType $type): array
    {
        return match ($type) {
            FieldType::KEYWORD => ['equals', 'contains', 'starts_with', 'regex', 'exists'],
            FieldType::INT, FieldType::FLOAT => ['equals', 'gt', 'gte', 'lt', 'lte', 'between', 'exists'],
            FieldType::BOOL => ['equals', 'exists'],
        };
    }
}
