{{-- Enlace del menú lateral; `is-active` por coincidencia de ruta (ISSUE-107). --}}
@props(['href' => null, 'icon' => null, 'active' => false])
<a href="{{ $href }}" {{ $attributes->merge(['class' => 'nav-link'.($active ? ' is-active' : '')]) }}>
    @if ($icon)<x-ui.icon :name="$icon" />@endif
    <span>{{ $slot }}</span>
</a>
