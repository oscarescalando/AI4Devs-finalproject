{{--
| ISSUE-163 (TQ-034, ADR-017) — Planilla PDF de UNA inscripción.
|
| DECISIÓN DOCUMENTADA: esta vista NO usa el kit x-ui.* ni @vite — dompdf
| renderiza HTML+CSS propio (sin JS ni assets del navegador), así que trae
| estilos inline mínimos y autónomos. Todo el texto visible pasa por
| lang/es/ui.php (NoInlineTextTest escanea también esta vista) y el payload
| llega YA formateado por BuildEntrySheet: aquí no se calcula nada.
|
| Sin PII sensible (INV-06): el documento y el nacimiento JAMÁS se pintan;
| el atleta sale como nombre público (menores con iniciales).
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ __('ui.entries.sheet_title') }}</title>
</head>
<body style="margin:0; padding:32px 40px; font-family:DejaVu Sans, sans-serif; color:#1f2937; font-size:13px;">
    <table style="width:100%; border-collapse:collapse;">
        <tr>
            <td style="padding:0;">
                <p style="margin:0; font-size:11px; letter-spacing:1px; color:#6b7280; text-transform:uppercase;">TDChronos</p>
                <h1 style="margin:2px 0 0; font-size:20px;">{{ $sheet['event_name'] }}</h1>
                <p style="margin:4px 0 0; color:#6b7280;">
                    {{ $sheet['event_date'] }} · {{ $sheet['event_status'] }}
                </p>
            </td>
            <td style="text-align:right; vertical-align:top;">
                <p style="margin:0 0 2px; font-size:11px; color:#6b7280; text-transform:uppercase;">{{ __('ui.entries.sheet_bib') }}</p>
                <p style="margin:0; font-family:DejaVu Sans Mono, monospace; font-size:44px; font-weight:bold; line-height:1;">{{ $sheet['bib'] }}</p>
            </td>
        </tr>
    </table>

    <hr style="border:none; border-top:2px solid #111827; margin:18px 0;">

    <table style="width:100%; border-collapse:collapse;">
        <tr>
            <td style="padding:6px 0; width:40%; color:#6b7280;">{{ __('ui.entries.sheet_athlete') }}</td>
            <td style="padding:6px 0; font-weight:bold;">{{ $sheet['athlete'] }}</td>
        </tr>
        <tr>
            <td style="padding:6px 0; color:#6b7280;">{{ __('ui.entries.sheet_category') }}</td>
            <td style="padding:6px 0;">{{ $sheet['category'] }}</td>
        </tr>
        <tr>
            <td style="padding:6px 0; color:#6b7280;">{{ __('ui.entries.sheet_race') }}</td>
            <td style="padding:6px 0;">{{ $sheet['race'] }}</td>
        </tr>
        <tr>
            <td style="padding:6px 0; color:#6b7280;">{{ __('ui.entries.sheet_wave') }}</td>
            <td style="padding:6px 0;">{{ $sheet['wave'] }}</td>
        </tr>
        <tr>
            <td style="padding:6px 0; color:#6b7280;">{{ __('ui.entries.sheet_entry_status') }}</td>
            <td style="padding:6px 0;">{{ $sheet['entry_status'] }}</td>
        </tr>
    </table>

    @if ($sheet['result'] !== null)
        <hr style="border:none; border-top:1px solid #d1d5db; margin:14px 0;">
        <p style="margin:0 0 8px; font-size:11px; letter-spacing:1px; color:#6b7280; text-transform:uppercase;">{{ __('ui.entries.sheet_result') }} · {{ $sheet['result']['status'] }}</p>
        <table style="width:100%; border-collapse:collapse;">
            <tr>
                <td style="padding:6px 0; width:40%; color:#6b7280;">{{ __('ui.entries.sheet_net') }}</td>
                <td style="padding:6px 0; font-family:DejaVu Sans Mono, monospace; font-size:18px; font-weight:bold;">{{ $sheet['result']['net'] }}</td>
            </tr>
            <tr>
                <td style="padding:6px 0; color:#6b7280;">{{ __('ui.entries.sheet_rank_general') }}</td>
                <td style="padding:6px 0;">{{ $sheet['result']['rank_general'] }}</td>
            </tr>
            <tr>
                <td style="padding:6px 0; color:#6b7280;">{{ __('ui.entries.sheet_rank_category') }}</td>
                <td style="padding:6px 0;">{{ $sheet['result']['rank_category'] }}</td>
            </tr>
        </table>
    @else
        <hr style="border:none; border-top:1px solid #d1d5db; margin:14px 0;">
        <p style="margin:0; color:#6b7280;">{{ __('ui.entries.sheet_no_result') }}</p>
    @endif

    <p style="margin:28px 0 0; font-size:10px; color:#9ca3af;">{{ __('ui.entries.sheet_footer') }}</p>
</body>
</html>
