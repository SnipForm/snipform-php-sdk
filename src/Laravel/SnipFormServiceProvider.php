<?php

namespace SnipForm\Laravel;

use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\ServiceProvider;
use SnipForm\Client;
use SnipForm\Laravel\Dispatch\WriteDispatcher;
use SnipForm\Laravel\Http\Controllers\BindController;
use SnipForm\Laravel\Listeners\IdentifyOnLogin;
use SnipForm\Laravel\Middleware\HydrateCookieMiddleware;
use SnipForm\Laravel\Middleware\IdentifyAuthUser;
use SnipForm\Laravel\View\HydrateLoader;
use SnipForm\Resources\Session;

/**
 * Laravel auto-wiring for the SnipForm SDK.
 *
 * Reads from `config/snipform.php` (publish via `--tag=snipform-config`) and
 * falls back to `config/services.php → snipform` for back-compat.
 *
 * What this provider does — all opt-in via config:
 *   - Binds `SnipForm\Client` as a singleton with token + dedup wired up
 *   - Binds `WriteDispatcher`, `IdentifyManager`, and `SnipFormManager`
 *     (the facade root) as singletons
 *   - Registers `IdentifyOnLogin` against the Auth Login event (when
 *     `snipform.identify.on_login` is true)
 *   - Aliases the `snipform.identify` middleware
 *   - Wires the hydrate loader (`@snipformHydrate`) + the request macro
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

        $this->app->singleton(WriteDispatcher::class, fn (Application $app): WriteDispatcher => new WriteDispatcher($app));

        $this->app->singleton(IdentifyManager::class, function (Container $app): IdentifyManager {
            $config = $this->config($app);

            return new IdentifyManager(
                client: $app->make(Client::class),
                container: $app,
                dispatcher: $app->make(WriteDispatcher::class),
                config: [
                    ...(array) ($config['identify'] ?? []),
                    'queue_name' => $config['queue_name'] ?? null,
                ],
            );
        });

        $this->app->singleton(SnipFormManager::class, function (Container $app): SnipFormManager {
            return new SnipFormManager(
                client: $app->make(Client::class),
                identify: $app->make(IdentifyManager::class),
                dispatcher: $app->make(WriteDispatcher::class),
                config: $this->config($app),
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
        $this->registerHydrate();
        $this->registerRequestMacro();
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

    // ======================================================================
    // Hydrate — bind route, Blade directive
    // ======================================================================

    /**
     * Register the bind endpoint + the `@snipformHydrate` Blade directive.
     * Both are opt-in via config (`snipform.hydrate.enabled`, default true).
     *
     * The bind endpoint receives `session_id` from the loader script and
     * stashes it into Laravel's session under `snipform_session_id`. The
     * Blade directive emits the inline loader that wires the tracker's
     * `window.signals.sessionId` to that endpoint.
     */
    private function registerHydrate(): void
    {
        $config = $this->config($this->app)['hydrate'] ?? [];
        if (! ($config['enabled'] ?? true)) {
            return;
        }

        $mode = $config['mode'] ?? HydrateLoader::MODE_COOKIE;
        $cookieName = (string) ($config['cookie_name'] ?? 'site_initializer');
        $bindPath = (string) ($config['bind_url'] ?? '/snipform/bind');
        $bindUrl = '/'.ltrim($bindPath, '/');

        if ($mode === HydrateLoader::MODE_COOKIE) {
            $this->registerHydrateCookie($cookieName);
        } else {
            $bindMiddleware = (array) ($config['bind_middleware'] ?? ['web']);
            RouteFacade::middleware($bindMiddleware)
                ->post($bindUrl, BindController::class)
                ->name('snipform.bind');
        }

        // Blade directive — emits the right loader call for the active mode,
        // baking in the currently-stashed sessionId via `renderForRequest`.
        $configJson = json_encode([
            'mode' => $mode,
            'cookie_name' => $cookieName,
            'bind_url' => $bindUrl,
        ]);
        Blade::directive(
            'snipformHydrate',
            fn () => '<?php echo \\'.HydrateLoader::class."::renderForRequest(request(), {$configJson}); ?>",
        );
    }

    /**
     * Cookie mode plumbing:
     *   1. Append `HydrateCookieMiddleware` to the `web` group so it reads
     *      the transient cookie + stashes in the session on every request.
     *   2. Tell Laravel's EncryptCookies middleware NOT to try and decrypt
     *      our cookie — it's set by JS so it's never going to be Laravel-
     *      encrypted, and without this exclusion EncryptCookies would
     *      silently strip it.
     */
    private function registerHydrateCookie(string $cookieName): void
    {
        // Bind the middleware with the cookie name as a constructor arg.
        $this->app->when(HydrateCookieMiddleware::class)
            ->needs('$cookieName')
            ->give($cookieName);

        // Append to the web group so all session-aware requests pick it up.
        if ($this->app->bound(Kernel::class)) {
            $kernel = $this->app->make(Kernel::class);
            if (method_exists($kernel, 'appendMiddlewareToGroup')) {
                $kernel->appendMiddlewareToGroup('web', HydrateCookieMiddleware::class);
            }
        }

        // Skip Laravel's cookie-encryption layer for this name. Static method
        // is available on Laravel 10+ via the EncryptCookies middleware.
        if (method_exists(EncryptCookies::class, 'except')) {
            EncryptCookies::except($cookieName);
        }
    }

    /**
     * Register `$request->snipformSessionId()` macro. Priority chain:
     *
     *   1. Laravel session     — populated by the hydrate loader on prior request
     *   2. Tracker header      — X-SnipForm-Session-Id (attachToFetch / AJAX)
     *   3. Form field          — snip_session_id (attachToForm / POST)
     *   4. Query string        — snip_session_id (manual)
     *
     * Returns null if none resolve — the browser is the only canonical source
     * of the session id, no server-side fingerprint fallback.
     */
    private function registerRequestMacro(): void
    {
        Request::macro('snipformSessionId', function (): ?string {
            /** @var Request $this */
            if ($this->hasSession()) {
                $stashed = $this->session()->get(BindController::SESSION_KEY);
                if (is_string($stashed) && $stashed !== '') {
                    return $stashed;
                }
            }

            $header = $this->headers->get(Session::SESSION_HEADER);
            if (is_string($header) && $header !== '') {
                return $header;
            }

            $form = $this->input(Session::SESSION_FORM_FIELD);
            if (is_string($form) && $form !== '') {
                return $form;
            }

            $query = $this->query(Session::SESSION_FORM_FIELD);

            return is_string($query) && $query !== '' ? $query : null;
        });
    }
}
