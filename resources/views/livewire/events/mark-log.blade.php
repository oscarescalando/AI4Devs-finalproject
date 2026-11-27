<?php

use App\Models\Event;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

/*
| ISSUE-115 (TQ-004) — Placeholder navegable de Registros: la ruta existe
| ya con su permiso; la bitácora de marcas en vivo y las correcciones
| llegan con EP-25.
*/

new #[Layout('components.layouts.panel')] #[Title('Registros')] class extends Component
{
    public Event $event;

    public function mount(Event $event): void
    {
        $this->event = $event;
    }
}

?>

<div class="flex flex-col gap-4">
    <x-ui.page-header title="Registros" subtitle="{{ $event->name }}" />

    <x-ui.card padding="none">
        <x-ui.empty-state
            icon="timer"
            title="Llega con su pantalla completa en EP-25"
            text="La bitácora de marcas en vivo y la corrección con operaciones 1/2/3 se construyen en EP-25 (ISSUE-134 e ISSUE-135)."
        />
    </x-ui.card>
</div>
