<?php

declare(strict_types=1);

use App\Actions\Corrections\ListMarkLog;
use App\Jobs\Results\RecalculateEventResultsJob;
use App\Models\Account;
use App\Models\Entry;
use App\Models\Event;
use App\Models\Result;
use App\Models\TimingMark;
use App\Models\TimingMarkCorrection;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

/**
 * ISSUE-135 (TQ-014) — Corrección 1/2/3 por la pantalla de la bitácora:
 * ajustar/reasignar/anular auditan timing_mark_corrections SIN tocar la
 * marca (append-only, INV-01 — los triggers de la base lo exigen) y
 * disparan el recálculo afterCommit (INV-08). La reasignación a un dorsal
 * con llegada exige confirmación explícita y deja la marca posterior como
 * duplicada. Corregir exige corrections.manage (organizer sí, timekeeper
 * no); ver la bitácora exige marks.view.
 */
uses(RefreshDatabase::class);

function correctorAccount(string $role): Account
{
    $account = Account::factory()->create();
    $account->assignRole($role);

    return $account;
}

function entryByBib(Event $event, int $bib): Entry
{
    return Entry::query()->where('event_id', $event->id)->where('bib', $bib)->firstOrFail();
}

function logMark(Event $event, ?Entry $entry, string $capturedAt): TimingMark
{
    return TimingMark::query()->create([
        'event_id' => $event->id,
        'entry_id' => $entry?->id,
        'client_mark_id' => (string) str()->uuid(),
        'captured_at' => CarbonImmutable::parse($capturedAt),
        'device_time_ms' => CarbonImmutable::parse($capturedAt)->getTimestampMs(),
        'source' => 'manual',
    ]);
}

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->event = Event::query()->where('slug', 'vuela-caracas-21k')->firstOrFail();
    $this->organizer = correctorAccount('organizer');
});

it('ajusta el tiempo ±1 s sin tocar la marca, audita y recalcula', function () {
    Queue::fake();

    // bib 102 corre la oleada General (salida real 07:15:09.640).
    $entry = entryByBib($this->event, 102);
    $mark = logMark($this->event, $entry, today()->setTime(9, 0, 0)->format('Y-m-d H:i:s.v'));
    $before = (array) DB::table('timing_marks')->find($mark->id);

    $this->actingAs($this->organizer);

    Livewire::test('events.mark-log', ['event' => $this->event])
        ->call('openCorrect', $mark->ulid)
        ->assertDispatched('open-mark-modal')
        ->set('reason', 'sensor adelantado por la lluvia')
        ->call('bump', 1000)
        ->call('submit', false)
        ->assertDispatched('close-mark-modal')
        ->assertDispatched('toast', type: 'success');

    // La marca original queda INTACTA (refresh desde la base; INV-01).
    expect((array) DB::table('timing_marks')->find($mark->id))->toBe($before);

    // La corrección audita quién, estado previo y resultante.
    $correction = TimingMarkCorrection::query()->where('timing_mark_id', $mark->id)->firstOrFail();

    expect($correction->account_id)->toBe($this->organizer->id)
        ->and($correction->operation)->toBe('adjust')
        ->and($correction->reason)->toBe('sensor adelantado por la lluvia')
        ->and($correction->before_after['before']['bib'])->toBe(102)
        ->and($correction->before_after['after']['delta_ms'])->toBe(1000);

    Queue::assertPushed(RecalculateEventResultsJob::class, fn (RecalculateEventResultsJob $job) => $job->eventId === $this->event->id);
});

it('ajustes sucesivos componen el offset total de la última corrección', function () {
    $entry = entryByBib($this->event, 102);
    $mark = logMark($this->event, $entry, today()->setTime(9, 0, 0)->format('Y-m-d H:i:s.v'));

    $this->actingAs($this->organizer);

    Livewire::test('events.mark-log', ['event' => $this->event])
        ->call('openCorrect', $mark->ulid)
        ->set('reason', 'primera corrección')
        ->call('bump', 1000)
        ->call('submit', false)
        ->assertDispatched('toast', type: 'success');

    Livewire::test('events.mark-log', ['event' => $this->event])
        ->call('openCorrect', $mark->ulid)
        ->set('reason', 'vuelve a corregir')
        ->call('bump', -1250)
        ->call('submit', false)
        ->assertDispatched('toast', type: 'success');

    $corrections = TimingMarkCorrection::query()->where('timing_mark_id', $mark->id)->orderBy('id')->get();

    // delta de ESA corrección (−1.250) sobre el offset vigente (+1.000).
    expect($corrections->pluck('before_after')->pluck('after')->pluck('delta_ms')->all())->toBe([1000, -250]);
});

