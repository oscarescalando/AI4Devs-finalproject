<?php

declare(strict_types=1);

namespace App\Actions\Corrections;

use App\Models\Event;
use App\Models\Person;
use App\Support\EffectiveMarks;
use App\Support\FormatClock;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * ISSUE-134 (TQ-014, versión mínima F1) — Bitácora de marcas del juez:
 * las marcas del evento con su ESTADO DERIVADO (asignada/huérfana/
 * duplicada/anulada/corregida) usando exactamente la misma derivación del
 * motor (EffectiveMarks: última corrección gana; duplicada = marca efectiva
 * que no es la MIN de su entry), contadores por estado de todo el evento y
 * paginación server-side de 25 en 25 por hora efectiva descendente.
 */
final readonly class ListMarkLog
{
    private const PER_PAGE = 25;

    private const STATUSES = ['assigned', 'orphan', 'duplicate', 'void', 'corrected'];

    /**
     * @return array{marks: LengthAwarePaginator<int, array<string, mixed>>, counters: array<string, int>}
     */
    public function __invoke(Event $event): array
    {
        $marks = DB::query()
            ->fromSub(EffectiveMarks::query($event->id), 'em')
            ->leftJoin('entries AS e', 'e.id', '=', 'em.effective_entry_id')
            ->leftJoin('persons AS p', 'p.id', '=', 'e.person_id')
            ->leftJoin('teams AS t', 't.id', '=', 'e.team_id')
            ->leftJoinSub(EffectiveMarks::firstArrivals($event->id), 'first', 'first.entry_id', '=', 'em.effective_entry_id')
            ->addSelect('em.ulid')
            ->addSelect('em.source')
            ->addSelect('em.effective_captured_at')
            ->addSelect('e.bib')
            ->addSelect('p.first_name')
            ->addSelect('p.last_name')
            ->addSelect('p.birthdate')
            ->addSelect('p.gender')
            ->addSelect('t.name AS team_name')
            ->addSelect(EffectiveMarks::statusSelect())
            ->orderByDesc('em.effective_captured_at')
            ->orderByDesc('em.id')
            ->paginate(self::PER_PAGE)
            ->through(fn (object $row): array => $this->row((array) $row, $event->timezone));

        $totals = DB::query()
            ->fromSub(EffectiveMarks::query($event->id), 'em')
            ->leftJoinSub(EffectiveMarks::firstArrivals($event->id), 'first', 'first.entry_id', '=', 'em.effective_entry_id')
            ->addSelect(EffectiveMarks::statusSelect())
            ->addSelect(DB::raw('COUNT(*) AS total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $counters = [];
        foreach (self::STATUSES as $status) {
            $counters[$status] = (int) $totals->get($status);
        }

        return ['marks' => $marks, 'counters' => $counters];
    }

    /**
     * Fila pública de la bitácora (INV-06: nombre público, sin documento ni
     * contacto): hora efectiva, dorsal/atleta, origen y estado derivado.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function row(array $row, ?string $timezone): array
    {
        return [
            'ulid' => (string) $row['ulid'],
            'time' => FormatClock::timeOfDay((string) $row['effective_captured_at'], $timezone),
            'bib' => $row['bib'] !== null ? (int) $row['bib'] : null,
            'athlete' => $this->athlete($row),
            'source' => (string) $row['source'],
            'status' => (string) $row['status'],
        ];
    }

    /**
     * Nombre público del atleta (menores con iniciales, INV-06) o el equipo;
     * null para huérfanas: la pantalla pinta «Huérfana».
     *
     * @param  array<string, mixed>  $row
     */
    private function athlete(array $row): ?string
    {
        if ($row['first_name'] !== null || $row['last_name'] !== null) {
            $person = new Person;
            $person->fill([
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'birthdate' => $row['birthdate'],
                'gender' => $row['gender'],
            ]);

            return $person->publicName();
        }

        return $row['team_name'] !== null ? (string) $row['team_name'] : null;
    }
}
