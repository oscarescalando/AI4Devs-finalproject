<?php

use App\Actions\Registration\ListEventEntries;
use App\Models\Event;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;

/*
| ISSUE-115 (TQ-004) — Inscripciones por evento: adelanto navegable del
| mapa to-be. Ya lista los inscritos con el contrato público del roster
| (INV-06: dorsal mono tabular, nombre público y club); los filtros, el
| formulario y la exportación llegan con la pantalla completa en EP-25.
*/

new #[Layout('components.layouts.panel')] #[Title('Inscripciones')] class extends Component
{
    use WithPagination;

    public Event $event;

    public function mount(Event $event): void
    {
        $this->event = $event;
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return ['entries' => app(ListEventEntries::class)($this->event)];
    }
}

?>

<div class="flex flex-col gap-4">
    <x-ui.page-header :title="__('ui.events.entries_title')" subtitle="{{ $event->name }}" />

    <x-ui.empty-state
        icon="clipboard-list"
        :title="__('ui.events.coming_soon')"
        :text="__('ui.events.entries_soon_text')"
    />

    <x-ui.card padding="none">
        @if ($entries->isEmpty())
            <x-ui.empty-state
                icon="users"
                :title="__('ui.events.entries_none_title')"
                :text="__('ui.events.entries_none_text')"
            />
        @else
            <x-ui.table :headers="[['label' => __('ui.events.header_bib'), 'align' => 'right', 'class' => 'mono'], __('ui.events.header_athlete'), __('ui.events.header_club')]">
                @foreach ($entries as $entry)
                    <tr wire:key="entry-{{ $entry->ulid }}">
                        <td class="td-right mono">{{ $entry->bib }}</td>
                        <td class="font-medium text-strong">{{ $entry->person?->publicName() ?? $entry->team?->name ?? '—' }}</td>
                        <td>{{ $entry->team?->organization?->name ?? '—' }}</td>
                    </tr>
                @endforeach

                <x-slot:footer>
                    {{ $entries->links('components.ui.pagination') }}
                </x-slot:footer>
            </x-ui.table>
        @endif
    </x-ui.card>
</div>
