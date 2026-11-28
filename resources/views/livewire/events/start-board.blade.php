<?php

use App\Actions\Start\GetEventClock;
use App\Actions\Start\ListWaveBoard;
use App\Actions\Start\MarkWaveStart;
use App\Actions\Start\UndoWaveStart;
use App\Models\Event;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

/*
| ISSUE-132/133 (TQ-013, versión mínima F1) — Tablero de salidas: reloj
| vivo contra la hora oficial del servidor y salida real de oleada. El
| componente no consulta la base (ADR-011/ADR-012): lee por ListWaveBoard
| (inscritos sobre el TOTAL de la oleada, RF-16) y escribe por
| MarkWaveStart/UndoWaveStart, que guardan la hora del servidor sin tocar
| la programada (RF-15) y rechazan fuera de orden con errores de negocio
| consumibles como toasts. El reloj pica en Alpine con offset contra el
| server_now sembrado en el render y se re-resincroniza cada 60 s con
| syncClock (RF-42).
|
| TQ-018 (EP-26, ISSUE-142): el tablero reacciona por WS — puente tcBridge
| (patrón compartido, ver arrival-capture) que escucha `wave.started` y
| `wave.undone` en ops y los retransmite como evento Livewire `tc-wave`.
| Los eventos de oleada no versionan datos (seq null): al recibirlos se
| refresca el tablero dirigido (with() → ListWaveBoard) y el reloj NO se
| toca — su offset es local y se re-resincroniza solo. Sondeo de respaldo
| a 15 s (RF-76).
*/

