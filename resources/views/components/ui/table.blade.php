{{-- <x-ui.table :headers="[$headers, 'Región', ['label' => 'Total', 'align' => 'right']]" compact :hover="false">
         <tr wire:key="fila-1"><td>…</td></tr>
         <x-slot:footer>{{ $rows->links('components.ui.pagination') }}</x-slot:footer>
     </x-ui.table>
     Tabla del kit (ISSUE-110): envuelta en .table-wrap (scroll horizontal en móvil).
     Cada header es string (label) o array con:
       - label (obligatorio)
       - align: left (default) | right | center — la celda del cuerpo lleva la misma clase
       - class: clases extra para th
       - href + icon: columna ordenable (enlace con icono Lucide de dirección) --}}
@props(['headers' => [], 'compact' => false, 'hover' => true])
@php
    $aligns = ['left' => '', 'right' => 'td-right', 'center' => 'td-center'];
    $columns = [];
    foreach ($headers as $header) {
        $columns[] = is_array($header) ? $header : ['label' => (string) $header];
    }
@endphp
<div class="table-wrap">
    <table class="table{{ $compact ? ' table-compact' : '' }}{{ $hover ? ' table-hover' : '' }}">
        <thead>
            <tr>
                @foreach ($columns as $column)
                    @php
                        $align = $aligns[$column['align'] ?? 'left'] ?? '';
                        $cellClass = trim($align.' '.($column['class'] ?? ''));
                    @endphp
                    <th scope="col" @if ($cellClass !== '') class="{{ $cellClass }}" @endif>
                        @if (isset($column['href']) && $column['href'] !== null)
                            <a href="{{ $column['href'] }}" class="th-sort">
                                {{ $column['label'] }}
                                @if (isset($column['icon']) && $column['icon'] !== null)
                                    <x-ui.icon :name="$column['icon']" />
                                @endif
                            </a>
                        @else
                            {{ $column['label'] }}
                        @endif
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            {{ $slot }}
        </tbody>
    </table>
    @isset($footer)
        <div class="table-footer">{{ $footer }}</div>
    @endisset
</div>
