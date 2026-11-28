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
    Queue::assertPushed(
        BroadcastEvent::class,
        function (BroadcastEvent $job) use ($event, $wave): bool {
            /** @var WaveStarted $broadcast */
            $broadcast = $job->event;

            if (! $broadcast instanceof WaveStarted) {
                return false;
            }

            $channels = $broadcast->broadcastOn();
            $payload = $broadcast->broadcastWith();
            $persistedMs = (int) $wave->fresh()->actual_start_at->getTimestampMs();

            return $broadcast->broadcastAs() === 'wave.started'
                && count($channels) === 1
                // PrivateChannel prefija «private-»: es el nombre de cable
                // que Reverb/Echo autorizan contra routes/channels.php.
                && $channels[0]->name === "private-event.{$event->ulid}.ops"
                && $payload['eventUlid'] === $event->ulid
                && $payload['waveUlid'] === $wave->ulid
                && abs($payload['actualStartAt'] - $persistedMs) <= 1_000
                && $payload['seq'] === null;
        },
    );
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
