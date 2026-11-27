{{-- Paginación con links de Livewire (ISSUE-110), tema propio del sistema.
     Uso (dentro de un componente con WithPagination):
         {{ $items->links('components.ui.pagination') }}
     Los botones llaman por wire:click a gotoPage/nextPage/previousPage del componente,
     así que la página vive en la query string (?page=) sin recarga completa. --}}
<div class="flex items-center justify-between gap-3">
    <p class="text-xs text-muted" role="status">
        @if ($paginator->total() > 0)
            {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} de {{ $paginator->total() }}
        @else
            Sin resultados
        @endif
    </p>

    @if ($paginator->hasPages())
        <nav class="flex items-center gap-0.5" aria-label="Paginación">
            <button
                type="button"
                class="page-btn"
                wire:click="previousPage('{{ $paginator->getPageName() }}')"
                @if ($paginator->onFirstPage()) disabled @endif
                aria-label="Página anterior"
            >
                <x-ui.icon name="chevron-left" />
            </button>

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="page-ellipsis" aria-hidden="true">…</span>
                @elseif (is_array($element))
                    @foreach ($element as $page => $url)
                        <button
                            type="button"
                            class="page-btn{{ $paginator->currentPage() === $page ? ' is-active' : '' }}"
                            wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')"
                            @if ($paginator->currentPage() === $page) aria-current="page" @endif
                        >{{ $page }}</button>
                    @endforeach
                @endif
            @endforeach

            <button
                type="button"
                class="page-btn"
                wire:click="nextPage('{{ $paginator->getPageName() }}')"
                @if (! $paginator->hasMorePages()) disabled @endif
                aria-label="Página siguiente"
            >
                <x-ui.icon name="chevron-right" />
            </button>
        </nav>
    @endif
</div>
