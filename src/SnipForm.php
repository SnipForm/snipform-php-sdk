<?php

namespace SnipForm;

/**
 * SnipForm PHP SDK — static entry point.
 *
 *   $snipform = SnipForm::client('xxx_access_token_xxx');
 *   $sessions = $snipform->signals()->period('last_7')->where('country', 'US')->sessions();
 */
class SnipForm
{
    /** Bump with every tagged release - rides on the User-Agent of every call. */
    public const VERSION = '0.1.0';

    /**
     * Create a property-scoped SnipForm client.
     *
     * @param  string  $token  Property API token (Personal Access Token).
     * @param  array{base_url?: string, timeout?: int, path_prefix?: string, verify_ssl?: bool}  $options
     */
    public static function client(string $token, array $options = []): Client
    {
        return new Client($token, $options);
    }
}
