<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\Entry;
use App\Models\Event;
use App\Models\Person;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Roster de la estación (GET /events/{e}/entries) y hora del servidor
 * (GET /time). El roster solo expone campos públicos (INV-06): sin
 * documento, nacimiento ni contacto; menores con iniciales.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->event = Event::query()->where('slug', 'vuela-caracas-21k')->firstOrFail();
    $account = Account::query()->where('email', 'admin@tdchronos.test')->firstOrFail();
    $this->token = $account->createToken('meta-tablet-01', ['device:marks', 'device:roster'])->plainTextToken;
});

it('devuelve la hora oficial del servidor sin autenticación', function () {
    $this->getJson('/api/v1/time')
        ->assertOk()
        ->assertJsonStructure(['server_ms', 'timezone']);
});

it('devuelve el roster con solo campos públicos', function () {
    $response = $this->getJson("/api/v1/events/{$this->event->ulid}/entries", ['Authorization' => "Bearer {$this->token}"]);

    $response->assertOk();
    $rows = collect($response->json('rows'));

    expect($rows)->not->toBeEmpty()
        ->and($rows->first())->toHaveKeys(['bib', 'name', 'org', 'race', 'category', 'wave', 'status']);

    $forbidden = ['document', 'birthdate', 'birth_date', 'email', 'phone', 'person_id', 'id'];
    $rows->each(function ($row) use ($forbidden) {
        foreach ($forbidden as $key) {
            expect($row)->not->toHaveKey($key);
        }
    });
});

it('muestra a los menores con iniciales, no con su nombre completo (INV-06)', function () {
    $minor = Person::query()->orderByDesc('birthdate')->first();
    $entry = Entry::query()->where('person_id', $minor->id)->firstOrFail();

    $response = $this->getJson("/api/v1/events/{$this->event->ulid}/entries", ['Authorization' => "Bearer {$this->token}"]);
    $row = collect($response->json('rows'))->first(fn ($r) => $r['bib'] === $entry->bib);

    expect($row['name'])->not->toContain($minor->first_name)
        ->and($row['name'])->toContain('.');
});

it('rechaza el roster sin token o con ability incorrecta', function () {
    $url = "/api/v1/events/{$this->event->ulid}/entries";

    $this->getJson($url)->assertUnauthorized();

    $weakToken = Account::query()->where('email', 'admin@tdchronos.test')->firstOrFail()
        ->createToken('chip', ['device:marks'])->plainTextToken;
    $this->getJson($url, ['Authorization' => "Bearer {$weakToken}"])->assertForbidden();
});
