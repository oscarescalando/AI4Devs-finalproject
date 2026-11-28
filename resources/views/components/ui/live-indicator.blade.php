{{-- <x-ui.live-indicator state="live" />
     <x-ui.live-indicator state="offline" :pending="4" />
     Estado de la conexión en vivo (G13, ISSUE-141/TQ-017): En vivo (punto
     accent pulsante) / Reconectando… / Sin conexión (N pendientes). El
     estado lo controla quien lo use vía data-state (Echo desde TQ-018);
     rol status + aria-live polite para lectores. --}}
@props(['state' => 'offline', 'pending' => 0])
@php
    $states = ['live', 'reconnecting', 'offline'];
    $state = in_array($state, $states, true) ? $state : 'offline';
    $label = match ($state) {
        'live' => __('ui.live.online'),
        'reconnecting' => __('ui.live.reconnecting'),
        default => __('ui.live.offline'),
    };
    $showPending = $state === 'offline' && (int) $pending > 0;
@endphp
<span {{ $attributes->merge(['class' => 'live-indicator']) }} role="status" aria-live="polite" data-state="{{ $state }}">
    <span class="live-dot" aria-hidden="true"></span>
    <span class="live-label">{{ $label }}</span>
    @if ($showPending)
        <span class="live-pending">({{ trans_choice('ui.live.pending', (int) $pending) }})</span>
    @endif
</span>
