<?php

namespace SnipForm\Data;

use JsonSerializable;

/**
 * Base class for every typed API DTO. DTOs are pure typed value objects —
 * no raw payload pass-through. If a caller needs the original API JSON,
 * they flip the resource into raw mode with `->asRaw()` and the terminal
 * returns the array directly without hydrating a DTO.
 *
 *   ->toArray()      typed fields as an array
 *   ->jsonSerialize  same as toArray — DTOs serialize cleanly when returned
 *                    from a Laravel controller or passed to json_encode
 *
 * Subclasses declare their typed fields with constructor-promoted
 * `public readonly` properties:
 *
 *   class Thing extends SnipFormDTO
 *   {
 *       public function __construct(
 *           public readonly string $id,
 *           public readonly string $name,
 *       ) {}
 *
 *       public static function fromArray(array $row): self
 *       {
 *           return new self(
 *               id: (string) ($row['id'] ?? ''),
 *               name: (string) ($row['name'] ?? ''),
 *           );
 *       }
 *   }
 */
abstract class SnipFormDTO implements JsonSerializable
{
    /**
     * Public typed fields as an array.
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
