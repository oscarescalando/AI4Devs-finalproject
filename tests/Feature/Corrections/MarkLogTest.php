<?php

declare(strict_types=1);

use App\Actions\Corrections\ListMarkLog;
use App\Models\Account;
use App\Models\Event;
use App\Models\TimingMark;
use App\Support\FormatClock;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * ISSUE-134 (TQ-014, versión mínima F1) — Bitácora de marcas en vivo del
 * juez: contadores por estado coherentes con la tabla (misma derivación del
 * motor, EffectiveMarks), paginación server-side de 25 por hora efectiva
 * descendente, huérfanas visibles y sondeo de respaldo wire:poll.15s
 * (el push por WS llegó con TQ-018/EP-26).
 */
uses(RefreshDatabase::class);

function logAccount(string $role): Account
{
    $account = Account::factory()->create();
    $account->assignRole($role);

    return $account;
}

function logMarkAt(Event $event, ?int $entryId, string $capturedAt): TimingMark
{
    return TimingMark::query()->create([
        'event_id' => $event->id,
        'entry_id' => $entryId,
        'client_mark_id' => (string) str()->uuid(),
        'captured_at' => CarbonImmutable::parse($capturedAt),
        'device_time_ms' => CarbonImmutable::parse($capturedAt)->getTimestampMs(),
        'source' => 'station',
    ]);
}

function logEntryId(Event $event, int $bib): int
{
    return (int) $event->entries()->where('bib', $bib)->value('id');
}

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->event = Event::query()->where('slug', 'vuela-caracas-21k')->firstOrFail();
});

it('deriva los cinco estados con contadores coherentes con la tabla', function () {
    $bib102 = logEntryId($this->event, 102);
    $bib103 = logEntryId($this->event, 103);
    $day = now()->format('Y-m-d');

    // Asignada + duplicada (dos lecturas del mismo dorsal).
    logMarkAt($this->event, $bib102, "$day 09:00:00.000");
    $duplicada = logMarkAt($this->event, $bib102, "$day 09:00:02.500");
    // Huérfana.
    $orphan = logMarkAt($this->event, null, "$day 09:01:00.000");
    // Anulada y corregida.
    logMarkAt($this->event, $bib103, "$day 09:02:00.000")->corrections()->create([
        'ulid' => strtolower((string) str()->ulid()),
        'operation' => 'void',
        'reason' => 'lectura falsa',
        'account_id' => null,
        'before_after' => ['before' => [], 'after' => []],
    ]);
    logMarkAt($this->event, $bib103, "$day 09:03:00.000")->corrections()->create([
        'ulid' => strtolower((string) str()->ulid()),
        'operation' => 'adjust',
        'reason' => 'ajuste fino',
        'account_id' => null,
        'before_after' => ['before' => [], 'after' => ['captured_at' => "$day 09:03:01.000", 'delta_ms' => 1000]],
    ]);

    $log = app(ListMarkLog::class)($this->event);

    expect($log['counters'])->toBe([
        'assigned' => 1,
        'orphan' => 1,
        'duplicate' => 1,
        'void' => 1,
        'corrected' => 1,
    ])->and($log['marks']->total())->toBe(5)
        ->and($log['marks']->keyBy('ulid')[$duplicada->ulid]['status'])->toBe('duplicate')
        ->and($log['marks']->keyBy('ulid')[$orphan->ulid]['status'])->toBe('orphan');

    // La huérfana pinta «Huérfana» y sin dorsal; la duplicada es info.
    $orphanRow = $log['marks']->keyBy('ulid')[$orphan->ulid];

    expect($orphanRow['bib'])->toBeNull()
        ->and($orphanRow['athlete'])->toBeNull();

    // Y la pantalla muestra contadores y badges del kit con los cinco estados.
    $html = $this->actingAs(logAccount('organizer'))->get(route('events.marks', $this->event))->getContent();

    foreach (['Asignadas', 'Huérfanas', 'Duplicadas', 'Anuladas', 'Corregidas'] as $label) {
        expect($html)->toContain($label);
    }
    expect($html)->toContain(__('ui.events.log_orphan_cell'))
        ->and($html)->toContain(__('ui.events.log_status_duplicate'));
});

it('pagina server-side de 25 por hora efectiva descendente', function () {
    $bib = logEntryId($this->event, 102);
    $day = now()->format('Y-m-d');

    foreach (range(1, 30) as $i) {
        logMarkAt($this->event, $bib, sprintf('%s 09:%02d:00.000', $day, $i));
    }

    $log = app(ListMarkLog::class)($this->event);

    $rows = collect($log['marks']->items());

    expect($log['marks']->total())->toBe(30)
        ->and($rows->count())->toBe(25)
        // la más nueva primero (hora efectiva descendente)
        ->and($rows->first()['time'])->toBe(FormatClock::timeOfDay(CarbonImmutable::parse("$day 09:30:00.000"), $this->event->timezone))
        ->and($rows->last()['time'])->toBe(FormatClock::timeOfDay(CarbonImmutable::parse("$day 09:06:00.000"), $this->event->timezone));

    // La segunda página trae las 5 restantes (26–30 de 30).
    $page2 = $this->actingAs(logAccount('organizer'))
        ->get(route('events.marks', ['event' => $this->event, 'page' => 2]))
        ->assertOk()
        ->getContent();

    expect($page2)->toContain('26–30 de 30')
        ->and($page2)->toContain(FormatClock::timeOfDay(CarbonImmutable::parse("$day 09:05:00.000"), $this->event->timezone))
        ->and($page2)->not->toContain(FormatClock::timeOfDay(CarbonImmutable::parse("$day 09:30:00.000"), $this->event->timezone));
});

it('traza la pantalla: sondeo interino, contadores y modales del kit', function () {
    $html = $this->actingAs(logAccount('organizer'))->get(route('events.marks', $this->event))->getContent();

    expect($html)->toContain('wire:poll.15s')
        ->and($html)->toContain('tcConfirm')
        ->and($html)->toContain('markModal')
        ->and($html)->toContain('historyModal')
        ->and($html)->toContain('mark-confirm-target');
});

it('la huérfana ofrece «Asignar» y el resto «Corregir» solo con el permiso', function () {
    $bib = logEntryId($this->event, 102);
    $day = now()->format('Y-m-d');
    $orphan = logMarkAt($this->event, null, "$day 09:00:00.000");
    $assigned = logMarkAt($this->event, $bib, "$day 09:01:00.000");

    $organizer = $this->actingAs(logAccount('organizer'))->get(route('events.marks', $this->event))->getContent();
    $timekeeper = $this->actingAs(logAccount('timekeeper'))->get(route('events.marks', $this->event))->getContent();

    // Organizador: asignar la huérfana, corregir la asignada, historial ambas
    // (el tip viaja en title + aria-label: dos apariciones por botón).
    expect($organizer)->toContain('wire:click="assign(')
        ->and($organizer)->toContain('wire:click="openCorrect(')
        ->and(substr_count($organizer, __('ui.events.log_tip_history')))->toBe(4);

    // Cronometrador: sin acciones de corrección, pero con historial.
    expect($timekeeper)->not->toContain('wire:click="assign(')
        ->and($timekeeper)->not->toContain('wire:click="openCorrect(')
        ->and(substr_count($timekeeper, __('ui.events.log_tip_history')))->toBe(4);

    expect($orphan->entry_id)->toBeNull()
        ->and($assigned->entry_id)->not->toBeNull();
});
