<?php

/*
| ISSUE-161 (TQ-028) — Directorio de personas: solo componentes del kit
| x-ui.* y clases del sistema; sin CSS/JS ad-hoc. PII (RNF-13): el
| documento sale ENMASCARADO (MaskDocument) y la fecha de nacimiento no
| sale — solo la edad calculada; el documento completo vive en el modal,
| que exige directory.write. Estados visibles siempre: esqueleto en carga,
| vacío con CTA, errores bajo el campo, toasts y confirmación en
| destructivas (spec/09-ui-estilo.md §6).
*/
?>

<div
    class="flex flex-col gap-4"
    x-data="{ personModal: false }"
    @open-person-modal.window="personModal = true"
    @close-person-modal.window="personModal = false"
>
    <x-ui.page-header
        :title="__('ui.directory.persons.title')"
        :subtitle="__('ui.directory.persons.subtitle')"
    >
        <x-slot:actions>
            @can('directory.write')
                <x-ui.button variant="primary" icon="plus" wire:click="startCreate">
                    {{ __('ui.directory.persons.new') }}
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
                    placeholder="{{ __('ui.directory.persons.search_placeholder') }}"
                    aria-label="{{ __('ui.directory.persons.search_aria') }}"
                    wire:model.live.debounce.400ms="q"
                >
            </label>

            @if (trim($q) !== '')
                <span class="chip" wire:key="result-count">
                    <x-ui.icon name="list-filter" />
                    {{ $persons->total() }} {{ trans_choice('ui.directory.persons.results', $persons->total()) }}
                </span>
                <x-ui.button variant="ghost" size="sm" icon="x" wire:click="$set('q', '')">
                    {{ __('ui.directory.persons.clear') }}
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
            @if ($persons->isEmpty())
                <x-ui.empty-state
                    icon="users"
                    :title="trim($q) !== '' ? __('ui.directory.persons.empty_search_title') : __('ui.directory.persons.empty_title')"
                    :text="trim($q) !== '' ? __('ui.directory.persons.empty_search_text') : __('ui.directory.persons.empty_text')"
                >
                    @if (trim($q) === '')
                        @can('directory.write')
                            <x-ui.button variant="primary" icon="plus" wire:click="startCreate">
                                {{ __('ui.directory.persons.new') }}
                            </x-ui.button>
                        @endcan
                    @endif
                </x-ui.empty-state>
            @else
                <x-ui.table :headers="$headers">
                    @foreach ($persons as $person)
                        <tr wire:key="person-{{ $person->ulid }}">
                            <td class="mono">{{ \App\Support\MaskDocument::mask($person->document) }}</td>
                            <td class="font-medium" style="color: var(--tc-text)">{{ $person->first_name }} {{ $person->last_name }}</td>
                            <td><span class="chip">{{ $person->gender }}</span></td>
                            <td class="td-right mono">{{ $person->birthdate->age }}</td>
                            <td class="td-right">
                                <span class="inline-flex items-center justify-end gap-1">
                                    @can('directory.write')
                                        <x-ui.icon-button
                                            icon="pencil"
                                            :tip="__('ui.directory.persons.edit')"
                                            wire:click="startEdit('{{ $person->ulid }}')"
                                        />
                                        <x-ui.icon-button
                                            icon="trash-2"
                                            :tip="__('ui.directory.persons.delete')"
                                            color="danger"
                                            wire:click="deletePerson('{{ $person->ulid }}')"
                                            wire:confirm="{{ __('ui.directory.persons.delete_confirm', ['name' => $person->first_name.' '.$person->last_name]) }}"
                                        />
                                    @endcan
                                </span>
                            </td>
                        </tr>
                    @endforeach

                    <x-slot:footer>
                        {{ $persons->links('components.ui.pagination') }}
                    </x-slot:footer>
                </x-ui.table>
            @endif
        </div>
    </x-ui.card>

    <x-ui.modal
        show="personModal"
        :title="$editingUlid === null ? __('ui.directory.persons.new') : __('ui.directory.persons.modal_edit')"
        :subtitle="__('ui.directory.persons.modal_subtitle')"
        size="md"
    >
        <form wire:submit="save" id="person-form" class="flex flex-col gap-4">
            <x-ui.form-group
                :label="__('ui.directory.persons.document')"
                for="person-document"
                required
                :help="__('ui.directory.persons.document_help')"
                :error="$errors->first('form.document')"
            >
                <input
                    id="person-document"
                    type="text"
                    class="form-control mono"
                    maxlength="30"
                    placeholder="{{ __('ui.directory.persons.document_placeholder') }}"
                    autocomplete="off"
                    wire:model="form.document"
                >
            </x-ui.form-group>

            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.form-group
                    :label="__('ui.directory.persons.first_name')"
                    for="person-first-name"
                    required
                    :error="$errors->first('form.first_name')"
                >
                    <input
                        id="person-first-name"
                        type="text"
                        class="form-control"
                        maxlength="100"
                        autocomplete="off"
                        wire:model="form.first_name"
                    >
                </x-ui.form-group>

                <x-ui.form-group
                    :label="__('ui.directory.persons.last_name')"
                    for="person-last-name"
                    required
                    :error="$errors->first('form.last_name')"
                >
                    <input
                        id="person-last-name"
                        type="text"
                        class="form-control"
                        maxlength="100"
                        autocomplete="off"
                        wire:model="form.last_name"
                    >
                </x-ui.form-group>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.form-group
                    :label="__('ui.directory.persons.birthdate')"
                    for="person-birthdate"
                    required
                    :help="__('ui.directory.persons.birthdate_help')"
                    :error="$errors->first('form.birthdate')"
                >
                    <input
                        id="person-birthdate"
                        type="date"
                        class="form-control"
                        wire:model="form.birthdate"
                    >
                </x-ui.form-group>

                <x-ui.form-group
                    :label="__('ui.directory.persons.gender')"
                    for="person-gender"
                    required
                    :error="$errors->first('form.gender')"
                >
                    <select id="person-gender" class="form-control" wire:model="form.gender">
                        <option value="" disabled>{{ __('ui.directory.persons.gender_placeholder') }}</option>
                        @foreach ($genders as $gender)
                            <option value="{{ $gender }}">{{ $gender }}</option>
                        @endforeach
                    </select>
                </x-ui.form-group>
            </div>
        </form>

        <x-slot:footer>
            <x-ui.button variant="ghost" icon="x" @click="personModal = false">{{ __('ui.directory.persons.cancel') }}</x-ui.button>
            <x-ui.button variant="primary" icon="check" type="submit" form="person-form" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save">{{ $editingUlid === null ? __('ui.directory.persons.create') : __('ui.directory.persons.save') }}</span>
                <span wire:loading wire:target="save" class="spinner" aria-hidden="true"></span>
            </x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
