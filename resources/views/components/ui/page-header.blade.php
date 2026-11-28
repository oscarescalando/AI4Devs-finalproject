{{-- <x-ui.page-header title="Organizaciones" subtitle="Clubes del directorio">
         <x-slot:actions><x-ui.button variant="primary" icon="plus">Nueva</x-ui.button></x-slot:actions>
     </x-ui.page-header>
     Cabecera de página del kit (spec/09-ui-estilo.md §4): dice dónde estoy y qué puedo
     hacer aquí; el slot recibe las acciones de página. --}}
@props(['title' => null, 'subtitle' => null])
<div class="page-header">
    <div class="min-w-0">
        @if ($title !== null)<h1 class="page-title">{{ $title }}</h1>@endif
        @if ($subtitle !== null)<p class="page-subtitle">{{ $subtitle }}</p>@endif
    </div>
    @isset($actions)
        <div class="page-actions">{{ $actions }}</div>
    @endisset
</div>
