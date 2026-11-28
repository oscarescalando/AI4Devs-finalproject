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
        :subtitle="__('ui.events.hub_date', ['date' => \App\Support\FormatDate::date($event->date)])"
    >
        <x-slot:actions>
            {{-- Editor del evento (ISSUE-160 fase 1, TQ-025): abre el listado
                 con el modal de edición precargado (?edit=ULID). --}}
            @can('events.manage')
                <x-ui.icon-button
                    icon="pencil"
                    :tip="__('ui.events.edit')"
                    :href="route('events.index', ['edit' => $event->ulid])"
                />
            @endcan
            <x-ui.badge
                :variant="EventStatus::badgeVariant($event->status)"
                :dot="$event->status === 'live'"
            >{{ EventStatus::label($event->status) }}</x-ui.badge>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($counts['categories'] === 0 || $counts['waves'] === 0)
        <x-ui.alert type="warning" :title="__('ui.events.missing_setup')">
            @if ($counts['categories'] === 0)
                <p>
                    {{ __('ui.events.no_categories') }}
                    @can('events.manage')
                        <a href="{{ route('events.categories', $event) }}">{{ __('ui.events.configure_categories') }}</a>.
                    @else
                        {{ __('ui.events.setup_ep25') }}
                    @endcan
                </p>
            @endif
            @if ($counts['waves'] === 0)
                <p>
                    {{ __('ui.events.no_waves') }}
                    @can('events.manage')
                        <a href="{{ route('events.waves', $event) }}">{{ __('ui.events.configure_waves') }}</a>.
                    @else
                        {{ __('ui.events.setup_ep25') }}
                    @endcan
                </p>
            @endif
        </x-ui.alert>
    @endif

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <x-ui.kpi :label="__('ui.events.kpi_categories')" :value="(string) $counts['categories']" icon="tags" />
        <x-ui.kpi :label="__('ui.events.kpi_waves')" :value="(string) $counts['waves']" icon="layers" />
        <x-ui.kpi :label="__('ui.events.kpi_entries')" :value="(string) $counts['entries']" icon="users" />
        <x-ui.kpi :label="__('ui.events.kpi_status')" :value="EventStatus::label($event->status)" icon="activity" />
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        @can('entries.view')
            <div class="card">
                <div class="card-body flex flex-col gap-3">
                    <span class="kpi-icon"><x-ui.icon name="clipboard-list" /></span>
                    <div>
                        <p class="card-title">{{ __('ui.events.op_entries') }}</p>
                        <p class="text-sm text-muted">{{ __('ui.events.op_entries_text') }}</p>
                    </div>
                    <x-ui.button variant="secondary" icon="arrow-right" href="{{ route('events.entries', $event) }}">{{ __('ui.events.open_section') }}</x-ui.button>
                </div>
            </div>
        @endcan

        @can('start.manage')
            <div class="card">
                <div class="card-body flex flex-col gap-3">
                    <span class="kpi-icon"><x-ui.icon name="flag" /></span>
                    <div>
                        <p class="card-title">{{ __('ui.events.op_start') }}</p>
                        <p class="text-sm text-muted">{{ __('ui.events.op_start_text') }}</p>
                    </div>
                    <x-ui.button variant="secondary" icon="arrow-right" href="{{ route('events.start', $event) }}">{{ __('ui.events.open_section') }}</x-ui.button>
                </div>
            </div>
        @endcan

        @can('marks.view')
            <div class="card">
                <div class="card-body flex flex-col gap-3">
                    <span class="kpi-icon"><x-ui.icon name="timer" /></span>
                    <div>
                        <p class="card-title">{{ __('ui.events.op_marks') }}</p>
                        <p class="text-sm text-muted">{{ __('ui.events.op_marks_text') }}</p>
                    </div>
                    <x-ui.button variant="secondary" icon="arrow-right" href="{{ route('events.marks', $event) }}">{{ __('ui.events.open_section') }}</x-ui.button>
                </div>
            </div>
        @endcan

        {{-- Captura de llegadas (ISSUE-130, TQ-012): escribir marcas exige
             marks.manage — la UI oculta, el servidor autoriza (ADR-011). --}}
        @can('marks.manage')
            <div class="card">
                <div class="card-body flex flex-col gap-3">
                    <span class="kpi-icon"><x-ui.icon name="keyboard" /></span>
                    <div>
                        <p class="card-title">{{ __('ui.events.op_arrivals') }}</p>
                        <p class="text-sm text-muted">{{ __('ui.events.op_arrivals_text') }}</p>
                    </div>
                    <x-ui.button variant="secondary" icon="arrow-right" href="{{ route('events.arrivals', $event) }}">{{ __('ui.events.open_section') }}</x-ui.button>
                </div>
            </div>
        @endcan

        @can('results.view')
            <div class="card">
                <div class="card-body flex flex-col gap-3">
                    <span class="kpi-icon"><x-ui.icon name="medal" /></span>
                    <div>
                        <p class="card-title">{{ __('ui.events.op_results') }}</p>
                        <p class="text-sm text-muted">{{ __('ui.events.op_results_text') }}</p>
                    </div>
                    <x-ui.button variant="secondary" icon="arrow-right" href="{{ route('events.results', $event) }}">{{ __('ui.events.open_section') }}</x-ui.button>
                </div>
            </div>
        @endcan
    </div>
</div>
