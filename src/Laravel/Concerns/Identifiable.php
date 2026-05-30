<?php

namespace SnipForm\Laravel\Concerns;

/**
 * Drop-in trait for Eloquent / Authenticatable models. Auto-derives a
 * sensible `snipformPayload()` by probing common columns. Override the
 * hooks below to tweak.
 *
 *   use SnipForm\Laravel\Concerns\Identifiable;
 *
 *   class User extends Authenticatable
 *   {
 *       use Identifiable;
 *
 *       // Optional — defaults to $this->getKey()
 *       protected function snipformExternalId(): string
 *       {
 *           return 'usr_'.$this->uuid;
 *       }
 *
 *       // Optional — merged on top of the auto-derived traits
 *       protected function snipformTraits(): array
 *       {
 *           return [
 *               'company' => $this->team?->name,
 *               'meta'    => [
 *                   ['key' => 'plan', 'value' => $this->subscription_plan],
 *               ],
 *           ];
 *       }
 *   }
 *
 * Implementing classes satisfy the `SnipForm\Laravel\Contracts\Identifiable`
 * contract via this trait's `snipformPayload()`.
 */
trait Identifiable
{
    /**
     * Columns auto-mapped onto the top-level payload.
     */
    private const SNIPFORM_TOP_LEVEL = ['email'];

    /**
     * Columns auto-mapped into the `traits` sub-array.
     */
    private const SNIPFORM_TRAIT_COLUMNS = [
        'first_name', 'last_name', 'phone', 'company',
        'job_title', 'website', 'country', 'city',
    ];

    public function snipformPayload(): array
    {
        $payload = ['external_id' => (string) $this->snipformResolveExternalId()];

        foreach (self::SNIPFORM_TOP_LEVEL as $col) {
            $value = $this->snipformReadAttribute($col);
            if ($value !== null && $value !== '') {
                $payload[$col] = $value;
            }
        }

        $traits = $this->snipformAutoTraits();

        if (method_exists($this, 'snipformTraits')) {
            $traits = array_replace($traits, (array) $this->snipformTraits());
        }

        if (! empty($traits)) {
            $payload['traits'] = $traits;
        }

        return $payload;
    }

    private function snipformResolveExternalId(): string
    {
        if (method_exists($this, 'snipformExternalId')) {
            return (string) $this->snipformExternalId();
        }

        if (method_exists($this, 'getKey')) {
            return (string) $this->getKey();
        }

        // Last resort: hope a public id-ish attribute exists.
        return (string) ($this->id ?? '');
    }

    private function snipformAutoTraits(): array
    {
        $traits = [];

        foreach (self::SNIPFORM_TRAIT_COLUMNS as $col) {
            $value = $this->snipformReadAttribute($col);
            if ($value !== null && $value !== '') {
                $traits[$col] = $value;
            }
        }

        // Fallback: split `name` into first/last when first_name absent.
        if (empty($traits['first_name'])) {
            $name = $this->snipformReadAttribute('name');
            if (is_string($name) && $name !== '') {
                $parts = preg_split('/\s+/', trim($name), 2);
                $traits['first_name'] = $parts[0];
                if (! empty($parts[1])) {
                    $traits['last_name'] = $parts[1];
                }
            }
        }

        return $traits;
    }

    private function snipformReadAttribute(string $column): mixed
    {
        // Eloquent models expose attributes via the offset/dot access; raw
        // public properties work too. Stay framework-agnostic on lookup.
        if (method_exists($this, 'getAttribute')) {
            $value = $this->getAttribute($column);
            if ($value !== null) {
                return is_scalar($value) ? (string) $value : null;
            }
        }

        if (property_exists($this, $column)) {
            $value = $this->{$column};

            return is_scalar($value) ? (string) $value : null;
        }

        return null;
    }
}
