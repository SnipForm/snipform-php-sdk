<?php

namespace SnipForm\Concerns;

/**
 * Adds an opt-in `asRaw()` switch to a resource or builder. When toggled,
 * terminal methods return the underlying API array instead of hydrating
 * the typed `Data\*` value object.
 *
 *   $client->linkGroups()->find($id);              // LinkGroup
 *   $client->linkGroups()->asRaw()->find($id);     // array
 *
 *   $client->signals()->last28Days()->metrics();              // MetricsResult
 *   $client->signals()->last28Days()->asRaw()->metrics();     // array
 *
 * Each `Client::someResource()` call returns a fresh instance, so flipping
 * the flag on one chain has no effect on the next.
 */
trait RawAware
{
    protected bool $asRaw = false;

    /**
     * Switch this resource into raw-array mode. Terminals return the API
     * response array verbatim; the typed DTO hydration is skipped.
     */
    public function asRaw(bool $on = true): static
    {
        $this->asRaw = $on;

        return $this;
    }

    /**
     * @internal Convenience used by terminals — return `$row` as-is when
     * raw mode is on, otherwise hand it to the supplied hydrator.
     *
     * @template T
     *
     * @param  callable(array): T  $hydrator
     * @return T|array
     */
    protected function hydrate(array $row, callable $hydrator): mixed
    {
        return $this->asRaw ? $row : $hydrator($row);
    }
}
