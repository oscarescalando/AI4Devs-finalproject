<?php

declare(strict_types=1);

use App\Actions\Timing\RecordTimingMarkBatch;
use App\Actions\Timing\RegisterManualArrival;
use App\Jobs\Results\RecalculateEventResultsJob;
use App\Models\Account;
use App\Models\Entry;
use App\Models\Event;
use App\Models\TimingMark;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

/**
 * ISSUE-130 (TQ-012) — Captura de llegadas en vivo: la pantalla Volt delega
 * en RegisterManualArrival, que reusa el conducto del lote (asignación por
 * (event_id, bib), huérfana, idempotencia por client_mark_id — INV-02) y
 * dispara el recálculo afterCommit sin bloquear la captura (INV-08).
 */
uses(RefreshDatabase::class);

function arrivalAccount(string $role): Account
{
    $account = Account::factory()->create();
    $account->assignRole($role);

    return $account;
}

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->event = Event::query()->where('slug', 'vuela-caracas-21k')->firstOrFail();
});

it('renderiza la captura para juez y cronometrador y rechaza con 403 sin marks.manage', function () {
    $this->actingAs(arrivalAccount('timekeeper'))->get(route('events.arrivals', $this->event))->assertOk();
    $this->actingAs(arrivalAccount('official'))->get(route('events.arrivals', $this->event))->assertOk();

    // El organizador tiene marks.view pero NO marks.manage (RolesPermissionsSeeder).
    $this->actingAs(arrivalAccount('organizer'))->get(route('events.arrivals', $this->event))->assertForbidden();
});

it('traza la pantalla de captura: input grande con foco y sondeo', function () {
    $html = $this->actingAs(arrivalAccount('timekeeper'))->get(route('events.arrivals', $this->event))->getContent();

    expect($html)->toContain('id="arrival-bib"')
        ->and($html)->toContain('autofocus')
        ->and($html)->toContain('wire:poll.15s');
});

it('captura una llegada asignada: persiste la marca manual y dispara el recálculo en cola', function () {
    Queue::fake();

    $entry = Entry::query()->where('event_id', $this->event->id)->where('bib', 128)->firstOrFail();

    Livewire::test('events.arrival-capture', ['event' => $this->event])
        ->set('bib', '128')
        ->call('capture')
        ->assertDispatched('toast', type: 'success')
        ->assertDispatched('arrival-recorded')
        ->assertSee($entry->person->publicName());

    $mark = TimingMark::query()->where('entry_id', $entry->id)->firstOrFail();

    expect($mark->source)->toBe('manual')
        ->and($mark->client_mark_id)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/');

    Queue::assertPushed(RecalculateEventResultsJob::class, fn (RecalculateEventResultsJob $job) => $job->eventId === $this->event->id);
});

it('deja huérfana la marca de un dorsal desconocido y la reporta', function () {
    Queue::fake();

    Livewire::test('events.arrival-capture', ['event' => $this->event])
        ->set('bib', '99999')
        ->call('capture')
        ->assertDispatched('toast', type: 'warning');

    $mark = TimingMark::query()->where('event_id', $this->event->id)->firstOrFail();

    expect($mark->entry_id)->toBeNull();
});

it('registra una llegada sin dorsal como huérfana, sin teclear 0 (RF-38)', function () {
    Queue::fake();

    Livewire::test('events.arrival-capture', ['event' => $this->event])
        ->set('bib', '')
        ->call('capture')
        ->assertDispatched('toast', type: 'warning');

    expect(TimingMark::query()->where('event_id', $this->event->id)->whereNull('entry_id')->count())->toBe(1);
});

it('rechaza el doble envío del mismo dorsal: duplicate y sin segunda marca', function () {
    Queue::fake();

    $component = Livewire::test('events.arrival-capture', ['event' => $this->event]);

    $component->set('bib', '128')->call('capture')->assertDispatched('toast', type: 'success');
    $component->set('bib', '128')->call('capture')->assertDispatched('toast', type: 'info');

    expect(TimingMark::query()->where('event_id', $this->event->id)->count())->toBe(1);
});

it('rechaza un dorsal inválido sin crear marca', function () {
    Queue::fake();

    Livewire::test('events.arrival-capture', ['event' => $this->event])
        ->set('bib', '12a')
        ->call('capture')
        ->assertDispatched('toast', type: 'warning');

    expect(TimingMark::query()->where('event_id', $this->event->id)->count())->toBe(0);
});

it('RegisterManualArrival genera client_mark_id UUIDv7 y reusa la idempotencia del lote', function () {
    $entry = Entry::query()->where('event_id', $this->event->id)->where('bib', 128)->firstOrFail();

    $result = app(RegisterManualArrival::class)($this->event, 128);

    expect($result['status'])->toBe('assigned')
        ->and($result['bib'])->toBe(128)
        ->and($result['athlete'])->toBe($entry->person->publicName())
        ->and($result['client_mark_id'])->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/');

    // El prefijo del UUIDv7 es unix_ms (48 bits, sin guiones): es de ahora.
    $hex = str_replace('-', '', $result['client_mark_id']);
    $uuidMs = (int) hexdec(substr($hex, 0, 12));

    expect($uuidMs)->toBeGreaterThan(now()->getTimestampMs() - 120_000)
        ->and($uuidMs)->toBeLessThan(now()->getTimestampMs() + 120_000);

    // Reenvío del mismo client_mark_id por el conducto del lote: duplicate
    // y no se crea una segunda fila (INV-02).
    $resend = app(RecordTimingMarkBatch::class)($this->event->ulid, [[
        'client_mark_id' => $result['client_mark_id'],
        'bib' => 128,
        'device_time_ms' => now()->getTimestampMs(),
        'source' => 'manual',
    ]]);

    expect($resend[0]['status'])->toBe('duplicate')
        ->and(TimingMark::query()->where('event_id', $this->event->id)->count())->toBe(1);
});
