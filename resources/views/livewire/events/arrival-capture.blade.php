<?php

use App\Actions\Timing\ListOrphanMarks;
use App\Actions\Timing\ListRecentArrivals;
use App\Actions\Timing\RegisterManualArrival;
use App\Models\Event;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

/*
| ISSUE-130 (TQ-012, versión mínima F1) — Captura de llegadas en vivo:
| dorsal + Enter usable con una mano en meta. El componente no consulta la
| base (ADR-011/ADR-012): escribe por RegisterManualArrival — que delega en
| el conducto del lote y dispara el recálculo afterCommit sin bloquear la
| captura (INV-08) — y lee por las Actions de listado. Tras cada envío el
| dorsal queda limpio y recupera el foco (evento arrival-recorded), la
| lista refresca sola cada 5 s y el feedback por estado es un toast
| (success asignada · warning huérfana · info duplicada).
*/

new #[Layout('components.layouts.panel')] #[Title('Captura de llegadas')] class extends Component
{
    public Event $event;

    /** Dorsal tecleado en crudo: viaja validado a la Action. */
    public string $bib = '';

    public function mount(Event $event): void
    {
        $this->event = $event;
    }

    public function capture(): void
    {
        $typed = trim($this->bib);

        // Validación del dorsal (mismos límites que la API, ISSUE-156):
        // lo que no es 1…99999 se rechaza sin crear marca.
        if ($typed !== '' && (! ctype_digit($typed) || (int) $typed > 99999)) {
            $this->reset('bib');
            $this->dispatch('arrival-recorded');
            $this->dispatch('toast', type: 'warning', message: __('ui.events.arrivals_invalid_bib'));

            return;
        }

        $result = app(RegisterManualArrival::class)($this->event, $typed === '' ? null : (int) $typed);

        $this->reset('bib');

        [$type, $message] = match ($result['status']) {
            'assigned' => [
                'success',
                __('ui.events.arrivals_toast_assigned', ['name' => $result['athlete'] ?? '', 'bib' => $result['bib']]),
            ],
            'orphan' => [
                'warning',
                $result['bib'] !== null
                    ? __('ui.events.arrivals_toast_orphan', ['bib' => $result['bib']])
                    : __('ui.events.arrivals_toast_orphan_no_bib'),
            ],
            default => [
                'info',
                __('ui.events.arrivals_toast_duplicate', ['bib' => $result['bib']]),
            ],
        };

        $this->dispatch('toast', type: $type, message: $message);
        $this->dispatch('arrival-recorded');
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'arrivals' => app(ListRecentArrivals::class)($this->event),
            'orphans' => app(ListOrphanMarks::class)($this->event),
        ];
    }
}

?>

<div class="flex flex-col gap-4" wire:poll.5s>
    <x-ui.page-header :title="__('ui.events.arrivals_title')" subtitle="{{ $event->name }}" />

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 items-start">
        <div class="lg:col-span-2 flex flex-col gap-4">
            {{-- Zona de captura: Enter envía (wire:submit) y el doble envío
                 queda bloqueado mientras viaja (wire:loading acotado aquí). --}}
            <x-ui.card :title="__('ui.events.arrivals_capture_title')" :subtitle="__('ui.events.arrivals_capture_text')">
                <form wire:submit="capture" class="flex flex-col gap-4">
                    <x-ui.form-group :label="__('ui.events.arrivals_bib')" for="arrival-bib" :help="__('ui.events.arrivals_bib_help')">
                        <input
                            id="arrival-bib"
                            type="text"
                            class="form-control mono h-auto! py-4! text-4xl! font-bold text-center"
                            inputmode="numeric"
                            autocomplete="off"
                            autofocus
                            placeholder="{{ __('ui.events.arrivals_bib_placeholder') }}"
                            wire:model="bib"
                            wire:loading.attr="disabled"
                            wire:target="capture"
                            x-data
                            @arrival-recorded.window="$el.focus()"
                        >
                    </x-ui.form-group>

                    <div class="flex flex-wrap items-center gap-3">
                        <x-ui.button
                            variant="primary"
                            icon="timer"
                            wire:loading.attr="disabled"
                            wire:target="capture"
                        >{{ __('ui.events.arrivals_submit') }}</x-ui.button>

                        <p class="text-sm text-muted" wire:loading.delay wire:target="capture">
                            {{ __('ui.events.arrivals_sending') }}
                        </p>
                    </div>
                </form>
            </x-ui.card>

            {{-- Llegadas recientes (15): la primera fila es la última llegada
                 y se resalta con accent (spec/09-ui-estilo.md §3). --}}
            <x-ui.card padding="none" :title="__('ui.events.arrivals_recent_title')" :subtitle="__('ui.events.arrivals_recent_text')">
                @if (count($arrivals) === 0)
                    <x-ui.empty-state
                        icon="timer"
                        :title="__('ui.events.arrivals_none_title')"
                        :text="__('ui.events.arrivals_none_text')"
                    />
                @else
                    <x-ui.table compact :headers="[
                        ['label' => __('ui.events.header_bib'), 'align' => 'right', 'class' => 'mono'],
                        __('ui.events.header_athlete'),
                        __('ui.events.header_club'),
                        ['label' => __('ui.events.arrivals_header_time'), 'align' => 'right', 'class' => 'mono'],
                        ['label' => __('ui.events.header_status')],
                    ]">
                        @foreach ($arrivals as $index => $arrival)
                            <tr
                                wire:key="recent-{{ $arrival['time'] }}-{{ $arrival['bib'] ?? 'sin-dorsal' }}-{{ $index }}"
                                class="{{ $index === 0 ? 'bg-accent-100/60 dark:bg-accent-500/10' : '' }}"
                            >
                                <td class="td-right mono font-semibold">{{ $arrival['bib'] ?? '—' }}</td>
                                <td class="font-medium text-strong">{{ $arrival['name'] ?? '—' }}</td>
                                <td>{{ $arrival['club'] ?? '—' }}</td>
                                <td class="td-right mono">{{ $arrival['time'] }}</td>
                                <td>
                                    <x-ui.badge :variant="$arrival['status'] === 'assigned' ? 'success' : 'warning'">
                                        {{ $arrival['status'] === 'assigned' ? __('ui.events.arrivals_status_assigned') : __('ui.events.arrivals_status_orphan') }}
                                    </x-ui.badge>
                                </td>
                            </tr>
                        @endforeach
                    </x-ui.table>
                @endif
            </x-ui.card>
        </div>

        {{-- Huérfanas del evento: aviso accionable para el juez (ISSUE-135
             con las correcciones); sin filas, estado vacío explícito. --}}
        <x-ui.card :title="__('ui.events.arrivals_orphans_title')" :subtitle="__('ui.events.arrivals_orphans_text')">
            @if (count($orphans) === 0)
                <x-ui.empty-state
                    icon="check-circle-2"
                    :title="__('ui.events.arrivals_orphans_none_title')"
                    :text="__('ui.events.arrivals_orphans_none_text')"
                />
            @else
                <ul class="flex flex-col gap-2">
                    @foreach ($orphans as $index => $orphan)
                        <li class="flex items-center justify-between gap-2 text-sm" wire:key="orphan-{{ $orphan['time'] }}-{{ $index }}">
                            <span class="mono">{{ $orphan['time'] }}</span>
                            <span class="text-muted">{{ __('ui.events.source.'.$orphan['source']) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>
    </div>
</div>
