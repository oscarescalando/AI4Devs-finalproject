<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Category;
use App\Models\Entry;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Race;
use App\Models\Region;
use App\Models\Team;
use App\Models\Wave;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder del primer corte (enmienda ADR-015): 100 % sintético.
 * Siembra el único camino completo: un evento en vivo con carreras,
 * oleadas, categorías e inscritos con dorsales, más el admin bootstrap.
 * La hora real de salida de cada oleada queda sembrada para que el
 * net_ms sea calculable desde la primera marca.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // --- Admin bootstrap (usuarios nuevos, sin datos heredados) ---
        $adminPerson = Person::query()->create([
            'document' => 'V-00000001',
            'first_name' => 'Admin',
            'last_name' => 'TDChronos',
            'birthdate' => '1990-01-01',
            'gender' => 'X',
        ]);
        Account::query()->create([
            'person_id' => $adminPerson->id,
            'email' => 'admin@tdchronos.test',
            'password' => Hash::make('tdchronos-admin-2026'),
        ]);

        // --- Roles y permisos base (ISSUE-159) + cuentas demo por rol ---
        $this->call(RolesPermissionsSeeder::class);

        Account::query()->where('email', 'admin@tdchronos.test')->first()?->assignRole('superadmin');

        foreach ([['organizer', 'organizador'], ['timekeeper', 'cronometrador']] as [$role, $slug]) {
            $person = Person::query()->create([
                'document' => 'V-0000000'.(2 + array_search($role, ['organizer', 'timekeeper'], true)),
                'first_name' => ucfirst($slug),
                'last_name' => 'TDChronos',
                'birthdate' => '1990-01-01',
                'gender' => 'X',
            ]);
            Account::query()->create([
                'person_id' => $person->id,
                'email' => $slug.'@tdchronos.test',
                'password' => Hash::make('tdchronos-'.$slug.'-2026'),
            ])->assignRole($role);
        }

        // --- Directorio sintético: 3 regiones y 22 organizaciones (TQ-003,
        //     datos para ejercitar paginación/búsqueda/orden del CRUD demo) ---
        $regions = [
            Region::query()->create(['name' => 'Distrito Capital']),
            Region::query()->create(['name' => 'Zulia']),
            Region::query()->create(['name' => 'Mérida']),
        ];

        $clubs = [];
        foreach ([['Corredores del Ávila', 0], ['Club Zulianos', 1], ['Guaros Runners', 1], ['Andes Trail', 2]] as [$name, $regionIndex]) {
            $clubs[] = Organization::query()->create([
                'region_id' => $regions[$regionIndex]->id,
                'name' => $name,
            ]);
        }

        // 18 organizaciones extra repartidas entre las regiones (22 en total).
        foreach (range(1, 18) as $i) {
            Organization::factory()->create([
                'region_id' => $regions[$i % 3]->id,
            ]);
        }

        // 110 atletas sintéticos, 4 de ellos menores para demostrar INV-06.
        $persons = Person::factory()->count(106)->create();
        $minors = Person::factory()->minor()->count(4)->create();

        $team = Team::query()->create([
            'organization_id' => $clubs[0]->id,
            'name' => 'Ávila Relevo Mixto',
        ]);
        $team->persons()->attach($persons->take(4)->pluck('id'));

        // --- Evento sembrado: el organizador "cargó" el evento y sus inscritos ---
        $eventDate = CarbonImmutable::today();
        $event = Event::query()->create([
            'name' => 'VuelaCaracas 21K',
            'slug' => 'vuela-caracas-21k',
            'date' => $eventDate->format('Y-m-d'),
            'timezone' => 'America/Caracas',
            'status' => 'live',
        ]);

        $race21k = Race::query()->create(['event_id' => $event->id, 'name' => '21K']);
        $race10k = Race::query()->create(['event_id' => $event->id, 'name' => '10K']);

        $elite = Wave::query()->create([
            'race_id' => $race21k->id,
            'name' => 'Élite',
            'scheduled_start_at' => $eventDate->setTime(7, 0, 0),
            'actual_start_at' => $eventDate->setTime(7, 0, 5, 120),
        ]);
        $general = Wave::query()->create([
            'race_id' => $race21k->id,
            'name' => 'General',
            'scheduled_start_at' => $eventDate->setTime(7, 15, 0),
            'actual_start_at' => $eventDate->setTime(7, 15, 9, 640),
        ]);
        $unica10k = Wave::query()->create([
            'race_id' => $race10k->id,
            'name' => 'Única',
            'scheduled_start_at' => $eventDate->setTime(7, 30, 0),
            'actual_start_at' => $eventDate->setTime(7, 30, 7, 310),
        ]);

        $categories = collect([
            ['name' => 'M 20-29', 'min_age' => 20, 'max_age' => 29],
            ['name' => 'M 30-39', 'min_age' => 30, 'max_age' => 39],
            ['name' => 'M 40-49', 'min_age' => 40, 'max_age' => 49],
            ['name' => 'F 20-29', 'min_age' => 20, 'max_age' => 29],
            ['name' => 'F 30-39', 'min_age' => 30, 'max_age' => 39],
            ['name' => 'F 40-49', 'min_age' => 40, 'max_age' => 49],
            ['name' => 'Junior', 'min_age' => 10, 'max_age' => 19],
        ])->map(fn (array $c) => Category::query()->create([
            'event_id' => $event->id,
            'name' => $c['name'],
            'for_teams' => false,
            'min_age' => $c['min_age'],
            'max_age' => $c['max_age'],
        ]));
        $teamCategory = Category::query()->create([
            'event_id' => $event->id,
            'name' => 'Relevo Mixto',
            'for_teams' => true,
            'min_age' => 18,
            'max_age' => null,
        ]);

        // --- Inscripciones con dorsales (bib 101…204) ---
        /** @var list<Person> $roster */
        $roster = [...$persons->all(), ...$minors->all()];
        $bib = 101;
        foreach ($roster as $index => $person) {
            $is10k = $index % 5 === 0; // 20 % corre el 10K
            $wave = $is10k ? $unica10k : ($index % 7 === 0 ? $elite : $general);

            Entry::query()->create([
                'event_id' => $event->id,
                'category_id' => $this->categoryFor($categories, $person)->id,
                'race_id' => ($is10k ? $race10k : $race21k)->id,
                'wave_id' => $wave->id,
                'person_id' => $person->id,
                'team_id' => null,
                'bib' => $bib++,
                'status' => 'registered',
            ]);
        }

        // 4 inscripciones de equipo (persona XOR equipo, INV-05).
        foreach (range(1, 4) as $i) {
            Entry::query()->create([
                'event_id' => $event->id,
                'category_id' => $teamCategory->id,
                'race_id' => $race21k->id,
                'wave_id' => $general->id,
                'person_id' => null,
                'team_id' => $team->id,
                'bib' => $bib++,
                'status' => 'registered',
            ]);
        }

        $this->command?->info('Seed listo: evento '.$event->name.' ('.$event->ulid.')');
        $this->command?->info('Inscritos: '.Entry::query()->where('event_id', $event->id)->count());
        $this->command?->warn('Admin: admin@tdchronos.test / tdchronos-admin-2026 (datos sintéticos)');
    }

    /**
     * @param  Collection<int, Category>  $categories
     */
    private function categoryFor(Collection $categories, Person $person): Category
    {
        $age = $person->birthdate->age;
        $prefix = $person->gender === 'F' ? 'F' : 'M';

        $match = $categories->first(function ($c) use ($prefix, $age) {
            if (! str_starts_with($c->name, $prefix)) {
                return false;
            }

            return $c->min_age <= $age && $age <= ($c->max_age ?? 120);
        });

        return $match ?? $categories->firstOrFail(fn ($c) => $c->name === 'Junior');
    }
}
