<?php

declare(strict_types=1);

namespace Tests\Support\Chaos;

use Illuminate\Queue\Connectors\ConnectorInterface;

/**
 * TQ-019 — Conector del driver de caos: se registra con Queue::extend()
 * y resuelve ChaosSyncQueue exactamente como SyncConnector resuelve la
 * conexión `sync` nativa (camino real, sin mocks del QueueManager).
 */
class ChaosSyncConnector implements ConnectorInterface
{
    /**
     * Establish a queue connection.
     *
     * @param  array<string, mixed>  $config
     */
    public function connect(array $config)
    {
        return new ChaosSyncQueue;
    }
}
