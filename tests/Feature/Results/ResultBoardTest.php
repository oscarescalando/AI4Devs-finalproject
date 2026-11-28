<?php

declare(strict_types=1);

use App\Actions\Results\ListEventClassification;
use App\Actions\Results\RecalculateEventResults;
use App\Models\Account;
use App\Models\Category;
use App\Models\Entry;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Race;
use App\Models\Region;
use App\Models\Result;
use App\Models\Team;
use App\Models\TimingMark;
use App\Models\Wave;
use App\Support\FormatDuration;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * ISSUE-137/TQ-015 — Ranking del panel: clasificación del evento que SOLO
 * lee la tabla derivada results (INV-07). La conciliación es el corazón:
 * lo que la pantalla muestra (posiciones, netos y diferencias) debe
 * coincidir exactamente con lo que escribió el motor — cero cálculo en
 * pantalla. Filtros carrera/categoría por ULID en la URL, paginación
 * server-side de 25, estado provisional/oficial del evento y empty-state.
 */
uses(RefreshDatabase::class);

function rankingAccount(string $role): Account
{
    $account = Account::factory()->create();
    $account->assignRole($role);

    return $account;
}

function rankingEvent(array $attributes = []): Event
{
    $suffix = str()->random(6);

    return Event::query()->create(array_merge([
        'name' => "Carrera Panel $suffix",
        'slug' => "carrera-panel-$suffix",
        'date' => today()->format('Y-m-d'),
        'timezone' => 'America/Caracas',
        'status' => 'live',
    ], $attributes));
}

function rankingWave(Race $race, string $actualStart): Wave
{
    return Wave::query()->create([
        'race_id' => $race->id,
        'name' => 'Única',
        'scheduled_start_at' => '2026-11-23 08:00:00.000',
        'actual_start_at' => $actualStart,
    ]);
}

