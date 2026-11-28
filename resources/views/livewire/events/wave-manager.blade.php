<?php

/*
| ISSUE-160 fase 2 (TQ-026) — Editor de oleadas + reprogramación en bloque
| (ISSUE-050): solo componentes del kit x-ui.* y clases del sistema. La hora
| programada viaja como dd/mm/yyyy H:i:s.v mono en la zona del evento y se
| edita con datetime-local (paso 0.001 → milisegundos). La reprogramación
| en bloque es dos pasos: formulario → resumen con el número de oleadas
| afectadas → UNA operación transaccional.
*/

?>

<div
    class="flex flex-col gap-4"
    x-data="{ waveModal: false, reprogramModal: false }"
    @open-wave-modal.window="waveModal = true"
    @close-wave-modal.window="waveModal = false"
    @open-reprogram-modal.window="reprogramModal = true"
    @close-reprogram-modal.window="reprogramModal = false"
>
    <x-ui.page-header :title="__('ui.events.waves_title')" :subtitle="$eventName">
        <x-slot:actions>
            <x-ui.button variant="primary" icon="plus" wire:click="startCreate">
                {{ __('ui.events.wave_new') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if (count($races) === 0)
        <x-ui.card padding="none">
            <x-ui.empty-state
                icon="layers"
                :title="__('ui.events.wave_no_races_title')"
                :text="__('ui.events.wave_no_races_text')"
            >
                <x-ui.button
                    variant="outline"
                    icon="pencil"
                    href="{{ route('events.index', ['edit' => $eventUlid]) }}"
                >
                    {{ __('ui.events.wave_no_races_cta') }}
                </x-ui.button>
            </x-ui.empty-state>
        </x-ui.card>
    @else
        @foreach ($races as $race)
            <x-ui.card :title="$race['name']" padding="none">
                <x-slot:actions>
                    <span class="inline-flex items-center gap-1">
                        <x-ui.button
                            variant="ghost"
                            size="sm"
                            icon="clock"
                            wire:click="startReprogram('{{ $race['ulid'] }}')"
                        >
                            {{ __('ui.events.wave_reprogram') }}
                        </x-ui.button>
                        <x-ui.button
                            variant="outline"
                            size="sm"
                            icon="plus"
                            wire:click="startCreate('{{ $race['ulid'] }}')"
                        >
                            {{ __('ui.events.wave_add') }}
                        </x-ui.button>
                    </span>
                </x-slot:actions>

                @if (count($race['waves']) === 0)
                    <div class="p-4">
                        <x-ui.empty-state
                            icon="layers"
                            :title="__('ui.events.wave_empty_title')"
                            :text="__('ui.events.wave_empty_text')"
                        >
                            <x-ui.button
                                variant="outline"
                                size="sm"
                                icon="plus"
                                wire:click="startCreate('{{ $race['ulid'] }}')"
                            >
                                {{ __('ui.events.wave_add') }}
                            </x-ui.button>
                        </x-ui.empty-state>
                    </div>
                @else
                    <x-ui.table :headers="$waveHeaders" compact>
                        @foreach ($race['waves'] as $wave)
                            <tr wire:key="wave-{{ $wave['ulid'] }}">
                                <td class="font-medium text-strong">{{ $wave['name'] }}</td>
                                <td class="td-right mono">{{ $wave['scheduled'] ?? '—' }}</td>
                                <td class="td-right mono">{{ $wave['entries_total'] }}</td>
                                <td class="td-right">
                                    <span class="inline-flex items-center justify-end gap-1">
                                        <x-ui.icon-button
                                            icon="pencil"
                                            :tip="__('ui.events.wave_edit')"
                                            wire:click="startEdit('{{ $wave['ulid'] }}')"
                                        />
                                        <x-ui.icon-button
                                            icon="trash-2"
                                            :tip="__('ui.events.wave_delete')"
                                            color="danger"
                                            wire:click="deleteWave('{{ $wave['ulid'] }}')"
                                            wire:confirm="{{ __('ui.events.wave_delete_confirm', ['name' => $wave['name']]) }}"
                                        />
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </x-ui.table>
                @endif
            </x-ui.card>
        @endforeach
    @endif

    <x-ui.modal
        show="waveModal"
        :title="$editingUlid === null ? __('ui.events.wave_new') : __('ui.events.wave_edit')"
        :subtitle="__('ui.events.modal_subtitle')"
        size="md"
    >
        <form wire:submit="save" id="wave-form" class="flex flex-col gap-4">
            <x-ui.form-group
                :label="__('ui.events.wave_race')"
                for="wave-race"
                required
                :error="$errors->first('form.race')"
            >
                <select id="wave-race" class="form-control" wire:model="form.race">
                    <option value="" disabled>{{ __('ui.events.wave_race_placeholder') }}</option>
                    @foreach ($races as $race)
                        <option value="{{ $race['ulid'] }}">{{ $race['name'] }}</option>
                    @endforeach
                </select>
            </x-ui.form-group>

            <x-ui.form-group
                :label="__('ui.events.name')"
                for="wave-name"
                required
                :help="__('ui.events.wave_name_help')"
                :error="$errors->first('form.name')"
            >
                <input
                    id="wave-name"
                    type="text"
                    class="form-control"
                    maxlength="50"
                    placeholder="{{ __('ui.events.wave_name_placeholder') }}"
                    autocomplete="off"
                    wire:model="form.name"
                >
            </x-ui.form-group>

            <x-ui.form-group
                :label="__('ui.events.wave_scheduled')"
                for="wave-scheduled"
                required
                :help="__('ui.events.wave_scheduled_help')"
                :error="$errors->first('form.scheduled_start_at')"
            >
                <input
                    id="wave-scheduled"
                    type="datetime-local"
                    class="form-control mono"
                    step="0.001"
                    wire:model="form.scheduled_start_at"
                >
            </x-ui.form-group>
        </form>

        <x-slot:footer>
            <x-ui.button variant="ghost" icon="x" @click="waveModal = false">{{ __('ui.events.cancel') }}</x-ui.button>
            <x-ui.button variant="primary" icon="check" type="submit" form="wave-form" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save">{{ $editingUlid === null ? __('ui.events.create') : __('ui.events.save') }}</span>
                <span wire:loading wire:target="save" class="spinner" aria-hidden="true"></span>
            </x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    <x-ui.modal
        show="reprogramModal"
        :title="__('ui.events.wave_reprogram_title')"
        :subtitle="__('ui.events.wave_reprogram_subtitle')"
        size="md"
    >
        @if (! $reprogramConfirm)
            <form wire:submit="previewReprogram" id="reprogram-form" class="flex flex-col gap-4">
                <x-ui.form-group
                    :label="__('ui.events.wave_reprogram_start')"
                    for="reprogram-start"
                    required
                    :help="__('ui.events.wave_scheduled_help')"
                    :error="$errors->first('reprogram.start_at')"
                >
                    <input
                        id="reprogram-start"
                        type="datetime-local"
                        class="form-control mono"
                        step="0.001"
                        wire:model="reprogram.start_at"
                    >
                </x-ui.form-group>

                <x-ui.form-group
                    :label="__('ui.events.wave_reprogram_interval')"
                    for="reprogram-interval"
                    required
                    :help="__('ui.events.wave_reprogram_interval_help')"
                    :error="$errors->first('reprogram.interval')"
                >
                    <input
                        id="reprogram-interval"
                        type="number"
                        class="form-control mono"
                        min="1"
                        max="1440"
                        step="1"
                        inputmode="numeric"
                        wire:model="reprogram.interval"
                    >
                </x-ui.form-group>
            </form>

            <x-slot:footer>
                <x-ui.button variant="ghost" icon="x" @click="reprogramModal = false">{{ __('ui.events.cancel') }}</x-ui.button>
                <x-ui.button variant="primary" icon="arrow-right" type="submit" form="reprogram-form" wire:loading.attr="disabled" wire:target="previewReprogram">
                    <span wire:loading.remove wire:target="previewReprogram">{{ __('ui.events.wave_reprogram_continue') }}</span>
                    <span wire:loading wire:target="previewReprogram" class="spinner" aria-hidden="true"></span>
                </x-ui.button>
            </x-slot:footer>
        @else
            <x-ui.alert type="warning" :title="__('ui.events.wave_reprogram_summary_title')">
                {{ __('ui.events.wave_reprogram_summary', ['count' => $reprogramCount, 'first' => $reprogramFirst, 'last' => $reprogramLast]) }}
            </x-ui.alert>

            <x-slot:footer>
                <x-ui.button variant="ghost" icon="arrow-left" wire:click="editReprogram">
                    {{ __('ui.events.wave_reprogram_back') }}
                </x-ui.button>
                <x-ui.button variant="primary" icon="clock" wire:click="confirmReprogram" wire:loading.attr="disabled" wire:target="confirmReprogram">
                    <span wire:loading.remove wire:target="confirmReprogram">{{ __('ui.events.wave_reprogram_apply') }}</span>
                    <span wire:loading wire:target="confirmReprogram" class="spinner" aria-hidden="true"></span>
                </x-ui.button>
            </x-slot:footer>
        @endif
    </x-ui.modal>
</div>