it('anula la marca: corrección void, fuera del cálculo y visible como anulada', function () {
    $entry = entryByBib($this->event, 102);
    $mark = logMark($this->event, $entry, today()->setTime(9, 0, 0)->format('Y-m-d H:i:s.v'));

    $this->actingAs($this->organizer);

    Livewire::test('events.mark-log', ['event' => $this->event])
        ->call('openCorrect', $mark->ulid)
        ->call('setOperation', 'void')
        ->set('reason', 'lectura falsa del chip')
        ->call('submit', false)
        ->assertDispatched('toast', type: 'success');

    expect(TimingMarkCorrection::query()->where('timing_mark_id', $mark->id)->firstOrFail()->operation)->toBe('void')
        ->and(Result::query()->where('entry_id', $entry->id)->exists())->toBeFalse();

    $log = app(ListMarkLog::class)($this->event);

    expect($log['counters']['void'])->toBe(1)
        ->and($log['marks']->first(fn (array $row): bool => $row['ulid'] === $mark->ulid)['status'])->toBe('void');
});

it('reasignar a un dorsal con llegada exige confirmación y deja la posterior como duplicada', function () {
    // El destino ya tiene llegada a las 09:10; la marca movida llegó antes
    // (09:00), así que la movida queda EFECTIVA y la anterior DUPLICADA.
    $destino = entryByBib($this->event, 103);
    $origen = entryByBib($this->event, 102);
    $anterior = logMark($this->event, $destino, today()->setTime(9, 10, 0)->format('Y-m-d H:i:s.v'));
    $movida = logMark($this->event, $origen, today()->setTime(9, 0, 0)->format('Y-m-d H:i:s.v'));

    $this->actingAs($this->organizer);

    $component = Livewire::test('events.mark-log', ['event' => $this->event])
        ->call('openCorrect', $movida->ulid)
        ->call('setOperation', 'reassign')
        ->set('targetBib', '103')
        ->set('reason', 'dorsal equivocado en la meta');

    // Sin confirmar: el servidor rechaza con el error que abre tcConfirm.
    $component->call('submit', false)->assertDispatched('mark-confirm-target');

    expect(TimingMarkCorrection::query()->where('timing_mark_id', $movida->id)->exists())->toBeFalse();

    // Con confirmación: la corrección pasa, la movida es la llegada efectiva
    // del destino y la anterior queda como duplicada.
    $component->call('submit', true)->assertDispatched('toast', type: 'success');

    $correction = TimingMarkCorrection::query()->where('timing_mark_id', $movida->id)->firstOrFail();

    expect($correction->operation)->toBe('reassign')
        ->and($correction->before_after['before']['bib'])->toBe(102)
        ->and($correction->before_after['after']['bib'])->toBe(103)
        // la marca original sigue apuntando a su entry de nacimiento
        ->and(TimingMark::query()->find($movida->id)->entry_id)->toBe($origen->id);

    $log = app(ListMarkLog::class)($this->event);
    $byUlid = $log['marks']->keyBy('ulid');

    expect($byUlid[$movida->ulid]['status'])->toBe('corrected')
        ->and($byUlid[$anterior->ulid]['status'])->toBe('duplicate')
        ->and($byUlid[$anterior->ulid]['bib'])->toBe(103)
        ->and($log['counters']['duplicate'])->toBe(1)
        ->and($log['counters']['corrected'])->toBe(1);

    // Recálculo de origen y destino: el neto del destino sale de la movida.
    // La salida real persistida de la oleada General del seed es 07:15:09
    // (Wave guarda sin milisegundos), así: 09:00:00 − 07:15:09 = 1:44:51.
    expect(Result::query()->where('entry_id', $destino->id)->firstOrFail()->net_ms)->toBe(6_291_000)
        ->and(Result::query()->where('entry_id', $origen->id)->exists())->toBeFalse();
});

it('reasigna a un dorsal sin llegada sin pedir confirmación', function () {
    $origen = entryByBib($this->event, 102);
    $destino = entryByBib($this->event, 104); // sin marcas
    $mark = logMark($this->event, $origen, today()->setTime(9, 0, 0)->format('Y-m-d H:i:s.v'));

    $this->actingAs($this->organizer);

    Livewire::test('events.mark-log', ['event' => $this->event])
        ->call('openCorrect', $mark->ulid)
        ->call('setOperation', 'reassign')
        ->set('targetBib', '104')
        ->set('reason', 'atleta corrió con el dorsal de otro')
        ->call('submit', false)
        ->assertDispatched('toast', type: 'success')
        ->assertNotDispatched('mark-confirm-target');

    expect(TimingMarkCorrection::query()->where('timing_mark_id', $mark->id)->firstOrFail()->before_after['after']['entry_id'])->toBe($destino->id)
        ->and(Result::query()->where('entry_id', $destino->id)->exists())->toBeTrue();
});

