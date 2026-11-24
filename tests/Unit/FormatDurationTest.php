<?php

declare(strict_types=1);

use App\Support\FormatDuration;

/**
 * ISSUE-118 (parcial): formato único de duraciones desde ms con soporte
 * > 24 h, décimas opcionales y diferencias negativas.
 */
it('formatea cero y tiempos menores a una hora', function () {
    expect(FormatDuration::fromMs(0))->toBe('0:00:00')
        ->and(FormatDuration::fromMs(1_543_000))->toBe('0:25:43');
});

it('formatea con décimas opcionales', function () {
    expect(FormatDuration::fromMs(1_543_120, true))->toBe('0:25:43.1')
        ->and(FormatDuration::fromMs(54_999, true))->toBe('0:00:54.9');
});

it('soporta tiempos mayores a 24 horas', function () {
    expect(FormatDuration::fromMs(194_734_000))->toBe('54:05:34');
});

it('formatea diferencias negativas', function () {
    expect(FormatDuration::fromMs(-5_200))->toBe('−0:00:05')
        ->and(FormatDuration::fromMs(-5_200, true))->toBe('−0:00:05.2');
});
