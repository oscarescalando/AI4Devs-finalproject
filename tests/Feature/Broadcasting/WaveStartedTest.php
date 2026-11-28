<?php

declare(strict_types=1);

use App\Actions\Start\MarkWaveStart;
use App\Broadcasting\WaveStarted;
use App\Models\Event;
use App\Models\Wave;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

/**
 * ISSUE-141/TQ-017 — Evento piloto `wave.started` (ADR-013): MarkWaveStart
 * persiste la salida real y SOLO ENTONCES la emite; ShouldBroadcast lleva
 * el BroadcastEvent a la cola `broadcast` (lo sirve Horizon) y
 * ShouldDispatchAfterCommit retiene la emisión hasta el commit: un
 * rollback no encola nada. Sin WS en la prueba: se observa la cola.
 */
uses(RefreshDatabase::class);

function waveStartedDemoEvent(): Event
{
    return Event::query()->where('slug', 'vuela-caracas-21k')->firstOrFail();
}

/**
 * El seeder trae las tres salidas marcadas: se anulan todas y se devuelve
 * la primera pendiente (la marcable).
 */
function waveStartedFirstPending(Event $event): Wave
{
    Wave::query()
        ->whereRelation('race', 'event_id', $event->id)
        ->get()
        ->each(fn (Wave $wave) => $wave->forceFill(['actual_start_at' => null])->save());

    return Wave::query()
        ->whereRelation('race', 'event_id', $event->id)
        ->with('race')
        ->orderBy('scheduled_start_at')
        ->orderBy('id')
        ->firstOrFail();
}

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

it('encola wave.started en la cola broadcast al marcar la salida', function () {
    Queue::fake();

    $event = waveStartedDemoEvent();
    $wave = waveStartedFirstPending($event);

    app(MarkWaveStart::class)($event, $wave->ulid);

    Queue::assertPushedOn('broadcast', BroadcastEvent::class);

    // TQ-032: DOS variantes como MarkRecorded — ops (privado, con detalle
    // de oleada) y pública (canal abierto, subconjunto sin PII).
    $started = [];
    Queue::pushed(BroadcastEvent::class, function (BroadcastEvent $job) use (&$started): void {
        if ($job->event instanceof WaveStarted) {
            $started[] = $job->event;
        }
    });

    expect($started)->toHaveCount(2);

    $ops = array_values(array_filter($started, static fn (WaveStarted $b): bool => ! $b->public));
    $public = array_values(array_filter($started, static fn (WaveStarted $b): bool => $b->public));

    expect($ops)->toHaveCount(1)
        ->and($public)->toHaveCount(1);

    $persistedMs = (int) $wave->fresh()->actual_start_at->getTimestampMs();

    // Variante ops: canal PRIVADO (PrivateChannel prefija «private-»: es el
    // nombre de cable que Reverb/Echo autorizan contra routes/channels.php)
    // con el detalle completo de la oleada.
    $opsPayload = $ops[0]->broadcastWith();

    expect($ops[0]->broadcastAs())->toBe('wave.started')
        ->and($ops[0]->broadcastOn())->toHaveCount(1)
        ->and($ops[0]->broadcastOn()[0]->name)->toBe("private-event.{$event->ulid}.ops")
        ->and(array_keys($opsPayload))->toBe(['eventUlid', 'waveUlid', 'actualStartAt', 'seq'])
        ->and($opsPayload['eventUlid'])->toBe($event->ulid)
        ->and($opsPayload['waveUlid'])->toBe($wave->ulid)
        ->and($opsPayload['seq'])->toBeNull()
        // datetime(3): el ms persistido puede redondear contra el Carbon
        // original que viajó en el evento — misma hora, tolerancia 1 s.
        ->and(abs($opsPayload['actualStartAt'] - $persistedMs))->toBeLessThanOrEqual(1_000);

    // Variante pública: canal ABIERTO con el subconjunto {eventUlid, seq,
    // actualStartAt} — asserts de FORMA (RF-75/INV-06): sin waveUlid ni
    // dato de persona alguno (aquí no aplica, el contrato lo garantiza).
    $payload = $public[0]->broadcastWith();

    expect($public[0]->broadcastAs())->toBe('wave.started')
        ->and($public[0]->broadcastOn())->toHaveCount(1)
        ->and($public[0]->broadcastOn()[0]->name)->toBe("event.{$event->ulid}.public")
        ->and($payload)->toHaveCount(3)
        ->and($payload)->not->toHaveKey('waveUlid')
        ->and($payload['eventUlid'])->toBe($event->ulid)
        ->and($payload['seq'])->toBeNull()
        ->and(abs($payload['actualStartAt'] - $persistedMs))->toBeLessThanOrEqual(1_000);
});

it('retiene la emisión hasta el commit de la transacción', function () {
    Queue::fake();

    $event = waveStartedDemoEvent();
    $wave = waveStartedFirstPending($event);

    DB::beginTransaction();
    app(MarkWaveStart::class)($event, $wave->ulid);

    // Dentro de la transacción abierta: nada encolado todavía.
    Queue::assertNotPushed(BroadcastEvent::class);

    DB::commit();

    Queue::assertPushedOn('broadcast', BroadcastEvent::class);
    expect($wave->fresh()->actual_start_at)->not->toBeNull();
});

it('un rollback no encola nada y la salida no queda marcada', function () {
    Queue::fake();

    $event = waveStartedDemoEvent();
    $wave = waveStartedFirstPending($event);

    DB::beginTransaction();
    app(MarkWaveStart::class)($event, $wave->ulid);
    DB::rollBack();

    Queue::assertNotPushed(BroadcastEvent::class);
    expect($wave->fresh()->actual_start_at)->toBeNull();
});
