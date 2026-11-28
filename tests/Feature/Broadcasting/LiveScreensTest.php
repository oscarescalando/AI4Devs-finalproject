<?php

declare(strict_types=1);

use App\Actions\Results\RecalculateEventResults;
use App\Actions\Start\UndoWaveStart;
use App\Actions\Timing\RegisterManualArrival;
use App\Models\Account;
use App\Models\Entry;
use App\Models\Event;
use App\Models\Result;
use App\Models\TimingMark;
use App\Models\Wave;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

/**
 * ISSUE-142 (TQ-018, EP-26 fase 2) — Listeners de las pantallas vivas: el
 * puente JS (tcBridge) retransmite el payload del WS como evento Livewire
 * tc-mark/tc-wave/tc-results; aquí se simula esa retransmisión con
 * ->dispatch(...) y se afirma el refresco dirigido (loader existente) y la
 * deduplicación por seq. Además las trazas de contrato de la vista: puente
 * presente, live-indicator ligado a tc-echo-status y sondeo de respaldo a
 * 15 s (RF-76) — el puente JS en sí no es testeable en Pest: revisado a
 * mano y documentado en resources/js/app.js (tcBridge).
 */
uses(RefreshDatabase::class);

// El test del single-flight congela el reloj: nunca gotea a los demás.
afterEach(fn () => CarbonImmutable::setTestNow(null));

function liveAccount(string $role): Account
{
    $account = Account::factory()->create();
    $account->assignRole($role);

    return $account;
}

function liveEntry(Event $event, int $bib): Entry
{
    return Entry::query()->where('event_id', $event->id)->where('bib', $bib)->firstOrFail();
}

function liveResultsVersion(Event $event): int
{
    return (int) Result::query()
        ->whereHas('entry', fn ($query) => $query->where('event_id', $event->id))
        ->max('id');
}

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->event = Event::query()->where('slug', 'vuela-caracas-21k')->firstOrFail();
});

it('la captura refresca sus llegadas con tc-mark de seq nueva y deduplica los viejos', function () {
    $entry = liveEntry($this->event, 150);

    $component = Livewire::test('events.arrival-capture', ['event' => $this->event]);

    // La marca se crea DESPUÉS del montaje: el render inicial no la conoce.
    app(RegisterManualArrival::class)($this->event, 150);

    $seq = (int) TimingMark::query()->where('event_id', $this->event->id)->max('id');

    $component->dispatch('tc-mark', payload: ['eventUlid' => $this->event->ulid, 'seq' => $seq])
        ->assertSee($entry->person->publicName())
        ->assertSet('lastMarkSeq', $seq);

    // Evento viejo (seq <= última vista) y evento de otro evento: sin cambio de estado.
    $component->dispatch('tc-mark', payload: ['eventUlid' => $this->event->ulid, 'seq' => $seq - 1])
        ->assertSet('lastMarkSeq', $seq);

    $component->dispatch('tc-mark', payload: ['eventUlid' => (string) Str::ulid(), 'seq' => $seq + 100])
        ->assertSet('lastMarkSeq', $seq);
});

it('el tablero de salidas refresca con tc-wave al deshacer una salida', function () {
    $component = Livewire::test('events.start-board', ['event' => $this->event])
        ->assertDontSee(__('ui.events.start_mark'));

    $wave = Wave::query()
        ->whereRelation('race', 'event_id', $this->event->id)
        ->with('race')
        ->orderByDesc('scheduled_start_at')
        ->orderByDesc('id')
        ->firstOrFail();

    app(UndoWaveStart::class)($this->event, $wave->ulid);

    // wave.undone no versiona datos: el refresco es incondicional.
    $component->dispatch('tc-wave')
        ->assertSee(__('ui.events.start_mark'));
});

