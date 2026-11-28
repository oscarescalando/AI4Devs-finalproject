<?php

declare(strict_types=1);

use App\Actions\Results\RecalculateEventResults;
use App\Actions\Start\UndoWaveStart;
use App\Actions\Timing\RecordTimingMarkBatch;
use App\Broadcasting\MarkRecorded;
use App\Broadcasting\ResultsUpdated;
use App\Broadcasting\WaveUndone;
use App\Jobs\Results\RecalculateEventResultsJob;
use App\Models\Entry;
use App\Models\Event;
use App\Models\Result;
use App\Models\TimingMark;
use App\Models\Wave;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

/**
 * ISSUE-141/142 (TQ-018, EP-26 fase 2) — Emisión de los eventos de dominio
 * (ADR-013): cada acción persiste y SOLO ENTONCES encola el broadcast en
 * `broadcast` (ShouldBroadcast + ShouldDispatchAfterCommit: rollback no
 * emite). Sin WS en la prueba: se observa la cola, como WaveStartedTest.
 * El canal público jamás lleva PII (RF-75/INV-06): se afirma que el payload
 * de MarkRecorded@public no contiene nombre ni estado.
 */
uses(RefreshDatabase::class);

function domainDemoEvent(): Event
{
    return Event::query()->where('slug', 'vuela-caracas-21k')->firstOrFail();
}

/**
 * @return list<MarkRecorded|ResultsUpdated|WaveUndone|object>
 */
function domainPushedBroadcasts(): array
{
    $events = [];

    Queue::pushed(BroadcastEvent::class, function (BroadcastEvent $job) use (&$events): void {
        $events[] = $job->event;
    });

    return $events;
}

/**
 * @return list<MarkRecorded>
 */
function domainPushedMarks(): array
{
    return array_values(array_filter(
        domainPushedBroadcasts(),
        static fn (object $event): bool => $event instanceof MarkRecorded,
    ));
}

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->event = domainDemoEvent();
});

it('encola mark.recorded por cada marca nueva: variante ops con detalle y pública sin PII', function () {
    Queue::fake();

    app(RecordTimingMarkBatch::class)($this->event->ulid, [
        ['client_mark_id' => 'mark-ops-1', 'bib' => 128, 'device_time_ms' => now()->getTimestampMs(), 'source' => 'station'],
        ['client_mark_id' => 'mark-ops-2', 'bib' => 99999, 'device_time_ms' => now()->getTimestampMs(), 'source' => 'station'],
    ]);

    Queue::assertPushedOn('broadcast', BroadcastEvent::class);

    $marks = [
        'assigned' => TimingMark::query()->where('client_mark_id', 'mark-ops-1')->firstOrFail(),
        'orphan' => TimingMark::query()->where('client_mark_id', 'mark-ops-2')->firstOrFail(),
    ];

    // 2 marcas nuevas × 2 variantes de canal (ops + public).
    expect(domainPushedMarks())->toHaveCount(4);

    $ops = array_values(array_filter(domainPushedMarks(), static fn (MarkRecorded $m): bool => ! $m->public));
    $public = array_values(array_filter(domainPushedMarks(), static fn (MarkRecorded $m): bool => $m->public));

    expect($ops)->toHaveCount(2)
        ->and($public)->toHaveCount(2);

    foreach ($ops as $i => $broadcast) {
        $mark = $i === 0 ? $marks['assigned'] : $marks['orphan'];
        $entry = $mark->entry;

        expect($broadcast->broadcastAs())->toBe('mark.recorded')
            ->and($broadcast->broadcastOn())->toHaveCount(1)
            // PrivateChannel prefija «private-»: nombre de cable contra channels.php.
            ->and($broadcast->broadcastOn()[0]->name)->toBe("private-event.{$this->event->ulid}.ops")
            ->and($broadcast->queue)->toBe('broadcast')
            // seq = id creciente de la marca (contrato v/seq mínimo).
            ->and($broadcast->seq)->toBe((int) $mark->id)
            ->and($broadcast->broadcastWith())->toBe([
                'eventUlid' => $this->event->ulid,
                'seq' => (int) $mark->id,
                'bib' => $i === 0 ? 128 : 99999,
                'capturedAtMs' => (int) $mark->captured_at->getTimestampMs(),
                'name' => $entry?->person?->publicName() ?? $entry?->team?->name,
                'status' => $i === 0 ? 'assigned' : 'orphan',
            ]);
    }

    foreach ($public as $i => $broadcast) {
        $mark = $i === 0 ? $marks['assigned'] : $marks['orphan'];

        $payload = $broadcast->broadcastWith();

        expect($broadcast->broadcastAs())->toBe('mark.recorded')
            ->and($broadcast->broadcastOn())->toHaveCount(1)
            // Canal ABIERTO: sin prefijo private-.
            ->and($broadcast->broadcastOn()[0]->name)->toBe("event.{$this->event->ulid}.public")
            ->and($payload)->toHaveCount(4)
            // INV-06/RF-75: el feed público no lleva nombre ni estado.
            ->and($payload)->not->toHaveKey('name')
            ->and($payload)->not->toHaveKey('status')
            ->and($payload['seq'])->toBe((int) $mark->id)
            ->and($payload['eventUlid'])->toBe($this->event->ulid)
            ->and($payload['bib'])->toBe($i === 0 ? 128 : 99999);
    }
});

