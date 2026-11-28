<?php

declare(strict_types=1);

namespace App\Actions\Corrections;

use App\Models\Event;
use App\Models\Person;
use App\Models\TimingMark;
use App\Models\TimingMarkCorrection;
use App\Support\EffectiveMarks;
use App\Support\FormatClock;
use App\Support\FormatDate;
use Illuminate\Support\Facades\DB;

/**
 * ISSUE-134/135 (TQ-014) — Contexto de UNA marca para el modal de corrección
 * y su historial: estado efectivo derivado con la misma consulta del motor
 * (EffectiveMarks) y las correcciones de la marca (operación, motivo, quién
 * y cuándo) para la auditoría del juez. La marca cruda nunca se expone
 * mutada: captured_at es la EFECTIVA vigente.
 */
final readonly class FindMarkForCorrection
{
    /**
     * @return array<string, mixed>|null null si la marca no es del evento
     */
    public function __invoke(Event $event, string $markUlid): ?array
    {
        $row = DB::query()
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
            ->where('em.ulid', $markUlid)
            ->first();

        if ($row === null) {
            return null;
        }

        /** @var array<string, mixed> $data */
        $data = (array) $row;

        return [
            'ulid' => (string) $data['ulid'],
            'captured_at' => (string) $data['effective_captured_at'],
            'time' => FormatClock::timeOfDay((string) $data['effective_captured_at'], $event->timezone),
            'bib' => $data['bib'] !== null ? (int) $data['bib'] : null,
            'athlete' => $this->athlete($data),
            'source' => (string) $data['source'],
            'status' => (string) $data['status'],
            'corrections' => $this->corrections($event, $markUlid),
        ];
    }

    /**
     * Historial de la marca: sus correcciones, la más reciente primero.
     *
     * @return list<array{operation: string, reason: string, who: string|null, when: string}>
     */
    private function corrections(Event $event, string $markUlid): array
    {
        $mark = TimingMark::query()->where('event_id', $event->id)->where('ulid', $markUlid)->first();

        if ($mark === null) {
            return [];
        }

        $rows = TimingMarkCorrection::query()
            ->where('timing_mark_id', $mark->id)
            ->with('account.person')
            ->orderByDesc('id')
            ->get()
            ->map(fn (TimingMarkCorrection $correction): array => [
                'operation' => $correction->operation,
                'reason' => $correction->reason,
                'who' => $correction->account?->person?->publicName() ?? $correction->account?->email,
                'when' => FormatDate::date($correction->created_at).' '.FormatClock::timeOfDay($correction->created_at, $event->timezone),
            ])
            ->all();

        return array_values($rows);
    }

    /**
     * Nombre público del atleta (INV-06) o el equipo; null si es huérfana.
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
