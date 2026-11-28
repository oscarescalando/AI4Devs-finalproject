<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Event;
use App\Models\Person;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * ISSUE-147 (TQ-021, ADR-014) — Token de estación para el emparejamiento
 * manual de F1: valida el evento por su ULID público, crea una persona y
 * una cuenta sintéticas dedicadas (INV-13: sin datos reales) SIN ningún
 * rol, y emite un token Sanctum con la ability device:marks — el único
 * permiso que la estación necesita para el lote de marcas (ADR-012). El
 * token plano se imprime una sola vez: la pantalla /station lo pega para
 * emparejarse y validarlo contra GET /api/v1/time. La pantalla de QR
 * llega con ISSUE-149.
 */
final class StationToken extends Command
{
    protected $signature = 'station:token
        {eventUlid : ULID público (26 caracteres) del evento}';

    protected $description = 'Emite un token de estación (ability device:marks) para emparejar la pantalla /station';

    public function handle(): int
    {
        $ulid = strtolower(trim((string) $this->argument('eventUlid')));

        $event = Event::query()->where('ulid', $ulid)->first();

        if ($event === null) {
            $this->error("No existe ningún evento con ULID {$ulid}.");

            return self::FAILURE;
        }

        // Email y documento únicos por construcción: prefijo con el ULID del
        // evento más aleatoriedad, porque el comando puede emitir varios
        // tokens para el mismo evento (una cuenta por estación de meta).
        $shortUlid = substr($ulid, 0, 8);
        $suffix = strtolower(Str::random(6));

        $person = Person::query()->create([
            'document' => "STATION-{$shortUlid}-{$suffix}",
            'first_name' => 'Estación',
            'last_name' => Str::title($event->slug),
            'birthdate' => '2000-01-01',
            'gender' => 'X',
        ]);

        // active=false: la cuenta existe SOLO para portar el token. Sin rol,
        // desactivada y con contraseña aleatoria jamás impresa, no entra al
        // panel (RF-68 rechaza cuentas inactivas); el token de dispositivo
        // sigue autenticando la API, que es su único propósito.
        $account = Account::query()->create([
            'person_id' => $person->id,
            'email' => "station-{$shortUlid}-{$suffix}@stations.tdchronos.test",
            'password' => Hash::make(Str::random(48)),
            'active' => false,
        ]);

        $token = $account->createToken('estacion', ['device:marks'])->plainTextToken;

        $this->info("Evento: {$event->name} ({$event->ulid})");
        $this->info('Endpoint del lote: '.url("/api/v1/events/{$event->ulid}/timing-marks"));
        $this->newLine();
        $this->line("token: {$token}");
        $this->newLine();
        $this->warn('Guárdalo ahora: no vuelve a mostrarse. Pégalo en /station para emparejar la estación.');

        return self::SUCCESS;
    }
}
