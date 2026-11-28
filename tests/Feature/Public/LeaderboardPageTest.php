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
 * ISSUE-137 (versión mínima) — pantalla pública /e/{slug}: responde con
 * la clasificación del servidor, sin PII, menores con iniciales, filtros
 * de categoría y distancia, y 404 para eventos desconocidos.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    [$this->event, $this->race, $this->wave, $this->category] = pageScenario();
});

it('muestra la clasificación en vivo del evento', function () {
    $entry = pageEntry($this->event, $this->race, $this->wave, $this->category, 'M', 11, 'Ricardo');
    pageMark($this->event, $entry, '2026-11-23 08:24:00.000');
    (new RecalculateEventResults)($this->event->id);

    $response = $this->get("/e/{$this->event->slug}");

    $response->assertOk()
        ->assertSee($this->event->name)
        ->assertSee('Ricardo')
        ->assertSee('0:24:00')
        ->assertSee('EN VIVO');
});

it('no expone ningún dato personal en la pantalla', function () {
    $entry = pageEntry($this->event, $this->race, $this->wave, $this->category, 'M', 12, 'Alejandro');
    $entry->person->account()->create([
        'ulid' => strtolower((string) str()->ulid()),
        'email' => 'alejandro.secreto@test-tdchronos.test',
        'password' => 'password',
    ]);
    pageMark($this->event, $entry, '2026-11-23 08:31:00.000');
    (new RecalculateEventResults)($this->event->id);

    $person = $entry->person;
    $html = $this->get("/e/{$this->event->slug}")->getContent();

    expect($html)->not->toContain($person->document)
        ->and($html)->not->toContain($person->birthdate->format('Y-m-d'))
        ->and($html)->not->toContain('birthdate')
        ->and($html)->not->toContain('alejandro.secreto@test-tdchronos.test');
});

it('muestra a los menores con iniciales', function () {
    $adulto = pageEntry($this->event, $this->race, $this->wave, $this->category, 'M', 13, 'Valentina');
    $menor = pageEntry($this->event, $this->race, $this->wave, $this->category, 'F', 14, 'Sofía', minor: true);
    pageMark($this->event, $adulto, '2026-11-23 08:40:00.000');
    pageMark($this->event, $menor, '2026-11-23 08:45:00.000');
    (new RecalculateEventResults)($this->event->id);

    $html = $this->get("/e/{$this->event->slug}")->getContent();

    expect($html)->toContain('Valentina Apellido13') // adulto: nombre completo
        ->and($html)->toContain('S. Apellido14')      // menor: iniciales (INV-06)
        ->and($html)->not->toContain('Sofía');
});

it('filtra por categoría y distancia desde el servidor', function () {
    $cat10k = Category::query()->create(['event_id' => $this->event->id, 'name' => 'M 50-59']);
    $a = pageEntry($this->event, $this->race, $this->wave, $this->category, 'M', 21, 'Andrés');
    $b = pageEntry($this->event, $this->race, $this->wave, $cat10k, 'M', 22, 'Bruno');
    pageMark($this->event, $a, '2026-11-23 08:50:00.000');
    pageMark($this->event, $b, '2026-11-23 08:55:00.000');
    (new RecalculateEventResults)($this->event->id);

    Livewire::test('public.leaderboard-page', ['slug' => $this->event->slug])
        ->assertSee('Andrés')
        ->assertSee('Bruno')
        ->set('category', 'M 50-59')
        ->assertSee('Bruno')
        ->assertDontSee('Andrés');
});

it('responde 404 para un evento desconocido', function () {
    $this->get('/e/no-existe')->assertNotFound();
});

it('la landing lista los eventos con enlace a su pantalla pública', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee($this->event->name)
        ->assertSee('/e/'.$this->event->slug);
});

// --- helpers del escenario ---

function pageScenario(): array
{
    $suffix = str()->random(6);
    $event = Event::query()->create([
        'name' => "Pantalla Test $suffix",
        'slug' => "pantalla-test-$suffix",
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

function pageEntry(Event $event, Race $race, Wave $wave, Category $category, string $gender, int $bib, string $firstName, bool $minor = false): Entry
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

function pageMark(Event $event, Entry $entry, string $capturedAt): TimingMark
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
