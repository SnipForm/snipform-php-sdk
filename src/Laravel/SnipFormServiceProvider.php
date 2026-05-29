<?php

namespace SnipForm\Laravel;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;
use SnipForm\Client;

/**
 * Optional Laravel auto-wiring for the SDK Client. Reads its config from
 * `config/services.php → snipform`:
 *
 *   'snipform' => [
 *       'token'       => env('SNIPFORM_TOKEN'),
 *       'base_url'    => env('SNIPFORM_BASE_URL'),      // optional
 *       'path_prefix' => env('SNIPFORM_PATH_PREFIX'),   // optional
 *       'timeout'     => env('SNIPFORM_TIMEOUT', 30),   // optional
 *       'verify_ssl'  => env('SNIPFORM_VERIFY_SSL', true), // optional
 *   ],
 *
 * Then anywhere in your app:
 *
 *   public function dashboard(\SnipForm\Client $client) { ... }
 *   // or
 *   $client = app(\SnipForm\Client::class);
 *
 * Registered automatically via Laravel's package-discovery in composer.json's
 * `extra.laravel.providers`. To skip it (e.g. you want a custom factory):
 *
 *   "extra": { "laravel": { "dont-discover": ["snipform/php-sdk"] } }
 *
 * The SDK has no `illuminate/*` dependency — this class extends Laravel's
 * `ServiceProvider`, so it only loads (and the autoloader only resolves it)
 * inside a Laravel app where that base class exists.
 */
class SnipFormServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Client::class, function (Container $app): Client {
            $config = (array) ($app['config']['services.snipform'] ?? []);

            $token = (string) ($config['token'] ?? '');

            $options = array_filter(
                [
                    'base_url' => $config['base_url'] ?? null,
                    'path_prefix' => $config['path_prefix'] ?? null,
                    'timeout' => $config['timeout'] ?? null,
                    'verify_ssl' => $config['verify_ssl'] ?? null,
                ],
                fn ($v) => $v !== null,
            );

            return new Client($token, $options);
        });
    }
}
