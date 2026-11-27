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
        :title="__('ui.directory.persons.title')"
        :subtitle="__('ui.directory.persons.subtitle')"
    />

    <x-ui.card padding="none">
        <x-ui.empty-state
            icon="users"
            :title="__('ui.events.coming_soon')"
            :text="__('ui.directory.persons.soon_text')"
        />
    </x-ui.card>
</div>
