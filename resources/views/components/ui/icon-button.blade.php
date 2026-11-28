{{-- <x-ui.icon-button icon="pencil" tip="Editar" />
     <x-ui.icon-button icon="trash-2" tip="Eliminar" color="danger" wire:confirm="¿Seguro?" />
     Acción de tabla por icono con tooltip (spec/09-ui-estilo.md §7): el icono es OBLIGATORIO
     y el tip accesible (title + aria-label). Con `href` renderiza <a>. --}}
@props(['icon' => null, 'tip' => null, 'color' => null, 'href' => null])
@if ($icon === null)
    @php
        throw new InvalidArgumentException('x-ui.icon-button exige la prop $icon (nombre Lucide).');
    @endphp
@endif
@php
    $colors = ['neutral' => '', 'primary' => 'icon-btn-primary', 'danger' => 'icon-btn-danger'];
    $classes = trim('icon-btn '.($colors[$color] ?? ''));
    $accessible = $tip !== null ? ['title' => $tip, 'aria-label' => $tip] : [];
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(array_merge(['class' => $classes], $accessible)) }}>
        <x-ui.icon :name="$icon" />
    </a>
@else
    <button {{ $attributes->merge(array_merge(['class' => $classes, 'type' => 'button'], $accessible)) }}>
        <x-ui.icon :name="$icon" />
    </button>
@endif
