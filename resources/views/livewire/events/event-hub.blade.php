<?php

use App\Actions\Events\GetEventHub;
use App\Models\Event;
use App\Support\Ui\EventStatus;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

/*
| ISSUE-115 (TQ-004) — Hub del evento: puerta a las cuatro operativas
| (Inscripciones/Salidas/Registros/Clasificación), visibles solo con el
| permiso real del servidor (la UI oculta; el servidor autoriza, ADR-011).
| Al abrirlo fija el evento activo en sesión (tc.event_ulid) para que el
| grupo Operación del menú enlace con este ULID, y avisa de forma
| accionable si al evento le falta configuración (categorías/oleadas).
*/

new #[Layout('components.layouts.panel')] #[Title('Evento')] class extends Component
{
    public Event $event;

    /** @var array{races: int, categories: int, waves: int, entries: int} */
    public array $counts = [];

    public function mount(Event $event): void
    {
        $this->event = $event;
        $this->counts = app(GetEventHub::class)($event);

        session(['tc.event_ulid' => $event->ulid]);
    }
}

?>

<div class="flex flex-col gap-4">
    <x-ui.page-header
        title="{{ $event->name }}"
        subtitle="Evento del {{ $event->date->format('d/m/Y') }}"
    >
        <x-slot:actions>
            <x-ui.badge
                :variant="EventStatus::badgeVariant($event->status)"
                :dot="$event->status === 'live'"
            >{{ EventStatus::label($event->status) }}</x-ui.badge>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($counts['categories'] === 0 || $counts['waves'] === 0)
        <x-ui.alert type="warning" title="Falta configuración del evento">
            @if ($counts['categories'] === 0)
                <p>
                    Este evento no tiene categorías configuradas.
                    @can('events.manage')
                        <a href="{{ route('events.categories', $event) }}">Configurar categorías</a>.
                    @else
                        La pantalla de configuración llega con EP-25.
                    @endcan
                </p>
            @endif
            @if ($counts['waves'] === 0)
                <p>
                    Este evento no tiene oleadas configuradas.
                    @can('events.manage')
                        <a href="{{ route('events.waves', $event) }}">Configurar oleadas</a>.
                    @else
                        La pantalla de configuración llega con EP-25.
                    @endcan
                </p>
            @endif
        </x-ui.alert>
    @endif

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <x-ui.kpi label="Categorías" :value="(string) $counts['categories']" icon="tags" />
        <x-ui.kpi label="Oleadas" :value="(string) $counts['waves']" icon="layers" />
        <x-ui.kpi label="Inscritos" :value="(string) $counts['entries']" icon="users" />
        <x-ui.kpi label="Estado" :value="EventStatus::label($event->status)" icon="activity" />
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        @can('entries.view')
            <div class="card">
                <div class="card-body flex flex-col gap-3">
                    <span class="kpi-icon"><x-ui.icon name="clipboard-list" /></span>
                    <div>
                        <p class="card-title">Inscripciones</p>
                        <p class="text-sm text-muted">Inscritos y dorsales del evento.</p>
                    </div>
                    <x-ui.button variant="secondary" icon="arrow-right" href="{{ route('events.entries', $event) }}">Abrir</x-ui.button>
                </div>
            </div>
        @endcan

        @can('start.manage')
            <div class="card">
                <div class="card-body flex flex-col gap-3">
                    <span class="kpi-icon"><x-ui.icon name="flag" /></span>
                    <div>
                        <p class="card-title">Salidas</p>
                        <p class="text-sm text-muted">Confirmaciones y salida real de cada oleada.</p>
                    </div>
                    <x-ui.button variant="secondary" icon="arrow-right" href="{{ route('events.start', $event) }}">Abrir</x-ui.button>
                </div>
            </div>
        @endcan

        @can('marks.view')
            <div class="card">
                <div class="card-body flex flex-col gap-3">
                    <span class="kpi-icon"><x-ui.icon name="timer" /></span>
                    <div>
                        <p class="card-title">Registros</p>
                        <p class="text-sm text-muted">Bitácora de marcas y correcciones.</p>
                    </div>
                    <x-ui.button variant="secondary" icon="arrow-right" href="{{ route('events.marks', $event) }}">Abrir</x-ui.button>
                </div>
            </div>
        @endcan

        @can('results.view')
            <div class="card">
                <div class="card-body flex flex-col gap-3">
                    <span class="kpi-icon"><x-ui.icon name="medal" /></span>
                    <div>
                        <p class="card-title">Clasificación</p>
                        <p class="text-sm text-muted">Resultados del evento.</p>
                    </div>
                    <x-ui.button variant="secondary" icon="arrow-right" href="{{ route('events.results', $event) }}">Abrir</x-ui.button>
                </div>
            </div>
        @endcan
    </div>
</div>
