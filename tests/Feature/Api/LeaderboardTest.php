<?php

declare(strict_types=1);

use App\Actions\Results\RecalculateEventResults;
use App\Models\Category;
use App\Models\Entry;
use App\Models\Event;
use App\Models\Person;
use App\Models\Race;
use App\Models\TimingMark;
use App\Models\Wave;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Leaderboard público (GET /api/v1/public/events/{e}/leaderboard):
 * sin login, sin PII (INV-06), menores con iniciales, contrato v/seq.
 */
uses(RefreshDatabase::class);

it('muestra la clasificación ordenada y sin ningún dato personal', function () {
    [$event, $race, $wave, $category] = seedBoard();
    $entry = boardEntry($event, $race, $wave, $category, 'M', 101, 'Valentina');
    boardMark($event, $entry, '2026-11-23 08:05:00.000');
    (new RecalculateEventResults)($event->id);

    $response = $this->getJson("/api/v1/public/events/{$event->ulid}/leaderboard");

    $response->assertOk();

    $payload = $response->json();
    expect($payload)->toHaveKeys(['v', 'seq', 'status', 'rows'])
        ->and($payload['rows'])->not->toBeEmpty();

    $row = $payload['rows'][0];
    expect($row)->toHaveKeys(['bib', 'name', 'org', 'race', 'category', 'net_ms', 'ranks', 'status']);

    $forbidden = ['document', 'birthdate', 'birth_date', 'email', 'phone', 'person_id', 'entry_id', 'id'];
    foreach ($forbidden as $key) {
        expect($row)->not->toHaveKey($key);
    }
});

it('muestra a los menores con iniciales y aumenta seq con cada llegada', function () {
    [$event, $race, $wave, $category] = seedBoard();

    $adulto = boardEntry($event, $race, $wave, $category, 'M', 201, 'Valentina');
    $menor = boardEntry($event, $race, $wave, $category, 'F', 202, 'Sofía', minor: true);
    boardMark($event, $adulto, '2026-11-23 08:04:00.000');
    boardMark($event, $menor, '2026-11-23 08:06:00.000');
    (new RecalculateEventResults)($event->id);

    $first = $this->getJson("/api/v1/public/events/{$event->ulid}/leaderboard")->json();
    $filaMenor = collect($first['rows'])->first(fn ($r) => $r['bib'] === 202);

    expect($filaMenor['name'])->toBe('S. Apellido202')
        ->and($filaMenor['name'])->not->toContain('Sofía');

    // una llegada más: el seq de la versión crece
    $otro = boardEntry($event, $race, $wave, $category, 'M', 203, 'Pedro');
    boardMark($event, $otro, '2026-11-23 08:08:00.000');
    (new RecalculateEventResults)($event->id);

    $second = $this->getJson("/api/v1/public/events/{$event->ulid}/leaderboard")->json();

    expect($second['seq'])->toBe($first['seq'] + 1)
        ->and(count($second['rows']))->toBe(count($first['rows']) + 1);
});

// --- helpers del escenario ---

function seedBoard(): array
{
    $suffix = str()->random(6);
    $event = Event::query()->create([
        'name' => "Lead Test $suffix",
        'slug' => "lead-test-$suffix",
        'date' => today()->format('Y-m-d'),
        'status' => 'live',
    ]);
    $race = Race::query()->create(['event_id' => $event->id, 'name' => '21K']);
    $wave = Wave::query()->create([
        'race_id' => $race->id,
        'name' => 'Única',
        'scheduled_start_at' => '2026-11-23 08:00:00.000',
        'actual_start_at' => '2026-11-23 08:00:00.000',
    ]);
    $category = Category::query()->create(['event_id' => $event->id, 'name' => 'M 30-39']);

    return [$event, $race, $wave, $category];
}

function boardEntry(Event $event, Race $race, Wave $wave, Category $category, string $gender, int $bib, string $firstName = 'Nombre', bool $minor = false): Entry
{
    $person = Person::query()->create([
        'ulid' => strtolower((string) str()->ulid()),
        'document' => 'V-'.str()->random(8),
        'first_name' => $firstName,
        'last_name' => 'Apellido'.$bib,
        'birthdate' => $minor ? '2012-05-10' : '1990-03-15',
        'gender' => $gender,
    ]);

    return Entry::query()->create([
        'event_id' => $event->id,
        'category_id' => $category->id,
        'race_id' => $race->id,
        'wave_id' => $wave->id,
        'person_id' => $person->id,
        'team_id' => null,
        'bib' => $bib,
        'status' => 'registered',
    ]);
}

function boardMark(Event $event, Entry $entry, string $capturedAt): TimingMark
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
