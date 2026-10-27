<?php

namespace SnipForm\Resources;

use SnipForm\Concerns\RawAware;
use SnipForm\Data\Form;
use SnipForm\Http\HttpClient;

/**
 * Forms resource. Read-only, needs the `forms:read` scope. The list comes back
 * in a single shot (no pagination), newest form first.
 *
 *   $client->forms()->all();   // Form[]
 *
 * Which visits submitted a form lives on the session rows, not here: filter
 * sessions with `SessionField::SNIP_FORM_ID` and read each row's `form_tags`
 * (`{snip_form_id, snip_form_submit_id, is_flagged}`) in raw mode. A flagged
 * submission scored as suspicious; spam is never tracked onto a visit at all.
 *
 * Append `->asRaw()` to get the underlying API arrays instead of DTOs.
 */
class Forms
{
    use RawAware;

    private const PATH = 'property/forms';

    public function __construct(private readonly HttpClient $http) {}

    /** @return array<int, Form>|array<int, array> */
    public function all(): array
    {
        $rows = (array) ($this->http->get(self::PATH)->data('forms') ?? []);

        return array_map(fn (array $row) => $this->hydrate($row, Form::fromArray(...)), $rows);
    }
}
