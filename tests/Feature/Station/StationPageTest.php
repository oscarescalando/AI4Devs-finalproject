<?php

declare(strict_types=1);

/**
 * ISSUE-147 (TQ-021) — Ruta pública /station: shell SIN sesión (nada de
 * auth()), con el formulario de emparejamiento (textos de lang/es) y
 * noindex. La lógica IndexedDB/JS se valida con Playwright en TQ-023:
 * aquí solo el contrato del HTML servido.
 */
it('sirve el shell de la estación sin sesión, con emparejamiento y noindex', function () {
    $response = $this->get(route('station.home'));

    $response->assertOk();

    $html = $response->getContent();

    // Formulario de emparejamiento presente (textos de lang, ISSUE-118)…
    expect($html)
        ->toContain(__('ui.station.pair_title'))
        ->toContain(__('ui.station.event_ulid'))
        ->toContain(__('ui.station.token'))
        ->toContain(__('ui.station.pair_submit'))
        ->toContain(__('ui.station.unpair'))
        // …y el modo captura vive en el mismo shell (x-show lo revela al
        // emparejar): input gigante, cola y badge Pendiente.
        ->toContain(__('ui.station.bib_placeholder'))
        ->toContain(__('ui.station.capture_text'))
        ->toContain(__('ui.station.status_pending'))
        ->toContain(__('ui.station.queue_empty_title'))
        // El componente Alpine recibe los textos de lang vía @js (TQ-021).
        ->toContain('tcStation(')
        // No indexado: pantalla operativa de campo.
        ->toContain('noindex')
        // Shell mínimo: sin runtime de Livewire ni cliente Echo.
        ->not->toContain('livewire.js')
        ->not->toContain('pusher')
        ->not->toContain('echo.js');
});