it('la bitácora deduplica tc-mark y tc-results por seq', function () {
    $component = Livewire::test('events.mark-log', ['event' => $this->event]);

    app(RegisterManualArrival::class)($this->event, 150);

    $markSeq = (int) TimingMark::query()->where('event_id', $this->event->id)->max('id');

    $component->dispatch('tc-mark', payload: ['eventUlid' => $this->event->ulid, 'seq' => $markSeq])
        ->assertSet('lastMarkSeq', $markSeq)
        ->assertSet('lastResultsSeq', 0);

    $component->dispatch('tc-mark', payload: ['eventUlid' => $this->event->ulid, 'seq' => $markSeq])
        ->assertSet('lastMarkSeq', $markSeq);

    // Versión de results aún no vista: se asume; una versión ya reflejada no mueve el estado.
    app(RecalculateEventResults::class)($this->event->id);
    $version = liveResultsVersion($this->event);

    $component->dispatch('tc-results', payload: ['eventUlid' => $this->event->ulid, 'seq' => $version])
        ->assertSet('lastResultsSeq', $version);

    $component->dispatch('tc-results', payload: ['eventUlid' => $this->event->ulid, 'seq' => $version - 1])
        ->assertSet('lastResultsSeq', $version);
});

it('el ranking del panel refresca la clasificación con tc-results de seq nueva', function () {
    $entry = liveEntry($this->event, 150);

    $component = Livewire::test('events.result-board', ['event' => $this->event]);

    TimingMark::query()->create([
        'event_id' => $this->event->id,
        'entry_id' => $entry->id,
        'client_mark_id' => 'mark-ranking',
        'captured_at' => now(),
        'device_time_ms' => now()->getTimestampMs(),
        'source' => 'station',
    ]);

    app(RecalculateEventResults::class)($this->event->id);

    $component->dispatch('tc-results', payload: ['eventUlid' => $this->event->ulid, 'seq' => liveResultsVersion($this->event)])
        ->assertSee($entry->person->publicName())
        ->assertSet('lastResultsSeq', liveResultsVersion($this->event));
});

it('la pantalla pública refresca con tc-results y resalta la llegada nueva', function () {
    $entry = liveEntry($this->event, 150);

    $component = Livewire::test('public.leaderboard-page', ['slug' => $this->event->slug]);

    TimingMark::query()->create([
        'event_id' => $this->event->id,
        'entry_id' => $entry->id,
        'client_mark_id' => 'mark-public',
        'captured_at' => now(),
        'device_time_ms' => now()->getTimestampMs(),
        'source' => 'station',
    ]);

    app(RecalculateEventResults::class)($this->event->id);

    $seq = liveResultsVersion($this->event);

    $component->dispatch('tc-results', payload: ['eventUlid' => $this->event->ulid, 'seq' => $seq])
        ->assertSee($entry->person->publicName())
        ->assertSet('lastPushSeq', $seq);

    // La SEGUNDA llegada sí se resalta como nueva: ya había una vista previa
    // con seq > 0 (contrato v/seq de GetPublicLeaderboard).
    $second = liveEntry($this->event, 151);

    TimingMark::query()->create([
        'event_id' => $this->event->id,
        'entry_id' => $second->id,
        'client_mark_id' => 'mark-public-2',
        'captured_at' => now(),
        'device_time_ms' => now()->getTimestampMs(),
        'source' => 'station',
    ]);

    app(RecalculateEventResults::class)($this->event->id);

    $next = liveResultsVersion($this->event);

    $component->dispatch('tc-results', payload: ['eventUlid' => $this->event->ulid, 'seq' => $next])
        ->assertSee($second->person->publicName())
        ->assertSet('newBibs', [151]);

    // Push repetido: la pantalla no reacciona de nuevo.
    $component->dispatch('tc-results', payload: ['eventUlid' => $this->event->ulid, 'seq' => $next])
        ->assertSet('lastPushSeq', $next);
});

