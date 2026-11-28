<?php

declare(strict_types=1);

use App\Support\FormatDate;
use Carbon\CarbonImmutable;

/**
 * ISSUE-118 (TQ-007) — Formato de fechas de la UI: la BD guarda ISO y la
 * pantalla muestra dd/mm/yyyy; un único helper evita formatos sueltos.
 */
it('formatea fechas ISO de la BD como dd/mm/yyyy', function (string $iso, string $expected) {
    expect(FormatDate::date(new DateTimeImmutable($iso)))->toBe($expected);
})->with([
    'fecha corriente' => ['2026-11-27', '27/11/2026'],
    'año bisiesto: 29 de febrero' => ['2024-02-29', '29/02/2024'],
    'febrero de año no bisiesto cae a 28' => ['2023-02-28', '28/02/2023'],
    'fin de mes de 31 días' => ['2026-12-31', '31/12/2026'],
    'fin de mes de 30 días' => ['2026-04-30', '30/04/2026'],
]);

it('descarta la hora: solo cambia el formato, nunca el día guardado', function () {
    expect(FormatDate::date(new DateTimeImmutable('2026-03-01 23:59:59.999')))->toBe('01/03/2026');
});

it('acepta instancias Carbon (modelos Eloquent)', function () {
    expect(FormatDate::date(CarbonImmutable::parse('2024-02-29 08:00:00')))->toBe('29/02/2024');
});
