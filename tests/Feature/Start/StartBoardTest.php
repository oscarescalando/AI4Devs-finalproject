<?php

declare(strict_types=1);

use App\Actions\Start\GetEventClock;
use App\Actions\Start\ListWaveBoard;
use App\Actions\Start\MarkWaveStart;
use App\Actions\Start\UndoWaveStart;
use App\Models\Account;
use App\Models\Category;
use App\Models\Entry;
use App\Models\Event;
use App\Models\Person;
use App\Models\Race;
use App\Models\Wave;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/**
 * ISSUE-132/133 (TQ-013, versión mínima F1) — Tablero de salidas: el Volt
 * delega en ListWaveBoard (inscritos sobre el TOTAL de la oleada, RF-16) y
 * en MarkWaveStart/UndoWaveStart, que guardan la hora real del servidor
 * sin tocar la programada (RF-15) y rechazan fuera de orden. El reloj pica
 * en Alpine con offset contra server_now y se re-resincroniza (RF-42);
 * check-in, broadcast y auditoría quedan diferidos (TQ-013).
 */
uses(RefreshDatabase::class);

function startBoardAccount(string $role): Account
{
    $account = Account::factory()->create();
    $account->assignRole($role);

    return $account;
}

function startBoardDemoEvent(): Event
{
    return Event::query()->where('slug', 'vuela-caracas-21k')->firstOrFail();
}

/** @return Collection<int, Wave> */
function startBoardOrderedWaves(Event $event): Collection
{
    return Wave::query()
        ->whereRelation('race', 'event_id', $event->id)
        ->with('race')
        ->orderBy('scheduled_start_at')
        ->orderBy('id')
        ->get();
}

/**
 * Evento con una oleada masiva (120 inscritos) y una corta (3): los
 * contadores del tablero deben dar el TOTAL de cada oleada, jamás una
 * página (RF-16).
 *
 * @return array{0: Event, 1: Wave, 2: Wave}
 */
function startBoardMassiveEvent(): array
{
    $event = Event::query()->create([
        'name' => 'Masiva 10K',
        'slug' => 'masiva-10k-prueba',
        'date' => '2026-12-06',
        'timezone' => 'America/Caracas',
        'status' => 'live',
    ]);
    $race = Race::query()->create(['event_id' => $event->id, 'name' => '10K']);
    $category = Category::query()->create([
        'event_id' => $event->id,
        'name' => 'General',
        'for_teams' => false,
        'min_age' => 18,
        'max_age' => null,
    ]);

    $massive = Wave::query()->create(['race_id' => $race->id, 'name' => 'Masiva', 'scheduled_start_at' => '2026-12-06 07:00:00']);
    $short = Wave::query()->create(['race_id' => $race->id, 'name' => 'Corta', 'scheduled_start_at' => '2026-12-06 07:30:00']);

    $bib = 1;
    foreach ([[$massive, 120], [$short, 3]] as [$wave, $count]) {
        foreach (range(1, $count) as $i) {
            Entry::query()->create([
                'event_id' => $event->id,
                'category_id' => $category->id,
                'race_id' => $race->id,
                'wave_id' => $wave->id,
                'person_id' => Person::factory()->create()->id,
                'team_id' => null,
                'bib' => $bib++,
                'status' => 'registered',
            ]);
        }
    }

    return [$event, $massive, $short];
}

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

it('renderiza el tablero con start.manage y rechaza con 403 sin el permiso', function () {
    $event = startBoardDemoEvent();

    $this->actingAs(startBoardAccount('organizer'))->get(route('events.start', $event))
        ->assertOk()
        ->assertSee('Salidas')
        ->assertSee($event->name);
    $this->actingAs(startBoardAccount('official'))->get(route('events.start', $event))->assertOk();

    // El cronometrador tiene marks.view/marks.manage pero NO start.manage
    // (RolesPermissionsSeeder): la autorización es del servidor (ADR-011).
    $this->actingAs(startBoardAccount('timekeeper'))->get(route('events.start', $event))->assertForbidden();
});

it('traza el tablero: reloj con server_now, sondeo y botones según el estado', function () {
    $event = startBoardDemoEvent();

    $html = $this->actingAs(startBoardAccount('official'))->get(route('events.start', $event))->getContent();

    expect($html)->toContain('wire:poll.5s')
        ->and($html)->toContain('tcStartClock')
        // El demo viene con las tres salidas marcadas: deshacer sí, marcar no.
        ->and($html)->toContain('Deshacer salida')
        ->and($html)->not->toContain('Marcar salida')
        // Mono tabular para horas y contadores (spec/09-ui-estilo.md §1.3/§8).
        ->and($html)->toContain('td-right mono');
});

it('cuenta los inscritos sobre el TOTAL de la oleada, nunca sobre una página (RF-16)', function () {
    [$event, $massive, $short] = startBoardMassiveEvent();

    $rows = app(ListWaveBoard::class)($event);

    expect($rows[0]['ulid'])->toBe($massive->ulid)
        ->and($rows[0]['entries_total'])->toBe(120)
        ->and($rows[1]['ulid'])->toBe($short->ulid)
        ->and($rows[1]['entries_total'])->toBe(3);

    // La pantalla pinta el número exacto: la paginación de otros listados
    // jamás recorta este contador.
    $html = $this->actingAs(startBoardAccount('organizer'))->get(route('events.start', $event))->getContent();

    expect($html)->toContain('>120<')
        ->and($html)->toContain('>3<');
});

