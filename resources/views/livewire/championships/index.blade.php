<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

/*
| ISSUE-115 (TQ-004) — Placeholder de la Liga: el modelo no tiene tablas
| de liga en v1 (ADR-015); la clasificación de organizaciones llega en v2.
| La ruta existe para que el mapa to-be y el menú no tengan huecos.
*/

new #[Layout('components.layouts.panel')] #[Title('Liga')] class extends Component
{
    //
}

?>

<div class="flex flex-col gap-4">
    <x-ui.page-header
        :title="__('ui.directory.championships.title')"
        :subtitle="__('ui.directory.championships.subtitle')"
    />

    <x-ui.card padding="none">
        <x-ui.empty-state
            icon="trophy"
            :title="__('ui.directory.championships.soon_title')"
            :text="__('ui.directory.championships.soon_text')"
        />
    </x-ui.card>
</div>
