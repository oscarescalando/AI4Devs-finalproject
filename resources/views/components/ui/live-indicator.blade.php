{{-- <x-ui.live-indicator state="live" />
     <x-ui.live-indicator state="offline" :pending="4" />
     <x-ui.live-indicator :wire="true" />
     Estado de la conexión en vivo (G13, ISSUE-141/TQ-017): En vivo (punto
     accent pulsante) / Reconectando… / Sin conexión (N pendientes). El
     estado lo controla quien lo use vía data-state.
     Con :wire="true" (TQ-018) el estado lo gobierna Alpine a partir del
     evento DOM tc-echo-status que publica el cliente Echo (TQ-017): los
     tres rótulos siguen saliendo de lang/es/ui.php (sembrados en el render
     inicial del servidor) y el CSS reacciona a data-state; sin WS el
     indicador queda en Sin conexión y la pantalla sigue por sondeo (O1).
     rol status + aria-live polite para lectores. --}}
@props(['state' => 'offline', 'pending' => 0, 'wire' => false])
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
@if ($wire)
    <span
        {{ $attributes->merge(['class' => 'live-indicator']) }}
        role="status"
        aria-live="polite"
        data-state="{{ $state }}"
        x-data="{
            state: @js($state),
            labels: {
                live: @js(__('ui.live.online')),
                reconnecting: @js(__('ui.live.reconnecting')),
                offline: @js(__('ui.live.offline')),
            },
            fromEcho: {
                connected: 'live',
                connecting: 'reconnecting',
                unavailable: 'reconnecting',
                disconnected: 'offline',
                offline: 'offline',
            },
        }"
        x-on:tc-echo-status.window="state = fromEcho[$event.detail.state] ?? 'offline'"
        x-init="state = fromEcho[window.tcEchoStatus ?? 'disconnected'] ?? 'offline'"
        :data-state="state"
    >
        <span class="live-dot" aria-hidden="true"></span>
        <span class="live-label" x-text="labels[state]">{{ $label }}</span>
    </span>
@else
    <span {{ $attributes->merge(['class' => 'live-indicator']) }} role="status" aria-live="polite" data-state="{{ $state }}">
        <span class="live-dot" aria-hidden="true"></span>
        <span class="live-label">{{ $label }}</span>
        @if ($showPending)
            <span class="live-pending">({{ trans_choice('ui.live.pending', (int) $pending) }})</span>
        @endif
    </span>
@endif
