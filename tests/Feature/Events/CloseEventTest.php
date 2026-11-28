<?php

declare(strict_types=1);

use App\Actions\Events\CloseEvent;
use App\Actions\Events\PreviewCloseEvent;
use App\Actions\Results\RecalculateEventResults;
use App\Broadcasting\EventClosed;
use App\Models\Account;
use App\Models\Category;
use App\Models\Entry;
use App\Models\Event;
use App\Models\Person;
use App\Models\Race;
use App\Models\Result;
use App\Models\TimingMark;
use App\Models\Wave;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

/**
 * ISSUE-163 (TQ-034) — Cierre/publicación del evento: guardas accionables
 * (oleadas con salida real, al menos una llegada, estado operable), estado
 * → closed con recálculo OFICIAL síncrono (conciliación: lo de vivo == lo
 * oficial) y emisión dual de event.closed (ops + pública sin PII) retenida
 * hasta el commit (ADR-013). El botón del hub se pinta solo con
 * results.publish (matriz ISSUE-159: organizer publica, official no).
 */
uses(RefreshDatabase::class);

function closeAccount(string $role): Account
{
    $account = Account::factory()->create();
    $account->assignRole($role);

    return $account;
}

function closeEvent(array $attributes = []): Event
{
    $suffix = str()->random(6);

    return Event::query()->create(array_merge([
        'name' => "Cierre Test $suffix",
        'slug' => "cierre-test-$suffix",
        'date' => today()->format('Y-m-d'),
        'status' => 'live',
    ], $attributes));
}

function closeRace(Event $event, string $name = '21K'): Race
{
    return Race::query()->create(['event_id' => $event->id, 'name' => $name]);
}

function closeWave(Race $race, ?string $actualStart = '2026-11-23 08:00:00.000', string $name = 'Única'): Wave
{
    return Wave::query()->create([
        'race_id' => $race->id,
        'name' => $name,
        'scheduled_start_at' => '2026-11-23 08:00:00.000',
        'actual_start_at' => $actualStart,
    ]);
}

function closeEntry(Event $event, Race $race, Wave $wave, int $bib): Entry
{
    $person = Person::query()->create([
        'ulid' => strtolower((string) str()->ulid()),
        'document' => 'V-'.str()->random(8),
        'first_name' => 'Nombre'.$bib,
        'last_name' => 'Apellido'.$bib,
        'birthdate' => '1990-03-15',
        'gender' => 'M',
    ]);

    return Entry::query()->create([
        'event_id' => $event->id,
        'category_id' => Category::query()->create(['event_id' => $event->id, 'name' => 'M 30-39'])->id,
        'race_id' => $race->id,
        'wave_id' => $wave->id,
        'person_id' => $person->id,
        'team_id' => null,
        'bib' => $bib,
        'status' => 'registered',
    ]);
}

function closeMark(Event $event, Entry $entry, string $capturedAt): TimingMark
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
 * Evento con las guardas cumplidas: salida real + al menos una llegada.
 *
 * @return array{0: Event, 1: Entry, 2: Entry}
 */
function closeableEvent(): array
{
    $event = closeEvent();
    $race = closeRace($event);
    $wave = closeWave($race);
    $a = closeEntry($event, $race, $wave, 40);
    $b = closeEntry($event, $race, $wave, 41);
    closeMark($event, $a, '2026-11-23 08:10:00.000');
    closeMark($event, $b, '2026-11-23 08:20:00.000');

    // El vivo ya corrió el motor (job por marca): queda provisional.
    (new RecalculateEventResults)($event->id);

    return [$event, $a, $b];
}

beforeEach(function () {
    $this->seed(RolesPermissionsSeeder::class);
});

/*
|--------------------------------------------------------------------------
| Cierre feliz: estado closed + resultados oficiales conciliados
|--------------------------------------------------------------------------
*/

it('cierra el evento: status closed, results official y posiciones estables', function () {
    [$event, $a, $b] = closeableEvent();

    $enVivo = Result::query()->get()
        ->map(fn (Result $r) => [$r->entry_id, $r->net_ms, $r->rank_general])
        ->all();

    $result = app(CloseEvent::class)($event);

    expect($event->fresh()->status)->toBe('closed')
        ->and($result)->toBe(['status' => 'closed', 'results' => 2])
        ->and(Result::query()->pluck('status')->unique()->all())->toBe(['official'])
        // Conciliación (INV-07): lo oficial es EXACTAMENTE lo que se vio en vivo.
        ->and(Result::query()->get()->map(fn (Result $r) => [$r->entry_id, $r->net_ms, $r->rank_general])->all())->toBe($enVivo);
});