it('la huérfana es asignable desde la bitácora (modal directo en Reasignar)', function () {
    $destino = entryByBib($this->event, 105);
    $orphan = logMark($this->event, null, today()->setTime(9, 5, 0)->format('Y-m-d H:i:s.v'));

    $this->actingAs($this->organizer);

    Livewire::test('events.mark-log', ['event' => $this->event])
        ->call('assign', $orphan->ulid)
        ->assertSet('operation', 'reassign')
        ->assertDispatched('open-mark-modal')
        ->set('targetBib', '105')
        ->set('reason', 'llegada sin dorsal tecleada')
        ->call('submit', false)
        ->assertDispatched('toast', type: 'success');

    $correction = TimingMarkCorrection::query()->where('timing_mark_id', $orphan->id)->firstOrFail();

    expect($correction->before_after['before']['bib'])->toBeNull()
        ->and($correction->before_after['after']['bib'])->toBe(105)
        ->and(TimingMark::query()->find($orphan->id)->entry_id)->toBeNull(); // huérfana de origen intacta

    $log = app(ListMarkLog::class)($this->event);
    $row = $log['marks']->first(fn (array $row): bool => $row['ulid'] === $orphan->ulid);

    expect($row['status'])->toBe('corrected')
        ->and($row['bib'])->toBe(105)
        ->and($row['athlete'])->toBe($destino->person->publicName())
        ->and($log['counters']['orphan'])->toBe(0);
});

it('valida motivo obligatorio, dorsal destino y ajuste cero en el servidor', function () {
    $entry = entryByBib($this->event, 102);
    $mark = logMark($this->event, $entry, today()->setTime(9, 0, 0)->format('Y-m-d H:i:s.v'));

    $this->actingAs($this->organizer);

    Livewire::test('events.mark-log', ['event' => $this->event])
        ->call('openCorrect', $mark->ulid)
        ->set('reason', '')
        ->call('submit', false)
        ->assertHasErrors(['reason']);

    Livewire::test('events.mark-log', ['event' => $this->event])
        ->call('openCorrect', $mark->ulid)
        ->call('setOperation', 'reassign')
        ->set('targetBib', '999')
        ->set('reason', 'motivo válido')
        ->call('submit', false)
        ->assertHasErrors(['targetBib']);

    Livewire::test('events.mark-log', ['event' => $this->event])
        ->call('openCorrect', $mark->ulid)
        ->set('reason', 'motivo válido')
        ->call('submit', false) // delta 0
        ->assertHasErrors(['delta']);

    expect(TimingMarkCorrection::query()->count())->toBe(0);
});

it('el historial de la marca lista sus correcciones con quién y cuándo', function () {
    $entry = entryByBib($this->event, 102);
    $mark = logMark($this->event, $entry, today()->setTime(9, 0, 0)->format('Y-m-d H:i:s.v'));

    $this->actingAs($this->organizer);

    Livewire::test('events.mark-log', ['event' => $this->event])
        ->call('openCorrect', $mark->ulid)
        ->set('reason', 'motivo auditado')
        ->call('bump', 1000)
        ->call('submit', false)
        ->assertDispatched('toast', type: 'success');

    Livewire::test('events.mark-log', ['event' => $this->event])
        ->call('openHistory', $mark->ulid)
        ->assertDispatched('open-history-modal')
        ->assertSee('motivo auditado')
        ->assertSee($this->organizer->person->publicName());
});

it('corrige solo con corrections.manage y ve solo con marks.view', function (string $seeing, bool $canCorrect) {
    // Una marca asignada: la fila existe para pintar (o no) las acciones.
    logMark($this->event, entryByBib($this->event, 102), today()->setTime(9, 0, 0)->format('Y-m-d H:i:s.v'));

    $html = $this->actingAs(correctorAccount($seeing))->get(route('events.marks', $this->event))->getContent();

    expect($html)->toContain(__('ui.events.log_tip_history'));

    // El disparador de corrección solo existe con corrections.manage; el
    // título del modal («Corregir marca») vive siempre en el markup oculto.
    if ($canCorrect) {
        expect($html)->toContain('wire:click="openCorrect(');
    } else {
        expect($html)->not->toContain('wire:click="openCorrect(');
    }
})->with([
    'organizer tiene corrections.manage' => ['organizer', true],
    'timekeeper solo lee' => ['timekeeper', false],
]);

it('rechaza con 403 corregir sin corrections.manage y ver sin marks.view', function () {
    // Ver la bitácora: timekeeper sí, atleta no.
    $this->actingAs(correctorAccount('timekeeper'))->get(route('events.marks', $this->event))->assertOk();
    $this->actingAs(correctorAccount('athlete'))->get(route('events.marks', $this->event))->assertForbidden();

    // Corregir: el servidor autoriza aunque la UI oculte el botón (ADR-011).
    $this->actingAs(correctorAccount('timekeeper'));
    $this->withoutExceptionHandling();

    $this->expectException(AuthorizationException::class);

    Livewire::test('events.mark-log', ['event' => $this->event])->call('submit', false);
});
