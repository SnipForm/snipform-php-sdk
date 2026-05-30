<?php

namespace SnipForm\Laravel\Contracts;

/**
 * Marker contract for any model that knows how to describe itself to
 * SnipForm. Implement it directly, or just `use Identifiable` from the
 * Concerns namespace for sensible auto-derived defaults.
 */
interface Identifiable
{
    /**
     * Returns the payload accepted by `$client->contacts()->identify()`.
     * Shape:
     *
     *   [
     *       'external_id' => '123',
     *       'email'       => 'jane@acme.com',
     *       'traits'      => [
     *           'first_name' => 'Jane',
     *           'last_name'  => 'Doe',
     *           'company'    => 'Acme',
     *           'meta'       => [['key' => 'plan', 'value' => 'pro']],
     *       ],
     *   ]
     */
    public function snipformPayload(): array;
}
