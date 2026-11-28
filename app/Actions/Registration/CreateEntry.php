<?php

declare(strict_types=1);

namespace App\Actions\Registration;

use App\Models\Category;
use App\Models\Entry;
use App\Models\Event;
use App\Models\Person;
use App\Models\Race;
use App\Models\Wave;
use App\Support\CategoryMatch;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Alta individual (ISSUE-162/TQ-030): la persona llega por su ULID del
 * directorio y no puede repetirse en el evento (UNIQUE event+person, INV-04
 * — cubre también las canceladas, que se reactivan, no se duplican); la
 * carrera debe ser del evento y la oleada, de esa carrera. La categoría NO
 * se elige: se deriva en el servidor por edad al día del evento + género
 * (CategoryMatch); sin coincidencia no hay alta, con error accionable. El
 * dorsal es editable (1-99999, único en el evento, INV-03) y cuando no
 * viene se asigna MAX(bib)+1. Todo en UNA transacción respetando el CHECK
 * persona XOR equipo (persona siempre, team null) — y si dos operadores
 * colisionan en la carrera fina, la base lo impide y el choque se traduce
 * a error de campo claro.
 */
final readonly class CreateEntry
{
    /**
     * @param  array<string, mixed>  $data  person_ulid, race_ulid, wave_ulid, bib (opcional)
     *
     * @throws ValidationException
     */
    public function __invoke(string $eventUlid, array $data): Entry
    {
        $event = Event::query()->where('ulid', $eventUlid)->firstOrFail();

        $validated = Validator::make($data, [
            'person_ulid' => ['required', 'string'],
            'race_ulid' => ['required', 'string'],
            'wave_ulid' => ['required', 'string'],
            'bib' => ['nullable', 'integer', 'min:1', 'max:99999'],
        ])->validate();

        $person = Person::query()->where('ulid', (string) $validated['person_ulid'])->first();

        if ($person === null) {
            throw ValidationException::withMessages([
                'person_ulid' => __('ui.directory.persons.toast_missing'),
            ]);
        }

        if (Entry::query()->where('event_id', $event->id)->where('person_id', $person->id)->exists()) {
            throw ValidationException::withMessages([
                'person_ulid' => __('ui.events.entry_err_duplicate', ['name' => $person->publicName()]),
            ]);
        }

        $race = Race::query()
            ->where('ulid', (string) $validated['race_ulid'])
            ->where('event_id', $event->id)
            ->first();

        if ($race === null) {
            throw ValidationException::withMessages([
                'race_ulid' => __('ui.events.entry_err_race'),
            ]);
        }

        $wave = Wave::query()
            ->where('ulid', (string) $validated['wave_ulid'])
            ->where('race_id', $race->id)
            ->first();

        if ($wave === null) {
            throw ValidationException::withMessages([
                'wave_ulid' => __('ui.events.entry_err_wave'),
            ]);
        }

        // Categoría automática: edad al DÍA del evento + género de la persona.
        $age = (int) $person->birthdate->diff($event->date)->y;
        $category = CategoryMatch::find($this->categories($event), $person->gender, $age);

        if ($category === null) {
            throw ValidationException::withMessages([
                'person_ulid' => __('ui.events.entry_err_no_category', ['age' => $age, 'gender' => $person->gender]),
            ]);
        }

        // Dorsal: sugerido por el modal, editable; sin valor → siguiente libre.
        $bib = $validated['bib'] ?? null;

        if ($bib !== null && Entry::query()->where('event_id', $event->id)->where('bib', $bib)->exists()) {
            throw ValidationException::withMessages([
                'bib' => __('ui.events.entry_err_bib_taken', ['bib' => (int) $bib]),
            ]);
        }

        $bib ??= (int) $event->entries()->max('bib') + 1;

        try {
            /** @var Entry $entry */
            $entry = DB::transaction(function () use ($event, $category, $race, $wave, $person, $bib): Entry {
                return Entry::query()->create([
                    'event_id' => $event->id,
                    'category_id' => $category->id,
                    'race_id' => $race->id,
                    'wave_id' => $wave->id,
                    'person_id' => $person->id, // CHECK persona XOR equipo: individual
                    'team_id' => null,
                    'bib' => $bib,
                ]);
            });

            return $entry;
        } catch (UniqueConstraintViolationException $e) {
            // Carrera fina entre operadores: la base custodia INV-03/INV-04 y
            // el choque se traduce al campo que lo causó con mensaje claro.
            throw ValidationException::withMessages(
                str_contains($e->getMessage(), 'bib')
                    ? ['bib' => __('ui.events.entry_err_bib_taken', ['bib' => $bib])]
                    : ['person_ulid' => __('ui.events.entry_err_duplicate', ['name' => $person->publicName()])],
            );
        }
    }

    /**
     * @return Collection<int, Category>
     */
    private function categories(Event $event): Collection
    {
        return $event->categories()->orderBy('min_age')->orderBy('id')->get();
    }
}
