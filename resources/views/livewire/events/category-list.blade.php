<?php

use App\Models\Event;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

/*
| ISSUE-115 (TQ-004) — Placeholder navegable de Categorías: destino del
| aviso accionable del hub cuando el evento no tiene categorías; el CRUD
| completo llega con EP-25.
*/

new #[Layout('components.layouts.panel')] #[Title('Categorías')] class extends Component
{
    public Event $event;

    public function mount(Event $event): void
    {
        $this->event = $event;
    }
}

?>

<div class="flex flex-col gap-4">
    <x-ui.page-header :title="__('ui.events.categories_title')" subtitle="{{ $event->name }}" />

    <x-ui.card padding="none">
        <x-ui.empty-state
            icon="tags"
            :title="__('ui.events.coming_soon')"
            :text="__('ui.events.categories_soon_text')"
        />
    </x-ui.card>
</div>
