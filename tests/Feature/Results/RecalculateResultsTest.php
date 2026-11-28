<?php

declare(strict_types=1);

use App\Actions\Results\RecalculateEventResults;
use App\Actions\Timing\RecordTimingMarkBatch;
use App\Models\Category;
use App\Models\Entry;
use App\Models\Event;
use App\Models\Person;
use App\Models\Race;
use App\Models\Result;
use App\Models\TimingMark;
use App\Models\TimingMarkCorrection;
use App\Models\Wave;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * ISSUE-158 — Motor de resultados: net_ms con salida real y ajustes,
 * RANK() por carrera/género/categoría, > 24 h, disparo por marca y
 * conciliación provisional ↔ oficial (INV-07).
 */
uses(RefreshDatabase::class);

function makeEvent(array $attributes = []): Event
{
    $suffix = str()->random(6);

    return Event::query()->create(array_merge([
        'name' => "Carrera Test $suffix",
        'slug' => "carrera-test-$suffix",
        'date' => today()->format('Y-m-d'),
        'status' => 'live',
    ], $attributes));
}

function makeWave(Race $race, string $actualStart): Wave
{
    return Wave::query()->create([
        'race_id' => $race->id,
        'name' => 'Única',
        'scheduled_start_at' => '2026-11-23 08:00:00.000',
        'actual_start_at' => $actualStart,
    ]);
}

function addEntry(Event $event, Race $race, Wave $wave, Category $category, string $gender, int $bib, bool $minor = false): Entry
{
    $person = Person::query()->create([
        'ulid' => strtolower((string) str()->ulid()),
        'document' => 'V-'.str()->random(8),
        'first_name' => 'Nombre'.$bib,
        'last_name' => 'Apellido'.$bib,
        'birthdate' => $minor ? '2012-05-10' : '1990-03-15',
        'gender' => $gender,
    ]);

    return Entry::query()->create([
        'event_id' => $event->id,
        'category_id' => $category->id,
        'race_id' => $race->id,
        'wave_id' => $wave->id,
        'person_id' => $person->id,
        'team_id' => null,
        'bib' => $bib,
        'status' => 'registered',
    ]);
}

function addMark(Event $event, Entry $entry, string $capturedAt): TimingMark
{
    return TimingMark::query()->create([
        'event_id' => $event->id,
        'entry_id' => $entry->id,
        'client_mark_id' => (string) str()->uuid(),
        'captured_at' => CarbonImmutable::parse($capturedAt),
        'device_time_ms' => CarbonImmutable::parse($capturedAt)->getTimestampMs(),
        'source' => 'station',
    ]);
}

/**
 * Corrección directa sobre la marca (el motor no pasa por la Action): solo
 * importa su `after`, que es lo que deriva EffectiveMarks.
 *
 * @param  array<string, mixed>  $after
 */
function addCorrection(TimingMark $mark, string $operation, array $after): TimingMarkCorrection
{
    return TimingMarkCorrection::query()->create([
        'ulid' => strtolower((string) str()->ulid()),
        'timing_mark_id' => $mark->id,
        'operation' => $operation,
        'reason' => 'corrección de prueba',
        'account_id' => null,
        'before_after' => ['before' => [], 'after' => $after],
    ]);
}

it('calcula net_ms con la salida real de la oleada y los ajustes acumulados', function () {
    $event = makeEvent();
    $race = Race::query()->create(['event_id' => $event->id, 'name' => '21K']);
    $wave = makeWave($race, '2026-11-23 08:00:00.000');
    $category = Category::query()->create(['event_id' => $event->id, 'name' => 'M 30-39']);
    $entry = addEntry($event, $race, $wave, $category, 'M', 101);

    // penalización de 30 s acumulable
    $entry->adjustments()->create([
        'ulid' => strtolower((string) str()->ulid()),
        'type' => 'penalty',
        'delta_ms' => 30_000,
        'reason' => 'salida nula',
    ]);

    addMark($event, $entry, '2026-11-23 08:30:05.250'); // bruto 1.805.250 ms

    (new RecalculateEventResults)($event->id);

    $result = Result::query()->where('entry_id', $entry->id)->firstOrFail();

    expect($result->net_ms)->toBe(1_805_250 + 30_000)
        ->and($result->rank_general)->toBe(1)
        ->and($result->status)->toBe('provisional');
});

