<?php

/*
| ISSUE-161 (TQ-028) — Directorio de equipos: solo componentes del kit
| x-ui.* y clases del sistema; sin CSS/JS ad-hoc. La organización viaja
| siempre por su ULID público (ADR-015): en el filtro ?org= y en el
| selector del modal. Estados visibles siempre: esqueleto en carga, vacío
| con CTA, errores bajo el campo, toasts y confirmación en destructivas
| (spec/09-ui-estilo.md §6).
*/
?>

<div
    class="flex flex-col gap-4"
    x-data="{ teamModal: false }"
    @open-team-modal.window="teamModal = true"
    @close-team-modal.window="teamModal = false"
>
    <x-ui.page-header
        :title="__('ui.directory.teams.title')"
        :subtitle="__('ui.directory.teams.subtitle')"
    >
        <x-slot:actions>
            @can('directory.write')
                <x-ui.button variant="primary" icon="plus" wire:click="startCreate">
                    {{ __('ui.directory.teams.new') }}
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
                    placeholder="{{ __('ui.directory.teams.search_placeholder') }}"
                    aria-label="{{ __('ui.directory.teams.search_aria') }}"
                    wire:model.live.debounce.400ms="q"
                >
            </label>

            <label class="sr-only" for="team-org-filter">{{ __('ui.directory.teams.filter_aria') }}</label>
            <select
                id="team-org-filter"
                class="form-control max-w-64"
                wire:model.live="org"
            >
                <option value="">{{ __('ui.directory.teams.filter_all') }}</option>
                @foreach ($organizations as $organization)
                    <option value="{{ $organization['ulid'] }}">{{ $organization['name'] }}</option>
                @endforeach
            </select>

            @if (trim($q) !== '' || trim($org) !== '')
                <span class="chip" wire:key="result-count">
                    <x-ui.icon name="list-filter" />
                    {{ $teams->total() }} {{ trans_choice('ui.directory.teams.results', $teams->total()) }}
                </span>
                <x-ui.button variant="ghost" size="sm" icon="x" wire:click="clearFilters">
                    {{ __('ui.directory.teams.clear') }}
                </x-ui.button>
            @endif
        </div>

        {{-- Carga: esqueleto acotado a la tabla mientras consulta (P4/G13) --}}
        <div wire:loading.delay wire:target="gotoPage,previousPage,nextPage,q,org" class="flex flex-col gap-2 p-4">
            @foreach (range(1, 6) as $row)
                <div class="skeleton h-9" wire:key="skeleton-{{ $row }}"></div>
            @endforeach
        </div>

        <div wire:loading.remove.delay wire:target="gotoPage,previousPage,nextPage,q,org">
            @if ($teams->isEmpty())
                <x-ui.empty-state
                    icon="users-round"
                    :title="trim($q) !== '' || trim($org) !== '' ? __('ui.directory.teams.empty_search_title') : __('ui.directory.teams.empty_title')"
                    :text="trim($q) !== '' || trim($org) !== '' ? __('ui.directory.teams.empty_search_text') : __('ui.directory.teams.empty_text')"
                >
                    @if (trim($q) === '' && trim($org) === '')
                        @can('directory.write')
                            <x-ui.button variant="primary" icon="plus" wire:click="startCreate">
                                {{ __('ui.directory.teams.new') }}
                            </x-ui.button>
                        @endcan
                    @endif
                </x-ui.empty-state>
            @else
                <x-ui.table :headers="$headers">
                    @foreach ($teams as $team)
                        <tr wire:key="team-{{ $team->ulid }}">
                            <td class="font-medium" style="color: var(--tc-text)">{{ $team->name }}</td>
                            <td><span class="chip">{{ $team->organization?->name ?? '—' }}</span></td>
                            <td class="td-right">
                                <span class="inline-flex items-center justify-end gap-1">
                                    @can('directory.write')
                                        <x-ui.icon-button
                                            icon="pencil"
                                            :tip="__('ui.directory.teams.edit')"
                                            wire:click="startEdit('{{ $team->ulid }}')"
                                        />
                                        <x-ui.icon-button
                                            icon="trash-2"
                                            :tip="__('ui.directory.teams.delete')"
                                            color="danger"
                                            wire:click="deleteTeam('{{ $team->ulid }}')"
                                            wire:confirm="{{ __('ui.directory.teams.delete_confirm', ['name' => $team->name]) }}"
                                        />
                                    @endcan
                                </span>
                            </td>
                        </tr>
                    @endforeach

                    <x-slot:footer>
                        {{ $teams->links('components.ui.pagination') }}
                    </x-slot:footer>
                </x-ui.table>
            @endif
        </div>
    </x-ui.card>

    <x-ui.modal
        show="teamModal"
        :title="$editingUlid === null ? __('ui.directory.teams.new') : __('ui.directory.teams.modal_edit')"
        :subtitle="__('ui.directory.teams.modal_subtitle')"
        size="md"
    >
        <form wire:submit="save" id="team-form" class="flex flex-col gap-4">
            <x-ui.form-group
                :label="__('ui.directory.teams.name')"
                for="team-name"
                required
                :help="__('ui.directory.teams.name_help')"
                :error="$errors->first('form.name')"
            >
                <input
                    id="team-name"
                    type="text"
                    class="form-control"
                    maxlength="150"
                    placeholder="{{ __('ui.directory.teams.name_placeholder') }}"
                    autocomplete="off"
                    wire:model="form.name"
                >
            </x-ui.form-group>

            <x-ui.form-group
                :label="__('ui.directory.teams.organization')"
                for="team-organization"
                required
                :error="$errors->first('form.organization_ulid')"
            >
                <select id="team-organization" class="form-control" wire:model="form.organization_ulid">
                    <option value="" disabled>{{ __('ui.directory.teams.organization_placeholder') }}</option>
                    @foreach ($organizations as $organization)
                        <option value="{{ $organization['ulid'] }}">{{ $organization['name'] }}</option>
                    @endforeach
                </select>
            </x-ui.form-group>
        </form>

        <x-slot:footer>
            <x-ui.button variant="ghost" icon="x" @click="teamModal = false">{{ __('ui.directory.teams.cancel') }}</x-ui.button>
            <x-ui.button variant="primary" icon="check" type="submit" form="team-form" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save">{{ $editingUlid === null ? __('ui.directory.teams.create') : __('ui.directory.teams.save') }}</span>
                <span wire:loading wire:target="save" class="spinner" aria-hidden="true"></span>
            </x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
