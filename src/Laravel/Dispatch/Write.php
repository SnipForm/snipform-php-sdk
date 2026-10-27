<?php

namespace SnipForm\Laravel\Dispatch;

use SnipForm\Client;

/**
 * The server-side writes the Laravel layer can defer. One case per SDK
 * write; `run()` is the single place that maps a case to its SDK call, so
 * a queued job, an after-response callback, and an inline call all do
 * exactly the same thing.
 */
enum Write: string
{
    case EVENT = 'event';
    case ACQUISITION = 'acquisition';
    case IDENTIFY = 'identify';

    public function run(Client $client, array $payload): void
    {
        match ($this) {
            self::EVENT => $client->session()->event($payload),
            self::ACQUISITION => $client->session()->acquisition($payload),
            self::IDENTIFY => $client->contacts()->identify($payload),
        };
    }
}
