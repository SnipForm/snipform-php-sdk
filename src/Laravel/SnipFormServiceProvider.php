<?php

namespace SnipForm\Laravel;

use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use SnipForm\Client;
use SnipForm\Laravel\Listeners\IdentifyOnLogin;
use SnipForm\Laravel\Middleware\IdentifyAuthUser;

/**
 * Laravel auto-wiring for the SnipForm SDK.
 *
 * Reads from `config/snipform.php` (publish via `--tag=snipform-config`) and
 * falls back to `config/services.php → snipform` for back-compat.
 *
 * What this provider does — all opt-in via config:
 *   - Binds `SnipForm\Client` as a singleton with token + dedup wired up
 *   - Binds `IdentifyManager` (the facade root) as a singleton
 *   - Registers `IdentifyOnLogin` against the Auth Login event (when
 *     `snipform.identify.on_login` is true)
 *   - Aliases the `snipform.identify` middleware
 *
 * Skip the provider in your app's `composer.json` to take full control:
 *
 *   "extra": { "laravel": { "dont-discover": ["snipform/php-sdk"] } }
 */
class SnipFormServiceProvider extends ServiceProvider
{
    /** Config key the runtime reads from. Single source of truth. */
    private const CONFIG_KEY = 'snipform';

    /** Legacy key — still readable so existing `services.snipform` setups keep working. */
    private const LEGACY_CONFIG_KEY = 'services.snipform';

    public function register(): void
    {
        $this->mergeConfigFrom($this->configPath(), self::CONFIG_KEY);

        $this->app->singleton(Client::class, function (Container $app): Client {
            $config = $this->config($app);

            $client = new Client((string) ($config['token'] ?? ''), array_filter([
                'base_url' => $config['base_url'] ?? null,
                'path_prefix' => $config['path_prefix'] ?? null,
                'timeout' => $config['timeout'] ?? null,
                'verify_ssl' => $config['verify_ssl'] ?? null,
            ], fn ($v) => $v !== null));

            $ttl = (int) ($config['identify']['dedup_ttl'] ?? 0);
            if ($ttl > 0) {
                $store = $config['identify']['cache_store'] ?? null;
                $cache = $store ? $app['cache']->store($store) : $app['cache']->store();

                $client->withIdentifyDedup(
                    fn (string $key, int $ttl): bool => (bool) $cache->add($key, true, $ttl),
                    $ttl,
                );
            }

            return $client;
        });

        $this->app->singleton(IdentifyManager::class, function (Container $app): IdentifyManager {
            return new IdentifyManager(
                client: $app->make(Client::class),
                container: $app,
                config: (array) ($this->config($app)['identify'] ?? []),
            );
        });
    }

    public function boot(): void
    {
        $this->publishes([
            $this->configPath() => $this->app->configPath(self::CONFIG_KEY.'.php'),
        ], 'snipform-config');

        $this->registerLoginListener();
        $this->registerMiddlewareAlias();
    }

    // ======================================================================
    // Internals
    // ======================================================================

    private function registerLoginListener(): void
    {
        $config = $this->config($this->app);
        if (empty($config['identify']['on_login'])) {
            return;
        }

        $this->app->make(Dispatcher::class)->listen(Login::class, IdentifyOnLogin::class);
    }

    private function registerMiddlewareAlias(): void
    {
        // Laravel 11 surfaces the router differently from 10; keep both paths.
        if ($this->app->bound(Router::class)) {
            $this->app->make(Router::class)->aliasMiddleware('snipform.identify', IdentifyAuthUser::class);

            return;
        }

        if ($this->app->bound(HttpKernel::class)) {
            $kernel = $this->app->make(HttpKernel::class);
            if (method_exists($kernel, 'addRouteMiddleware')) {
                $kernel->addRouteMiddleware('snipform.identify', IdentifyAuthUser::class);
            }
        }
    }

    /**
     * Merge the new `snipform` config over legacy `services.snipform`, so
     * apps that wired the SDK via `config/services.php` before the Laravel
     * layer existed keep working without touching anything.
     */
    private function config(Container $app): array
    {
        $repo = $app['config'];
        $primary = (array) ($repo->get(self::CONFIG_KEY) ?? []);
        $legacy = (array) ($repo->get(self::LEGACY_CONFIG_KEY) ?? []);

        // Primary wins; legacy fills gaps.
        return array_replace_recursive($legacy, $primary);
    }

    private function configPath(): string
    {
        return __DIR__.'/config/snipform.php';
    }
}
