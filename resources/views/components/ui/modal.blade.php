{{-- <x-ui.modal show="orgModal" title="Nueva organización" subtitle="Los datos se validan en el servidor." size="md">
         <form wire:submit="save">…</form>
         <x-slot:footer>botones</x-slot:footer>
     </x-ui.modal>
     Diálogo del kit (ISSUE-110): `show` es una EXPRESIÓN Alpine de la vista contenedora
     (p. ej. una propiedad de su x-data); escape y clic en el fondo la ponen en false.
     Foco al abrir (x-effect sobre el diálogo), tamaño md|lg, pie opcional. --}}
@props(['show' => 'false', 'title' => null, 'subtitle' => null, 'size' => 'md'])
<div
    class="modal-overlay"
    x-cloak
    x-show="{{ $show }}"
    x-transition.opacity.duration.150ms
    @keydown.escape.window="{{ $show }} = false"
    @click.self="{{ $show }} = false"
    {{ $attributes }}
>
    <div
        class="modal-dialog{{ $size === 'lg' ? ' modal-lg' : '' }}"
        role="dialog"
        aria-modal="true"
        @if ($title !== null) aria-label="{{ $title }}" @endif
        tabindex="-1"
        x-effect="if ({{ $show }}) { $el.focus() }"
    >
        @if ($title !== null || $subtitle !== null)
            <div class="modal-header">
                <div class="min-w-0">
                    @if ($title !== null)<h2 class="modal-title">{{ $title }}</h2>@endif
                    @if ($subtitle !== null)<p class="modal-subtitle">{{ $subtitle }}</p>@endif
                </div>
                <button type="button" class="icon-btn" @click="{{ $show }} = false" aria-label="{{ __('ui.kit.close') }}">
                    <x-ui.icon name="x" />
                </button>
            </div>
        @endif

        <div class="modal-body">{{ $slot }}</div>

        @isset($footer)
            <div class="modal-footer">{{ $footer }}</div>
        @endisset
    </div>
</div>
