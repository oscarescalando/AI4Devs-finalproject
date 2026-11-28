<?php

/*
| ISSUE-162 (TQ-030) — Gestor de inscripciones: solo componentes del kit
| x-ui.* y clases del sistema; sin CSS/JS ad-hoc. Dorsal mono tabular (§1.3),
| menores con nombre público (INV-06) y documento ausente de la pantalla; el
| picker enmascara el documento (RNF-13). Estados visibles siempre: esqueleto
| en carga, vacíos (con y sin filtros), errores bajo el campo, toasts y
| confirmación en la baja lógica (spec/09-ui-estilo.md §6).
*/

?>

<div
    class="flex flex-col gap-4"
    x-data="{ entryModal: false }"
    @open-entry-modal.window="entryModal = true"
    @close-entry-modal.window="entryModal = false"
>
    <x-ui.page-header :title="__('ui.events.entries_title')" :subtitle="$eventName">
        <x-slot:actions>
            @can('entries.manage')
                <x-ui.button variant="primary" icon="user-plus" wire:click="startCreate">
                    {{ __('ui.events.entry_new') }}
                </x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    @if (count($races) === 0)
        {{-- Sin carreras no hay alta posible: el evento se configura primero. --}}
        <x-ui.card padding="none">
            <x-ui.empty-state
                icon="clipboard-list"
                :title="__('ui.events.entry_no_setup_title')"
                :text="__('ui.events.entry_no_setup_text')"
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
    @endif

    <x-ui.card padding="none">
        <div class="toolbar">
            <label class="search">
                <x-ui.icon name="search" />
                <input
                    type="search"
                    class="form-control"
                    placeholder="{{ __('ui.events.entry_search_placeholder') }}"
                    aria-label="{{ __('ui.events.entry_search_aria') }}"
                    wire:model.live.debounce.400ms="q"
                >
            </label>

            <select
                class="form-control"
                aria-label="{{ __('ui.events.entry_filter_race') }}"
                wire:model.live="race"
            >
                <option value="">{{ __('ui.events.entry_filter_all_races') }}</option>
                @foreach ($races as $raceOption)
                    <option value="{{ $raceOption['ulid'] }}">{{ $raceOption['name'] }}</option>
                @endforeach
            </select>

            <select
                class="form-control"
                aria-label="{{ __('ui.events.entry_filter_category') }}"
                wire:model.live="category"
            >
                <option value="">{{ __('ui.events.entry_filter_all_categories') }}</option>
                @foreach ($categories as $categoryOption)
                    <option value="{{ $categoryOption['ulid'] }}">{{ $categoryOption['name'] }}</option>
                @endforeach
            </select>

            <select
                class="form-control"
                aria-label="{{ __('ui.events.entry_filter_status') }}"
                wire:model.live="status"
            >
                <option value="">{{ __('ui.events.entry_filter_all_statuses') }}</option>
                @foreach ($statuses as $statusOption)
                    <option value="{{ $statusOption }}">{{ __('ui.events.entry_status_'.$statusOption) }}</option>
                @endforeach
            </select>

            @if (trim($q) !== '' || $race !== '' || $category !== '' || $status !== '')
                <span class="chip" wire:key="entry-count">
                    <x-ui.icon name="list-filter" />
                    {{ $entries->total() }} {{ trans_choice('ui.events.entry_results', $entries->total()) }}
                </span>
                <x-ui.button variant="ghost" size="sm" icon="x" wire:click="clearFilters">
                    {{ __('ui.events.entry_filter_clear') }}
                </x-ui.button>
            @endif
        </div>

        {{-- Carga: esqueleto acotado a la tabla mientras consulta (P4/G13) --}}
        <div wire:loading.delay wire:target="gotoPage,previousPage,nextPage,q,race,category,status" class="flex flex-col gap-2 p-4">
            @foreach (range(1, 6) as $row)
                <div class="skeleton h-9" wire:key="skeleton-{{ $row }}"></div>
            @endforeach
        </div>

        <div wire:loading.remove.delay wire:target="gotoPage,previousPage,nextPage,q,race,category,status">
            @if ($entries->isEmpty())
                @php($filtered = trim($q) !== '' || $race !== '' || $category !== '' || $status !== '')
                <x-ui.empty-state
                    icon="clipboard-list"
                    :title="$filtered ? __('ui.events.entry_empty_filtered_title') : __('ui.events.entries_none_title')"
                    :text="$filtered ? __('ui.events.entry_empty_filtered_text') : __('ui.events.entries_none_text')"
                >
                    @if (! $filtered)
                        @can('entries.manage')
                            <x-ui.button variant="primary" icon="user-plus" wire:click="startCreate">
                                {{ __('ui.events.entry_new') }}
                            </x-ui.button>
                        @endcan
                    @endif
                </x-ui.empty-state>
            @else
                <x-ui.table :headers="$headers">
                    @foreach ($entries as $entry)
                        <tr wire:key="entry-{{ $entry->ulid }}">
                            <td class="td-right mono">{{ $entry->bib }}</td>
                            <td class="font-medium text-strong">{{ $entry->person?->publicName() ?? $entry->team?->name ?? '—' }}</td>
                            <td>{{ $entry->category?->name ?? '—' }}</td>
                            <td>{{ $entry->race?->name ?? '—' }}</td>
                            <td>
                                @if ($entry->status === 'cancelled')
                                    <x-ui.badge variant="danger">{{ __('ui.events.entry_status_cancelled') }}</x-ui.badge>
                                @else
                                    <x-ui.badge variant="success" dot>{{ __('ui.events.entry_status_registered') }}</x-ui.badge>
                                @endif
                            </td>
                            <td class="td-right">
                                @can('entries.manage')
                                    <span class="inline-flex items-center justify-end gap-1">
                                        @if ($entry->status === 'cancelled')
                                            <x-ui.icon-button
                                                icon="rotate-ccw"
                                                :tip="__('ui.events.entry_reactivate_tip')"
                                                wire:click="reactivateEntry('{{ $entry->ulid }}')"
                                            />
                                        @else
                                            <x-ui.icon-button
                                                icon="ban"
                                                :tip="__('ui.events.entry_cancel_tip')"
                                                color="danger"
                                                wire:click="cancelEntry('{{ $entry->ulid }}')"
                                                wire:confirm="{{ __('ui.events.entry_cancel_confirm', ['name' => $entry->person?->publicName() ?? $entry->team?->name ?? '—', 'bib' => $entry->bib]) }}"
                                            />
                                        @endif
                                    </span>
                                @endcan
                            </td>
                        </tr>
                    @endforeach

                    <x-slot:footer>
                        {{ $entries->links('components.ui.pagination') }}
                    </x-slot:footer>
                </x-ui.table>
            @endif
        </div>
    </x-ui.card>

    {{-- Alta individual (ISSUE-162): picker de personas, categoría automática
         previsualizada y dorsal sugerido editable. --}}
    <x-ui.modal
        show="entryModal"
        :title="__('ui.events.entry_new')"
        :subtitle="__('ui.events.entry_modal_subtitle')"
        size="lg"
    >
        <form wire:submit="save" id="entry-form" class="flex flex-col gap-4">
            <x-ui.form-group
                :label="__('ui.events.entry_athlete')"
                for="entry-person"
                required
                :help="$selected === null ? __('ui.events.entry_athlete_help') : null"
                :error="$errors->first('form.person_ulid')"
            >
                @if ($selected === null)
                    <input
                        id="entry-person"
                        type="search"
                        class="form-control"
                        placeholder="{{ __('ui.events.entry_athlete_placeholder') }}"
                        aria-label="{{ __('ui.events.entry_search_aria') }}"
                        wire:model.live.debounce.400ms="personQuery"
                    >

                    @if ($personResults !== null)
                        <div wire:loading.delay wire:target="personQuery" class="flex flex-col gap-2">
                            <div class="skeleton h-10" wire:key="picker-loading"></div>
                        </div>

                        <div wire:loading.remove.delay wire:target="personQuery" class="flex flex-col gap-2">
                            @if ($personResults->isEmpty())
                                <x-ui.empty-state
                                    icon="users"
                                    :title="__('ui.events.entry_picker_none_title')"
                                    :text="__('ui.events.entry_picker_none_text')"
                                />
                            @else
                                <ul class="flex flex-col gap-2">
                                    @foreach ($personResults as $person)
                                        <li wire:key="picker-{{ $person->ulid }}">
                                            <button
                                                type="button"
                                                class="flex w-full flex-wrap items-center gap-2 rounded-[12px] border border-neutral-200 p-3 text-left hover:bg-neutral-50 dark:border-neutral-800 dark:hover:bg-neutral-900"
                                                wire:click="selectPerson('{{ $person->ulid }}')"
                                            >
                                                <span class="font-medium">{{ $person->publicName() }}</span>
                                                <span class="mono text-xs text-muted">{{ \App\Support\MaskDocument::mask($person->document) }}</span>
                                                <span class="chip">{{ $person->gender }}</span>
                                                <span class="ml-auto mono text-xs text-muted">{{ $person->birthdate->age }}</span>
                                            </button>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @endif
                @else
                    <div class="flex flex-col gap-3 rounded-[12px] border border-neutral-200 p-3 dark:border-neutral-800">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-medium text-strong">{{ $selected['name'] }}</span>
                            <span class="mono text-xs text-muted">{{ $selected['document'] }}</span>
                            <span class="chip">{{ $selected['gender'] }}</span>
                            <span class="ml-auto">
                                <x-ui.button variant="ghost" size="sm" icon="refresh-cw" wire:click="changePerson">
                                    {{ __('ui.events.entry_picker_change') }}
                                </x-ui.button>
                            </span>
                        </div>

                        {{-- Previsualización en vivo de la categoría que se asignará:
                             la MISMA regla que aplicará CreateEntry al guardar. --}}
                        <div class="flex flex-wrap items-center gap-2" data-field="entry-category-preview">
                            <span class="text-xs text-muted">{{ __('ui.events.entry_preview_age', ['age' => $selected['age']]) }}</span>
                            @if ($selected['category'] !== null)
                                <x-ui.badge variant="info" icon="tag">{{ $selected['category'] }}</x-ui.badge>
                            @else
                                <x-ui.badge variant="danger" icon="triangle-alert">
                                    {{ __('ui.events.entry_preview_none', ['age' => $selected['age'], 'gender' => $selected['gender']]) }}
                                </x-ui.badge>
                            @endif
                        </div>
                    </div>
                @endif
            </x-ui.form-group>

            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.form-group
                    :label="__('ui.events.entry_race')"
                    for="entry-race"
                    required
                    :error="$errors->first('form.race_ulid')"
                >
                    <select id="entry-race" class="form-control" wire:model.live="form.race_ulid">
                        <option value="" disabled>{{ __('ui.events.entry_race_placeholder') }}</option>
                        @foreach ($races as $raceOption)
                            <option value="{{ $raceOption['ulid'] }}">{{ $raceOption['name'] }}</option>
                        @endforeach
                    </select>
                </x-ui.form-group>

                <x-ui.form-group
                    :label="__('ui.events.entry_wave')"
                    for="entry-wave"
                    required
                    :help="__('ui.events.entry_wave_help')"
                    :error="$errors->first('form.wave_ulid')"
                >
                    @php($currentRace = collect($races)->first(fn ($raceOption) => $raceOption['ulid'] === $form['race_ulid']))
                    <select id="entry-wave" class="form-control" wire:model="form.wave_ulid">
                        <option value="" disabled>{{ __('ui.events.entry_wave_placeholder') }}</option>
                        @foreach (($currentRace['waves'] ?? []) as $waveOption)
                            <option value="{{ $waveOption['ulid'] }}">{{ $waveOption['name'] }}</option>
                        @endforeach
                    </select>
                </x-ui.form-group>
            </div>

            <x-ui.form-group
                :label="__('ui.events.entry_bib')"
                for="entry-bib"
                :help="__('ui.events.entry_bib_help')"
                :error="$errors->first('form.bib')"
            >
                <input
                    id="entry-bib"
                    type="number"
                    class="form-control mono"
                    min="1"
                    max="99999"
                    inputmode="numeric"
                    wire:model="form.bib"
                >
            </x-ui.form-group>
        </form>

        <x-slot:footer>
            <x-ui.button variant="ghost" icon="x" @click="entryModal = false">{{ __('ui.events.cancel') }}</x-ui.button>
            <x-ui.button variant="primary" icon="check" type="submit" form="entry-form" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save">{{ __('ui.events.create') }}</span>
                <span wire:loading wire:target="save" class="spinner" aria-hidden="true"></span>
            </x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
