<?php

use App\Actions\Corrections\CorrectTimingMark;
use App\Actions\Corrections\FindMarkForCorrection;
use App\Actions\Corrections\ListMarkLog;
use App\Models\Event;
use App\Support\FormatClock;
use App\Support\FormatDuration;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;

/*
| ISSUE-134/135 (TQ-014, versión mínima F1) — Bitácora de marcas en vivo y
| corrección 1/2/3. Lee por ListMarkLog (estados DERIVADOS con la misma
| consulta del motor: EffectiveMarks) y escribe por CorrectTimingMark, que
| audita la corrección sin tocar la marca (append-only, INV-01) y dispara el
| recálculo afterCommit (INV-08). Ver exige marks.view (ruta); corregir
| exige corrections.manage (Gate en submit, ISSUE-159). La reasignación a un
| dorsal con llegada efectiva se confirma dos veces: tcConfirm al enviar y
| el error `confirm` del servidor reabre tcConfirm para reenviar con
| confirm=true. El push < 2 s queda para EP-26: interino wire:poll.5s.
*/

new #[Layout('components.layouts.panel')] #[Title('Registros')] class extends Component
{
    use WithPagination;

    public Event $event;

    /** Contexto de la marca abierta (FindMarkForCorrection): efectiva + historial. */
    public array $mark = [];

    public string $operation = 'adjust';

    public string $reason = '';

    /** Desplazamiento acumulado de ESTA corrección (ms; botones ±1000/±100). */
    public int $delta = 0;

    public string $targetBib = '';

    public function mount(Event $event): void
    {
        $this->event = $event;
    }

    public function openCorrect(string $markUlid, ?string $operation = null): void
    {
        $this->resetCorrectionForm();

        $context = app(FindMarkForCorrection::class)($this->event, $markUlid);

        if ($context === null) {
            $this->dispatch('toast', type: 'danger', message: __('ui.events.correct_err_missing'));

            return;
        }

        $this->mark = $context;
        $this->operation = $operation ?? 'adjust';
        $this->resetValidation();
        $this->dispatch('open-mark-modal');
    }

    public function openHistory(string $markUlid): void
    {
        $context = app(FindMarkForCorrection::class)($this->event, $markUlid);

        if ($context === null) {
            $this->dispatch('toast', type: 'danger', message: __('ui.events.correct_err_missing'));

            return;
        }

        $this->mark = $context;
        $this->dispatch('open-history-modal');
    }

    /** Huérfana asignable (ISSUE-135): abre el modal directo en Reasignar. */
    public function assign(string $markUlid): void
    {
        $this->openCorrect($markUlid, 'reassign');
    }

    public function setOperation(string $operation): void
    {
        if (in_array($operation, ['adjust', 'reassign', 'void'], true)) {
            $this->operation = $operation;
        }
    }

    public function bump(int $milliseconds): void
    {
        $this->delta += $milliseconds;
    }

    public function cancel(): void
    {
        $this->resetCorrectionForm();
        $this->resetValidation();
        $this->dispatch('close-mark-modal');
    }

    public function submit(bool $confirm = false): void
    {
        Gate::authorize('corrections.manage');

        if ($this->mark === []) {
            $this->dispatch('toast', type: 'danger', message: __('ui.events.correct_err_missing'));

            return;
        }

        $bib = trim($this->targetBib);

        try {
            $result = app(CorrectTimingMark::class)(
                $this->event,
                (string) $this->mark['ulid'],
                $this->operation,
                $this->reason,
                $this->delta,
                $bib === '' ? null : (int) $bib,
                $confirm,
            );
        } catch (ValidationException $exception) {
            $errors = $exception->errors();

            // Destino con llegada efectiva: tcConfirm en el navegador y
            // reenvío con confirm=true (ISSUE-135).
            if (isset($errors['confirm'])) {
                $this->dispatch('mark-confirm-target', message: (string) collect($errors['confirm'])->first());

                return;
            }

            if (isset($errors['mark'])) {
                $this->dispatch('close-mark-modal');
                $this->dispatch('toast', type: 'danger', message: (string) collect($errors['mark'])->first());

                return;
            }

            throw $exception; // el bag viaja a los campos del modal
        }

        $this->resetCorrectionForm();
        $this->resetValidation();
        $this->dispatch('close-mark-modal');
        $this->dispatch('toast', type: 'success', message: __('ui.events.correct_toast_done', [
            'op' => __('ui.events.correct_toast_op_'.$result['operation'], ['bib' => $result['bib'] ?? '']),
            'time' => $result['time'],
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $data = app(ListMarkLog::class)($this->event);

        $variants = [
            'assigned' => 'success',
            'orphan' => 'warning',
            'duplicate' => 'info',
            'void' => 'danger',
            'corrected' => 'primary',
        ];

        $counterItems = [];
        foreach ($variants as $status => $variant) {
            $counterItems[] = [
                'label' => __("ui.events.log_count_{$status}"),
                'total' => $data['counters'][$status],
                'variant' => $variant,
            ];
        }

        $markTime = (string) ($this->mark['time'] ?? '');
        $typedBib = trim($this->targetBib);

        return [
            'marks' => $data['marks'],
            'counterItems' => $counterItems,
            'statusVariant' => $variants,
            'canCorrect' => Gate::allows('corrections.manage'),
            'provisionalTime' => $this->provisionalTime(),
            'deltaLabel' => $this->deltaLabel(),
            'confirmMessage' => match ($this->operation) {
                'void' => __('ui.events.correct_confirm_void', ['time' => $markTime]),
                'reassign' => __('ui.events.correct_confirm_reassign', [
                    'time' => $markTime,
                    'bib' => $typedBib !== '' ? $typedBib : '—',
                ]),
                default => __('ui.events.correct_confirm', ['time' => $markTime]),
            },
        ];
    }

    private function provisionalTime(): string
    {
        if ($this->mark === [] || $this->operation !== 'adjust') {
            return '';
        }

        $effective = CarbonImmutable::parse((string) $this->mark['captured_at']);

        return FormatClock::timeOfDay($effective->addMilliseconds($this->delta), $this->event->timezone);
    }

    private function deltaLabel(): string
    {
        $text = FormatDuration::fromMs($this->delta, true);

        return $this->delta > 0 ? '+'.$text : $text;
    }

    private function resetCorrectionForm(): void
    {
        $this->reset(['mark', 'operation', 'reason', 'delta', 'targetBib']);
        $this->operation = 'adjust';
    }
}

?>

<div
    class="flex flex-col gap-4"
    wire:poll.5s
    x-data="{ markModal: false, historyModal: false }"
    @open-mark-modal.window="markModal = true"
    @close-mark-modal.window="markModal = false"
    @open-history-modal.window="historyModal = true"
    @close-history-modal.window="historyModal = false"
    @mark-confirm-target.window="tcConfirm({
        title: @js(__('ui.events.correct_confirm_target_title')),
        message: $event.detail.message,
        danger: true,
    }).then((ok) => ok && $wire.submit(true))"
>
    <x-ui.page-header :title="__('ui.events.marks_title')" :subtitle="__('ui.events.log_text')" />

    {{-- Contadores por estado derivado (ISSUE-134): coherentes con la tabla
         porque ambos salen de la misma consulta de EffectiveMarks. --}}
    <div class="card">
        <div class="card-body flex flex-wrap items-center gap-x-6 gap-y-3">
            @foreach ($counterItems as $item)
                <span class="flex items-center gap-2" wire:key="counter-{{ $item['variant'] }}">
                    <x-ui.badge :variant="$item['variant']" dot>{{ $item['label'] }}</x-ui.badge>
                    <span class="mono text-base font-semibold">{{ $item['total'] }}</span>
                </span>
            @endforeach
        </div>
    </div>

    {{-- Bitácora paginada server-side (25) por hora efectiva descendente;
         la huérfana ofrece «Asignar» y toda fila su historial auditado. --}}
    <x-ui.card padding="none">
        @if ($marks->isEmpty())
            <x-ui.empty-state
                icon="timer"
                :title="__('ui.events.log_none_title')"
                :text="__('ui.events.log_none_text')"
            />
        @else
            <x-ui.table :headers="[
                ['label' => __('ui.events.log_header_time'), 'align' => 'right', 'class' => 'mono'],
                ['label' => __('ui.events.header_bib'), 'align' => 'right', 'class' => 'mono'],
                __('ui.events.header_athlete'),
                __('ui.events.log_header_source'),
                __('ui.events.header_status'),
                ['label' => __('ui.events.header_actions'), 'align' => 'right'],
            ]">
                @foreach ($marks as $row)
                    <tr wire:key="mark-{{ $row['ulid'] }}">
                        <td class="td-right mono font-semibold">{{ $row['time'] }}</td>
                        <td class="td-right mono">{{ $row['bib'] ?? '—' }}</td>
                        <td class="font-medium text-strong">
                            {{ $row['bib'] !== null ? ($row['athlete'] ?? '—') : __('ui.events.log_orphan_cell') }}
                        </td>
                        <td>{{ __('ui.events.source.'.$row['source']) }}</td>
                        <td>
                            <x-ui.badge :variant="$statusVariant[$row['status']]">
                                {{ __('ui.events.log_status_'.$row['status']) }}
                            </x-ui.badge>
                        </td>
                        <td class="td-right">
                            <div class="flex items-center justify-end gap-1">
                                @if ($row['status'] === 'orphan' && $canCorrect)
                                    <x-ui.icon-button
                                        icon="corner-down-right"
                                        :tip="__('ui.events.log_tip_assign')"
                                        wire:click="assign('{{ $row['ulid'] }}')"
                                        wire:loading.attr="disabled"
                                        wire:target="assign"
                                    />
                                @elseif ($canCorrect)
                                    <x-ui.icon-button
                                        icon="pencil"
                                        :tip="__('ui.events.log_tip_correct')"
                                        wire:click="openCorrect('{{ $row['ulid'] }}')"
                                        wire:loading.attr="disabled"
                                        wire:target="openCorrect"
                                    />
                                @endif
                                <x-ui.icon-button
                                    icon="history"
                                    :tip="__('ui.events.log_tip_history')"
                                    wire:click="openHistory('{{ $row['ulid'] }}')"
                                    wire:loading.attr="disabled"
                                    wire:target="openHistory"
                                />
                            </div>
                        </td>
                    </tr>
                @endforeach

                <x-slot:footer>
                    {{ $marks->links('components.ui.pagination') }}
                </x-slot:footer>
            </x-ui.table>
        @endif
    </x-ui.card>

    {{-- Modal de corrección (ISSUE-135): tres operaciones, motivo SIEMPRE y
         confirmación previa con tcConfirm; el ajuste muestra el resultado
         provisional de los botones ±1 s / ±0,1 s. --}}
    <x-ui.modal show="markModal" :title="__('ui.events.correct_modal_title')" :subtitle="__('ui.events.correct_modal_subtitle')" size="lg">
        @if ($mark !== [])
            <div class="flex flex-col gap-4">
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
                    <span class="mono text-base font-semibold">{{ $mark['time'] }}</span>
                    @if ($mark['bib'] !== null)
                        <span class="mono">{{ $mark['bib'] }}</span>
                        <span class="font-medium text-strong">{{ $mark['athlete'] ?? '—' }}</span>
                    @else
                        <span class="text-muted">{{ __('ui.events.log_orphan_cell') }}</span>
                    @endif
                    <x-ui.badge :variant="$statusVariant[$mark['status']]">{{ __('ui.events.log_status_'.$mark['status']) }}</x-ui.badge>
                    <span class="text-muted">{{ __('ui.events.source.'.$mark['source']) }}</span>
                </div>

                <div class="flex flex-wrap gap-2" role="group" aria-label="{{ __('ui.events.correct_modal_title') }}">
                    <x-ui.button
                        :variant="$operation === 'adjust' ? 'primary' : 'outline'"
                        size="sm"
                        wire:click="setOperation('adjust')"
                        :aria-pressed="$operation === 'adjust' ? 'true' : 'false'"
                    >{{ __('ui.events.correct_op_adjust') }}</x-ui.button>
                    <x-ui.button
                        :variant="$operation === 'reassign' ? 'primary' : 'outline'"
                        size="sm"
                        wire:click="setOperation('reassign')"
                        :aria-pressed="$operation === 'reassign' ? 'true' : 'false'"
                    >{{ __('ui.events.correct_op_reassign') }}</x-ui.button>
                    <x-ui.button
                        :variant="$operation === 'void' ? 'primary' : 'outline'"
                        size="sm"
                        wire:click="setOperation('void')"
                        :aria-pressed="$operation === 'void' ? 'true' : 'false'"
                    >{{ __('ui.events.correct_op_void') }}</x-ui.button>
                </div>

                @if ($operation === 'adjust')
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <p class="kpi-label">{{ __('ui.events.correct_adjust_current') }}</p>
                            <p class="mono text-lg font-semibold">{{ $mark['time'] }}</p>
                        </div>
                        <div>
                            <p class="kpi-label">{{ __('ui.events.correct_adjust_delta') }}</p>
                            <p class="mono text-lg font-semibold">{{ $deltaLabel }}</p>
                        </div>
                        <div>
                            <p class="kpi-label">{{ __('ui.events.correct_adjust_result') }}</p>
                            <p class="mono text-lg font-semibold text-primary-700 dark:text-primary-300">{{ $provisionalTime }}</p>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <x-ui.button variant="soft" size="sm" wire:click="bump(1000)">{{ __('ui.events.correct_adjust_plus_1s') }}</x-ui.button>
                        <x-ui.button variant="soft" size="sm" wire:click="bump(-1000)">{{ __('ui.events.correct_adjust_minus_1s') }}</x-ui.button>
                        <x-ui.button variant="soft" size="sm" wire:click="bump(100)">{{ __('ui.events.correct_adjust_plus_100ms') }}</x-ui.button>
                        <x-ui.button variant="soft" size="sm" wire:click="bump(-100)">{{ __('ui.events.correct_adjust_minus_100ms') }}</x-ui.button>
                    </div>
                    <p class="text-xs text-muted">{{ __('ui.events.correct_adjust_help') }}</p>
                @elseif ($operation === 'reassign')
                    <x-ui.form-group
                        :label="__('ui.events.correct_reassign_bib')"
                        for="correct-target-bib"
                        :help="__('ui.events.correct_reassign_bib_help')"
                        :error="$errors->first('targetBib')"
                    >
                        <input
                            id="correct-target-bib"
                            type="text"
                            class="form-control mono"
                            inputmode="numeric"
                            autocomplete="off"
                            :placeholder="__('ui.events.correct_reassign_bib_placeholder')"
                            wire:model="targetBib"
                        >
                    </x-ui.form-group>
                @else
                    <x-ui.alert type="warning">{{ __('ui.events.correct_void_text') }}</x-ui.alert>
                @endif

                <x-ui.form-group
                    :label="__('ui.events.correct_reason')"
                    for="correct-reason"
                    required
                    :help="__('ui.events.correct_reason_help')"
                    :error="$errors->first('reason')"
                >
                    <textarea
                        id="correct-reason"
                        class="form-control"
                        rows="3"
                        maxlength="500"
                        :placeholder="__('ui.events.correct_reason_placeholder')"
                        wire:model="reason"
                    ></textarea>
                </x-ui.form-group>
            </div>

            <x-slot:footer>
                <x-ui.button variant="ghost" icon="x" wire:click="cancel">{{ __('ui.events.correct_cancel') }}</x-ui.button>
                @can('corrections.manage')
                    <x-ui.button
                        variant="primary"
                        icon="check"
                        x-data
                        wire:loading.attr="disabled"
                        wire:target="submit"
                        @click="tcConfirm({
                            title: @js(__('ui.events.correct_confirm_title')),
                            message: @js($confirmMessage),
                            danger: @js($operation === 'void'),
                        }).then((ok) => ok && $wire.submit(false))"
                    >{{ __('ui.events.correct_submit') }}</x-ui.button>
                @endcan
            </x-slot:footer>
        @endif
    </x-ui.modal>

    {{-- Historial de la marca: sus correcciones auditadas (ISSUE-135). --}}
    <x-ui.modal show="historyModal" :title="__('ui.events.correct_history_title')" :subtitle="__('ui.events.correct_history_text')" size="lg">
        @if ($mark !== [])
            @if (count($mark['corrections']) === 0)
                <x-ui.empty-state
                    icon="history"
                    :title="__('ui.events.correct_history_none_title')"
                    :text="__('ui.events.correct_history_none_text')"
                />
            @else
                <ul class="flex flex-col gap-3">
                    @foreach ($mark['corrections'] as $index => $correction)
                        <li
                            class="flex flex-col gap-1 rounded-[12px] border border-neutral-200 p-3 dark:border-neutral-800"
                            wire:key="correction-{{ $mark['ulid'] }}-{{ $index }}"
                        >
                            <div class="flex flex-wrap items-center gap-2">
                                <x-ui.badge variant="neutral" icon="{{ ['adjust' => 'clock', 'reassign' => 'corner-down-right', 'void' => 'x-circle'][$correction['operation']] ?? 'circle' }}">
                                    {{ __('ui.events.correct_op_'.$correction['operation']) }}
                                </x-ui.badge>
                                <span class="mono text-xs">{{ $correction['when'] ?? '—' }}</span>
                                <span class="text-xs text-muted">{{ $correction['who'] ?? '—' }}</span>
                            </div>
                            <p class="text-sm">{{ $correction['reason'] }}</p>
                        </li>
                    @endforeach
                </ul>
            @endif
        @endif
    </x-ui.modal>
</div>
