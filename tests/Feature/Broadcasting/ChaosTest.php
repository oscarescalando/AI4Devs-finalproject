<?php

declare(strict_types=1);

use App\Actions\Timing\RegisterManualArrival;
use App\Broadcasting\MarkRecorded;
use App\Jobs\Results\RecalculateEventResultsJob;
use App\Models\Account;
use App\Models\Entry;
use App\Models\Event;
use App\Models\Person;
use App\Models\Result;
use App\Models\TimingMark;
use App\Models\Wave;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Chaos\ChaosSyncConnector;
use Tests\Support\Chaos\DeadSocketBroadcaster;

/**
 * TQ-019 (ISSUE-145/146, versión F1) — Caos mínimo del tiempo real: la
 * marca jamás falla por el broadcast (INV-08/RF-74). Dos niveles de fallo:
 * (1) encolar el BroadcastEvent revienta (driver ChaosSyncQueue: solo la
 * cola `broadcast` está caída) — la petición responde lo normal, la marca
 * quedó persistida y el recálculo corrió igual; (2) el worker de la cola
 * `broadcast` explota al emitir (DeadSocketBroadcaster + queue:work real
 * sobre la conexión database) — el fallo muere aislado en failed_jobs sin
 * tocar la cola `default` ni los datos. Además: ráfaga de 100 marcas con
 * reenvíos duplicados y huérfanas (ISSUE-146) y degradación WS muerto +
 * cola viva.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->event = Event::query()->where('slug', 'vuela-caracas-21k')->firstOrFail();
    $this->station = Account::query()->where('email', 'admin@tdchronos.test')->firstOrFail();
    $this->token = $this->station->createToken('meta-tablet-01', ['device:marks', 'device:roster'])->plainTextToken;
});

/**
 * Registra el driver de caos: misma resolución que cualquier conexión
 * (config + Queue::extend), pero push a `broadcast` → RuntimeException.
 */
function chaosQueueDownForBroadcast(): void
{
    Queue::extend('chaos-sync', fn () => new ChaosSyncConnector);

    config([
        'queue.connections.chaos-sync' => ['driver' => 'chaos-sync'],
        'queue.default' => 'chaos-sync',
    ]);
}

function chaosMarkPayload(int $bib, string $clientMarkId): array
{
    return [
        'client_mark_id' => $clientMarkId,
        'bib' => $bib,
        'device_time_ms' => now()->getTimestampMs(),
        'clock_offset_ms' => -320,
        'source' => 'station',
    ];
}

/*
|--------------------------------------------------------------------------
| 1) Fallo al ENCOLAR el broadcast
|--------------------------------------------------------------------------
*/

it('la marca persiste con respuesta normal aunque encolar el broadcast reviente (ISSUE-145, INV-08)', function () {
    Exceptions::fake();

    chaosQueueDownForBroadcast();

    $entry = Entry::query()->where('event_id', $this->event->id)->where('bib', 128)->firstOrFail();
    $markId = (string) str()->uuid();

    $response = $this->postJson("/api/v1/events/{$this->event->ulid}/timing-marks", [
        'marks' => [chaosMarkPayload(128, $markId)],
    ], ['Authorization' => "Bearer {$this->token}"]);

    // La respuesta ES la normal del lote: el broadcast es best-effort.
    $response->assertOk()
        ->assertJsonPath('results.0.status', 'assigned')
        ->assertJsonPath('results.0.client_mark_id', $markId)
        ->assertJsonPath('results.0.entry_id', $entry->ulid);

    // El commit del lote ya había ocurrido: la marca quedó persistida.
    expect(TimingMark::query()->where('client_mark_id', $markId)->exists())->toBeTrue();

    // La cola default seguía viva: el recálculo se encoló/ejecutó y el
    // resultado de la llegada existe (net_ms calculado por el motor).
    expect(Result::query()->where('entry_id', $entry->id)->exists())->toBeTrue();

    // El fallo del broadcast quedó REPORTADO (visible en logs), no tragado.
    Exceptions::assertReported(RuntimeException::class);
});

/*
|--------------------------------------------------------------------------
| 2) Fallo del WORKER de broadcast
|--------------------------------------------------------------------------
|
| El BroadcastEvent ya está en la cola (Redis vivo) pero el WS está
| muerto: al procesarlo, el broadcaster revienta. Se ejercita con un
| `queue:work` REAL sobre la conexión database (jobs/failed_jobs de
| verdad) y un broadcaster de caos: el veneno en `broadcast` no puede
| tocar la cola `default` ni los datos. Las suites nocturnas con Reverb
| real quedan para el CI nocturno (TQ-016/defer).
*/

