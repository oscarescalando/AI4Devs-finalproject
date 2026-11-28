<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\Event;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Artisan;

/**
 * ISSUE-147 (TQ-021) — Comando artesanal station:token: valida el evento
 * por ULID, crea persona y cuenta sintéticas SIN ningún rol (INV-13) y
 * emite un token Sanctum con la ability device:marks que autentica
 * GET /api/v1/time (la validación del emparejamiento de /station) y el
 * lote de marcas; sin token, el lote responde 401.
 */
beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->event = Event::query()->where('slug', 'vuela-caracas-21k')->firstOrFail();
});

/** Extrae el token plano de la salida del comando (línea «token: …»). */
function stationTokenFromOutput(string $output): string
{
    return preg_match('/^token:\s*(\S+)$/m', $output, $matches) === 1
        ? (string) $matches[1]
        : '';
}

it('emite un token de estación que autentica /time y sirve para el lote', function () {
    expect(Artisan::call('station:token', ['eventUlid' => $this->event->ulid]))->toBe(0);

    $output = Artisan::output();
    expect($output)
        ->toContain($this->event->ulid)
        ->toContain("/api/v1/events/{$this->event->ulid}/timing-marks");

    $token = stationTokenFromOutput($output);
    expect($token)->not->toBe('');

    // Con el Bearer, /time acepta: es la llamada con la que /station valida
    // el emparejamiento (sin efectos secundarios).
    $this->getJson('/api/v1/time', ['Authorization' => "Bearer {$token}"])
        ->assertOk()
        ->assertJsonStructure(['server_ms', 'timezone']);

    // Y el token sirve para SU propósito real: el lote idempotente (una
    // huérfana de dorsal sin inscripción, ISSUE-156).
    $this->postJson("/api/v1/events/{$this->event->ulid}/timing-marks", [
        'marks' => [[
            'client_mark_id' => (string) str()->uuid(),
            'bib' => 99998,
            'device_time_ms' => now()->getTimestampMs(),
            'clock_offset_ms' => 0,
            'source' => 'station',
        ]],
    ], ['Authorization' => "Bearer {$token}"])
        ->assertOk()
        ->assertJsonPath('results.0.status', 'orphan');
});

it('crea una cuenta sintética de estación sin ningún rol ni acceso al panel', function () {
    Artisan::call('station:token', ['eventUlid' => $this->event->ulid]);

    $account = Account::query()
        ->where('email', 'like', 'station-%@stations.tdchronos.test')
        ->firstOrFail();

    expect($account->person)->not->toBeNull()
        ->and($account->getRoleNames())->toBeEmpty()
        ->and($account->active)->toBeFalse()
        ->and($account->tokens)->toHaveCount(1)
        ->and($account->tokens->first()->name)->toBe('estacion')
        ->and($account->tokens->first()->abilities)->toBe(['device:marks']);
});

it('rechaza con un ULID de evento que no existe', function () {
    expect(Artisan::call('station:token', ['eventUlid' => '01arz3ndektsv4rrffq69g5fav']))->toBe(1);

    expect(Artisan::output())->toContain('No existe ningún evento');
});

it('rechaza el lote de marcas sin token (401)', function () {
    $this->postJson("/api/v1/events/{$this->event->ulid}/timing-marks", ['marks' => []])
        ->assertUnauthorized();
});

it('rechaza en /time un Bearer inválido o sin la ability device:marks', function () {
    $this->getJson('/api/v1/time', ['Authorization' => 'Bearer 99|token-falso'])
        ->assertUnauthorized();

    $rosterOnly = Account::query()->where('email', 'admin@tdchronos.test')->firstOrFail()
        ->createToken('solo-roster', ['device:roster'])->plainTextToken;
    $this->getJson('/api/v1/time', ['Authorization' => "Bearer {$rosterOnly}"])
        ->assertForbidden();

    // Sin cabecera sigue siendo pública (ADR-012): reloj de pantallas.
    $this->getJson('/api/v1/time')->assertOk();
});
