{{-- <x-ui.kpi label="Inscritos" value="114" icon="users" delta="+12" trend="up" note="vs. evento anterior" />
     Métrica destacada del kit (spec/09-ui-estilo.md §5): el valor SIEMPRE mono tabular
     (regla §1.3); `trend` up (default) | down colorea el delta. --}}
@props(['label' => null, 'value' => null, 'icon' => null, 'delta' => null, 'trend' => 'up', 'note' => null])
<div {{ $attributes->merge(['class' => 'card']) }}>
    <div class="card-body kpi">
        @if ($icon !== null)<span class="kpi-icon"><x-ui.icon :name="$icon" /></span>@endif
        @if ($label !== null)<span class="kpi-label">{{ $label }}</span>@endif
        @if ($value !== null)<span class="kpi-value">{{ $value }}</span>@endif
        @if ($delta !== null)
            <span class="kpi-delta {{ $trend === 'down' ? 'kpi-down' : 'kpi-up' }}">{{ $delta }}</span>
        @endif
        @if ($note !== null)<span class="kpi-note">{{ $note }}</span>@endif
    </div>
</div>