it('encola event.closed en ambos canales tras el cierre', function () {
    Queue::fake();

    [$event] = closeableEvent();

    app(CloseEvent::class)($event);

    Queue::assertPushedOn('broadcast', BroadcastEvent::class);

    $closed = [];
    Queue::pushed(BroadcastEvent::class, function (BroadcastEvent $job) use (&$closed): void {
        if ($job->event instanceof EventClosed) {
            $closed[] = $job->event;
        }
    });

    expect($closed)->toHaveCount(2);

    $ops = array_values(array_filter($closed, static fn ($b): bool => ! $b->public));
    $public = array_values(array_filter($closed, static fn ($b): bool => $b->public));

    expect($ops)->toHaveCount(1)
        ->and($public)->toHaveCount(1)
        ->and($ops[0]->broadcastAs())->toBe('event.closed')
        ->and($ops[0]->broadcastOn())->toHaveCount(1)
        ->and($ops[0]->broadcastOn()[0]->name)->toBe("private-event.{$event->ulid}.ops")
        ->and($public[0]->broadcastOn()[0]->name)->toBe("event.{$event->ulid}.public");

    // Contrato sin PII: {eventUlid, seq, closedAt} — nada de personas.
    foreach ([$ops[0], $public[0]] as $variant) {
        $payload = $variant->broadcastWith();

        expect(array_keys($payload))->toBe(['eventUlid', 'seq', 'closedAt'])
            ->and($payload['eventUlid'])->toBe($event->ulid)
            ->and($payload['seq'])->toBeNull()
            ->and($payload['closedAt'])->toBeInt();
    }
});

it('retiene la emisión del cierre hasta el commit de la transacción', function () {
    Queue::fake();

    [$event] = closeableEvent();

    DB::beginTransaction();
    app(CloseEvent::class)($event);

    Queue::assertNotPushed(BroadcastEvent::class);

    DB::commit();

    Queue::assertPushedOn('broadcast', BroadcastEvent::class);
    expect($event->fresh()->status)->toBe('closed');
});

it('un rollback del cierre no cambia el estado ni emite nada', function () {
    Queue::fake();

    [$event] = closeableEvent();

    DB::beginTransaction();
    app(CloseEvent::class)($event);
    DB::rollBack();

    Queue::assertNotPushed(BroadcastEvent::class);
    expect($event->fresh()->status)->toBe('live');
});

/*
|--------------------------------------------------------------------------
| Guardas: rechazos accionables sin tocar el estado
|--------------------------------------------------------------------------
*/

/** Ejecuta el cierre esperando el rechazo y devuelve el mensaje del servidor. */
function closeExpectRejection(Event $event): string
{
    try {
        app(CloseEvent::class)($event);
    } catch (ValidationException $exception) {
        return (string) collect($exception->errors())->flatten()->first();
    }

    throw new RuntimeException('El cierre debió rechazarse y no lanzó ValidationException.');
}

it('rechaza si una oleada sigue sin salida real, nombrándola', function () {
    $event = closeEvent();
    $race = closeRace($event, '10K');
    $wave = closeWave($race, null, 'Élite');
    $entry = closeEntry($event, $race, $wave, 10);
    closeMark($event, $entry, '2026-11-23 08:10:00.000');

    $message = closeExpectRejection($event);

    expect($message)->toBe('La oleada «10K · Élite» sigue pendiente: marca su salida real antes de cerrar.')
        ->and($event->fresh()->status)->toBe('live');
});

it('rechaza con cero llegadas aunque todas las oleadas salieron', function () {
    $event = closeEvent();
    $race = closeRace($event);
    $wave = closeWave($race);
    closeEntry($event, $race, $wave, 10);

    $message = closeExpectRejection($event);

    expect($message)->toBe('El evento no tiene llegadas registradas: captura al menos una antes de cerrar.')
        ->and($event->fresh()->status)->toBe('live');
});

it('rechaza si el evento ya está cerrado', function () {
    [$event] = closeableEvent();
    $event->update(['status' => 'closed']);

    $message = closeExpectRejection($event);

    expect($message)->toBe('El evento ya está cerrado.');
});

it('rechaza un evento sin oleadas con error accionable', function () {
    $event = closeEvent();
    closeRace($event);

    $message = closeExpectRejection($event);

    expect($message)->toBe('El evento no tiene oleadas configuradas: configura sus carreras y oleadas antes de cerrar.')
        ->and($event->fresh()->status)->toBe('live');
});

