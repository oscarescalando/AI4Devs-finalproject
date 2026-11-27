<?php

use App\Actions\Events\ListEvents;
use App\Support\Ui\EventStatus;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;

/*
| ISSUE-115 (TQ-004) — Listado de eventos del panel: puerta de entrada al
| hub de cada evento. Datos vía Action (ADR-012), tabla del kit y estado
| de paginación en query string; la creación/edición llega con EP-25.
*/

new #[Layout('components.layouts.panel')] #[Title('Eventos')] class extends Component
{
    use WithPagination;

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return ['events' => app(ListEvents::class)()];
    }
}

?>

<div class="flex flex-col gap-4">
    <x-ui.page-header
        title="Eventos"
        subtitle="Directorio de eventos; abre uno para operarlo."
    />

    <x-ui.card padding="none">
        @if ($events->isEmpty())
            <x-ui.empty-state
                icon="calendar-days"
                title="Aún no hay eventos"
                text="La pantalla de creación y edición de eventos llega con EP-25 (ISSUE-124)."
            />
        @else
            <x-ui.table :headers="['Evento', 'Fecha', 'Estado', ['label' => 'Acciones', 'align' => 'right']]">
                @foreach ($events as $event)
                    <tr wire:key="event-{{ $event->ulid }}">
                        <td class="font-medium text-strong">{{ $event->name }}</td>
                        <td class="mono">{{ $event->date->format('d/m/Y') }}</td>
                        <td>
                            <x-ui.badge
                                :variant="EventStatus::badgeVariant($event->status)"
                                :dot="$event->status === 'live'"
                            >{{ EventStatus::label($event->status) }}</x-ui.badge>
                        </td>
                        <td class="td-right">
                            <x-ui.icon-button
                                icon="eye"
                                tip="Abrir evento"
                                :href="route('events.show', $event)"
                            />
                        </td>
                    </tr>
                @endforeach

                <x-slot:footer>
                    {{ $events->links('components.ui.pagination') }}
                </x-slot:footer>
            </x-ui.table>
        @endif
    </x-ui.card>
</div>
