{{-- <x-ui.icon name="timer" class="w-4 h-4" /> — iconos Lucide (spec/09-ui-estilo.md §7) --}}
@props(['name'])
<i data-lucide="{{ $name }}" {{ $attributes }}></i>
