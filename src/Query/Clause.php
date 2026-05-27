<?php

namespace SnipForm\Query;

/**
 * One filter clause. Identity is the public `id` (matches
 * SignalFieldMappingSet on the server); field/subfield/type are derived
 * server-side at apply time, so the wire format is small and stable.
 *
 * Serialized form (what hits the wire under `clauses[]`):
 *   {id: "country", op: "equals", value: "US"}
 *   {id: "device", op: "equals", value: "mobile", where: "or", not: true}
 *
 * `where` and `not` are omitted from the wire when at defaults (and / false).
 */
class Clause
{
    public function __construct(
        public readonly string $id,
        public readonly string $op,
        public readonly mixed $value,
        public readonly string $where = 'and',
        public readonly bool $not = false,
    ) {}

    /**
     * @return array{id: string, op: string, value: mixed, where?: string, not?: bool}
     */
    public function toArray(): array
    {
        $out = ['id' => $this->id, 'op' => $this->op, 'value' => $this->value];
        if ($this->where !== 'and') {
            $out['where'] = $this->where;
        }
        if ($this->not) {
            $out['not'] = true;
        }

        return $out;
    }
}
