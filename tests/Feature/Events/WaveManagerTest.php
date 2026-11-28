<?php

declare(strict_types=1);

use App\Actions\Events\ReprogramWaves;
use App\Livewire\Events\WaveManager;
use App\Models\Account;
use App\Models\Category;
use App\Models\Entry;
use App\Models\Event;
use App\Models\Person;
use App\Models\Race;
use App\Models\Wave;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * ISSUE-160 fase 2 (TQ-026) — Editor de oleadas + reprogramación en bloque
 * (ISSUE-050): render por rol, alta/edición con hora programada datetime(3)
 * (pared del evento ↔ zona de la app con milisegundos, vía EventTime),
 * baja bloqueada por inscripciones y bloque transaccional: inicial +
 * i·intervalo para todas las oleadas de UNA carrera o ninguna.
 *
 * Las horas se siembran y se comprueban como PARED del evento
 * (America/Caracas): la conversión al almacenamiento la hace EventTime con
 * config('app.timezone'), sea cual sea la zona configurada.
 */
uses(RefreshDatabase::class);

function wavesAccount(string $role): Account
{
    $account = Account::factory()->create();
    $account->assignRole($role);

    return $account;
}

function makeWaveEvent(string $name = 'VuelaCaracas 21K'): Event
{
    return Event::query()->create([
        'name' => $name,
        'slug' => Str::slug($name).'-'.Str::lower((string) Str::ulid()),
        'date' => '2026-12-05',
        'timezone' => 'America/Caracas',
        'status' => 'registration',
    ]);
}

function seedRace(Event $event, string $name): Race
{
    return Race::query()->create(['event_id' => $event->id, 'name' => $name]);
}

/**
 * Oleada con hora programada escrita como pared del evento: EventTime la
 * lleva a la zona de almacenamiento (datetime(3) con milisegundos).
 */
function seedWave(Race $race, string $name, string $caracasAt): Wave
{
    return Wave::query()->create([
        'race_id' => $race->id,
        'name' => $name,
        'scheduled_start_at' => CarbonImmutable::parse($caracasAt, 'America/Caracas')
            ->setTimezone(config('app.timezone')),
    ]);
}

/**
 * Inscripción mínima sobre una oleada (necesita categoría y persona).
 */
function seedEntryOnWave(Event $event, Race $race, Wave $wave, int $bib): Entry
{
    $category = Category::query()->firstOrCreate(
        ['event_id' => $event->id, 'name' => 'General'],
        ['for_teams' => false, 'min_age' => 18, 'max_age' => null],
    );

    return Entry::query()->create([
        'event_id' => $event->id,
        'category_id' => $category->id,
        'race_id' => $race->id,
        'wave_id' => $wave->id,
        'person_id' => Person::factory()->create()->id,
        'team_id' => null,
        'bib' => $bib,
    ]);
}

beforeEach(function () {
    $this->seed(RolesPermissionsSeeder::class);
});

/*
|--------------------------------------------------------------------------
| Acceso (la UI oculta; el servidor autoriza — ADR-011)
|--------------------------------------------------------------------------
*/

it('muestra el editor de oleadas al organizador y al superadmin', function (string $role) {
    $event = makeWaveEvent();

    $this->actingAs(wavesAccount($role))
        ->get(route('events.waves', $event->ulid))
        ->assertOk()
        ->assertSee('Oleadas')
        ->assertSee($event->name);
})->with(['organizer', 'superadmin']);

it('rechaza con 403 a roles sin events.manage', function () {
    $event = makeWaveEvent();

    $this->actingAs(wavesAccount('timekeeper'))
        ->get(route('events.waves', $event->ulid))
        ->assertForbidden();
});

