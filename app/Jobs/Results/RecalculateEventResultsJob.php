<?php

declare(strict_types=1);

namespace App\Jobs\Results;

use App\Actions\Results\RecalculateEventResults;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Recalculo de la clasificación al llegar cada marca: en cola y
 * afterCommit (INV-08) para que nunca bloquee la captura — la marca
 * responde aunque este job espere su turno.
 */
final class RecalculateEventResultsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 60;

    public function __construct(public readonly int $eventId) {}

    public function handle(RecalculateEventResults $action): void
    {
        $action($this->eventId);
    }
}
