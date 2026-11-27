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
        :title="__('ui.directory.teams.title')"
        :subtitle="__('ui.directory.teams.subtitle')"
    />

    <x-ui.card padding="none">
        <x-ui.empty-state
            icon="users-round"
            :title="__('ui.events.coming_soon')"
            :text="__('ui.directory.teams.soon_text')"
        />
    </x-ui.card>
</div>
