<?php

namespace SnipForm\Laravel\Listeners;

use Illuminate\Auth\Events\Login;
use SnipForm\Laravel\IdentifyManager;

/**
 * Listens for Auth's `Login` event and fires identify on the user that
 * just authenticated. Registered automatically by `SnipFormServiceProvider`
 * when `snipform.identify.on_login` is true (default).
 *
 * The user model needs to implement `SnipForm\Laravel\Contracts\Identifiable`
 * or use the `SnipForm\Laravel\Concerns\Identifiable` trait — otherwise the
 * listener no-ops cleanly (the manager returns false when it can't derive
 * a payload).
 */
class IdentifyOnLogin
{
    public function __construct(private readonly IdentifyManager $manager) {}

    public function handle(Login $event): void
    {
        $this->manager->user($event->user);
    }
}
