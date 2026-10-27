<?php

namespace SnipForm\Laravel\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use SnipForm\Client;
use SnipForm\Laravel\Dispatch\Write;

/**
 * A deferred SDK write. Carries only the write type + payload (plain data,
 * serializes cleanly); the `Client` is resolved from the container when the
 * job runs, so the token never rides on the queue.
 *
 * Deliberately trait-free: `$queue` / `$connection` are the public
 * properties Laravel's bus reads, so it queues correctly without pulling
 * illuminate/bus into the SDK's dependencies.
 */
class SignalWriteJob implements ShouldQueue
{
    public ?string $queue = null;

    public ?string $connection = null;

    public function __construct(
        public readonly Write $write,
        public readonly array $payload,
    ) {}

    public function onQueue(?string $queue): self
    {
        $this->queue = $queue;

        return $this;
    }

    public function handle(Client $client): void
    {
        $this->write->run($client, $this->payload);
    }
}
