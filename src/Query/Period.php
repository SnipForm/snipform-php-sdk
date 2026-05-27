<?php

namespace SnipForm\Query;

use SnipForm\Exceptions\InvalidPeriodException;

/**
 * The fixed catalog of named periods accepted by the SnipForm API.
 *
 * Use a case directly when calling `period()`:
 *
 *   $client->signals()->period(Period::LAST_28)->metrics();
 *
 * Or use the typed shorthand methods on the Builder (`->last28Days()`,
 * `->today()`, etc.) which set the same value with full IDE autocomplete.
 *
 * `Period::CUSTOM` is set automatically by `->between($from, $to)`; you
 * usually don't pick it by hand.
 */
enum Period: string
{
    case TODAY = 'today';
    case YESTERDAY = 'yesterday';
    case LAST_7 = 'last_7';
    case LAST_28 = 'last_28';
    case MONTH_TO_DATE = 'month_to_date';
    case YEAR_TO_DATE = 'year_to_date';
    case LAST_12_MONTHS = 'last_12_months';
    case CUSTOM = 'custom';

    /**
     * Best-effort coercion. Accepts a Period or one of its string values;
     * throws InvalidPeriodException for anything else.
     */
    public static function coerce(self|string $period): self
    {
        if ($period instanceof self) {
            return $period;
        }

        $case = self::tryFrom($period);
        if ($case === null) {
            throw InvalidPeriodException::for($period);
        }

        return $case;
    }
}
