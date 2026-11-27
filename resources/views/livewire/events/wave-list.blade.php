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
    <x-ui.page-header title="Oleadas" subtitle="{{ $event->name }}" />

    <x-ui.card padding="none">
        <x-ui.empty-state
            icon="layers"
            title="Llega con su pantalla completa en EP-25"
            text="El listado de oleadas y la reprogramación en bloque se construyen en EP-25 (ISSUE-126)."
        />
    </x-ui.card>
</div>
