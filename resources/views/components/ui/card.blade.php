{{-- <x-ui.card title="Organizaciones" subtitle="Clubes y entidades">
         <x-slot:actions><x-ui.button icon="plus">Nueva</x-ui.button></x-slot:actions>
         …contenido…
         <x-slot:footer>pie</x-slot:footer>
     </x-ui.card>
     Contenedor del kit (spec/09-ui-estilo.md §5): cabecera con título/subtítulo y slot
     `actions`, cuerpo (padding md por defecto, `none` lo quita) y pie opcional. --}}
@props(['title' => null, 'subtitle' => null, 'padding' => 'md'])
<div {{ $attributes->merge(['class' => 'card']) }}>
    @if ($title !== null || $subtitle !== null || isset($actions))
        <div class="card-header">
            <div class="min-w-0">
                @if ($title !== null)<h2 class="card-title">{{ $title }}</h2>@endif
                @if ($subtitle !== null)<p class="page-subtitle">{{ $subtitle }}</p>@endif
            </div>
            @isset($actions)
                <div class="toolbar-spacer sm:ml-0">{{ $actions }}</div>
            @endisset
        </div>
    @endif

    <div class="card-body{{ $padding === 'none' ? ' card-body--flush' : '' }}">{{ $slot }}</div>

    @isset($footer)
        <div class="card-footer">{{ $footer }}</div>
    @endisset
</div>
