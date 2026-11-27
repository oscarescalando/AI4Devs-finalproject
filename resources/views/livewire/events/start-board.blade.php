<?php

use App\Models\Event;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

/*
| ISSUE-115 (TQ-004) — Placeholder navegable de Salidas: la ruta existe ya
| con su permiso para que el mapa to-be sea estable; la pantalla completa
| (reloj, confirmaciones y salida real) llega con EP-25.
*/

new #[Layout('components.layouts.panel')] #[Title('Salidas')] class extends Component
{
    public Event $event;

    public function mount(Event $event): void
    {
        $this->event = $event;
    }
}

?>

<div class="flex flex-col gap-4">
    <x-ui.page-header :title="__('ui.events.start_title')" subtitle="{{ $event->name }}" />

    <x-ui.card padding="none">
        <x-ui.empty-state
            icon="flag"
            :title="__('ui.events.coming_soon')"
            :text="__('ui.events.start_soon_text')"
        />
    </x-ui.card>
</div>
