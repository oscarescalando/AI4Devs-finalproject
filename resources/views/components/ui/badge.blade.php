{{-- <x-ui.badge variant="success" dot>Activo</x-ui.badge>
     <x-ui.badge variant="info" icon="copy">Duplicado</x-ui.badge>
     Estado/etiqueta del kit (spec/09-ui-estilo.md §5): variantes primary|success|danger|warning|info|neutral,
     punto opcional (`dot`) e icono Lucide opcional. --}}
@props(['variant' => 'neutral', 'dot' => false, 'icon' => null])
@php
    $variants = ['primary', 'success', 'danger', 'warning', 'info', 'neutral'];
    $classes = 'badge badge-'.(in_array($variant, $variants, true) ? $variant : 'neutral').($dot ? ' badge-dot' : '');
@endphp
<span {{ $attributes->merge(['class' => $classes]) }}>
    @if ($icon !== null)<x-ui.icon :name="$icon" />@endif
    {{ $slot }}
</span>