it('la pública refresca por tc-mark de seq nueva, deduplica viejos y colapsa el gap en un solo refetch', function () {
    $entry = liveEntry($this->event, 150);

    // Tiempo congelado (TQ-032): la ventana single-flight de 500 ms se
    // ejerce determinista, sin depender de la velocidad del runner.
    CarbonImmutable::setTestNow('2026-11-28 12:00:00');

    $component = Livewire::test('public.leaderboard-page', ['slug' => $this->event->slug])
        ->assertSet('lastMarkSeq', 0)
        ->assertSet('refetches', 1); // el mount cuenta su carga inicial

    app(RegisterManualArrival::class)($this->event, 150);

    $seq = (int) TimingMark::query()->where('event_id', $this->event->id)->max('id');

    // Push de seq nueva (lo que entrega el puente tras su debounce):
    // refetch dirigido inmediato, sin esperar al sondeo ni al motor. El
    // dorsal del push queda sellado como llegada nueva y el refresh lo
    // resalta (recalculo síncrono en la prueba: la fila ya está).
    $component->dispatch('tc-mark', payload: ['eventUlid' => $this->event->ulid, 'seq' => $seq, 'bib' => 150])
        ->assertSet('lastMarkSeq', $seq)
        ->assertSet('refetches', 2)
        ->assertSet('newBibs', [150]);

    // El resaltado SOBREVIVE al refresh encadenado del motor (tc-mark y
    // tc-results batched en el mismo commit): sello de 30 s por dorsal,
    // no diff de la última carga (hallazgo E2E TQ-032).
    $version = liveResultsVersion($this->event);

    $component->dispatch('tc-results', payload: ['eventUlid' => $this->event->ulid, 'seq' => $version])
        ->assertSet('newBibs', [150]);

    // Push repetido/viejo y de otro evento: sin refetch.
    $component->dispatch('tc-mark', payload: ['eventUlid' => $this->event->ulid, 'seq' => $seq])
        ->dispatch('tc-mark', payload: ['eventUlid' => (string) Str::ulid(), 'seq' => $seq + 100])
        ->assertSet('refetches', 3);

    // GAP de seq en ráfaga (dos saltos inmediatos, < 500 ms): el seq
    // avanza pero NO se acumulan refetchs — queda UNO pendiente que el
    // siguiente disparo resuelve.
    $component->dispatch('tc-mark', payload: ['eventUlid' => $this->event->ulid, 'seq' => $seq + 7])
        ->dispatch('tc-mark', payload: ['eventUlid' => $this->event->ulid, 'seq' => $seq + 9])
        ->assertSet('lastMarkSeq', $seq + 9)
        ->assertSet('refetches', 3)
        ->assertSet('pendingRefetch', true);

    // Fuera de la ventana: la RECONEXIÓN (tc-resync del puente) dispara un
    // único refetch que consume el pendiente y cierra el hueco.
    CarbonImmutable::setTestNow(now()->addMilliseconds(600));

    $component->dispatch('tc-resync')
        ->assertSet('refetches', 4)
        ->assertSet('pendingRefetch', false);

    CarbonImmutable::setTestNow(null);
});

it('las cinco pantallas vivas trazan puente WS, indicador y sondeo de respaldo a 15 s', function () {
    $public = $this->get("/e/{$this->event->slug}")->getContent();

    expect($public)->toContain('wire:poll.15s')
        ->and($public)->toContain('tcBridge')
        ->and($public)->toContain('.results.updated')
        // TQ-032: la pública también escucha la marca y la salida (push),
        // y su reloj se alinea contra /time público (fetch sin Bearer).
        ->and($public)->toContain('.mark.recorded')
        ->and($public)->toContain('.wave.started')
        ->and($public)->toContain('tcPublicClock')
        ->and($public)->toContain('/api/v1/time')
        ->and($public)->toContain('tc-echo-status')
        ->and($public)->not->toContain('wire:poll.4s');

    // Regresión TQ-032: /time SIN cabecera sigue siendo público (200) —
    // el reloj de la pública no tiene sesión ni token.
    $this->getJson('/api/v1/time')
        ->assertOk()
        ->assertJsonStructure(['server_ms', 'timezone']);

    $screens = [
        ['events.arrivals', 'timekeeper', ['event' => $this->event], '.mark.recorded', 'wire:poll.5s'],
        ['events.start', 'official', ['event' => $this->event], '.wave.started', 'wire:poll.5s'],
        ['events.marks', 'organizer', ['event' => $this->event], '.mark.recorded', 'wire:poll.5s'],
        ['events.results', 'organizer', ['event' => $this->event], '.results.updated', 'wire:poll.10s'],
    ];

    foreach ($screens as [$route, $role, $params, $wsEvent, $oldPoll]) {
        $html = $this->actingAs(liveAccount($role))->get(route($route, $params))->getContent();

        expect($html)->toContain('wire:poll.15s')
            ->and($html)->not->toContain($oldPoll)
            ->and($html)->toContain('tcBridge')
            ->and($html)->toContain($wsEvent)
            ->and($html)->toContain('tc-echo-status');
    }
});