it('las marcas duplicate no emiten: reenvío idempotente sin broadcasts nuevos', function () {
    Queue::fake();

    $mark = ['client_mark_id' => 'mark-dup', 'bib' => 128, 'device_time_ms' => now()->getTimestampMs(), 'source' => 'station'];

    app(RecordTimingMarkBatch::class)($this->event->ulid, [$mark]);
    expect(domainPushedMarks())->toHaveCount(2); // ops + public de la marca nueva

    app(RecordTimingMarkBatch::class)($this->event->ulid, [$mark]);
    expect(domainPushedMarks())->toHaveCount(2); // duplicate: nada nuevo que anunciar
});

it('el rollback del lote no emite y no persiste la marca', function () {
    Queue::fake();

    DB::beginTransaction();
    app(RecordTimingMarkBatch::class)($this->event->ulid, [
        ['client_mark_id' => 'mark-rb', 'bib' => 128, 'device_time_ms' => now()->getTimestampMs(), 'source' => 'station'],
    ]);

    Queue::assertNotPushed(BroadcastEvent::class);

    DB::rollBack();

    Queue::assertNotPushed(BroadcastEvent::class);
    expect(TimingMark::query()->where('client_mark_id', 'mark-rb')->exists())->toBeFalse();
});

it('des-hacer la salida encola wave.undone en el canal ops', function () {
    Queue::fake();

    // El seeder trae las tres salidas marcadas: se deshace la ÚLTIMA (orden inverso).
    $wave = Wave::query()
        ->whereRelation('race', 'event_id', $this->event->id)
        ->with('race')
        ->orderByDesc('scheduled_start_at')
        ->orderByDesc('id')
        ->firstOrFail();

    app(UndoWaveStart::class)($this->event, $wave->ulid);

    Queue::assertPushedOn('broadcast', BroadcastEvent::class);

    $undone = array_values(array_filter(
        domainPushedBroadcasts(),
        static fn (object $event): bool => $event instanceof WaveUndone,
    ));

    expect($undone)->toHaveCount(1)
        ->and($undone[0]->broadcastAs())->toBe('wave.undone')
        ->and($undone[0]->broadcastOn()[0]->name)->toBe("private-event.{$this->event->ulid}.ops")
        ->and($undone[0]->broadcastWith())->toBe([
            'eventUlid' => $this->event->ulid,
            'waveUlid' => $wave->ulid,
            'seq' => null,
        ])
        // Persistir antes de emitir: la oleada volvió a Pendiente.
        ->and($wave->fresh()->actual_start_at)->toBeNull();
});

it('el job de recálculo emite results.updated en public y ops con la versión nueva', function () {
    $entry = Entry::query()->where('event_id', $this->event->id)->where('bib', 150)->firstOrFail();

    TimingMark::query()->create([
        'event_id' => $this->event->id,
        'entry_id' => $entry->id,
        'client_mark_id' => 'mark-results',
        'captured_at' => now(),
        'device_time_ms' => now()->getTimestampMs(),
        'source' => 'station',
    ]);

    Queue::fake();

    (new RecalculateEventResultsJob($this->event->id))->handle(app(RecalculateEventResults::class));

    Queue::assertPushedOn('broadcast', BroadcastEvent::class);

    $updated = array_values(array_filter(
        domainPushedBroadcasts(),
        static fn (object $event): bool => $event instanceof ResultsUpdated,
    ));

    $version = (int) Result::query()
        ->whereHas('entry', fn ($query) => $query->where('event_id', $this->event->id))
        ->max('id');

    expect($updated)->toHaveCount(1)
        ->and($updated[0]->broadcastAs())->toBe('results.updated')
        ->and($updated[0]->broadcastOn())->toHaveCount(2)
        // Mismo payload en el canal abierto y en el privado.
        ->and($updated[0]->broadcastOn()[0]->name)->toBe("event.{$this->event->ulid}.public")
        ->and($updated[0]->broadcastOn()[1]->name)->toBe("private-event.{$this->event->ulid}.ops")
        ->and($updated[0]->broadcastWith())->toBe([
            'eventUlid' => $this->event->ulid,
            'seq' => $version,
            'status' => 'provisional',
        ])
        // seq = versión creciente (MAX results.id del evento).
        ->and($version)->toBeGreaterThan(0);
});

it('la Action de recálculo NO emite: la emisión vive en el job, no en el SQL', function () {
    Queue::fake();

    app(RecalculateEventResults::class)($this->event->id);

    Queue::assertNotPushed(BroadcastEvent::class);
});
