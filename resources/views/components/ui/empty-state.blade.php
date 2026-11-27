{{-- <x-ui.empty-state icon="building-2" title="Sin organizaciones" text="Crea la primera para empezar.">
         <x-ui.button variant="primary" icon="plus">Nueva organización</x-ui.button>
     </x-ui.empty-state>
     Estado vacío del kit (spec/09-ui-estilo.md §6): nunca tabla fantasma; el slot recibe el CTA. --}}
@props(['icon' => 'inbox', 'title' => null, 'text' => null])
<div {{ $attributes->merge(['class' => 'empty-state']) }}>
    <x-ui.icon :name="$icon" />
    @if ($title !== null)<p class="empty-title">{{ $title }}</p>@endif
    @if ($text !== null)<p class="empty-text">{{ $text }}</p>@endif
    @if ($slot->isNotEmpty())<div class="mt-2">{{ $slot }}</div>@endif
</div>
