<?php

declare(strict_types=1);

namespace App\Actions\Timing;

use Carbon\CarbonImmutable;

/** Hora oficial del servidor: referencia para el offset del reloj de la estación (ISSUE-150). */
final class ServerTime
{
    /** @return array{server_ms: int, timezone: string} */
    public function __invoke(): array
    {
        return [
            'server_ms' => (int) (CarbonImmutable::now(config('app.timezone'))->getTimestampMs()),
            'timezone' => (string) config('app.timezone'),
        ];
    }
}
