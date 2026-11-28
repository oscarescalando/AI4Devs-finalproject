<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\ExecutableFinder;

/**
 * ISSUE-163 (TQ-035) — Comando artesanal ops:backup: genera un dump
 * comprimido de la BD de pruebas (tdchronos_testing, ya migrada por
 * RefreshDatabase) con mysqldump REAL vía Symfony Process. El comando exige
 * el cliente de MySQL instalado (Herd/Laragon lo traen; un CI sin él no
 * puede ejecutar nada útil aquí): si mysqldump no existe, la prueba se
 * salta — documentado en docs/procesos/runbook-operacion.md §4.
 */
it('genera un respaldo .sql.gz con el esquema de la BD de pruebas', function () {
    if ((new ExecutableFinder)->find('mysqldump') === null) {
        $this->markTestSkipped('mysqldump no está instalado en este sistema (requisito documentado en docs/procesos/runbook-operacion.md §4).');
    }

    $database = (string) config('database.connections.mysql.database');
    $directory = storage_path('app/backups');

    $exitCode = Artisan::call('ops:backup', ['--database' => $database]);

    // Código de salida fiel del mysqldump subyacente: 0 = respaldo completo.
    expect($exitCode)->toBe(0);
    expect(Artisan::output())->toContain($database);

    // El archivo nuevo de ESTA corrida: patrón fechado del comando, el más
    // reciente por mtime (puede haber backups previos de la misma BD).
    $candidates = File::glob($directory."/tdchronos-{$database}-*.sql.gz") ?: [];

    expect($candidates)->not->toBeEmpty();

    $path = (string) collect($candidates)->sortByDesc(fn (string $f): int => (int) filemtime($f))->first();

    expect($path)->toEndWith('.sql.gz');
    expect((int) File::size($path))->toBeGreaterThan(0);

    // El dump descomprimido es SQL válido del núcleo: DDL presente y con las
    // tablas cuya integridad defiende el esquema (entries UNIQUE/CHECK XOR,
    // timing_marks append-only — INV-01/05/09).
    $sql = (string) gzdecode((string) File::get($path));

    expect($sql)
        ->toContain('CREATE TABLE')
        ->toContain('entries')
        ->toContain('timing_marks');

    // Limpieza: el backup de prueba no se acumula en storage/app/backups.
    File::delete($path);
});
