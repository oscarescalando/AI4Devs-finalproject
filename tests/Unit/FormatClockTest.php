<?php

declare(strict_types=1);

use App\Support\FormatClock;
use Carbon\CarbonImmutable;

/**
 * ISSUE-130 (TQ-012): hora del día con milisegundos y zona del evento —
 * FormatClock es la única vía para pintar horas (FormatDuration queda para
 * duraciones).
 */
it('formatea la hora del día con milisegundos', function () {
    expect(FormatClock::timeOfDay(CarbonImmutable::parse('2026-11-27 09:05:03.412')))
        ->toBe('09:05:03.412')
        ->and(FormatClock::timeOfDay(CarbonImmutable::parse('2026-11-27 23:59:59.999')))
        ->toBe('23:59:59.999');
});

it('convierte a la zona horaria pedida sin mutar la hora original', function () {
    $utc = CarbonImmutable::parse('2026-11-27 12:34:56.789', 'UTC');

    expect(FormatClock::timeOfDay($utc, 'America/Caracas'))->toBe('08:34:56.789')
        ->and($utc->format('H:i'))->toBe('12:34');
});

it('acepta la hora como cadena, la que persiste timing_marks', function () {
    expect(FormatClock::timeOfDay('2026-11-27 19:00:00.005'))->toBe('19:00:00.005');
});
