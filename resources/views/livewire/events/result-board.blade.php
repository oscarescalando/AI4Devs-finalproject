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
        :title="__('ui.events.results_title')"
        subtitle="{{ $event->name }}"
    >
        <x-slot:actions>
            <x-ui.button variant="ghost" icon="eye" href="{{ route('public.event', $event->slug) }}">
                {{ __('ui.panel.view_public') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card padding="none">
        <x-ui.empty-state
            icon="medal"
            :title="__('ui.events.coming_soon')"
            :text="__('ui.events.results_soon_text', ['slug' => $event->slug])"
        />
    </x-ui.card>
</div>
