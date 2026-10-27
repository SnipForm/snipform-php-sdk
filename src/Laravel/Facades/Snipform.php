<?php

namespace SnipForm\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use SnipForm\Laravel\SnipFormManager;

/**
 * Facade for the SnipForm Laravel layer. Routes to `SnipFormManager` so
 * every write gets the visitor's session id resolved for it and honors the
 * config-driven dispatch mode (sync / after_response / queue).
 *
 *   use SnipForm\Laravel\Facades\Snipform;
 *
 *   Snipform::event('purchase', 9900, ['order' => 'X-1']);  // custom event on the current visitor
 *   Snipform::eventFor($sessionId, 'order_shipped');        // ...or on a session id you stored
 *   Snipform::revenue(9900, 'USD');                         // acquisition value, cents
 *   Snipform::acquisition(['cost' => 250, 'tags' => ['paid']]);
 *
 *   Snipform::identify($user);                              // contact from an Identifiable model
 *   Snipform::auth();                                       // identify auth()->user(), no-op for guests
 *   Snipform::payload(['email' => '...']);                  // raw identify passthrough
 *
 *   Snipform::sessionId();                                  // the visitor's session id, or null
 *   Snipform::signals()->last7Days()->metrics();            // reads
 *   Snipform::contacts()->find($id);                        // SDK Contacts resource
 *   Snipform::client();                                     // underlying SDK Client - escape hatch
 *
 * @method static bool event(string $name, mixed $value = null, array $meta = [], ?\Illuminate\Http\Request $request = null)
 * @method static bool eventFor(string $sessionId, string $name, mixed $value = null, array $meta = [])
 * @method static bool acquisition(array $attributes, ?\Illuminate\Http\Request $request = null)
 * @method static bool acquisitionFor(string $sessionId, array $attributes)
 * @method static bool revenue(int $cents, ?string $currencyCode = null, ?\Illuminate\Http\Request $request = null)
 * @method static bool identify(mixed $subject, ?object $request = null)
 * @method static bool user(mixed $subject, ?object $request = null)
 * @method static bool auth(?string $guard = null, ?object $request = null)
 * @method static bool payload(array $payload, ?object $request = null)
 * @method static ?string sessionId(?\Illuminate\Http\Request $request = null)
 * @method static \SnipForm\Resources\Contacts contacts()
 * @method static \SnipForm\Resources\Session session()
 * @method static \SnipForm\Resources\Signals signals()
 * @method static \SnipForm\Client client()
 *
 * @see SnipFormManager
 */
class Snipform extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SnipFormManager::class;
    }
}
