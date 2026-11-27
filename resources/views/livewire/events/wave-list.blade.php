<?php

use App\Models\Event;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

/*
| ISSUE-115 (TQ-004) — Placeholder navegable de Oleadas: destino del aviso
| accionable del hub cuando el evento no tiene oleadas; el listado con
| reprogramación en bloque llega con EP-25.
*/

new #[Layout('components.layouts.panel')] #[Title('Oleadas')] class extends Component
{
    public Event $event;

    public function mount(Event $event): void
    {
        $this->event = $event;
    }
}

?>

<div class="flex flex-col gap-4">
    <x-ui.page-header :title="__('ui.events.waves_title')" subtitle="{{ $event->name }}" />

    <x-ui.card padding="none">
        <x-ui.empty-state
            icon="layers"
            :title="__('ui.events.coming_soon')"
            :text="__('ui.events.waves_soon_text')"
        />
    </x-ui.card>
</div>
