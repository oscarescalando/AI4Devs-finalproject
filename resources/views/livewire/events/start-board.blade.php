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
    <x-ui.page-header title="Salidas" subtitle="{{ $event->name }}" />

    <x-ui.card padding="none">
        <x-ui.empty-state
            icon="flag"
            title="Llega con su pantalla completa en EP-25"
            text="El tablero de salidas con reloj sincronizado y confirmaciones se construye en EP-25 (ISSUE-132 e ISSUE-133)."
        />
    </x-ui.card>
</div>