it('asigna posiciones RANK por carrera, género y categoría con empates', function () {
    $event = makeEvent();
    $race = Race::query()->create(['event_id' => $event->id, 'name' => '21K']);
    $wave = makeWave($race, '2026-11-23 08:00:00.000');
    $catA = Category::query()->create(['event_id' => $event->id, 'name' => 'M 30-39']);
    $catF = Category::query()->create(['event_id' => $event->id, 'name' => 'F 30-39']);

    $a = addEntry($event, $race, $wave, $catA, 'M', 1);   // 1000 ms
    $b = addEntry($event, $race, $wave, $catA, 'M', 2);   // 1000 ms (empate)
    $c = addEntry($event, $race, $wave, $catF, 'F', 3);   // 1500 ms
    $d = addEntry($event, $race, $wave, $catA, 'M', 4);   // 3000 ms

    addMark($event, $a, '2026-11-23 08:00:01.000');
    addMark($event, $b, '2026-11-23 08:00:01.000');
    addMark($event, $c, '2026-11-23 08:00:01.500');
    addMark($event, $d, '2026-11-23 08:00:03.000');

    // otra carrera: su clasificación es independiente
    $race2 = Race::query()->create(['event_id' => $event->id, 'name' => '10K']);
    $wave2 = makeWave($race2, '2026-11-23 09:00:00.000');
    $e = addEntry($event, $race2, $wave2, $catA, 'F', 5);
    addMark($event, $e, '2026-11-23 09:00:02.500');

    (new RecalculateEventResults)($event->id);

    $byBib = Entry::query()->where('event_id', $event->id)->with('result')->get()->keyBy('bib');

    // RANK(): el empate comparte posición y la siguiente se salta (1,1,3,4)
    expect($byBib[1]->result->rank_general)->toBe(1)
        ->and($byBib[2]->result->rank_general)->toBe(1)
        ->and($byBib[3]->result->rank_general)->toBe(3)
        ->and($byBib[4]->result->rank_general)->toBe(4)
        // género: M 1,1,3 · F sola 1
        ->and($byBib[1]->result->rank_gender)->toBe(1)
        ->and($byBib[4]->result->rank_gender)->toBe(3)
        ->and($byBib[3]->result->rank_gender)->toBe(1)
        // categoría: catA 1,1,3 · catF 1
        ->and($byBib[4]->result->rank_category)->toBe(3)
        ->and($byBib[3]->result->rank_category)->toBe(1)
        // la otra carrera clasifica aparte
        ->and($byBib[5]->result->rank_general)->toBe(1);
});

it('guarda tiempos mayores a 24 horas completos', function () {
    $event = makeEvent();
    $race = Race::query()->create(['event_id' => $event->id, 'name' => 'Trail 80K']);
    $wave = makeWave($race, '2026-11-23 06:00:00.000');
    $category = Category::query()->create(['event_id' => $event->id, 'name' => 'M 40-49']);
    $entry = addEntry($event, $race, $wave, $category, 'M', 10);

    addMark($event, $entry, '2026-11-24 06:00:01.234'); // 24 h + 1.234 ms

    (new RecalculateEventResults)($event->id);

    expect(Result::query()->where('entry_id', $entry->id)->firstOrFail()->net_ms)
        ->toBe(86_400_000 + 1_234);
});

it('no genera resultado para inscripciones sin llegada', function () {
    $event = makeEvent();
    $race = Race::query()->create(['event_id' => $event->id, 'name' => '21K']);
    $wave = makeWave($race, '2026-11-23 08:00:00.000');
    $category = Category::query()->create(['event_id' => $event->id, 'name' => 'M 30-39']);
    $entry = addEntry($event, $race, $wave, $category, 'M', 20);
    addMark($event, $entry, '2026-11-23 08:00:10.000');

    $sinLlegada = addEntry($event, $race, $wave, $category, 'M', 21);

    (new RecalculateEventResults)($event->id);

    expect(Result::query()->where('entry_id', $sinLlegada->id)->exists())->toBeFalse()
        ->and(Result::query()->count())->toBe(1);
});

it('la marca dispara el recálculo en cola sin bloquear la respuesta', function () {
    $event = makeEvent();
    $race = Race::query()->create(['event_id' => $event->id, 'name' => '21K']);
    $wave = makeWave($race, '2026-11-23 08:00:00.000');
    $category = Category::query()->create(['event_id' => $event->id, 'name' => 'M 30-39']);
    $entry = addEntry($event, $race, $wave, $category, 'M', 30);

    // cola sync en tests: el job corre tras el commit y el resultado aparece
    (new RecordTimingMarkBatch)($event->ulid, [
        ['client_mark_id' => (string) str()->uuid(), 'bib' => 30, 'device_time_ms' => now()->getTimestampMs()],
    ]);

    expect(Result::query()->where('entry_id', $entry->id)->exists())->toBeTrue();
});

it('cierra como oficial y concilia con lo mostrado en vivo', function () {
    $event = makeEvent();
    $race = Race::query()->create(['event_id' => $event->id, 'name' => '21K']);
    $wave = makeWave($race, '2026-11-23 08:00:00.000');
    $category = Category::query()->create(['event_id' => $event->id, 'name' => 'M 30-39']);
    $a = addEntry($event, $race, $wave, $category, 'M', 40);
    $b = addEntry($event, $race, $wave, $category, 'M', 41);
    addMark($event, $a, '2026-11-23 08:10:00.000');
    addMark($event, $b, '2026-11-23 08:20:00.000');

    (new RecalculateEventResults)($event->id);

    $enVivo = Result::query()->get()->map(fn ($r) => [$r->entry_id, $r->net_ms, $r->rank_general])->all();
    expect(Result::query()->pluck('status')->unique()->all())->toBe(['provisional']);

    $event->update(['status' => 'finished']);
    (new RecalculateEventResults)($event->id);

    $oficial = Result::query()->get()->map(fn ($r) => [$r->entry_id, $r->net_ms, $r->rank_general])->all();

    expect(Result::query()->pluck('status')->unique()->all())->toBe(['official'])
        ->and($oficial)->toBe($enVivo); // conciliación: lo de vivo == lo oficial
});

