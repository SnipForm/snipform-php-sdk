<?php

namespace Snipform\Query;

/**
 * Serializes Clause objects into the Snipform URL query-string DSL that the
 * `query[]` API parameter expects. Mirrors QueryStringParser on the server.
 *
 *   equality       country = US        → country:US
 *   multi value    country IN (US,CA)  → country:US,CA
 *   starts_with    entry_path:/blog    → entry_path:/blog with a trailing star
 *   contains       title:welcome       → title wrapped in stars
 *   regex          source:goog.*       → source wrapped in slashes
 *   gt/gte/lt/lte  views > 5           → views:>5
 *   between        views:[3 TO 10]     → views:[3 TO 10]
 *   exists         field with no colon → field
 *   or prefix      or_field:...
 *   not prefix     not_field:...
 *   combined       or_not_field:...
 */
class ClauseSerializer
{
    public static function serialize(Clause $clause): string
    {
        $prefix = '';
        if ($clause->or) {
            $prefix .= 'or_';
        }
        if ($clause->not) {
            $prefix .= 'not_';
        }

        $field = $prefix.$clause->field;

        if ($clause->value === null) {
            return $field;
        }

        return $field.':'.self::serializeValue($clause->op, $clause->value);
    }

    private static function serializeValue(string $op, mixed $value): string
    {
        return match ($op) {
            'equals' => is_array($value) ? self::serializeMulti($value) : self::quoteIfNeeded((string) $value),
            'contains' => '*'.$value.'*',
            'starts_with' => $value.'*',
            'regex' => '/'.$value.'/',
            'gte' => '>='.$value,
            'gt' => '>'.$value,
            'lte' => '<='.$value,
            'lt' => '<'.$value,
            'between' => '['.$value[0].' TO '.$value[1].']',
            default => (string) $value,
        };
    }

    private static function serializeMulti(array $values): string
    {
        return implode(',', array_map(fn ($v) => self::quoteIfNeeded((string) $v), $values));
    }

    private static function quoteIfNeeded(string $value): string
    {
        // Quote when the value contains a delimiter the parser would otherwise mis-split on.
        if (str_contains($value, ',') || str_contains($value, ' ')) {
            return '"'.$value.'"';
        }

        return $value;
    }
}
