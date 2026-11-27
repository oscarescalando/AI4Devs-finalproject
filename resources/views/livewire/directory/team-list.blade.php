<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

/*
| ISSUE-115 (TQ-004) — Placeholder navegable de Equipos del directorio: la
| ruta existe ya con su permiso para que el mapa to-be sea estable; la
| pantalla de equipos llega con EP-25.
*/

new #[Layout('components.layouts.panel')] #[Title('Equipos')] class extends Component
{
    //
}

?>

<div class="flex flex-col gap-4">
    <x-ui.page-header
        title="Equipos"
        subtitle="Directorio de equipos; su alineación es por evento."
    />

    <x-ui.card padding="none">
        <x-ui.empty-state
            icon="users-round"
            title="Llega con su pantalla completa en EP-25"
            text="El listado y el formulario de equipos con miembros autocompletables se construyen en EP-25 (ISSUE-123)."
        />
    </x-ui.card>
</div>