/*
|--------------------------------------------------------------------------
| Estado efectivo: la ÚLTIMA corrección decide (TQ-014, ISSUE-135)
|--------------------------------------------------------------------------
*/

it('el ajuste de la última corrección desplaza el net de la llegada', function () {
    $event = makeEvent();
    $race = Race::query()->create(['event_id' => $event->id, 'name' => '21K']);
    $wave = makeWave($race, '2026-11-23 08:00:00.000');
    $category = Category::query()->create(['event_id' => $event->id, 'name' => 'M 30-39']);
    $entry = addEntry($event, $race, $wave, $category, 'M', 50);
    $mark = addMark($event, $entry, '2026-11-23 08:30:05.250'); // neto 1.805.250

    addCorrection($mark, 'adjust', ['captured_at' => '2026-11-23 08:30:06.250', 'delta_ms' => 1000]);

    (new RecalculateEventResults)($event->id);

    expect(Result::query()->where('entry_id', $entry->id)->firstOrFail()->net_ms)->toBe(1_805_250 + 1_000);
});

it('la marca anulada por corrección queda fuera del cálculo', function () {
    $event = makeEvent();
    $race = Race::query()->create(['event_id' => $event->id, 'name' => '21K']);
    $wave = makeWave($race, '2026-11-23 08:00:00.000');
    $category = Category::query()->create(['event_id' => $event->id, 'name' => 'M 30-39']);
    $entry = addEntry($event, $race, $wave, $category, 'M', 51);
    $mark = addMark($event, $entry, '2026-11-23 08:30:05.250');

    addCorrection($mark, 'void', []);

    (new RecalculateEventResults)($event->id);

    expect(Result::query()->where('entry_id', $entry->id)->exists())->toBeFalse()
        ->and(Result::query()->count())->toBe(0);
});

it('la reasignación mueve la llegada del origen al dorsal destino con su oleada', function () {
    $event = makeEvent();
    $race = Race::query()->create(['event_id' => $event->id, 'name' => '21K']);
    $waveOrigen = makeWave($race, '2026-11-23 08:00:00.000');
    $waveDestino = makeWave($race, '2026-11-23 09:00:00.000');
    $category = Category::query()->create(['event_id' => $event->id, 'name' => 'M 30-39']);
    $origen = addEntry($event, $race, $waveOrigen, $category, 'M', 52);
    $destino = addEntry($event, $race, $waveDestino, $category, 'M', 53);

    $mark = addMark($event, $origen, '2026-11-23 09:30:00.000'); // 90 min en origen, 30 min en destino
    addCorrection($mark, 'reassign', ['bib' => 53, 'entry_id' => $destino->id, 'captured_at' => '2026-11-23 09:30:00.000']);

    (new RecalculateEventResults)($event->id);

    // La llegada vive en el destino (neto contra SU salida real de las 09:00)
    // y el origen se queda sin resultado.
    expect(Result::query()->where('entry_id', $destino->id)->firstOrFail()->net_ms)->toBe(1_800_000)
        ->and(Result::query()->where('entry_id', $origen->id)->exists())->toBeFalse();
});

it('la última corrección gana: corregir y volver a corregir', function () {
    $event = makeEvent();
    $race = Race::query()->create(['event_id' => $event->id, 'name' => '21K']);
    $wave = makeWave($race, '2026-11-23 08:00:00.000');
    $category = Category::query()->create(['event_id' => $event->id, 'name' => 'M 30-39']);
    $entry = addEntry($event, $race, $wave, $category, 'M', 54);
    $mark = addMark($event, $entry, '2026-11-23 08:30:05.250'); // neto crudo 1.805.250

    // Primera corrección +1 s… y el juez vuelve a corregir a −250 ms totales.
    addCorrection($mark, 'adjust', ['captured_at' => '2026-11-23 08:30:06.250', 'delta_ms' => 1000]);
    addCorrection($mark, 'adjust', ['captured_at' => '2026-11-23 08:30:05.000', 'delta_ms' => -250]);

    (new RecalculateEventResults)($event->id);

    expect(Result::query()->where('entry_id', $entry->id)->firstOrFail()->net_ms)->toBe(1_805_250 - 250);

    // Y si la última es anular, la marca desaparece del cálculo aunque hubo
    // ajustes previos.
    $mark->corrections()->create([
        'ulid' => strtolower((string) str()->ulid()),
        'operation' => 'void',
        'reason' => 'lectura falsa al final',
        'account_id' => null,
        'before_after' => ['before' => [], 'after' => []],
    ]);

    (new RecalculateEventResults)($event->id);

    expect(Result::query()->where('entry_id', $entry->id)->exists())->toBeFalse();
});
