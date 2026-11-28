{{-- <x-ui.button variant="primary" size="sm" icon="plus">Crear</x-ui.button>
     <x-ui.button variant="danger" href="#", icon="trash-2">Eliminar</x-ui.button>
     Botón del kit (spec/09-ui-estilo.md §5): variante primary|secondary|outline|soft|ghost|danger|danger-soft,
     tamaño sm|md, icono Lucide opcional; con `href` renderiza <a>. --}}
@props([
    'variant' => 'secondary',
    'size' => 'md',
    'icon' => null,
    'href' => null,
])
@php
    $variants = ['primary', 'secondary', 'outline', 'soft', 'ghost', 'danger', 'danger-soft'];
    $sizes = ['sm' => 'btn-sm', 'md' => ''];
    $classes = trim(
        'btn btn-'.(in_array($variant, $variants, true) ? $variant : 'secondary')
        .' '.($sizes[$size] ?? ''),
    );
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<x-ui.icon :name="$icon" />@endif
        <span>{{ $slot }}</span>
    </a>
@else
    <button {{ $attributes->merge(['class' => $classes, 'type' => 'button']) }}>
        @if ($icon)<x-ui.icon :name="$icon" />@endif
        <span>{{ $slot }}</span>
    </button>
@endif
