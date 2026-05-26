<?php

namespace Snipform;

/**
 * Snipform PHP SDK — static entry point.
 *
 *   $snipform = Snipform::client('snipform_pat_xxx');
 *   $sessions = $snipform->signals()->period('last_7')->where('country', 'US')->sessions();
 */
class Snipform
{
    /**
     * Create a property-scoped Snipform client.
     *
     * @param  string  $token  Property API token (Personal Access Token).
     * @param  array{base_url?: string, timeout?: int}  $options
     */
    public static function client(string $token, array $options = []): Client
    {
        return new Client($token, $options);
    }
}
