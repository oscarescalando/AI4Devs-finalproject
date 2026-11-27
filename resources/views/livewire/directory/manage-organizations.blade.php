<?php

/*
| ISSUE-110 (TQ-003) — CRUD demo Organizaciones: solo componentes del kit
| x-ui.* y clases del sistema; sin CSS/JS ad-hoc. Estados visibles siempre:
| esqueleto en carga, vacío con CTA, errores bajo el campo, toasts y
| confirmación en destructivas (spec/09-ui-estilo.md §6).
*/
?>

<div
    class="flex flex-col gap-4"
    x-data="{ orgModal: false }"
    @open-org-modal.window="orgModal = true"
    @close-org-modal.window="orgModal = false"
>
    <x-ui.page-header
        title="Organizaciones"
        subtitle="Clubes y entidades del directorio (CRUD de demostración del kit)."
    >
        <x-slot:actions>
            <x-ui.button variant="primary" icon="plus" wire:click="startCreate">
                Nueva organización
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card padding="none">
        <div class="toolbar">
            <label class="search">
                <x-ui.icon name="search" />
                <input
                    type="search"
                    class="form-control"
                    placeholder="Buscar por nombre…"
                    aria-label="Buscar organización por nombre"
                    wire:model.live.debounce.400ms="q"
                >
            </label>

            @if (trim($q) !== '')
                <span class="chip" wire:key="result-count">
                    <x-ui.icon name="list-filter" />
                    {{ $organizations->total() }} resultado{{ $organizations->total() === 1 ? '' : 's' }}
                </span>
                <x-ui.button variant="ghost" size="sm" icon="x" wire:click="$set('q', '')">
                    Limpiar
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
            @if ($organizations->isEmpty())
                <x-ui.empty-state
                    icon="building-2"
                    title="{{ trim($q) !== '' ? 'Sin resultados' : 'Aún no hay organizaciones' }}"
                    text="{{ trim($q) !== '' ? 'Ninguna organización coincide con la búsqueda.' : 'Crea la primera para empezar a inscribir equipos.' }}"
                >
                    @if (trim($q) === '')
                        <x-ui.button variant="primary" icon="plus" wire:click="startCreate">
                            Nueva organización
                        </x-ui.button>
                    @endif
                </x-ui.empty-state>
            @else
                <x-ui.table :headers="$headers">
                    @foreach ($organizations as $organization)
                        <tr wire:key="org-{{ $organization->ulid }}">
                            <td class="font-medium" style="color: var(--tc-text)">{{ $organization->name }}</td>
                            <td><span class="chip">{{ $organization->region?->name ?? '—' }}</span></td>
                            <td class="td-right mono">{{ $organization->created_at->timezone('America/Caracas')->format('d/m/Y') }}</td>
                            <td class="td-right">
                                <span class="inline-flex items-center justify-end gap-1">
                                    <x-ui.icon-button icon="eye" tip="Próximamente" disabled />
                                    <x-ui.icon-button
                                        icon="pencil"
                                        tip="Editar"
                                        wire:click="startEdit('{{ $organization->ulid }}')"
                                    />
                                    <x-ui.icon-button
                                        icon="trash-2"
                                        tip="Eliminar"
                                        color="danger"
                                        wire:click="deleteOrganization('{{ $organization->ulid }}')"
                                        wire:confirm="¿Eliminar «{{ $organization->name }}»? Esta acción no se puede deshacer."
                                    />
                                </span>
                            </td>
                        </tr>
                    @endforeach

                    <x-slot:footer>
                        {{ $organizations->links('components.ui.pagination') }}
                    </x-slot:footer>
                </x-ui.table>
            @endif
        </div>
    </x-ui.card>

    <x-ui.modal
        show="orgModal"
        title="{{ $editingUlid === null ? 'Nueva organización' : 'Editar organización' }}"
        subtitle="Los datos se validan en el servidor."
        size="md"
    >
        <form wire:submit="save" id="org-form" class="flex flex-col gap-4">
            <x-ui.form-group
                label="Nombre"
                for="org-name"
                required
                help="Hasta 150 caracteres."
                :error="$errors->first('form.name')"
            >
                <input
                    id="org-name"
                    type="text"
                    class="form-control"
                    maxlength="150"
                    placeholder="Nombre de la organización"
                    autocomplete="off"
                    wire:model="form.name"
                >
            </x-ui.form-group>

            <x-ui.form-group
                label="Región"
                for="org-region"
                required
                :error="$errors->first('form.region_id')"
            >
                <select id="org-region" class="form-control" wire:model="form.region_id">
                    <option value="" disabled>Selecciona una región…</option>
                    @foreach ($regions as $region)
                        <option value="{{ $region['id'] }}">{{ $region['name'] }}</option>
                    @endforeach
                </select>
            </x-ui.form-group>
        </form>

        <x-slot:footer>
            <x-ui.button variant="ghost" icon="x" @click="orgModal = false">Cancelar</x-ui.button>
            <x-ui.button variant="primary" icon="check" type="submit" form="org-form" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save">{{ $editingUlid === null ? 'Crear' : 'Guardar cambios' }}</span>
                <span wire:loading wire:target="save" class="spinner" aria-hidden="true"></span>
            </x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