it('rechaza estados no operables (draft/registration) sin tocar el estado', function (string $status) {
    $event = closeEvent(['status' => $status]);

    $message = closeExpectRejection($event);

    expect($message)->toBe('Solo un evento en vivo o finalizado puede cerrarse (está en estado '.__("ui.events.status.{$status}").').')
        ->and($event->fresh()->status)->toBe($status);
})->with(['draft', 'registration']);

it('el rechazo no cambia el estado ni los resultados previos', function () {
    [$event] = closeableEvent();

    $before = Result::query()->get()
        ->map(fn (Result $r) => [$r->entry_id, $r->net_ms, $r->rank_general, $r->status])
        ->all();

    // Bloqueo por oleada pendiente: se anula la salida real ya guardada.
    Wave::query()->whereRelation('race', 'event_id', $event->id)
        ->update(['actual_start_at' => null]);

    try {
        app(CloseEvent::class)($event);
        $this->fail('El cierre con una oleada pendiente debió rechazarse.');
    } catch (ValidationException $exception) {
        expect(collect($exception->errors())->flatten()->first())
            ->toBe('La oleada «21K · Única» sigue pendiente: marca su salida real antes de cerrar.');
    }

    expect($event->fresh()->status)->toBe('live')
        ->and(Result::query()->get()
            ->map(fn (Result $r) => [$r->entry_id, $r->net_ms, $r->rank_general, $r->status])
            ->all())->toBe($before);
});

/*
|--------------------------------------------------------------------------
| Previsualización: misma fuente de guardas que la confirmación del hub
|--------------------------------------------------------------------------
*/

it('la previsualización reporta todos los blockers sin mutar nada', function () {
    $event = closeEvent();
    $race = closeRace($event);
    closeWave($race, null, 'Élite');

    $preview = app(PreviewCloseEvent::class)($event);

    expect($preview['can'])->toBeFalse()
        ->and($preview['blockers'])->toHaveCount(2)
        ->and($preview['blockers'][0])->toBe('La oleada «21K · Élite» sigue pendiente: marca su salida real antes de cerrar.')
        ->and($preview['blockers'][1])->toBe('El evento no tiene llegadas registradas: captura al menos una antes de cerrar.')
        ->and($event->fresh()->status)->toBe('live');
});

it('la previsualización aprueba un evento con las guardas cumplidas', function () {
    [$event] = closeableEvent();

    $preview = app(PreviewCloseEvent::class)($event);

    expect($preview)->toBe(['can' => true, 'blockers' => []]);
});

/*
|--------------------------------------------------------------------------
| Hub: botón con results.publish y ejecución por Livewire
|--------------------------------------------------------------------------
*/

it('el hub muestra el botón de cierre a quien publica y no al juez', function () {
    [$event] = closeableEvent();

    $html = (string) $this->actingAs(closeAccount('organizer'))
        ->get(route('events.show', $event->ulid))
        ->getContent();

    expect($html)->toContain('Cerrar evento');

    $official = (string) $this->actingAs(closeAccount('official'))
        ->get(route('events.show', $event->ulid))
        ->getContent();

    // official tiene results.view (ve la clasificación) pero NO publica.
    expect($official)->not->toContain('Cerrar evento');
});

it('el hub no muestra el botón en un evento ya cerrado', function () {
    [$event] = closeableEvent();
    $event->update(['status' => 'closed']);

    $html = (string) $this->actingAs(closeAccount('organizer'))
        ->get(route('events.show', $event->ulid))
        ->getContent();

    expect($html)->not->toContain('Cerrar evento');
});

it('el hub cierra el evento por Livewire con guardas cumplidas', function () {
    Queue::fake();
    [$event] = closeableEvent();

    $this->actingAs(closeAccount('organizer'));

    Livewire::test('events.event-hub', ['event' => $event])
        ->call('close')
        ->assertDispatched('toast');

    expect($event->fresh()->status)->toBe('closed')
        ->and(Result::query()->pluck('status')->unique()->all())->toBe(['official']);
});

it('el servidor rechaza cerrar desde el hub sin results.publish', function () {
    [$event] = closeableEvent();

    $this->actingAs(closeAccount('official')); // results.view sin publish

    Livewire::test('events.event-hub', ['event' => $event])
        ->call('close')
        ->assertForbidden();

    expect($event->fresh()->status)->toBe('live');
});
