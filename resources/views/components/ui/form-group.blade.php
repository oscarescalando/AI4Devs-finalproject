{{-- <x-ui.form-group label="Nombre" for="org-name" required help="Hasta 150 caracteres." :error="$errors->first('form.name')">
         <input id="org-name" class="form-control" wire:model="form.name">
     </x-ui.form-group>
     Campo del kit (spec/09-ui-estilo.md §5): label asociado por `for`, marca de obligatorio,
     ayuda y error del SERVIDOR bajo el campo (ISSUE-110). `data-field` alimenta el foco
     automático al primer inválido (app.js). --}}
@props(['label' => null, 'for' => null, 'required' => false, 'help' => null, 'error' => null])
<div {{ $attributes->merge(['class' => 'form-group']) }} @if ($for !== null) data-field="{{ $for }}" @endif>
    @if ($label !== null)
        <label class="form-label" @if ($for !== null) for="{{ $for }}" @endif>
            {{ $label }}@if ($required)<span class="req" aria-hidden="true">*</span><span class="sr-only"> {{ __('ui.kit.required') }}</span>@endif
        </label>
    @endif

    {{ $slot }}

    @if ($error !== null && $error !== '')
        <p class="form-error" role="alert">{{ $error }}</p>
    @elseif ($help !== null)
        <p class="form-help" @if ($for !== null) id="{{ $for }}-help" @endif>{{ $help }}</p>
    @endif
</div>
