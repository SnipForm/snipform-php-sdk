<?php

namespace SnipForm\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use SnipForm\Laravel\IdentifyManager;

/**
 * Facade for the SnipForm Laravel layer. Routes to `IdentifyManager` so
 * everything goes through one place (config-aware queueing, dedup, request
 * auto-enrichment).
 *
 *   use SnipForm\Laravel\Facades\Snipform;
 *
 *   Snipform::auth();                        // identify auth()->user(), no-op if guest
 *   Snipform::user($user);                   // identify a specific user
 *   Snipform::user($user, $request);         // forward an explicit request
 *   Snipform::payload(['email' => '...']);   // raw passthrough
 *
 *   Snipform::contacts()->find($id);         // SDK Contacts resource (find/update/delete/all)
 *   Snipform::client();                      // underlying SDK Client — escape hatch
 *
 * @method static bool   user(mixed $subject, ?object $request = null)
 * @method static bool   auth(?string $guard = null, ?object $request = null)
 * @method static bool   payload(array $payload, ?object $request = null)
 * @method static \SnipForm\Resources\Contacts contacts()
 * @method static \SnipForm\Client client()
 *
 * @see IdentifyManager
 */
class Snipform extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return IdentifyManager::class;
    }
}