function rankingEntry(Event $event, Race $race, Wave $wave, Category $category, string $gender, int $bib, bool $minor = false): Entry
{
    $person = Person::query()->create([
        'ulid' => strtolower((string) str()->ulid()),
        'document' => 'V-'.str()->random(8),
        'first_name' => 'Nombre'.$bib,
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

function rankingMark(Event $event, Entry $entry, string $capturedAt): TimingMark
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

/**
 * Escenario de conciliación: 4 llegadas 21K (un menor), 1 en 10K, 1 equipo
 * con club y 1 inscrito sin llegada.
 *
 * @return array<string, mixed>
 */
function rankingScenario(): array
{
    $event = rankingEvent();
    $race21k = Race::query()->create(['event_id' => $event->id, 'name' => '21K']);
    $race10k = Race::query()->create(['event_id' => $event->id, 'name' => '10K']);
    $wave21 = rankingWave($race21k, '2026-11-23 08:00:00.000');
    $wave10 = rankingWave($race10k, '2026-11-23 09:00:00.000');
    $catM = Category::query()->create(['event_id' => $event->id, 'name' => 'M 30-39']);
    $catF = Category::query()->create(['event_id' => $event->id, 'name' => 'F 30-39']);

    $a = rankingEntry($event, $race21k, $wave21, $catM, 'M', 101); // 0:10:00.0
    $b = rankingEntry($event, $race21k, $wave21, $catM, 'M', 102); // 0:10:30.5
    $c = rankingEntry($event, $race21k, $wave21, $catF, 'F', 103); // 0:12:00.2
    $minor = rankingEntry($event, $race21k, $wave21, $catM, 'M', 104, true);
    $e = rankingEntry($event, $race10k, $wave10, $catF, 'F', 105); // 10K aparte
    $pending = rankingEntry($event, $race21k, $wave21, $catM, 'M', 106); // sin llegada

    $organization = Organization::query()->create([
        'region_id' => Region::query()->create(['name' => 'Distrito Capital'])->id,
        'name' => 'Club Tablero',
    ]);
    $team = Team::query()->create(['organization_id' => $organization->id, 'name' => 'Equipo Prueba']);
    $teamEntry = Entry::query()->create([
        'event_id' => $event->id,
        'category_id' => $catM->id,
        'race_id' => $race21k->id,
        'wave_id' => $wave21->id,
        'person_id' => null,
        'team_id' => $team->id,
        'bib' => 107,
        'status' => 'registered',
    ]);

    rankingMark($event, $a, '2026-11-23 08:10:00.000');
    rankingMark($event, $b, '2026-11-23 08:10:30.500');
    rankingMark($event, $c, '2026-11-23 08:12:00.250');
    rankingMark($event, $minor, '2026-11-23 08:20:00.000');
    rankingMark($event, $e, '2026-11-23 09:00:30.000');
    rankingMark($event, $teamEntry, '2026-11-23 08:15:00.000');

    (new RecalculateEventResults)($event->id);

    return [
        'event' => $event,
        'race21k' => $race21k,
        'catM' => $catM,
        'catF' => $catF,
        'a' => $a,
        'b' => $b,
        'c' => $c,
        'minor' => $minor,
        'e' => $e,
        'pending' => $pending,
        'teamEntry' => $teamEntry,
        'team' => $team,
        'organization' => $organization,
    ];
}

beforeEach(function () {
    $this->seed(RolesPermissionsSeeder::class);
});

it('exige results.view: organizador y juez entran, cronometrador recibe 403', function () {
    $event = rankingEvent();

    $this->actingAs(rankingAccount('organizer'))->get(route('events.results', $event))->assertOk();
    $this->actingAs(rankingAccount('official'))->get(route('events.results', $event))->assertOk();
    $this->actingAs(rankingAccount('timekeeper'))->get(route('events.results', $event))->assertForbidden();
});

it('concilia: lo mostrado es exactamente la tabla results del motor (INV-07)', function () {
    $s = rankingScenario();

    $data = app(ListEventClassification::class)($s['event']);
    $rows = collect($data['rows']->items())->keyBy('bib');

    // Fuente única de verdad: results. El primer neto de la carrera 21K,
    // mínimo sobre la propia tabla, es la referencia de las diferencias.
    $results = Result::query()->get()->keyBy('entry_id');
    $race21Ids = array_map(static fn (Entry $entry): int => (int) $entry->id, [$s['a'], $s['b'], $s['c'], $s['minor'], $s['teamEntry']]);
    $leader21 = (int) $results->filter(fn (Result $r) => in_array((int) $r->entry_id, $race21Ids, true))->min('net_ms');

    foreach ([$s['a'], $s['b'], $s['c'], $s['minor'], $s['teamEntry']] as $entry) {
        $result = $results[$entry->id];
        $row = $rows[$entry->bib];
        $expectedDiff = $result->net_ms - $leader21;

        expect($row['rank_general'])->toBe($result->rank_general)
            ->and($row['rank_gender'])->toBe($result->rank_gender)
            ->and($row['rank_category'])->toBe($result->rank_category)
            ->and($row['net_ms'])->toBe($result->net_ms)
            ->and($row['net'])->toBe(FormatDuration::fromMs($result->net_ms, true))
            // diferencia = neto − primer neto de SU carrera (resta entre
            // valores ya escritos: cero cálculo de posición en pantalla);
            // el líder no arrastra diferencia (se pinta «—»)
            ->and($row['diff_ms'])->toBe($expectedDiff === 0 ? null : $expectedDiff);
    }

    // La otra carrera clasifica aparte y su líder no arrastra diferencia.
    expect($rows[$s['e']->bib]['rank_general'])->toBe(1)
        ->and($rows[$s['e']->bib]['diff_ms'])->toBeNull()
        // contadores del encabezado: 6 llegadas de 7 inscritos
        ->and($data['counters'])->toBe(['arrived' => 6, 'pending' => 1, 'total' => 7])
        // sin llegada no hay fila
        ->and($rows)->not->toHaveKey($s['pending']->bib)
        // menores con iniciales (INV-06, mismo criterio del leaderboard público)
        ->and($rows[$s['minor']->bib]['athlete'])->toBe($s['minor']->person->publicName())
        // inscripción de equipo: nombre del equipo y su club
        ->and($rows[$s['teamEntry']->bib]['athlete'])->toBe($s['team']->name)
        ->and($rows[$s['teamEntry']->bib]['club'])->toBe($s['organization']->name);

    // Y la pantalla pinta lo mismo que devuelve la Action.
    $html = $this->actingAs(rankingAccount('organizer'))->get(route('events.results', $s['event']))->getContent();

    expect($html)->toContain(FormatDuration::fromMs($results[$s['a']->id]->net_ms, true))
        ->and($html)->toContain('+'.FormatDuration::fromMs($results[$s['b']->id]->net_ms - $leader21, true))
        ->and($html)->toContain($s['minor']->person->publicName())
        ->and($html)->not->toContain('Nombre'.$s['minor']->bib)
        ->and($html)->not->toContain((string) $s['minor']->person->document);
});

it('la diferencia con el primero es exacta: segundo = net2 − net1', function () {
    $s = rankingScenario();

    $net1 = (int) Result::query()->where('entry_id', $s['a']->id)->value('net_ms');
    $net2 = (int) Result::query()->where('entry_id', $s['b']->id)->value('net_ms');

    expect($net1)->toBe(600_000)
        ->and($net2)->toBe(630_500);

    $rows = collect(app(ListEventClassification::class)($s['event'])['rows']->items())->keyBy('bib');

    expect($rows[$s['a']->bib]['diff_ms'])->toBeNull() // el líder no arrastra diferencia
        ->and($rows[$s['b']->bib]['diff_ms'])->toBe($net2 - $net1)
        ->and($rows[$s['b']->bib]['diff'])->toBe('+'.FormatDuration::fromMs($net2 - $net1, true));

    $this->actingAs(rankingAccount('official'))->get(route('events.results', $s['event']))
        ->assertOk()
        ->assertSee('+'.FormatDuration::fromMs($net2 - $net1, true), false);
});

it('el estado provisional/oficial se deriva del status del evento (como el motor)', function () {
    $s = rankingScenario();

    $live = $this->actingAs(rankingAccount('organizer'))->get(route('events.results', $s['event']))->getContent();
    expect($live)->toContain(__('ui.events.results_status_provisional'))
        ->and($live)->not->toContain(__('ui.events.results_status_official'));

    // El evento cierra SIN recalcular: el badge deriva del status del evento.
    $s['event']->update(['status' => 'finished']);

    $closed = $this->actingAs(rankingAccount('organizer'))->get(route('events.results', $s['event']))->getContent();
    expect($closed)->toContain(__('ui.events.results_status_official'))
        ->and($closed)->not->toContain(__('ui.events.results_status_provisional'));
});

it('filtra por carrera y categoría por ULID y conserva el estado en la URL', function () {
    $s = rankingScenario();
    $organizer = rankingAccount('organizer');

    // Por carrera: llegadas del 21K sí, las del 10K no.
    $byRace = $this->actingAs($organizer)
        ->get(route('events.results', ['event' => $s['event'], 'race' => $s['race21k']->ulid]))
        ->assertOk()
        ->getContent();

    expect($byRace)->toContain('Nombre'.$s['c']->bib)
        ->and($byRace)->not->toContain('Nombre'.$s['e']->bib)
        // el filtro queda en la URL: el select lo trae seleccionado
        ->and($byRace)->toContain(sprintf('value="%s" selected', $s['race21k']->ulid));

    // Por categoría: M 30-39 sí, la F 103 no.
    $byCategory = $this->actingAs($organizer)
        ->get(route('events.results', ['event' => $s['event'], 'category' => $s['catM']->ulid]))
        ->assertOk()
        ->getContent();

    expect($byCategory)->toContain('Nombre'.$s['b']->bib)
        ->and($byCategory)->not->toContain('Nombre'.$s['c']->bib)
        ->and($byCategory)->toContain(sprintf('value="%s" selected', $s['catM']->ulid));

    // Combinado carrera + categoría: solo la F del 21K.
    $combined = $this->actingAs($organizer)
        ->get(route('events.results', ['event' => $s['event'], 'race' => $s['race21k']->ulid, 'category' => $s['catF']->ulid]))
        ->assertOk()
        ->getContent();

    expect($combined)->toContain('Nombre'.$s['c']->bib)
        ->and($combined)->not->toContain('Nombre'.$s['a']->bib)
        ->and($combined)->not->toContain('Nombre'.$s['e']->bib);

    // Y un ULID desconocido se ignora: la tabla completa.
    $unknown = $this->actingAs($organizer)
        ->get(route('events.results', ['event' => $s['event'], 'race' => strtolower((string) str()->ulid())]))
        ->assertOk()
        ->getContent();

    expect($unknown)->toContain('Nombre'.$s['a']->bib)
        ->and($unknown)->toContain('Nombre'.$s['e']->bib);
});

it('pagina server-side de 25 por posición dentro de cada carrera', function () {
    $event = rankingEvent();
    $race = Race::query()->create(['event_id' => $event->id, 'name' => '21K']);
    $wave = rankingWave($race, '2026-11-23 08:00:00.000');
    $category = Category::query()->create(['event_id' => $event->id, 'name' => 'M 30-39']);

    foreach (range(1, 30) as $i) {
        $entry = rankingEntry($event, $race, $wave, $category, 'M', 100 + $i);
        rankingMark($event, $entry, sprintf('2026-11-23 08:%02d:00.000', $i));
    }

    (new RecalculateEventResults)($event->id);

    $data = app(ListEventClassification::class)($event);
    $page1 = collect($data['rows']->items());

    expect($data['rows']->total())->toBe(30)
        ->and($page1->count())->toBe(25)
        // orden: carrera, posición, neto, dorsal
        ->and($page1->first()['rank_general'])->toBe(1)
        ->and($page1->last()['rank_general'])->toBe(25);

    // La segunda página trae las 5 restantes (26–30 de 30).
    $page2 = $this->actingAs(rankingAccount('organizer'))
        ->get(route('events.results', ['event' => $event, 'page' => 2]))
        ->assertOk()
        ->getContent();

    expect($page2)->toContain('26–30 de 30')
        ->and($page2)->toContain(FormatDuration::fromMs(1_560_000, true)) // #26: 08:26 → 26 min
        ->and($page2)->not->toContain(FormatDuration::fromMs(60_000, true)); // #1: 08:01 → 1 min
});

it('muestra el empty-state cuando el evento no tiene llegadas', function () {
    $event = rankingEvent();
    $race = Race::query()->create(['event_id' => $event->id, 'name' => '21K']);
    $wave = rankingWave($race, '2026-11-23 08:00:00.000');
    $category = Category::query()->create(['event_id' => $event->id, 'name' => 'M 30-39']);
    rankingEntry($event, $race, $wave, $category, 'M', 201); // inscrito, sin marca

    $data = app(ListEventClassification::class)($event);

    expect($data['rows']->total())->toBe(0)
        ->and($data['counters'])->toBe(['arrived' => 0, 'pending' => 1, 'total' => 1]);

    $html = $this->actingAs(rankingAccount('organizer'))->get(route('events.results', $event))->getContent();

    expect($html)->toContain(__('ui.events.results_none_title'))
        ->and($html)->toContain(__('ui.events.results_none_text'))
        ->and($html)->toContain('wire:poll.10s');
});
