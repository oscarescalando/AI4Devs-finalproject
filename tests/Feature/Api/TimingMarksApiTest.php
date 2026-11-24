<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\Entry;
use App\Models\Event;
use App\Models\TimingMark;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * ISSUE-156 — Endpoint idempotente de marcas:
 * lote → reenvío sin duplicados (INV-02) → huérfana por dorsal desconocido.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->event = Event::query()->where('slug', 'vuela-caracas-21k')->firstOrFail();
    $this->station = Account::query()->where('email', 'admin@tdchronos.test')->firstOrFail();
    $this->token = $this->station->createToken('meta-tablet-01', ['device:marks', 'device:roster'])->plainTextToken;
});

function markPayload(int $bib, string $clientMarkId): array
{
    return [
        'client_mark_id' => $clientMarkId,
        'bib' => $bib,
        'device_time_ms' => now()->getTimestampMs(),
        'clock_offset_ms' => -320,
        'source' => 'station',
    ];
}

it('asigna la marca al dorsal inscrito y devuelve el entry público', function () {
    $entry = Entry::query()->where('event_id', $this->event->id)->where('bib', 128)->firstOrFail();
    $markId = (string) str()->uuid();

    $response = $this->postJson("/api/v1/events/{$this->event->ulid}/timing-marks", [
        'marks' => [markPayload(128, $markId)],
    ], ['Authorization' => "Bearer {$this->token}"]);

    $response->assertOk()
        ->assertJsonPath('results.0.status', 'assigned')
        ->assertJsonPath('results.0.entry_id', $entry->ulid)
        ->assertJsonPath('results.0.client_mark_id', $markId);
});

it('reenvía el mismo lote sin duplicar (INV-02)', function () {
    $marks = [
        markPayload(101, (string) str()->uuid()),
        markPayload(102, (string) str()->uuid()),
    ];
    $payload = ['marks' => $marks];

    $first = $this->postJson("/api/v1/events/{$this->event->ulid}/timing-marks", $payload, ['Authorization' => "Bearer {$this->token}"]);
    $first->assertOk();
    expect(TimingMark::query()->count())->toBe(2);

    $second = $this->postJson("/api/v1/events/{$this->event->ulid}/timing-marks", $payload, ['Authorization' => "Bearer {$this->token}"]);

    $second->assertOk();
    expect(TimingMark::query()->count())->toBe(2); // no duplicó

    // el reenvío reporta duplicate por marca y conserva la asignación persistida
    foreach ($second->json('results') as $i => $r) {
        expect($r['status'])->toBe('duplicate')
            ->and($r['client_mark_id'])->toBe($first->json("results.$i.client_mark_id"))
            ->and($r['entry_id'])->toBe($first->json("results.$i.entry_id"));
    }
});

it('deja huérfana la marca con dorsal desconocido', function () {
    $response = $this->postJson("/api/v1/events/{$this->event->ulid}/timing-marks", [
        'marks' => [
            ['client_mark_id' => (string) str()->uuid(), 'bib' => null, 'device_time_ms' => now()->getTimestampMs(), 'source' => 'station'],
            ['client_mark_id' => (string) str()->uuid(), 'bib' => 99999, 'device_time_ms' => now()->getTimestampMs(), 'source' => 'station'],
        ],
    ], ['Authorization' => "Bearer {$this->token}"]);

    $response->assertOk()
        ->assertJsonPath('results.0.status', 'orphan')
        ->assertJsonPath('results.0.entry_id', null)
        ->assertJsonPath('results.1.status', 'orphan')
        ->assertJsonPath('results.1.entry_id', null);
});

it('rechaza sin token (401) y con token sin ability (403)', function () {
    $url = "/api/v1/events/{$this->event->ulid}/timing-marks";
    $payload = ['marks' => [markPayload(101, (string) str()->uuid())]];

    $this->postJson($url, $payload)->assertUnauthorized();

    $weakToken = $this->station->createToken('visor', ['device:roster'])->plainTextToken;
    $this->postJson($url, $payload, ['Authorization' => "Bearer {$weakToken}"])->assertForbidden();
});

it('rechaza lotes inválidos con 422', function () {
    $url = "/api/v1/events/{$this->event->ulid}/timing-marks";

    $this->postJson($url, ['marks' => []], ['Authorization' => "Bearer {$this->token}"])->assertUnprocessable();

    $tooMany = ['marks' => array_fill(0, 101, markPayload(101, (string) str()->uuid()))];
    $this->postJson($url, $tooMany, ['Authorization' => "Bearer {$this->token}"])->assertUnprocessable();
});