it('un broadcast que explota en el worker muere aislado en failed_jobs sin tocar default ni los datos (ISSUE-145)', function () {
    Broadcast::extend('dead-socket', fn () => new DeadSocketBroadcaster);

    config([
        'broadcasting.connections.dead-socket' => ['driver' => 'dead-socket'],
        'broadcasting.default' => 'dead-socket',
        'queue.default' => 'database',
    ]);

    $entry = Entry::query()->where('event_id', $this->event->id)->where('bib', 150)->firstOrFail();

    $mark = TimingMark::query()->create([
        'event_id' => $this->event->id,
        'entry_id' => $entry->id,
        'client_mark_id' => (string) str()->uuid(),
        'captured_at' => now(),
        'device_time_ms' => now()->getTimestampMs(),
        'source' => 'station',
    ]);

    // Veneno en `broadcast`: el anuncio real de la marca (BroadcastEvent de
    // MarkRecorded) que el worker no podrá emitir con el socket muerto.
    Queue::connection('database')->pushOn('broadcast', new BroadcastEvent(new MarkRecorded(
        eventUlid: $this->event->ulid,
        seq: (int) $mark->id,
        bib: 150,
        capturedAtMs: (int) $mark->captured_at->getTimestampMs(),
    )));

    // Trabajo bueno en `default`: el recálculo de la clasificación.
    Queue::connection('database')->push(new RecalculateEventResultsJob($this->event->id));

    $this->artisan('queue:work', [
        '--queue' => 'broadcast,default',
        '--stop-when-empty' => true,
        '--sleep' => 0,
        '--tries' => 1,
    ])->assertExitCode(0);

    // La cola default completó su trabajo pese al veneno: el motor escribió
    // el resultado de la llegada.
    expect(Result::query()->where('entry_id', $entry->id)->exists())->toBeTrue();

    // Los broadcasts fallidos quedaron registrados (el veneno + el
    // results.updated que el propio recálculo encoló), sin colgar al worker.
    expect(DB::table('failed_jobs')->where('queue', 'broadcast')->count())->toBe(2);

    // Nada quedó atascado en la cola y la marca sigue intacta.
    expect(DB::table('jobs')->count())->toBe(0)
        ->and(TimingMark::query()->where('event_id', $this->event->id)->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| 3) Ráfaga: 100 marcas mezcladas con reenvíos y huérfanas (ISSUE-146)
|--------------------------------------------------------------------------
*/

it('absorbe la ráfaga de 100 marcas con reenvíos duplicados y huérfanas sin duplicar y con resultados conciliados (ISSUE-146)', function () {
    $startedAt = microtime(true);

    // Roster a 120 inscritos: 114 del seeder + 6 de relleno.
    $template = Entry::query()->where('event_id', $this->event->id)->firstOrFail();
    $nextBib = (int) Entry::query()->where('event_id', $this->event->id)->max('bib');

    foreach (range(1, 6) as $i) {
        Entry::query()->create([
            'event_id' => $this->event->id,
            'category_id' => $template->category_id,
            'race_id' => $template->race_id,
            'wave_id' => $template->wave_id,
            'person_id' => Person::factory()->create()->id,
            'team_id' => null,
            'bib' => $nextBib + $i,
            'status' => 'registered',
        ]);
    }

    // 92 marcas de dorsal (86 únicas + 3 doble pasada del tapete) y 8
    // huérfanas = 100 client_mark_id únicos, mezclados.
    $bibs = Entry::query()->where('event_id', $this->event->id)->pluck('bib')->shuffle();
    $doubleTap = $bibs->take(3);
    $single = $bibs->skip(3)->take(86);

    // Base temporal determinista: todos llegan ≥ 10 min tras la ÚLTIMA
    // salida real sembrada, escalonadas 1,5 s.
    $lastStartMs = CarbonImmutable::parse(
        Wave::query()->whereRelation('race', 'event_id', $this->event->id)->max('actual_start_at'),
    )->getTimestampMs();
    $baseMs = $lastStartMs + 10 * 60 * 1000;

    $marks = [];
    $sentCapturedMs = []; // bib => ms oficial enviada (device_time + offset)
    $i = 0;
    foreach ([...$single->all(), ...$doubleTap->all(), ...$doubleTap->all(), ...range(99_901, 99_908)] as $bib) {
        $capturedMs = $baseMs + ($i++ * 1_500) - 320; // clock_offset_ms
        $marks[] = [
            'client_mark_id' => (string) str()->uuid(),
            'bib' => $bib,
            'device_time_ms' => $baseMs + ($i - 1) * 1_500,
            'clock_offset_ms' => -320,
            'source' => 'station',
        ];
        $sentCapturedMs[$bib] = min($sentCapturedMs[$bib] ?? PHP_INT_MAX, $capturedMs);
    }

    expect($marks)->toHaveCount(100);
    shuffle($marks);

    // Envío en lotes de 10 con reenvíos cruzados: cada lote reenvía 2 marcas
    // del lote anterior (mismos client_mark_id) y al final se reenvían los
    // 2 últimos → 20 respuestas duplicate esperadas.
    $statuses = ['assigned' => 0, 'orphan' => 0, 'duplicate' => 0];
    $entryIdByMark = [];
    $carried = [];
    $url = "/api/v1/events/{$this->event->ulid}/timing-marks";

    $sendBatch = function (array $payload) use ($url, &$statuses, &$entryIdByMark): void {
        $response = $this->postJson($url, ['marks' => $payload], ['Authorization' => "Bearer {$this->token}"]);
        $response->assertOk();

        foreach ($response->json('results') as $r) {
            $statuses[$r['status']]++;

            if ($r['status'] === 'duplicate') {
                // Cada duplicado reportado conserva la asignación persistida.
                expect($r['entry_id'])->toBe($entryIdByMark[$r['client_mark_id']]);
            } else {
                $entryIdByMark[$r['client_mark_id']] = $r['entry_id'];
            }
        }
    };

    foreach (array_chunk($marks, 10) as $chunk) {
        $batch = [...$carried, ...$chunk];
        shuffle($batch);
        $sendBatch($batch);
        $carried = array_slice($chunk, 0, 2);
    }
    $sendBatch($carried);

    // Cero duplicados: exactamente 100 filas y 100 client_mark_id distintos.
    expect(TimingMark::query()->where('event_id', $this->event->id)->count())->toBe(100)
        ->and(TimingMark::query()->where('event_id', $this->event->id)->distinct()->count('client_mark_id'))->toBe(100)
        ->and($statuses)->toBe(['assigned' => 92, 'orphan' => 8, 'duplicate' => 20]);

    // El motor absorbió la ráfaga: 89 llegadas únicas (92 marcas − 3 dobles
    // pasadas que comparten dorsal) conciliadas con MIN(captured_at).
    $results = Result::query()
        ->whereHas('entry', fn ($query) => $query->where('event_id', $this->event->id))
        ->with('entry.wave')
        ->get();

    expect($results)->toHaveCount(89);

    $byRace = [];
    foreach ($results as $result) {
        $bib = $result->entry->bib;
        $expectedNet = $sentCapturedMs[$bib] - $result->entry->wave->actual_start_at->getTimestampMs();

        expect($result->net_ms)->toBe($expectedNet);

        $byRace[$result->entry->race_id][] = $result;
    }

    // Clasificación estable: por carrera, el orden por net asc no rompe el
    // RANK() (monótono no decreciente, empezando en 1).
    foreach ($byRace as $raceResults) {
        usort($raceResults, fn (Result $a, Result $b) => $a->net_ms <=> $b->net_ms);

        $previousRank = 0;
        foreach ($raceResults as $position => $result) {
            if ($previousRank === 0) {
                expect($result->rank_general)->toBe(1);
            } else {
                expect($result->rank_general)->toBeGreaterThanOrEqual($previousRank)
                    ->and($result->rank_general)->toBeLessThanOrEqual($position + 1);
            }
            $previousRank = $result->rank_general;
        }
    }

    // La ráfaga completa (sembrado + 11 lotes + recálculos) es razonable.
    expect(microtime(true) - $startedAt)->toBeLessThan(30.0);
});

/*
|--------------------------------------------------------------------------
| 4) WS muerto + cola viva (degradación liviana)
|--------------------------------------------------------------------------
*/

it('con el WS muerto la captura manual persiste y las pantallas siguen sirviendo sin eventos fantasma', function () {
    // WS muerto: el broadcasting degradado a log (sin Reverb que emitir).
    config(['broadcasting.default' => 'log']);

    $entry = Entry::query()->where('event_id', $this->event->id)->where('bib', 150)->firstOrFail();

    // Cola viva: la captura manual corre el conducto completo (marca +
    // recálculo) aunque no haya WS que anunciar.
    $arrival = app(RegisterManualArrival::class)($this->event, 150);

    expect($arrival['status'])->toBe('assigned')
        ->and(TimingMark::query()->where('client_mark_id', $arrival['client_mark_id'])->where('source', 'manual')->exists())->toBeTrue()
        ->and(Result::query()->where('entry_id', $entry->id)->exists())->toBeTrue();

    // La pantalla sigue sirviendo (HTTP 200) con su sondeo de respaldo y
    // muestra la llegada en la bitácora.
    $organizer = Account::factory()->create();
    $organizer->assignRole('organizer');

    $this->actingAs($organizer)
        ->get(route('events.marks', ['event' => $this->event]))
        ->assertOk()
        ->assertSee('wire:poll.15s')
        ->assertSee('150');

    // Sin eventos fantasma: el reenvío del dorsal ya llegado NO encola nada
    // (ni broadcast ni recálculo — no hay dato nuevo que anunciar).
    Queue::fake();

    $again = app(RegisterManualArrival::class)($this->event, 150);

    expect($again['status'])->toBe('duplicate');
    Queue::assertNotPushed(BroadcastEvent::class);
    Queue::assertNotPushed(RecalculateEventResultsJob::class);
});