it('marca la salida real con la hora del servidor, sin tocar la programada y solo en la primera pendiente', function () {
    $event = startBoardDemoEvent();
    $waves = startBoardOrderedWaves($event);
    $waves->each(fn (Wave $wave) => $wave->forceFill(['actual_start_at' => null])->save());

    $first = $waves[0];
    $second = $waves[1];

    // Una oleada anterior pendiente bloquea: error de negocio, nada guardado.
    Livewire::test('events.start-board', ['event' => $event])
        ->call('markStart', $second->ulid)
        ->assertDispatched('toast', type: 'danger');
    expect($second->fresh()->actual_start_at)->toBeNull();

    // La primera sí: guarda la real (ahora del servidor, RF-15) y deja la
    // programada exactamente como estaba.
    Livewire::test('events.start-board', ['event' => $event])
        ->call('markStart', $first->ulid)
        ->assertDispatched('toast', type: 'success');

    $marked = $first->fresh();
    expect($marked->actual_start_at)->not->toBeNull()
        ->and($marked->actual_start_at->getTimestampMs())->toBeGreaterThan(now()->getTimestampMs() - 60_000)
        ->and($marked->actual_start_at->getTimestampMs())->toBeLessThan(now()->getTimestampMs() + 60_000)
        ->and($marked->scheduled_start_at->getTimestampMs())->toBe($first->scheduled_start_at->getTimestampMs())
        ->and($second->fresh()->actual_start_at)->toBeNull();
});

it('deshace la salida real con confirmación y en orden inverso', function () {
    $event = startBoardDemoEvent();
    $waves = startBoardOrderedWaves($event); // las tres traen real del seeder

    // Con oleadas posteriores iniciadas no se deshace: error y nada anulado.
    Livewire::test('events.start-board', ['event' => $event])
        ->call('undoStart', $waves[0]->ulid)
        ->assertDispatched('toast', type: 'danger');
    expect($waves[0]->fresh()->actual_start_at)->not->toBeNull();

    // La última sí: la real queda null y la programada intacta (RF-15).
    $last = $waves[2];
    $scheduledBefore = $last->scheduled_start_at->getTimestampMs();

    Livewire::test('events.start-board', ['event' => $event])
        ->call('undoStart', $last->ulid)
        ->assertDispatched('toast', type: 'success');

    $undone = $last->fresh();
    expect($undone->actual_start_at)->toBeNull()
        ->and($undone->scheduled_start_at->getTimestampMs())->toBe($scheduledBefore);

    // Deshacer de nuevo (sin real) → error de negocio, no excepción cruda.
    Livewire::test('events.start-board', ['event' => $event])
        ->call('undoStart', $last->ulid)
        ->assertDispatched('toast', type: 'danger');
});

it('las guardas de negocio de marcar/deshacer lanzan ValidationException sin escribir', function () {
    [$event, $massive, $short] = startBoardMassiveEvent();

    // ULID que ya no existe en el evento: error de negocio consumible, no 404.
    $ghost = (string) Str::ulid();
    expect(fn () => app(MarkWaveStart::class)($event, $ghost))->toThrow(ValidationException::class);

    // Fuera de orden: la corta no puede salir antes que la masiva.
    expect(fn () => app(MarkWaveStart::class)($event, $short->ulid))->toThrow(ValidationException::class);
    expect($short->fresh()->actual_start_at)->toBeNull();

    app(MarkWaveStart::class)($event, $massive->ulid);

    // Repetida: ya tiene su salida marcada.
    expect(fn () => app(MarkWaveStart::class)($event, $massive->ulid))->toThrow(ValidationException::class);

    // Deshacer una oleada que no tiene salida marcada.
    expect(fn () => app(UndoWaveStart::class)($event, $short->ulid))->toThrow(ValidationException::class);
    expect($massive->fresh()->actual_start_at)->not->toBeNull();
});

it('expone la hora del servidor para calcular el offset del reloj (RF-42)', function () {
    $event = startBoardDemoEvent();

    $html = $this->actingAs(startBoardAccount('organizer'))->get(route('events.start', $event))->getContent();

    expect($html)->toContain('server_now');

    // La semilla viaja en el render (@js escapa las comillas): el número
    // tras server_now es la hora con la que el navegador calcula el offset.
    expect(preg_match('/server_now.{0,24}?(\d{12,14})/', $html, $match))->toBe(1);

    $offset = (int) $match[1] - now()->getTimestampMs();
    expect(abs($offset))->toBeLessThan(10_000);

    // La re-resincronización pide la hora oficial fresca en la zona del evento.
    $clock = app(GetEventClock::class)($event);
    expect($clock['server_now'])->toBeGreaterThan(now()->getTimestampMs() - 10_000)
        ->and($clock['server_now'])->toBeLessThan(now()->getTimestampMs() + 10_000)
        ->and($clock['timezone'])->toBe($event->timezone);

    Livewire::test('events.start-board', ['event' => $event])->call('syncClock');
});

it('tras marcar la primera oleada, la siguiente pasa a ser la marcable', function () {
    $event = startBoardDemoEvent();
    startBoardOrderedWaves($event)->each(fn (Wave $wave) => $wave->forceFill(['actual_start_at' => null])->save());

    $rows = app(ListWaveBoard::class)($event);
    expect($rows[0]['is_next'])->toBeTrue()
        ->and($rows[0]['status'])->toBe('pending')
        ->and($rows[1]['is_next'])->toBeFalse()
        ->and($rows[2]['is_next'])->toBeFalse();

    app(MarkWaveStart::class)($event, $rows[0]['ulid']);

    $rows = app(ListWaveBoard::class)($event);
    expect($rows[0]['status'])->toBe('started')
        ->and($rows[0]['is_next'])->toBeFalse()
        ->and($rows[1]['is_next'])->toBeTrue()
        ->and($rows[2]['is_next'])->toBeFalse();
});
