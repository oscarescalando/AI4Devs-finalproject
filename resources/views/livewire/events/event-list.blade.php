<?php

/*
| ISSUE-160 fase 1 (TQ-025) — CRUD de eventos y carreras: solo componentes
| del kit x-ui.* y clases del sistema; sin CSS/JS ad-hoc. Estados visibles
| siempre: esqueleto en carga, vacío con CTA, errores bajo el campo, toasts
| y estados de carga del botón (spec/09-ui-estilo.md §6). Sin botón eliminar:
| los eventos se cierran, no se borran (DEFER de TQ-025).
*/

?>
<div
    class="flex flex-col gap-4"
    x-data="{ eventModal: {{ $editingUlid !== null ? 'true' : 'false' }} }"
    @open-event-modal.window="eventModal = true"
    @close-event-modal.window="eventModal = false"
>
    <x-ui.page-header
        :title="__('ui.events.title')"
        :subtitle="__('ui.events.subtitle')"
    >
        <x-slot:actions>
            @can('events.manage')
                <x-ui.button variant="primary" icon="plus" wire:click="startCreate">
                    {{ __('ui.events.new') }}
                </x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card padding="none">
        <div class="toolbar">
            <label class="search">
                <x-ui.icon name="search" />
                <input
                    type="search"
                    class="form-control"
                    placeholder="{{ __('ui.events.search_placeholder') }}"
                    aria-label="{{ __('ui.events.search_aria') }}"
                    wire:model.live.debounce.400ms="q"
                >
            </label>

            @if (trim($q) !== '')
                <span class="chip" wire:key="result-count">
                    <x-ui.icon name="list-filter" />
                    {{ $events->total() }} {{ trans_choice('ui.events.results', $events->total()) }}
                </span>
                <x-ui.button variant="ghost" size="sm" icon="x" wire:click="$set('q', '')">
                    {{ __('ui.events.clear') }}
                </x-ui.button>
            @endif
        </div>

        {{-- Carga: esqueleto acotado a la tabla mientras consulta (P4/G13) --}}
        <div wire:loading.delay wire:target="gotoPage,previousPage,nextPage,q" class="flex flex-col gap-2 p-4">
            @foreach (range(1, 6) as $row)
                <div class="skeleton h-9" wire:key="skeleton-{{ $row }}"></div>
            @endforeach
        </div>

        <div wire:loading.remove.delay wire:target="gotoPage,previousPage,nextPage,q">
            @if ($events->isEmpty())
                <x-ui.empty-state
                    icon="calendar-days"
                    :title="trim($q) !== '' ? __('ui.events.empty_search_title') : __('ui.events.empty_title')"
                    :text="trim($q) !== '' ? __('ui.events.empty_search_text') : __('ui.events.empty_text')"
                >
                    @if (trim($q) === '')
                        @can('events.manage')
                            <x-ui.button variant="primary" icon="plus" wire:click="startCreate">
                                {{ __('ui.events.new') }}
                            </x-ui.button>
                        @endcan
                    @endif
                </x-ui.empty-state>
            @else
                <x-ui.table :headers="$headers">
                    @foreach ($events as $event)
                        <tr wire:key="event-{{ $event->ulid }}">
                            <td class="font-medium text-strong">{{ $event->name }}</td>
                            <td class="td-right mono">{{ \App\Support\FormatDate::date($event->date) }}</td>
                            <td class="td-right mono">{{ $event->races_count }}</td>
                            <td class="td-right mono">{{ $event->entries_count }}</td>
                            <td>
                                <x-ui.badge
                                    :variant="\App\Support\Ui\EventStatus::badgeVariant($event->status)"
                                    :dot="$event->status === 'live'"
                                >{{ \App\Support\Ui\EventStatus::label($event->status) }}</x-ui.badge>
                            </td>
                            <td class="td-right">
                                <span class="inline-flex items-center justify-end gap-1">
                                    <x-ui.icon-button
                                        icon="eye"
                                        :tip="__('ui.events.open')"
                                        :href="route('events.show', $event->ulid)"
                                    />
                                    @can('events.manage')
                                        <x-ui.icon-button
                                            icon="pencil"
                                            :tip="__('ui.events.edit')"
                                            wire:click="startEdit('{{ $event->ulid }}')"
                                        />
                                    @endcan
                                </span>
                            </td>
                        </tr>
                    @endforeach

                    <x-slot:footer>
                        {{ $events->links('components.ui.pagination') }}
                    </x-slot:footer>
                </x-ui.table>
            @endif
        </div>
    </x-ui.card>

    <x-ui.modal
        show="eventModal"
        :title="$editingUlid === null ? __('ui.events.new') : __('ui.events.modal_edit')"
        :subtitle="__('ui.events.modal_subtitle')"
        size="lg"
    >
        <form wire:submit="save" id="event-form" class="flex flex-col gap-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-ui.form-group
                    :label="__('ui.events.name')"
                    for="event-name"
                    required
                    :help="__('ui.events.name_help')"
                    :error="$errors->first('form.name')"
                >
                    <input
                        id="event-name"
                        type="text"
                        class="form-control"
                        maxlength="150"
                        placeholder="{{ __('ui.events.name_placeholder') }}"
                        autocomplete="off"
                        wire:model="form.name"
                    >
                </x-ui.form-group>

                <x-ui.form-group
                    :label="__('ui.events.slug')"
                    for="event-slug"
                    required
                    :help="__('ui.events.slug_help')"
                    :error="$errors->first('form.slug')"
                >
                    <input
                        id="event-slug"
                        type="text"
                        class="form-control mono"
                        maxlength="150"
                        placeholder="{{ __('ui.events.slug_placeholder') }}"
                        autocomplete="off"
                        spellcheck="false"
                        wire:model="form.slug"
                    >
                </x-ui.form-group>

                <x-ui.form-group
                    :label="__('ui.events.date')"
                    for="event-date"
                    required
                    :error="$errors->first('form.date')"
                >
                    <input
                        id="event-date"
                        type="date"
                        class="form-control mono"
                        wire:model="form.date"
                    >
                </x-ui.form-group>

                {{-- El estado solo se elige al editar: la creación nace con
                     el estado inicial del esquema (default 'live'). --}}
                @if ($editingUlid !== null)
                    <x-ui.form-group
                        :label="__('ui.events.status_field')"
                        for="event-status"
                        required
                        :error="$errors->first('form.status')"
                    >
                        <select id="event-status" class="form-control" wire:model="form.status">
                            @foreach ($statusOptions as $statusOption)
                                <option value="{{ $statusOption }}">{{ \App\Support\Ui\EventStatus::label($statusOption) }}</option>
                            @endforeach
                        </select>
                    </x-ui.form-group>
                @endif
            </div>

            <div class="flex flex-col gap-2">
                <div class="flex items-center justify-between gap-2">
                    <span class="form-label">{{ __('ui.events.races') }}</span>
                    <x-ui.button variant="outline" size="sm" icon="plus" type="button" wire:click="addRace">
                        {{ __('ui.events.race_add') }}
                    </x-ui.button>
                </div>
                <p class="form-help">{{ __('ui.events.races_help') }}</p>

                @forelse ($races as $index => $race)
                    <x-ui.form-group
                        for="race-name-{{ $index }}"
                        :error="$errors->first('races.'.$index.'.name')"
                    >
                        <div class="flex items-center gap-2">
                            <span class="mono text-muted" aria-hidden="true">{{ $index + 1 }}</span>
                            <input
                                id="race-name-{{ $index }}"
                                type="text"
                                class="form-control"
                                maxlength="50"
                                placeholder="{{ __('ui.events.race_placeholder') }}"
                                aria-label="{{ __('ui.events.race_name') }}"
                                autocomplete="off"
                                wire:model="races.{{ $index }}.name"
                            >
                            <x-ui.icon-button
                                icon="x"
                                :tip="__('ui.events.race_remove')"
                                wire:click="removeRace({{ $index }})"
                            />
                        </div>
                    </x-ui.form-group>
                @empty
                    <p class="text-sm text-muted">{{ __('ui.events.races_none') }}</p>
                @endforelse
            </div>
        </form>

        <x-slot:footer>
            <x-ui.button variant="ghost" icon="x" @click="eventModal = false">{{ __('ui.events.cancel') }}</x-ui.button>
            <x-ui.button variant="primary" icon="check" type="submit" form="event-form" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save">{{ $editingUlid === null ? __('ui.events.create') : __('ui.events.save') }}</span>
                <span wire:loading wire:target="save" class="spinner" aria-hidden="true"></span>
            </x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