it('el servidor rechaza guardar y reprogramar sin events.manage aunque la UI se salte', function () {
    $event = makeWaveEvent();
    $race = seedRace($event, '21K');
    $this->actingAs(wavesAccount('admin')); // events.view sin manage

    Livewire::test(WaveManager::class, ['event' => $event->ulid])
        ->set('form.name', 'Ilegal')
        ->set('form.race', $race->ulid)
        ->set('form.scheduled_start_at', '2026-12-05T07:00')
        ->call('save')
        ->assertForbidden();

    Livewire::test(WaveManager::class, ['event' => $event->ulid])
        ->call('startReprogram', $race->ulid)
        ->set('reprogram.start_at', '2026-12-05T07:00')
        ->set('reprogram.interval', '15')
        ->call('previewReprogram')
        ->assertForbidden();

    expect(Wave::query()->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Listado agrupado por carrera (dd/mm/yyyy H:i:s.v en la zona del evento)
|--------------------------------------------------------------------------
*/

it('lista las oleadas agrupadas por carrera con su hora programada', function () {
    $event = makeWaveEvent();
    $race21k = seedRace($event, '21K');
    $race10k = seedRace($event, '10K');
    seedWave($race21k, 'Élite', '2026-12-05T07:00:00');
    seedWave($race21k, 'General', '2026-12-05T07:15:30.250');
    seedWave($race10k, 'Única', '2026-12-05T08:30:00');

    $html = (string) $this->actingAs(wavesAccount('organizer'))
        ->get(route('events.waves', $event->ulid))
        ->getContent();

    // Carreras como grupos y horas del evento en mono tabular, con ms.
    expect($html)
        ->toContain('21K')
        ->toContain('10K')
        ->toContain('05/12/2026 07:00:00.000')
        ->toContain('05/12/2026 07:15:30.250')
        ->toContain('05/12/2026 08:30:00.000')
        ->toContain('td-right mono')
        ->toContain('wire:confirm'); // destructiva con confirmación
});

it('muestra el estado vacío por carrera y el aviso cuando el evento no tiene carreras', function () {
    $this->actingAs(wavesAccount('organizer'));
    $event = makeWaveEvent();

    Livewire::test(WaveManager::class, ['event' => $event->ulid])
        ->assertSee('Este evento no tiene carreras');

    seedRace($event, '21K');

    Livewire::test(WaveManager::class, ['event' => $event->ulid])
        ->assertSee('Sin oleadas en esta carrera')
        ->assertSee('Reprogramar en bloque');
});

/*
|--------------------------------------------------------------------------
| Crear (hora con milisegundos, pared del evento → almacenamiento)
|--------------------------------------------------------------------------
*/

it('crea una oleada con hora programada en milisegundos', function () {
    $this->actingAs(wavesAccount('organizer'));
    $event = makeWaveEvent();
    $race = seedRace($event, '21K');

    Livewire::test(WaveManager::class, ['event' => $event->ulid])
        ->set('form.name', 'Élite')
        ->set('form.race', $race->ulid)
        ->set('form.scheduled_start_at', '2026-12-05T07:00:30.125')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('toast')
        ->assertDispatched('close-wave-modal');

    $wave = Wave::query()->sole();

    // El instante guardado es exactamente 07:00:30.125 Caracas (datetime(3)).
    $expected = CarbonImmutable::parse('2026-12-05T07:00:30.125', 'America/Caracas');

    expect($wave->race_id)->toBe($race->id)
        ->and($wave->scheduled_start_at->getTimestampMs())->toBe($expected->getTimestampMs())
        ->and($wave->scheduled_start_at->setTimezone('America/Caracas')->format('H:i:s.v'))->toBe('07:00:30.125');
});

it('exige nombre, carrera propia del evento y hora programada', function () {
    $this->actingAs(wavesAccount('organizer'));
    $event = makeWaveEvent();
    $race = seedRace($event, '21K');

    $other = makeWaveEvent('Evento Ajeno');
    $foreignRace = seedRace($other, '10K');

    $component = Livewire::test(WaveManager::class, ['event' => $event->ulid]);

    $component->set('form.race', $race->ulid)
        ->set('form.scheduled_start_at', '2026-12-05T07:00')
        ->set('form.name', '')
        ->call('save')
        ->assertHasErrors(['form.name']);

    $component->set('form.name', 'Sin Carrera')
        ->set('form.race', '')
        ->call('save')
        ->assertHasErrors(['form.race']);

    // Carrera de otro evento: el select no la ofrece y el servidor la rechaza.
    $component->set('form.race', $foreignRace->ulid)
        ->call('save')
        ->assertHasErrors(['form.race']);

    $component->set('form.race', $race->ulid)
        ->set('form.scheduled_start_at', '')
        ->call('save')
        ->assertHasErrors(['form.scheduled_start_at']);

    expect(Wave::query()->count())->toBe(0);
});

it('rechaza un nombre repetido dentro de la misma carrera', function () {
    $this->actingAs(wavesAccount('organizer'));
    $event = makeWaveEvent();
    $race = seedRace($event, '21K');
    seedWave($race, 'Élite', '2026-12-05T07:00:00');

    Livewire::test(WaveManager::class, ['event' => $event->ulid])
        ->set('form.name', 'Élite')
        ->set('form.race', $race->ulid)
        ->set('form.scheduled_start_at', '2026-12-05T08:00')
        ->call('save')
        ->assertHasErrors(['form.name'])
        ->assertSee('Ya existe una oleada');

    expect(Wave::query()->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Editar (prefill con ms, reprogramación de una y cambio de carrera)
|--------------------------------------------------------------------------
*/

it('carga la oleada al editar con la pared del evento y guarda cambios', function () {
    $this->actingAs(wavesAccount('organizer'));
    $event = makeWaveEvent();
    $race21k = seedRace($event, '21K');
    $race10k = seedRace($event, '10K');
    $wave = seedWave($race21k, 'Élite', '2026-12-05T07:00:30.125');

    Livewire::test(WaveManager::class, ['event' => $event->ulid])
        ->call('startEdit', $wave->ulid)
        ->assertSet('editingUlid', $wave->ulid)
        ->assertSet('form.name', 'Élite')
        ->assertSet('form.race', $race21k->ulid)
        ->assertSet('form.scheduled_start_at', '2026-12-05T07:00:30.125')
        ->assertDispatched('open-wave-modal');

    // Cambia hora y carrera dentro del mismo evento.
    Livewire::test(WaveManager::class, ['event' => $event->ulid])
        ->call('startEdit', $wave->ulid)
        ->set('form.scheduled_start_at', '2026-12-05T08:45:00.500')
        ->set('form.race', $race10k->ulid)
        ->call('save')
        ->assertHasNoErrors();

    $wave->refresh();

    expect($wave->race_id)->toBe($race10k->id)
        ->and($wave->scheduled_start_at->setTimezone('America/Caracas')->format('H:i:s.v'))->toBe('08:45:00.500');
});

it('no deja colar una oleada de otro evento por su ULID', function () {
    $this->actingAs(wavesAccount('organizer'));
    $mine = makeWaveEvent('Evento Mío');
    $other = makeWaveEvent('Evento Ajeno');
    $foreignRace = seedRace($other, '21K');
    $foreign = seedWave($foreignRace, 'Élite', '2026-12-05T07:00:00');

    Livewire::test(WaveManager::class, ['event' => $mine->ulid])
        ->call('startEdit', $foreign->ulid)
        ->assertDispatched('toast', type: 'error')
        ->assertSet('editingUlid', null);
});

/*
|--------------------------------------------------------------------------
| Borrar (bloqueado con inscripciones — FK restrict)
|--------------------------------------------------------------------------
*/

it('elimina una oleada sin inscripciones tras confirmar', function () {
    $this->actingAs(wavesAccount('organizer'));
    $event = makeWaveEvent();
    $race = seedRace($event, '21K');
    $wave = seedWave($race, 'Élite', '2026-12-05T07:00:00');

    Livewire::test(WaveManager::class, ['event' => $event->ulid])
        ->call('deleteWave', $wave->ulid)
        ->assertDispatched('toast', type: 'success');

    expect(Wave::query()->whereKey($wave->id)->exists())->toBeFalse();
});

it('bloquea la baja de una oleada con inscripciones con error de negocio', function () {
    $this->actingAs(wavesAccount('organizer'));
    $event = makeWaveEvent();
    $race = seedRace($event, '21K');
    $wave = seedWave($race, 'Élite', '2026-12-05T07:00:00');
    seedEntryOnWave($event, $race, $wave, 101);

    Livewire::test(WaveManager::class, ['event' => $event->ulid])
        ->call('deleteWave', $wave->ulid)
        ->assertDispatched('toast', type: 'error');

    expect(Wave::query()->whereKey($wave->id)->exists())->toBeTrue()
        ->and(Entry::query()->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Reprogramación en bloque (ISSUE-050): confirmación + UNA transacción
|--------------------------------------------------------------------------
*/

it('reprograma todas las oleadas de la carrera como inicial + i·intervalo', function () {
    $this->actingAs(wavesAccount('organizer'));
    $event = makeWaveEvent();
    $race21k = seedRace($event, '21K');
    $race10k = seedRace($event, '10K');
    $elite = seedWave($race21k, 'Élite', '2026-12-05T07:00:00');
    $general = seedWave($race21k, 'General', '2026-12-05T07:10:00');
    $late = seedWave($race21k, 'Tarde', '2026-12-05T07:20:00');
    $ajena = seedWave($race10k, 'Única', '2026-12-05T08:30:00');

    $component = Livewire::test(WaveManager::class, ['event' => $event->ulid])
        ->call('startReprogram', $race21k->ulid)
        ->assertDispatched('open-reprogram-modal')
        ->set('reprogram.start_at', '2026-12-05T08:00')
        ->set('reprogram.interval', '15')
        ->call('previewReprogram')
        ->assertHasNoErrors()
        ->assertSet('reprogramConfirm', true)
        ->assertSet('reprogramCount', 3)
        ->assertSet('reprogramFirst', '05/12/2026 08:00:00.000')
        ->assertSet('reprogramLast', '05/12/2026 08:30:00.000')
        ->assertSee('Se reprogramarán 3 oleadas');

    // Una sola operación: confirm y toast con el alcance.
    $component->call('confirmReprogram')
        ->assertDispatched('toast')
        ->assertDispatched('close-reprogram-modal');

    $elite->refresh();
    $general->refresh();
    $late->refresh();
    $ajena->refresh();

    expect($elite->scheduled_start_at->setTimezone('America/Caracas')->format('H:i:s.v'))->toBe('08:00:00.000')
        ->and($general->scheduled_start_at->setTimezone('America/Caracas')->format('H:i:s.v'))->toBe('08:15:00.000')
        ->and($late->scheduled_start_at->setTimezone('America/Caracas')->format('H:i:s.v'))->toBe('08:30:00.000')
        // La otra carrera no se toca.
        ->and($ajena->scheduled_start_at->setTimezone('America/Caracas')->format('H:i:s.v'))->toBe('08:30:00.000');
});

it('valida la hora inicial y el intervalo antes de confirmar', function () {
    $this->actingAs(wavesAccount('organizer'));
    $event = makeWaveEvent();
    $race = seedRace($event, '21K');
    seedWave($race, 'Élite', '2026-12-05T07:00:00');

    $component = Livewire::test(WaveManager::class, ['event' => $event->ulid])
        ->call('startReprogram', $race->ulid);

    $component->set('reprogram.start_at', '')
        ->set('reprogram.interval', '15')
        ->call('previewReprogram')
        ->assertHasErrors(['reprogram.start_at'])
        ->assertSet('reprogramConfirm', false);

    $component->set('reprogram.start_at', '2026-12-05T08:00')
        ->set('reprogram.interval', '0')
        ->call('previewReprogram')
        ->assertHasErrors(['reprogram.interval'])
        ->assertSet('reprogramConfirm', false);

    expect(Wave::query()->sole()->scheduled_start_at->setTimezone('America/Caracas')->format('H:i:s.v'))
        ->toBe('07:00:00.000');
});

it('avisa cuando la carrera no tiene oleadas que reprogramar', function () {
    $this->actingAs(wavesAccount('organizer'));
    $event = makeWaveEvent();
    $race = seedRace($event, '21K');

    Livewire::test(WaveManager::class, ['event' => $event->ulid])
        ->call('startReprogram', $race->ulid)
        ->set('reprogram.start_at', '2026-12-05T08:00')
        ->set('reprogram.interval', '15')
        ->call('previewReprogram')
        ->assertHasErrors(['reprogram.race'])
        ->assertSet('reprogramConfirm', false);
});

it('una oleada que falla a mitad del bloque revierte TODA la reprogramación', function () {
    $event = makeWaveEvent();
    $race = seedRace($event, '21K');
    $first = seedWave($race, 'Élite', '2026-12-05T07:00:00');
    $second = seedWave($race, 'General', '2026-12-05T07:10:00');

    // Fallo REAL a mitad del lote: la primera oleada cabe en datetime(3)
    // (9999-12-31 23:30) y la segunda se desborda al año 10000 → Carbon o
    // el motor rechazan DENTRO de la transacción (según configuración) y
    // todo vuelve al estado original: nunca media reprogramación.
    try {
        app(ReprogramWaves::class)($event->ulid, $race->ulid, '9999-12-31T23:30', 120);
        $this->fail('La reprogramación debía fallar al desbordar la segunda oleada.');
    } catch (Throwable) {
        // esperado: el año 10000 no cabe en datetime(3)
    }

    $first->refresh();
    $second->refresh();

    expect($first->scheduled_start_at->setTimezone('America/Caracas')->format('Y-m-d H:i:s.v'))->toBe('2026-12-05 07:00:00.000')
        ->and($second->scheduled_start_at->setTimezone('America/Caracas')->format('Y-m-d H:i:s.v'))->toBe('2026-12-05 07:10:00.000');
});

it('ordena el bloque por la hora programada vigente con empate por id', function () {
    $event = makeWaveEvent();
    $race = seedRace($event, '21K');
    $late = seedWave($race, 'Tarde', '2026-12-05T07:30:00');
    $early = seedWave($race, 'Temprana', '2026-12-05T07:00:00');
    $sinHora = seedWave($race, 'Sin Hora', '2026-12-05T09:00:00');
    $sinHora->update(['scheduled_start_at' => null]);

    $count = app(ReprogramWaves::class)($event->ulid, $race->ulid, '2026-12-05T07:00', 10);

    expect($count)->toBe(3)
        ->and($late->name)->toBe('Tarde')
        ->and($early->name)->toBe('Temprana');

    /** @var Collection<string, Wave> $byName */
    $byName = Wave::query()->where('race_id', $race->id)->get()->keyBy('name');

    // Sin hora queda primera (MySQL ASC pone los NULL primero), luego las
    // programadas en su orden vigente.
    expect($byName['Sin Hora']->scheduled_start_at->setTimezone('America/Caracas')->format('H:i'))->toBe('07:00')
        ->and($byName['Temprana']->scheduled_start_at->setTimezone('America/Caracas')->format('H:i'))->toBe('07:10')
        ->and($byName['Tarde']->scheduled_start_at->setTimezone('America/Caracas')->format('H:i'))->toBe('07:20');
});
