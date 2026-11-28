<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>TDChronos · {{ __('ui.station.title') }}</title>
    {{-- PWA de la estación (ADR-014): manifest compartido con la app
         (start_url /); uno propio con start_url /station llega con
         ISSUE-149, igual que el QR de emparejamiento. --}}
    <link rel="manifest" href="/pwa/manifest">
    <meta name="theme-color" content="#0284C7">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    {{-- Stub anti-autoarranque: el ESM de vendor del que station.js importa
         Alpine arranca Livewire en DOMContentLoaded salvo que esta variable
         exista (ver resources/js/station.js). Aquí solo corre Alpine. --}}
    <script>window.livewireScriptConfig = window.livewireScriptConfig || {};</script>
    {{-- Entry propio de la estación (TQ-021): app.css (tokens + kit) +
         station.js (Alpine + IndexedDB). SIN app.js, Echo ni Livewire:
         nada del panel viaja en esta página y la captura sobrevive sin red. --}}
    @vite(['resources/css/app.css', 'resources/js/station.js'])
</head>
{{-- Pantalla operativa de campo (spec/09-ui-estilo §1.6): SIEMPRE oscura,
     sin PII (INV-06) y sin sesión — nada de auth() en este shell. La clase
     dark activa los tokens de superficie del kit sobre el fondo propio. --}}
