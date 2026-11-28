<?php

declare(strict_types=1);

namespace Tests\Support\Chaos;

use Illuminate\Queue\SyncQueue;
use RuntimeException;

/**
 * TQ-019 — Doble de caos, nivel «fallo al ENCOLAR el broadcast».
 *
 * Es un driver de cola REAL (mismo camino de resolución que cualquier
 * conexión: QueueManager → conector → driver → pushOn/push), pero su
 * push a la cola `broadcast` revienta — el Redis que solo está caído
 * para los eventos del WS. El resto de las colas (default…) funciona:
 * así la prueba separa el fallo del broadcast del resto del pipeline
 * (ISSUE-145: la marca jamás falla por el broadcast).
 */
class ChaosSyncQueue extends SyncQueue
{
    public function push($job, $data = '', $queue = null)
    {
        if ($queue === 'broadcast') {
            throw new RuntimeException('chaos: la cola de broadcast está caída');
        }

        return parent::push($job, $data, $queue);
    }
}
