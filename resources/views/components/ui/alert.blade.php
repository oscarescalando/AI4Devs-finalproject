{{-- <x-ui.alert type="danger" title="No se pudo guardar" dismissible>Revisa los campos marcados.</x-ui.alert>
     Mensaje en línea del kit (spec/09-ui-estilo.md §5): type success|danger|warning|info,
     título opcional y cierre con Alpine cuando `dismissible`. --}}
@props(['type' => 'info', 'title' => null, 'dismissible' => false])
@php
    $types = ['success' => 'check-circle-2', 'danger' => 'x-circle', 'warning' => 'alert-triangle', 'info' => 'info'];
    $kind = array_key_exists($type, $types) ? $type : 'info';
    $icon = $types[$kind];
@endphp
<div
    {{ $attributes->merge(['class' => 'alert alert-'.$kind]) }}
    x-data="{ visible: true }"
    x-show="visible"
    role="{{ in_array($kind, ['danger', 'warning'], true) ? 'alert' : 'status' }}"
>
    <x-ui.icon :name="$icon" class="mt-0.5" />
    <div class="min-w-0 flex-1">
        @if ($title !== null)<p class="font-semibold">{{ $title }}</p>@endif
        @if ($slot->isNotEmpty())<div>{{ $slot }}</div>@endif
    </div>
    @if ($dismissible)
        <button type="button" class="alert-close" @click="visible = false" aria-label="{{ __('ui.kit.close') }}">
            <x-ui.icon name="x" />
        </button>
    @endif
</div>