new #[Layout('components.layouts.panel')] #[Title('Salidas')] class extends Component
{
    public Event $event;

    /** Puente WS→Livewire (tcBridge en la vista): salida marcada o deshecha. */
    #[On('tc-wave')]
    public function onWaveChanged(): void
    {
        // wave.started/wave.undone no llevan seq: refresco dirigido del
        // tablero — la respuesta al evento re-renderiza y ListWaveBoard
        // vuelve a leer el estado real de las oleadas.
    }

    /** Hora oficial (ms de época) para re-resincronizar el reloj del cliente. */
    public function syncClock(): int
    {
        return app(GetEventClock::class)($this->event)['server_now'];
    }

    public function markStart(string $waveUlid): void
    {
        try {
            $result = app(MarkWaveStart::class)($this->event, $waveUlid);
        } catch (ValidationException $exception) {
            $this->dispatch('toast', type: 'danger', message: (string) collect($exception->errors())->flatten()->first());

            return;
        }

        $this->dispatch('toast', type: 'success', message: __('ui.events.start_toast_marked', [
            'name' => $result['name'],
            'time' => $result['actual'],
        ]));
    }

    public function undoStart(string $waveUlid): void
    {
        try {
            $result = app(UndoWaveStart::class)($this->event, $waveUlid);
        } catch (ValidationException $exception) {
            $this->dispatch('toast', type: 'danger', message: (string) collect($exception->errors())->flatten()->first());

            return;
        }

        $this->dispatch('toast', type: 'success', message: __('ui.events.start_toast_undone', [
            'name' => $result['name'],
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $clock = app(GetEventClock::class)($this->event);

        return [
            'waves' => app(ListWaveBoard::class)($this->event),
            'serverNow' => $clock['server_now'],
            'clockTimezone' => $clock['timezone'],
        ];
    }
}

?>

<div
    class="flex flex-col gap-4"
    wire:poll.15s
    x-data="tcBridge(@js(['channel' => 'event.'.$event->ulid.'.ops', 'events' => ['.wave.started' => 'tc-wave', '.wave.undone' => 'tc-wave']]))"
>
    <x-ui.page-header :title="__('ui.events.start_title')" subtitle="{{ $event->name }}">
        <x-slot:actions>
            {{-- Indicador de conexión WS ligado a tc-echo-status (G13, TQ-018). --}}
            <x-ui.live-indicator :wire="true" />
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 items-start">
        {{-- Reloj oficial: wire:ignore — el tick vive en Alpine y wire:poll
             no debe morfarlo; la re-resincronización va por syncClock. --}}
        <x-ui.card :title="__('ui.events.start_clock_title')" :subtitle="__('ui.events.start_clock_text')">
            <div wire:ignore x-data="tcStartClock(@js(['server_now' => $serverNow, 'timezone' => $clockTimezone]))">
                <p class="mono py-8 text-center text-5xl font-bold sm:text-6xl" x-text="clock" x-cloak>—</p>
                <p class="text-center text-sm text-muted">{{ $clockTimezone }}</p>
            </div>
        </x-ui.card>

        {{-- Tablero de oleadas: botón «Marcar salida» solo en la primera sin
             real; «Deshacer salida» en las iniciadas (ISSUE-133, RF-15). --}}
        <x-ui.card class="lg:col-span-2" padding="none" :title="__('ui.events.start_table_title')" :subtitle="__('ui.events.start_table_text')">
            @if (count($waves) === 0)
                <x-ui.empty-state
                    icon="layers"
                    :title="__('ui.events.start_none_title')"
                    :text="__('ui.events.no_waves')"
                />
            @else
                <x-ui.table :headers="[
                    __('ui.events.header_wave'),
                    ['label' => __('ui.events.header_scheduled'), 'align' => 'right', 'class' => 'mono'],
                    ['label' => __('ui.events.header_actual'), 'align' => 'right', 'class' => 'mono'],
                    ['label' => __('ui.events.header_entries'), 'align' => 'right', 'class' => 'mono'],
                    __('ui.events.header_status'),
                    __('ui.events.header_actions'),
                ]">
                    @foreach ($waves as $wave)
                        <tr wire:key="wave-{{ $wave['ulid'] }}">
                            <td>
                                <span class="font-medium text-strong">{{ $wave['name'] }}</span>
                                <span class="text-muted"> · {{ $wave['race'] }}</span>
                            </td>
                            <td class="td-right mono">{{ $wave['scheduled'] ?? '—' }}</td>
                            <td class="td-right mono {{ $wave['differs'] ? 'font-semibold text-accent-600 dark:text-accent-400' : '' }}"@if ($wave['differs']) title="{{ __('ui.events.start_differs_title') }}"@endif>
                                {{ $wave['actual'] ?? '—' }}
                            </td>
                            <td class="td-right mono">{{ $wave['entries_total'] }}</td>
                            <td>
                                <x-ui.badge :variant="$wave['status'] === 'started' ? 'success' : 'neutral'" :dot="$wave['status'] === 'started'">
                                    {{ $wave['status'] === 'started' ? __('ui.events.start_status_started') : __('ui.events.start_status_pending') }}
                                </x-ui.badge>
                            </td>
                            <td>
                                @if ($wave['is_next'])
                                    <x-ui.button
                                        variant="primary"
                                        size="sm"
                                        icon="play"
                                        x-data
                                        wire:loading.attr="disabled"
                                        wire:target="markStart"
                                        @click="tcConfirm({
                                            title: @js(__('ui.events.start_confirm_mark_title')),
                                            message: @js(__('ui.events.start_confirm_mark', ['name' => $wave['name']])) + ' ' + ($store.tcClock ? $store.tcClock.now : ''),
                                        }).then((ok) => ok && $wire.markStart('{{ $wave['ulid'] }}'))"
                                    >{{ __('ui.events.start_mark') }}</x-ui.button>
                                @elseif ($wave['actual'] !== null)
                                    <x-ui.button
                                        variant="danger-soft"
                                        size="sm"
                                        icon="rotate-ccw"
                                        x-data
                                        wire:loading.attr="disabled"
                                        wire:target="undoStart"
                                        @click="tcConfirm({
                                            title: @js(__('ui.events.start_confirm_undo_title')),
                                            message: @js(__('ui.events.start_confirm_undo', ['name' => $wave['name']])),
                                            danger: true,
                                        }).then((ok) => ok && $wire.undoStart('{{ $wave['ulid'] }}'))"
                                    >{{ __('ui.events.start_undo') }}</x-ui.button>
                                @else
                                    <span class="text-sm text-muted">{{ __('ui.events.start_wait') }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </x-ui.table>
            @endif
        </x-ui.card>
    </div>
</div>

@once
    <script>
        /* Reloj vivo del tablero (ISSUE-132, RF-42): offset = server_now −
           Date.now() del navegador; el tick deriva del reloj local y cada
           60 s vuelve a pedir la hora oficial ($wire.syncClock), así la
           deriva queda < 1 s/h. El store tcClock publica la hora formateada
           para que las confirmaciones muestren la hora de sistema. */
        window.tcStartClock ||= (config) => ({
            offsetMs: 0,
            clock: '',
            init() {
                this.applyServerNow(config.server_now);
                this.tick();
                setInterval(() => this.tick(), 500);
                setInterval(async () => {
                    try {
                        this.applyServerNow(await this.$wire.syncClock());
                    } catch (error) {
                        /* sin respuesta: el offset vigente sigue mandando */
                    }
                }, 60000);
            },
            applyServerNow(serverNow) {
                if (! Number.isFinite(serverNow)) {
                    return;
                }
                this.offsetMs = serverNow - Date.now();
                window.Alpine?.store('tcClock', { now: this.clock, offsetMs: this.offsetMs });
            },
            tick() {
                const moment = new Date(Date.now() + this.offsetMs);
                this.clock = this.format(moment);
                window.Alpine?.store('tcClock', { now: this.clock, offsetMs: this.offsetMs });
            },
            format(moment) {
                try {
                    return new Intl.DateTimeFormat('es-VE', {
                        hour: '2-digit',
                        minute: '2-digit',
                        second: '2-digit',
                        hourCycle: 'h23',
                        timeZone: config.timezone,
                    }).format(moment);
                } catch (error) {
                    return moment.toTimeString().slice(0, 8);
                }
            },
        });
    </script>
@endonce
