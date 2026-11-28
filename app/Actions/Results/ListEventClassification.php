<?php

declare(strict_types=1);

namespace App\Actions\Results;

use App\Models\Category;
use App\Models\Event;
use App\Models\Person;
use App\Models\Race;
use App\Models\Result;
use App\Support\FormatDuration;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * ISSUE-137/TQ-015 — Clasificación operativa del panel (results.view): la
 * variante con sesión de la proyección pública. Lee la tabla derivada
 * `results` que SOLO escribe el motor RecalculateEventResults y JAMÁS
 * calcula posiciones (INV-07): rangos general/género/categoría y netos
 * vienen escritos; la única aritmética es la diferencia con el primero de
 * la carrera, una resta entre netos ya persistidos (el líder de cada
 * carrera se resuelve SIN filtros para que la referencia no cambie al
 * filtrar). Estado provisional/oficial con la misma expresión del motor
 * (derivado del status del evento); contadores de llegadas/pendientes
 * sobre las entries del evento (withdrawn fuera, como el motor).
 */
final readonly class ListEventClassification
{
    private const PER_PAGE = 25;

    /**
     * @param  string  $race  ULID de la carrera (filtro; '' = todas).
     * @param  string  $category  ULID de la categoría (filtro; '' = todas).
     * @return array{
     *     rows: LengthAwarePaginator<int, array<string, mixed>>,
     *     counters: array{arrived: int, pending: int, total: int},
     *     status: string,
     *     races: list<array{ulid: string, name: string}>,
     *     categories: list<array{ulid: string, name: string}>,
     * }
     */
    public function __invoke(Event $event, string $race = '', string $category = '')
    {
        /** @var Collection<int, Race> $races */
        $races = $event->races()->orderBy('id')->get();

        /** @var Collection<int, Category> $categories */
        $categories = $event->categories()->orderBy('id')->get();

        // Filtros por ULID público (ADR-015); uno desconocido se ignora.
        $raceModel = $race !== '' ? $races->firstWhere('ulid', $race) : null;
        $categoryModel = $category !== '' ? $categories->firstWhere('ulid', $category) : null;

        $arrived = Result::query()
            ->whereHas('entry', fn ($query) => $query->where('event_id', $event->id))
            ->count();
        $total = $event->entries()->where('status', '<>', 'withdrawn')->count();

        // Primer neto de cada carrera del evento, SIN filtros: la diferencia
        // siempre se mide contra el líder real de la carrera.
        $leaders = DB::query()
            ->from('results AS r2')
            ->join('entries AS e2', 'e2.id', '=', 'r2.entry_id')
            ->where('e2.event_id', $event->id)
            ->groupBy('e2.race_id')
            ->selectRaw('e2.race_id AS race_id, MIN(r2.net_ms) AS leader_ms');

        $board = DB::query()
            ->from('results AS r')
            ->join('entries AS e', 'e.id', '=', 'r.entry_id')
            ->join('races AS ra', 'ra.id', '=', 'e.race_id')
            ->join('categories AS c', 'c.id', '=', 'e.category_id')
            ->leftJoin('persons AS p', 'p.id', '=', 'e.person_id')
            ->leftJoin('teams AS t', 't.id', '=', 'e.team_id')
            ->leftJoin('organizations AS o', 'o.id', '=', 't.organization_id')
            ->leftJoinSub($leaders, 'leader', 'leader.race_id', '=', 'e.race_id')
            ->where('e.event_id', $event->id);

        if ($raceModel !== null) {
            $board->where('e.race_id', $raceModel->id);
        }

        if ($categoryModel !== null) {
            $board->where('e.category_id', $categoryModel->id);
        }

        $rows = $board
            ->addSelect('r.net_ms')
            ->addSelect('r.rank_general')
            ->addSelect('r.rank_gender')
            ->addSelect('r.rank_category')
            ->addSelect('e.bib')
            ->addSelect('p.first_name')
            ->addSelect('p.last_name')
            ->addSelect('p.birthdate')
            ->addSelect('p.gender')
            ->addSelect('t.name AS team_name')
            ->addSelect('o.name AS club_name')
            ->addSelect('ra.name AS race_name')
            ->addSelect('c.name AS category_name')
            ->addSelect(DB::raw('r.net_ms - leader.leader_ms AS diff_ms'))
            ->orderBy('e.race_id')
            ->orderBy('r.rank_general')
            ->orderBy('r.net_ms')
            ->orderBy('e.bib')
            ->paginate(self::PER_PAGE)
            ->through(fn (object $row): array => $this->row((array) $row));

        return [
            'rows' => $rows,
            'counters' => [
                'arrived' => $arrived,
                'pending' => max(0, $total - $arrived),
                'total' => $total,
            ],
            'status' => in_array($event->status, ['finished', 'closed'], true) ? 'official' : 'provisional',
            'races' => array_values($races->map(fn (Race $item): array => ['ulid' => $item->ulid, 'name' => $item->name])->all()),
            'categories' => array_values($categories->map(fn (Category $item): array => ['ulid' => $item->ulid, 'name' => $item->name])->all()),
        ];
    }

    /**
     * Fila de la clasificación: posiciones y neto tal cual los escribió el
     * motor; diferencia con el primero de SU carrera en ms y formateada
     * (null para el líder). Nombre público INV-06: menores con iniciales.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function row(array $row): array
    {
        $diffMs = (int) $row['diff_ms'];

        return [
            'bib' => (int) $row['bib'],
            'athlete' => $this->athlete($row),
            'club' => $row['club_name'] !== null ? (string) $row['club_name'] : null,
            'race' => (string) $row['race_name'],
            'category' => (string) $row['category_name'],
            'rank_general' => (int) $row['rank_general'],
            'rank_gender' => $row['rank_gender'] === null ? null : (int) $row['rank_gender'],
            'rank_category' => (int) $row['rank_category'],
            'net_ms' => (int) $row['net_ms'],
            'net' => FormatDuration::fromMs((int) $row['net_ms'], true),
            'diff_ms' => $diffMs > 0 ? $diffMs : null,
            'diff' => $diffMs > 0 ? '+'.FormatDuration::fromMs($diffMs, true) : null,
        ];
    }

    /**
     * Nombre público (mismo criterio del leaderboard público y de la bitácora):
     * persona con iniciales si es menor, o el nombre del equipo (XOR, INV-05).
     *
     * @param  array<string, mixed>  $row
     */
    private function athlete(array $row): string
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

        return (string) $row['team_name'];
    }
}
