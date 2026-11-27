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
    <x-ui.page-header :title="__('ui.events.marks_title')" subtitle="{{ $event->name }}" />

    <x-ui.card padding="none">
        <x-ui.empty-state
            icon="timer"
            :title="__('ui.events.coming_soon')"
            :text="__('ui.events.marks_soon_text')"
        />
    </x-ui.card>
</div>
