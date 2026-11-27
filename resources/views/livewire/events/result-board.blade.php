<?php

use App\Models\Event;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

/*
| ISSUE-115 (TQ-004) — Placeholder navegable de Clasificación del panel:
| la vista pública /e/{slug} ya existe (ISSUE-137 del corte 1); la
| operativa del panel llega con EP-25.
*/

new #[Layout('components.layouts.panel')] #[Title('Clasificación')] class extends Component
{
    public Event $event;

    public function mount(Event $event): void
    {
        $this->event = $event;
    }
}

?>

<div class="flex flex-col gap-4">
    <x-ui.page-header
        title="Clasificación"
        subtitle="{{ $event->name }}"
    >
        <x-slot:actions>
            <x-ui.button variant="ghost" icon="eye" href="{{ route('public.event', $event->slug) }}">
                Ver pública
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card padding="none">
        <x-ui.empty-state
            icon="medal"
            title="Llega con su pantalla completa en EP-25"
            text="La clasificación operativa del panel (publicación y cierre de resultados) se construye en EP-25; la proyección pública ya vive en /e/{{ $event->slug }}."
        />
    </x-ui.card>
</div>
