<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

/*
| ISSUE-115 (TQ-004) — Placeholder navegable de Personas del directorio:
| la ruta existe ya con su permiso para que el mapa to-be sea estable; la
| pantalla Personas/Cuentas llega con EP-25.
*/

new #[Layout('components.layouts.panel')] #[Title('Personas')] class extends Component
{
    //
}

?>

<div class="flex flex-col gap-4">
    <x-ui.page-header
        title="Personas"
        subtitle="Directorio de personas y cuentas (persona ≠ cuenta)."
    />

    <x-ui.card padding="none">
        <x-ui.empty-state
            icon="users"
            title="Llega con su pantalla completa en EP-25"
            text="El listado con PII enmascarada según rol y la gestión de cuentas se construyen en EP-25 (ISSUE-121)."
        />
    </x-ui.card>
</div>
