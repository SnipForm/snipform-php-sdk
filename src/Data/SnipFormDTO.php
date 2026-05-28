<?php

namespace SnipForm\Data;

/**
 * Base class for every typed API DTO in the SDK. Holds the original payload
 * privately and exposes two shared helpers:
 *
 *   ->raw()                       full unwrapped payload
 *   ->raw('key')                  single field from the payload (or null)
 *   ->raw('acquisition.value')    dot-path into nested arrays (or null)
 *   ->toArray()                   public typed fields as an array, raw excluded
 *
 * Subclasses declare their typed fields with constructor-promoted
 * `public readonly` properties and forward the raw payload up:
 *
 *   class Thing extends SnipFormDTO
 *   {
 *       public function __construct(
 *           public readonly string $id,
 *           public readonly string $name,
 *           array $raw = [],
 *       ) {
 *           parent::__construct($raw);
 *       }
 *
 *       public static function fromArray(array $row): self
 *       {
 *           return new self(
 *               id: (string) ($row['id'] ?? ''),
 *               name: (string) ($row['name'] ?? ''),
 *               raw: $row,
 *           );
 *       }
 *   }
 */
abstract class SnipFormDTO
{
    public function __construct(protected readonly array $raw = []) {}

    /**
     * Full original payload, or a single field if a key is given. Dot-paths
     * dig into nested arrays — e.g. `raw('acquisition.value')` walks
     * `$this->raw['acquisition']['value']` and returns null on any miss.
     */
    public function raw(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->raw;
        }

        $value = $this->raw;
        foreach (explode('.', $key) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Public typed fields as an array. Excludes the raw payload — call raw()
     * for that.
     */
    public function toArray(): array
    {
        $fields = get_object_vars($this);
        unset($fields['raw']);

        return $fields;
    }
}
