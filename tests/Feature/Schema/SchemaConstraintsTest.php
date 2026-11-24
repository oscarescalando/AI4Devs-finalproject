<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\Category;
use App\Models\Entry;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Race;
use App\Models\Region;
use App\Models\Team;
use App\Models\TimingMark;
use App\Models\Wave;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * ISSUE-157 — Pruebas de esquema del núcleo: recorren information_schema
 * y verifican en el motor real las reglas INV-01…INV-05 e INV-09.
 */
uses(RefreshDatabase::class);

function schemaIndexes(string $table): array
{
    return DB::select(
        'SELECT DISTINCT INDEX_NAME AS name FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
        [$table]
    );
}

function schemaTriggers(string $table): array
{
    return DB::select(
        'SELECT TRIGGER_NAME AS name FROM information_schema.TRIGGERS
         WHERE EVENT_OBJECT_SCHEMA = DATABASE() AND EVENT_OBJECT_TABLE = ?',
        [$table]
    );
}

function schemaChecks(string $table): array
{
    // MySQL 8.0: CHECK_CONSTRAINTS no tiene TABLE_NAME; se une con TABLE_CONSTRAINTS
    return DB::select(
        'SELECT cc.CONSTRAINT_NAME AS name
         FROM information_schema.CHECK_CONSTRAINTS cc
         JOIN information_schema.TABLE_CONSTRAINTS tc
           ON tc.CONSTRAINT_SCHEMA = cc.CONSTRAINT_SCHEMA AND tc.CONSTRAINT_NAME = cc.CONSTRAINT_NAME
         WHERE tc.TABLE_SCHEMA = DATABASE() AND tc.TABLE_NAME = ?',
        [$table]
    );
}

it('tiene índice único de dorsal por evento (INV-03)', function () {
    $names = array_column(schemaIndexes('entries'), 'name');
    expect($names)->toContain('entries_event_id_bib_unique');
});

it('tiene índice único de persona por evento (INV-04)', function () {
    $names = array_column(schemaIndexes('entries'), 'name');
    expect($names)->toContain('entries_event_id_person_id_unique');
});

it('tiene check de persona xor equipo en la inscripción (INV-05)', function () {
    $names = array_column(schemaChecks('entries'), 'name');
    expect($names)->toContain('entries_person_xor_team');
});

it('tiene client_mark_id único para idempotencia (INV-02)', function () {
    $names = array_column(schemaIndexes('timing_marks'), 'name');
    expect($names)->toContain('timing_marks_client_mark_id_unique');
});

it('tiene triggers append-only sobre timing_marks (INV-01)', function () {
    $names = array_column(schemaTriggers('timing_marks'), 'name');
    expect($names)->toContain('timing_marks_no_update')
        ->and($names)->toContain('timing_marks_no_delete');
});

it('rechaza dos inscripciones con el mismo dorsal en un evento', function () {
    $event = seedEventWithCategory();
    [$race, $wave, $category] = $event['parts'];
    $person = Person::factory()->create();

    Entry::query()->create(entryAttributes($event['event'], $race, $wave, $category, $person, 101));

    expect(fn () => Entry::query()->create(
        entryAttributes($event['event'], $race, $wave, $category, Person::factory()->create(), 101)
    ))->toThrow(QueryException::class);
});

it('rechaza inscripción con persona y equipo a la vez (XOR)', function () {
    $event = seedEventWithCategory();
    [$race, $wave, $category] = $event['parts'];
    $person = Person::factory()->create();
    $team = Team::query()->create([
        'organization_id' => $event['organization']->id,
        'name' => 'XOR Team',
    ]);

    expect(fn () => DB::table('entries')->insert(
        array_merge(entryAttributes($event['event'], $race, $wave, $category, null, 300), [
            'person_id' => $person->id,
            'team_id' => $team->id,
        ])
    ))->toThrow(QueryException::class);
});

it('rechaza inscripción sin persona ni equipo (XOR)', function () {
    $event = seedEventWithCategory();
    [$race, $wave, $category] = $event['parts'];

    expect(fn () => DB::table('entries')->insert(
        entryAttributes($event['event'], $race, $wave, $category, null, 301)
    ))->toThrow(QueryException::class);
});

it('timing_marks rechaza update y delete directamente (append-only)', function () {
    $mark = seedTimingMark();

    expect(fn () => DB::table('timing_marks')->where('id', $mark->id)->update(['device_time_ms' => 1]))
        ->toThrow(QueryException::class)
        ->and(fn () => DB::table('timing_marks')->where('id', $mark->id)->delete())
        ->toThrow(QueryException::class);
});

it('timing_mark_corrections sí permite auditar sin tocar la marca', function () {
    $mark = seedTimingMark();
    $account = Account::factory()->create();

    DB::table('timing_mark_corrections')->insert([
        'ulid' => (string) str()->ulid(),
        'timing_mark_id' => $mark->id,
        'operation' => 'adjust',
        'reason' => 'prueba de auditoría',
        'account_id' => $account->id,
        'before_after' => json_encode(['before' => ['device_time_ms' => $mark->device_time_ms], 'after' => ['captured_at' => '+500ms']]),
        'created_at' => now(),
    ]);

    expect(DB::table('timing_mark_corrections')->count())->toBe(1)
        ->and($mark->fresh()->device_time_ms)->toBe($mark->device_time_ms); // la original no cambió
});

/**
 * @return array{event: Event, parts: array{0: Race, 1: Wave, 2: Category}, organization: Organization}
 */
function seedEventWithCategory(): array
{
    $region = Region::query()->create(['name' => 'Región Test']);
    $organization = Organization::query()->create(['region_id' => $region->id, 'name' => 'Club Test']);
    $event = Event::query()->create([
        'name' => 'Evento Test '.str()->random(6),
        'slug' => 'evento-test-'.str()->random(6),
        'date' => today()->format('Y-m-d'),
    ]);
    $race = Race::query()->create(['event_id' => $event->id, 'name' => '21K']);
    $wave = Wave::query()->create(['race_id' => $race->id, 'name' => 'Única']);
    $category = Category::query()->create(['event_id' => $event->id, 'name' => 'M 30-39']);

    return ['event' => $event, 'parts' => [$race, $wave, $category], 'organization' => $organization];
}

function entryAttributes(Event $event, Race $race, Wave $wave, Category $category, ?Person $person, int $bib): array
{
    return [
        'ulid' => (string) str()->ulid(),
        'event_id' => $event->id,
        'category_id' => $category->id,
        'race_id' => $race->id,
        'wave_id' => $wave->id,
        'person_id' => $person?->id,
        'team_id' => null,
        'bib' => $bib,
        'status' => 'registered',
        'created_at' => now(),
        'updated_at' => now(),
    ];
}

function seedTimingMark(): TimingMark
{
    $event = seedEventWithCategory();
    [$race, $wave, $category] = $event['parts'];
    $person = Person::factory()->create();
    Entry::query()->create(entryAttributes($event['event'], $race, $wave, $category, $person, 101));

    return TimingMark::query()->create([
        'event_id' => $event['event']->id,
        'entry_id' => Entry::query()->first()->id,
        'client_mark_id' => (string) str()->uuid(),
        'captured_at' => now(),
        'device_time_ms' => now()->getTimestampMs(),
        'source' => 'station',
    ]);
}
