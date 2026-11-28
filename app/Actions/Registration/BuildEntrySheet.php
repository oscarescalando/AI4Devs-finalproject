<?php

declare(strict_types=1);

namespace App\Actions\Registration;

use App\Models\Entry;
use App\Support\FormatDate;
use App\Support\FormatDuration;
use App\Support\Ui\EventStatus;

/**
 * ISSUE-163 (TQ-034, ADR-017) — Datos de la planilla PDF de UNA inscripción:
 * devuelve TODO lo que pinta la vista pdf/entry-sheet (escalar, ya formateado
 * y traducido) y el nombre del archivo. El render de la vista y el armado
 * del PDF con dompdf los orquesta el controlador de la descarga.
 *
 * Sin PII sensible (INV-06): el documento y la fecha de nacimiento JAMÁS
 * viajan en el payload — el atleta sale por Person::publicName() (menores
 * con iniciales), igual que en las pantallas. Si la inscripción tiene
 * resultado (tabla derivada leída por la relación, no por el modelo), se
 * agregan net y posiciones tal como quedó el último recálculo.
 */
final readonly class BuildEntrySheet
{
    /**
     * @return array{
     *     filename: string,
     *     sheet: array{
     *         event_name: string, event_date: string, event_status: string,
     *         bib: string, athlete: string, category: string, race: string,
     *         wave: string, entry_status: string,
     *         result: array{net: string, rank_general: int, rank_category: int, status: string}|null
     *     }
     * }
     */
    public function __invoke(Entry $entry): array
    {
        $entry->loadMissing(['event', 'person', 'team', 'category', 'race', 'wave', 'result']);

        $result = $entry->result;

        // XOR persona/equipo del esquema (ISSUE-157): uno de los dos, jamás ambos.
        $person = $entry->person;
        $team = $entry->team;

        if ($person !== null) {
            $athlete = $person->publicName();
        } elseif ($team !== null) {
            $athlete = $team->name ?? '—';
        } else {
            $athlete = '—';
        }

        return [
            'filename' => 'planilla-'.$entry->bib.'.pdf',
            'sheet' => [
                'event_name' => (string) $entry->event->name,
                'event_date' => FormatDate::date($entry->event->date),
                'event_status' => EventStatus::label((string) $entry->event->status),
                'bib' => (string) $entry->bib,
                'athlete' => $athlete,
                'category' => $entry->category->name,
                'race' => $entry->race->name,
                'wave' => $entry->wave->name ?? __('ui.events.start_wave_fallback'),
                'entry_status' => __('ui.events.entry_status_'.$entry->status),
                'result' => $result === null ? null : [
                    'net' => FormatDuration::fromMs($result->net_ms, true),
                    'rank_general' => (int) $result->rank_general,
                    'rank_category' => (int) $result->rank_category,
                    'status' => __('ui.events.results_status_'.$result->status),
                ],
            ],
        ];
    }
}
