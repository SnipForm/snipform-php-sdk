<?php

namespace SnipForm\Laravel\Dispatch;

use Illuminate\Contracts\Bus\Dispatcher as BusDispatcher;
use Illuminate\Contracts\Foundation\Application;
use SnipForm\Client;
use SnipForm\Laravel\Jobs\SignalWriteJob;

/**
 * Decides WHEN a write leaves the box. Three modes, picked per write type
 * in config:
 *
 *   sync            - inline, the caller waits for the API round-trip
 *   after_response  - runs in the container's terminating phase, after the
 *                     response is sent; no queue worker needed
 *   queue           - a real ShouldQueue job on your queue connection;
 *                     survives the request entirely and retries like any job
 *
 * Legacy booleans still work: true = after_response, false = sync.
 */
class WriteDispatcher
{
    public const SYNC = 'sync';

    public const AFTER_RESPONSE = 'after_response';

    public const QUEUE = 'queue';

    /** Needs the Application contract (not bare Container) for terminating(). */
    public function __construct(private readonly Application $container) {}

    public function dispatch(string|bool|null $mode, Write $write, array $payload, ?string $queue = null): void
    {
        $job = new SignalWriteJob($write, $payload);

        switch (self::normalize($mode)) {
            case self::QUEUE:
                $this->container->make(BusDispatcher::class)->dispatch($job->onQueue($queue));

                return;

            case self::AFTER_RESPONSE:
                $this->container->terminating(function () use ($job): void {
                    $job->handle($this->container->make(Client::class));
                });

                return;
        }

        $job->handle($this->container->make(Client::class));
    }

    public static function normalize(string|bool|null $mode): string
    {
        if ($mode === true) {
            return self::AFTER_RESPONSE;
        }
        if ($mode === false || $mode === null || $mode === '') {
            return self::SYNC;
        }

        return in_array($mode, [self::SYNC, self::AFTER_RESPONSE, self::QUEUE], true) ? $mode : self::SYNC;
    }
}
