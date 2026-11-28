<?php

declare(strict_types=1);

namespace App\Http\Controllers\Registration;

use App\Actions\Registration\BuildEntrySheet;
use App\Http\Controllers\Controller;
use App\Models\Entry;
use Dompdf\Dompdf;
use Illuminate\Http\Response;

/**
 * Descarga de la planilla PDF por inscripción (ISSUE-163, TQ-034, ADR-017):
 * descarga directa por <a href> desde el listado (sin JS). El controlador
 * orquesta la respuesta HTTP — el flujo de dominio vive en BuildEntrySheet
 * (datos sin PII) y la presentación en la vista pdf/entry-sheet (estilos
 * inline mínimos, sin assets Vite: dompdf renderiza su propio HTML).
 *
 * Autorización: la ruta exige sesión + entries.view (ISSUE-159); el binding
 * implícito resuelve el ULID público (ADR-015) y el patrón de ruta rechaza
 * con 404 cualquier otra cosa sin tocar la base.
 */
final class EntrySheetPdfController extends Controller
{
    public function __invoke(Entry $entry): Response
    {
        $built = app(BuildEntrySheet::class)($entry);

        $dompdf = new Dompdf(['isRemoteEnabled' => false]);
        $dompdf->loadHtml(view('pdf.entry-sheet', ['sheet' => $built['sheet']])->render());
        $dompdf->setPaper('a4');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$built['filename'].'"',
        ]);
    }
}