<body class="tc-shell public-body dark min-h-screen bg-neutral-950 font-sans text-neutral-100 antialiased">
    <main class="mx-auto flex w-full max-w-3xl flex-col gap-6 px-4 py-8" x-data="tcStation(@js(__('ui.station')))">
        {{-- Cabecera: título + reloj corregido contra GET /time (TQ-022:
             hora local + offset del servidor, mono tabular, ISSUE-150). --}}
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div class="min-w-0">
                <h1>{{ __('ui.station.title') }}</h1>
                <p class="text-sm text-neutral-400">{{ __('ui.station.subtitle') }}</p>
            </div>
            <div class="text-right">
                <p class="text-xs uppercase tracking-wide text-neutral-500">{{ __('ui.station.clock_label') }}</p>
                <p class="mono text-3xl font-semibold leading-tight" x-text="clock"></p>
            </div>
        </header>

        {{-- Avisos persistentes (TQ-022): desfase del reloj > 500 ms contra
             el servidor (ISSUE-150) y token muerto detectado al sincronizar
             (ISSUE-149): desempareja y pide emparejar de nuevo. --}}
        <div x-show="skewVisible" x-cloak>
            <x-ui.alert type="warning">
                <p class="mono" x-text="skewLabel"></p>
            </x-ui.alert>
        </div>
        <div x-show="authError" x-cloak>
            <x-ui.alert type="danger">
                <p>{{ __('ui.station.sync_err_auth') }}</p>
            </x-ui.alert>
        </div>

        {{-- Sin emparejar: SOLO el formulario (ISSUE-147). --}}
        <section class="card" x-show="!paired" x-cloak>
            <div class="card-header">
                <div class="min-w-0">
                    <h2 class="card-title">{{ __('ui.station.pair_title') }}</h2>
                    <p class="page-subtitle">{{ __('ui.station.pair_text') }}</p>
                </div>
            </div>
            <form class="card-body flex flex-col gap-4" novalidate @submit.prevent="pair()">
                <x-ui.form-group :label="__('ui.station.event_ulid')" for="station-event" required>
                    <input id="station-event" type="text" class="form-control mono"
                           autocomplete="off" autocapitalize="off" spellcheck="false"
                           placeholder="{{ __('ui.station.event_ulid_placeholder') }}"
                           x-model="form.eventUlid" :disabled="pairing">
                </x-ui.form-group>

                <x-ui.form-group :label="__('ui.station.token')" for="station-token" required>
                    <input id="station-token" type="text" class="form-control mono"
                           autocomplete="off" autocapitalize="off" spellcheck="false"
                           placeholder="{{ __('ui.station.token_placeholder') }}"
                           x-model="form.token" :disabled="pairing">
                </x-ui.form-group>

                <p class="form-error" role="alert" x-show="pairError" x-text="pairError" x-cloak></p>

                <div class="flex items-center gap-3">
                    <x-ui.button variant="primary" icon="link-2" type="submit" x-bind:disabled="pairing">
                        <span x-text="pairing ? t.pair_checking : t.pair_submit"></span>
                    </x-ui.button>
                </div>
            </form>
        </section>

        {{-- Emparejado: modo captura 100 % local (ISSUE-148). --}}
        <section class="flex flex-col gap-6" x-show="paired" x-cloak>
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-neutral-800 bg-neutral-900 px-4 py-3">
                <div class="min-w-0">
                    <p class="text-xs uppercase tracking-wide text-neutral-500">{{ __('ui.station.paired_event') }}</p>
                    <p class="mono truncate text-lg" x-text="eventUlid"></p>
                </div>
                <button type="button" class="btn btn-secondary btn-sm" @click="unpair()">{{ __('ui.station.unpair') }}</button>
            </div>

            <form class="flex flex-col gap-3" @submit.prevent="capture()">
                <label class="sr-only" for="station-bib">{{ __('ui.station.bib_label') }}</label>
                <input id="station-bib" type="text"
                       class="form-control mono h-auto! w-full py-10! text-center text-6xl! font-bold"
                       inputmode="numeric" autocomplete="off" autofocus
                       placeholder="{{ __('ui.station.bib_placeholder') }}"
                       x-model="bib" :readonly="!dbReady">
                <p class="form-error text-center" role="alert" x-show="bibError" x-text="t.bib_invalid" x-cloak></p>
                <p class="text-center text-sm text-neutral-400">{{ __('ui.station.capture_text') }}</p>
                <p class="flex flex-wrap items-center justify-center gap-2 text-sm" role="status">
                    <span class="badge badge-warning badge-dot" x-text="pendingLabel"></span>
                    {{-- Indicador de red (TQ-022): junto al contador de pendientes. --}}
                    <span class="badge badge-success" x-show="online">
                        <x-ui.icon name="wifi" class="h-3 w-3" />
                        {{ __('ui.station.net_online') }}
                    </span>
                    <span class="badge badge-danger" x-show="!online" x-cloak>
                        <x-ui.icon name="cloud-off" class="h-3 w-3" />
                        {{ __('ui.station.net_offline') }}
                    </span>
                </p>
            </form>

            <div class="card">
                <div class="card-header">
                    <div class="min-w-0">
                        <h2 class="card-title">{{ __('ui.station.queue_title') }}</h2>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="badge badge-warning badge-dot" x-text="pendingLabel"></span>
                        {{-- Sync manual (ISSUE-149): ignora el backoff; se
                             deshabilita mientras corre un flush. --}}
                        <x-ui.button variant="secondary" size="sm" icon="refresh-cw"
                                     x-bind:disabled="syncing || pending === 0" @click="synchronize()">
                            <span x-text="syncLabel"></span>
                        </x-ui.button>
                    </div>
                </div>
                <div class="card-body">
                    <x-ui.empty-state icon="timer" :title="__('ui.station.queue_empty_title')" :text="__('ui.station.queue_empty_text')" x-show="dbReady && marks.length === 0" x-cloak />
                    <ul class="list-divide" x-show="marks.length > 0" x-cloak>
                        <template x-for="mark in marks" :key="mark.client_mark_id">
                            <li class="flex items-center gap-3 py-2.5">
                                <span class="mono w-24 text-right text-2xl font-bold" x-text="mark.bib"></span>
                                <span class="mono text-sm text-neutral-400" x-text="clockOf(mark.captured_at)"></span>
                                {{-- Estado por marca (TQ-022): pendiente hasta
                                     respuesta definitiva; después asignada
                                     (éxito) / huérfana (alerta) / duplicada
                                     (info) — spec/09-ui-estilo §3. --}}
                                <span class="badge badge-dot ml-auto" :class="'badge-' + badgeVariant(mark)" x-text="badgeLabel(mark)"></span>
                                <button type="button" class="btn btn-ghost btn-sm" :aria-label="t.delete_tip" @click="removeMark(mark)">
                                    <x-ui.icon name="trash-2" class="h-4 w-4" />
                                </button>
                            </li>
                        </template>
                    </ul>
                </div>
                <div class="card-footer">{{ __('ui.station.sync_hint') }}</div>
            </div>
        </section>
    </main>
</body>
</html>
