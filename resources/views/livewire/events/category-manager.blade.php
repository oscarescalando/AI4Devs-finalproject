<?php

/*
| ISSUE-160 fase 2 (TQ-026) — Editor de categorías: solo componentes del kit
| x-ui.* y clases del sistema; sin CSS/JS ad-hoc. Estados visibles siempre:
| vacío con CTA, errores bajo el campo, toast en errores de negocio (borrado
| bloqueado por inscripciones) y confirmación en la destructiva
| (spec/09-ui-estilo.md §6).
*/

?>

<div
    class="flex flex-col gap-4"
    x-data="{ categoryModal: false }"
    @open-category-modal.window="categoryModal = true"
    @close-category-modal.window="categoryModal = false"
>
    <x-ui.page-header :title="__('ui.events.categories_title')" :subtitle="$eventName">
        <x-slot:actions>
            <x-ui.button variant="primary" icon="plus" wire:click="startCreate">
                {{ __('ui.events.cat_new') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card padding="none">
        @if (count($categories) === 0)
            <x-ui.empty-state
                icon="tags"
                :title="__('ui.events.cat_empty_title')"
                :text="__('ui.events.cat_empty_text')"
            >
                <x-ui.button variant="primary" icon="plus" wire:click="startCreate">
                    {{ __('ui.events.cat_new') }}
                </x-ui.button>
            </x-ui.empty-state>
        @else
            <x-ui.table :headers="$headers">
                @foreach ($categories as $category)
                    <tr wire:key="category-{{ $category['ulid'] }}">
                        <td class="font-medium text-strong">{{ $category['name'] }}</td>
                        <td>
                            <x-ui.badge :variant="$category['for_teams'] ? 'info' : 'neutral'">
                                {{ $category['for_teams'] ? __('ui.events.cat_type_teams') : __('ui.events.cat_type_individual') }}
                            </x-ui.badge>
                        </td>
                        <td class="td-right mono">{{ $category['range'] }}</td>
                        <td class="td-right mono">{{ $category['entries_total'] }}</td>
                        <td class="td-right">
                            <span class="inline-flex items-center justify-end gap-1">
                                <x-ui.icon-button
                                    icon="pencil"
                                    :tip="__('ui.events.cat_edit')"
                                    wire:click="startEdit('{{ $category['ulid'] }}')"
                                />
                                <x-ui.icon-button
                                    icon="trash-2"
                                    :tip="__('ui.events.cat_delete')"
                                    color="danger"
                                    wire:click="deleteCategory('{{ $category['ulid'] }}')"
                                    wire:confirm="{{ __('ui.events.cat_delete_confirm', ['name' => $category['name']]) }}"
                                />
                            </span>
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>
        @endif
    </x-ui.card>

    <x-ui.modal
        show="categoryModal"
        :title="$editingUlid === null ? __('ui.events.cat_new') : __('ui.events.cat_edit')"
        :subtitle="__('ui.events.modal_subtitle')"
        size="md"
    >
        <form wire:submit="save" id="category-form" class="flex flex-col gap-4">
            <x-ui.form-group
                :label="__('ui.events.name')"
                for="category-name"
                required
                :help="__('ui.events.cat_name_help')"
                :error="$errors->first('form.name')"
            >
                <input
                    id="category-name"
                    type="text"
                    class="form-control"
                    maxlength="50"
                    placeholder="{{ __('ui.events.cat_name_placeholder') }}"
                    autocomplete="off"
                    wire:model="form.name"
                >
            </x-ui.form-group>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-ui.form-group
                    :label="__('ui.events.cat_min_age')"
                    for="category-min-age"
                    :help="__('ui.events.cat_ages_help')"
                    :error="$errors->first('form.min_age')"
                >
                    <input
                        id="category-min-age"
                        type="number"
                        class="form-control mono"
                        min="1"
                        max="120"
                        inputmode="numeric"
                        wire:model="form.min_age"
                    >
                </x-ui.form-group>

                <x-ui.form-group
                    :label="__('ui.events.cat_max_age')"
                    for="category-max-age"
                    :error="$errors->first('form.max_age')"
                >
                    <input
                        id="category-max-age"
                        type="number"
                        class="form-control mono"
                        min="1"
                        max="120"
                        inputmode="numeric"
                        wire:model="form.max_age"
                    >
                </x-ui.form-group>
            </div>

            <div class="form-group" data-field="category-teams">
                <label class="flex items-center gap-2" for="category-teams">
                    <input id="category-teams" type="checkbox" class="form-check" wire:model="form.for_teams">
                    <span>{{ __('ui.events.cat_for_teams') }}</span>
                </label>
                <p class="form-help" id="category-teams-help">{{ __('ui.events.cat_for_teams_help') }}</p>
            </div>
        </form>

        <x-slot:footer>
            <x-ui.button variant="ghost" icon="x" @click="categoryModal = false">{{ __('ui.events.cancel') }}</x-ui.button>
            <x-ui.button variant="primary" icon="check" type="submit" form="category-form" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save">{{ $editingUlid === null ? __('ui.events.create') : __('ui.events.save') }}</span>
                <span wire:loading wire:target="save" class="spinner" aria-hidden="true"></span>
            </x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
