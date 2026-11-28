<?php

use App\Actions\Results\GetPublicLeaderboard;
use App\Models\Event;
use App\Support\FormatDuration;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

/*
| ISSUE-137 (versión mínima del corte 1) — Clasificación pública en vivo.
| Convención (ISSUE-106): las pantallas son componentes Volt funcionales;
| el dato llega por Actions del servidor y nunca se calcula en el cliente
| (INV-07). El contrato v/seq del leaderboard permite detectar cambios y
| resaltar llegadas nuevas.
|
| TQ-018 (EP-26, ISSUE-142/RF-75): reacción por WS — puente tcBridge
| (patrón compartido, ver arrival-capture) que escucha `results.updated`
| en el canal ABIERTO event.{ulid}.public y lo retransmite como evento
| Livewire `tc-results`; el método compara el seq del push con la última
| versión recibida y llama al refresh() existente. OJO: el seq del push
| (versión de results, MAX id) y el seq del payload público (conteo de
| marcas del contrato v/seq) son dominios distintos — se vigilan por
| separado. Sondeo de respaldo degradado a 15 s (RF-76): el WS hace el
| trabajo fino; sin WS (kiosco sin clave, Reverb caído) el poll mantiene
| la pantalla viva.
|
| TQ-032 (EP-26 completo, RF-54/F2) — kiosco por push + resync:
| · DECISIÓN delta: al llegar `mark.recorded` (tc-mark) con seq nuevo NO
|   se pinta nada en el cliente (la posición JAMÁS se calcula ahí,
|   ADR-011/012): el dorsal del push queda SELLADO como llegada nueva
|   (bibSeenAt) y se dispara UN refetch dirigido del leaderboard — el
|   mismo refresh() que ya carga la colección del servidor. La señal
|   temprana de la marca + la de results.updated (motor) comparten el
|   mismo camino; la fila nueva aparece resaltada en cuanto el motor la
|   escribe, sin esperar al sondeo de 15 s. El resaltado dura 30 s por
|   SELLO DE TIEMPO (no por diff de la última carga): sobrevive a los
|   refrescos encadenados aunque tc-mark y tc-results viajen en el mismo
|   commit de Livewire (hallazgo E2E: el render final borraba el resaltado
|   antes de pintarse).
| · Dominios de seq: lastMarkSeq vigila mark.recorded (timing_marks.id);
|   lastPushSeq, results.updated (MAX results.id); y $seq, el conteo del
|   contrato v/seq del leaderboard. Tres contadores, ninguno se mezcla.
| · Resync por gap/reconexión: un seq salteado (seq > last+1) o la
|   reconexión del WS (tc-resync del puente) NO acumulan refetchs: UN
|   solo refetch del contrato v/seq devuelve el estado completo del
|   servidor y cierra cualquier hueco. Single-flight de 500 ms SOLO para
|   la sonda temprana de tc-mark (refetchSoon): una ráfaga de marcas que
|   caiga dentro de la ventana queda pendiente y la resuelve el disparo
|   siguiente. tc-results y tc-resync son señales AUTORIZADAS (dato nuevo
|   verificado / reconexión tras hueco real) y refrescan siempre: jamás
|   se retrasa una llegada por la ventana — cada una cuesta a lo sumo UN
|   refetch gracias al debounce del puente.
*/

new #[Layout('components.layouts.public')] class extends Component
{
    public string $slug = '';

    public string $eventUlid = '';

    public string $eventName = '';

    public int $seq = 0;

    public string $status = 'provisional';

    /** @var list<array<string, mixed>> */
    public array $rows = [];

    public string $category = '';

    public string $race = '';

    /** @var list<int> */
    public array $newBibs = [];

    /** @var array<int, int> Dorsal → ms de época de su llegada (0 = preexistente). Resaltado 30 s (TQ-032). */
    public array $bibSeenAt = [];

    /** Última versión de results anunciada por WS: deduplica los push. */

    /** Última versión de results anunciada por WS: deduplica los push. */
    public int $lastPushSeq = 0;

    /** Última marca anunciada por WS (dominio timing_marks.id): deduplica y detecta gaps. */
    public int $lastMarkSeq = 0;

    /** Cargas del leaderboard (contador observable: pruebas y depuración). */
    public int $refetches = 0;

    /** Arranque (ms de época) del último refetch dirigido por push: ventana single-flight. */
    public float $lastRefetchMs = 0.0;

    /** true = un push llegó dentro de la ventana y su refetch queda pendiente del siguiente disparo. */
    public bool $pendingRefetch = false;

    public function mount(string $slug): void
    {
        $event = Event::query()->where('slug', $slug)->firstOrFail();

        $this->slug = $slug;
        $this->eventUlid = $event->ulid;
        $this->eventName = $event->name;
        $this->refresh();
    }

    /** Puente WS→Livewire (tcBridge en la vista): el motor recalculó. */
    #[On('tc-results')]
    public function onResultsUpdated(array $payload = []): void
    {
        if (($payload['eventUlid'] ?? null) !== $this->eventUlid) {
            return;
        }

        $seq = (int) ($payload['seq'] ?? 0);

        if ($seq <= $this->lastPushSeq) {
            return; // versión ya reflejada
        }

        $this->lastPushSeq = $seq;

        // Señal AUTORIZADA (el motor ya escribió la versión): refresh
        // directo — la ventana single-flight jamás retrasa una llegada.
        $this->refresh();
    }

    /**
     * Puente WS→Livewire: llegó una marca (tc-mark, TQ-032). Refetch
     * dirigido inmediato — sin esperar al sondeo ni al motor: si results
     * aún no recalculó, el refresh no ve cambios y el resaltado lo trae
     * el tc-results que le sigue; el coste es el mismo de un poll.
     */
    #[On('tc-mark')]
    public function onMarkRecorded(array $payload = []): void
    {
        if (($payload['eventUlid'] ?? null) !== $this->eventUlid) {
            return;
        }

        $seq = (int) ($payload['seq'] ?? 0);

        if ($seq <= $this->lastMarkSeq) {
            return; // push repetido o viejo
        }

        // Un gap (seq > last+1) no recibe tratamiento extra: un refetch
        // del contrato v/seq ya devuelve TODO el estado del servidor y
        // cierra el hueco; pedir «solo el delta» exigiría un endpoint de
        // deltas que hoy no existe (DEFER del plan TQ-031).
        $this->lastMarkSeq = $seq;

        // RF-54: el dorsal llega EN el push; se sella como llegada nueva
        // (bibSeenAt = ahora) y TODA renderización de los próximos 30 s lo
        // resalta apenas el motor lo escriba (la posición la sigue
        // calculando el servidor — aquí solo se apunta el dorsal,
        // ADR-011/012). El sello sobrevive a los refrescos encadenados:
        // cuando tc-mark y tc-results viajan en el mismo commit, el render
        // final ya no borra el resaltado (hallazgo E2E TQ-032).
        $bib = isset($payload['bib']) && is_numeric($payload['bib']) ? (int) $payload['bib'] : null;

        if ($bib !== null) {
            $this->bibSeenAt[$bib] ??= (float) CarbonImmutable::now()->getTimestampMs();
        }

        $this->refetchSoon();
    }

    /**
     * Puente WS→Livewire: reconexión del WS (tc-resync, TQ-032). Un único
     * refetch — refresh() actualiza $seq desde el contrato v/seq del
     * servidor, así la pantalla queda al día con lo perdido mientras no
     * había socket. Directo (sin ventana): tras un hueco real no hay nada
     * que deduplicar y el debounce del puente ya colapsa los rebotes.
     */
    #[On('tc-resync')]
    public function onResync(): void
    {
        $this->refresh();
    }

    /**
     * Single-flight de la sonda de tc-mark (TQ-032): una ráfaga de marcas
     * que caiga dentro de los 500 ms posteriores a un refetch no acumula
     * peticiones — queda pendiente y la resuelve el siguiente disparo (el
     * tc-results autorizado que le sigue o el sondeo de 15 s). tc-mark es
     * sonda temprana: diferirla no pierde datos, el motor confirma después.
     */
    private function refetchSoon(): void
    {
        $nowMs = (float) CarbonImmutable::now()->getTimestampMs();

        if ($nowMs - $this->lastRefetchMs < 500.0) {
            $this->pendingRefetch = true;

            return;
        }

        $this->lastRefetchMs = $nowMs;
        $this->refresh();
    }

    public function refresh(): void
    {
        $this->pendingRefetch = false;
        $this->refetches++;

        $payload = (new GetPublicLeaderboard)($this->eventUlid);

        $currentBibs = array_map(static fn (array $r) => (int) $r['bib'], $payload['rows']);

        // Sello de llegada por dorsal (RF-54): preexistentes del primer
        // render quedan en 0 (nunca «nuevos», igual que el diff clásico);
        // los que aparecen después se sellan AHORA — por push (bibSeenAt
        // ya lo sembró onMarkRecorded) o por sondeo sin WS — y se
        // resaltan durante 30 s EN TODOS los refrescos intermedios: el
        // resaltado es una ventana de tiempo, no un diff de la última
        // carga, así ningún refresh encadenado lo borra antes de tiempo.
        $nowMs = (float) CarbonImmutable::now()->getTimestampMs();
        $isFirstLoad = $this->seq === 0;

        foreach ($currentBibs as $bib) {
            $this->bibSeenAt[$bib] ??= ($isFirstLoad ? 0.0 : $nowMs);
        }

        $this->bibSeenAt = array_filter(
            $this->bibSeenAt,
            static fn (float $seenAt, int $bib): bool => in_array($bib, $currentBibs, true) || ($nowMs - $seenAt) < 30_000.0,
            ARRAY_FILTER_USE_BOTH,
        );

        $seenAtMap = $this->bibSeenAt;

        $this->newBibs = array_values(array_filter(
            $currentBibs,
            static fn (int $bib): bool => ($nowMs - ($seenAtMap[$bib] ?? 0.0)) < 30_000.0
                && ($seenAtMap[$bib] ?? 0.0) > 0.0,
        ));

        $this->seq = $payload['seq'];
        $this->status = $payload['status'];
        $this->rows = $payload['rows'];
    }

    /** @return list<array<string, mixed>> */
    public function visibleRows(): array
    {
        return array_values(array_filter($this->rows, function (array $row): bool {
            if ($this->category !== '' && $row['category'] !== $this->category) {
                return false;
            }

            return $this->race === '' || $row['race'] === $this->race;
        }));
    }

    /** @return list<string> */
    public function categories(): array
    {
        return $this->uniqueColumn('category');
    }

    /** @return list<string> */
    public function races(): array
    {
        return $this->uniqueColumn('race');
    }

    /** @return list<string> */
    private function uniqueColumn(string $key): array
    {
        $values = array_map(static fn (array $row) => (string) $row[$key], $this->rows);

        return array_values(array_unique($values));
    }
};

?>

<div
    class="mx-auto max-w-6xl px-4 py-6"
    wire:poll.15s="refresh"
    wire:key="leaderboard-{{ $eventUlid }}"
    {{-- Puente WS→Livewire (patrón compartido, ver arrival-capture): canal
         ABIERTO del evento (sin «private-»), feed sin PII (RF-75/INV-06).
         TQ-032: escucha también mark.recorded (tc-mark → refetch dirigido,
         colapsado a 500 ms por ráfaga) y wave.started (relay de CLIENTE
         tc-wave-started para el reloj); tc-resync tras reconexión del WS
         dispara el único refetch de resync (opciones opt-in del tcBridge,
         ver resources/js/app.js). --}}
    x-data="tcBridge(@js([
        'channel' => 'event.'.$eventUlid.'.public',
        'private' => false,
        'events' => [
            '.mark.recorded' => 'tc-mark',
            '.results.updated' => 'tc-results',
        ],
        'debounce' => ['tc-mark' => 500, 'tc-results' => 500],
        'resync' => 'tc-resync',
        'browser' => ['.wave.started' => 'tc-wave-started'],
    ]))"
>

    {{-- Reloj definido DENTRO de la raíz del componente: un <script> ANTES
         del div raíz hace que Livewire 4 cuelgue wire:id del script y el
         morph deje de tocar la tabla — los pushes actualizaban el estado
         pero jamás el DOM (hallazgo E2E real de TQ-032). Con ||=, la
         reejecución tras un morph es un no-op idempotente. --}}
    <script>
        /* Reloj de la pública (TQ-032, RF-42/RF-54) — patrón del tablero
           de salidas (tcStartClock) adaptado a pantalla anónima: en vez de
           $wire.syncClock (que gastaría una vuelta de Livewire), alinea el
           offset contra GET /api/v1/time, PÚBLICO sin Bearer (ADR-012), al
           montar y cada 60 s; el tick deriva del reloj local corregido.
           INTEGRACIÓN MÍNIMA COHERENTE del cronómetro de carrera: la misma
           ranura del encabezado muestra el reloj de pared (es-VE) y pasa a
           cronómetro (hora oficial − actualStartAt de la oleada) cuando
           llega un wave.started por push (relay de cliente del tcBridge:
           tc-wave-started, sin vuelta al servidor — el leaderboard no
           cambia con una salida). Una página abierta a mitad de carrera
           mantiene el reloj de pared: el contrato del leaderboard no
           expone la salida vigente y sembrarlo tocaría GetPublicLeaderboard
           v1 (DEFER del plan TQ-031); la ÚLTIMA oleada anunciada mientras
           la pantalla está abierta es la que manda (multialeada: la más
           reciente). */
        window.tcPublicClock ||= (config) => ({
            offsetMs: 0,
            now: '',
            raceStartMs: null,
            race: '',
            onWaveStarted: null,
            init() {
                this.tick();
                setInterval(() => this.tick(), 1000);
                this.syncServerTime();
                setInterval(() => this.syncServerTime(), 60000);
                this.onWaveStarted = (event) => {
                    const start = Number(event?.detail?.payload?.actualStartAt);
                    if (Number.isFinite(start) && start > 0) {
                        this.raceStartMs = start;
                    }
                };
                window.addEventListener(config.waveEvent, this.onWaveStarted);
            },
            destroy() {
                if (this.onWaveStarted) {
                    window.removeEventListener(config.waveEvent, this.onWaveStarted);
                }
            },
            /* /time sin cabecera es público: nada de Bearer ni sesión. */
            async syncServerTime() {
                try {
                    const response = await window.fetch('/api/v1/time');
                    const data = await response.json();
                    const serverMs = Number(data?.server_ms);
                    if (Number.isFinite(serverMs)) {
                        this.offsetMs = serverMs - Date.now();
                    }
                } catch (error) {
                    /* sin red: el offset vigente sigue mandando */
                }
            },
            tick() {
                const serverNowMs = Date.now() + this.offsetMs;
                this.now = new Date(serverNowMs).toLocaleTimeString('es-VE', { hour12: false });
                this.race = this.raceStartMs === null
                    ? ''
                    : this.formatElapsed(serverNowMs - this.raceStartMs);
            },
            formatElapsed(elapsedMs) {
                const total = Math.max(0, Math.floor(elapsedMs / 1000));
                const pad = (value) => String(value).padStart(2, '0');
                return `${Math.floor(total / 3600)}:${pad(Math.floor((total % 3600) / 60))}:${pad(total % 60)}`;
            },
        });
    </script>

    <header class="mb-6 flex flex-wrap items-center justify-between gap-4"
            x-data="tcPublicClock(@js(['waveEvent' => 'tc-wave-started']))">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ $eventName }}</h1>
            <p class="mt-1 flex flex-wrap items-center gap-x-1 text-sm text-neutral-400">
                <span class="me-1 inline-flex items-center gap-1 font-medium text-success-400">
                    <span class="size-2 animate-pulse rounded-full bg-success-400"></span>
                    {{ $status === 'official' ? __('ui.public.official') : __('ui.public.live') }}
                </span>
                · {{ __('ui.public.arrivals', ['count' => count($rows)]) }} · {{ __('ui.public.refresh') }} · <span class="font-mono tabular-nums" wire:ignore x-text="race || now"></span>
                {{-- Indicador de conexión WS ligado a tc-echo-status (G13, TQ-018). --}}
                <x-ui.live-indicator :wire="true" class="ms-2" />
            </p>
        </div>
        <button type="button"
                class="rounded-lg border border-neutral-700 px-3 py-1.5 text-sm text-neutral-300 hover:bg-neutral-800"
                x-data
                @click="document.fullscreenElement ? document.exitFullscreen() : document.documentElement.requestFullscreen()">
            {{ __('ui.public.fullscreen') }}
        </button>
    </header>

    <div class="mb-4 flex flex-wrap gap-3" wire:loading.remove delay>
        <label class="flex items-center gap-2 text-sm text-neutral-400">
            {{ __('ui.public.category') }}
            <select wire:model.live="category" class="rounded-lg border border-neutral-700 bg-neutral-900 px-2 py-1.5 text-neutral-100">
                <option value="">{{ __('ui.public.all') }}</option>
                @foreach ($this->categories() as $cat)
                    <option value="{{ $cat }}">{{ $cat }}</option>
                @endforeach
            </select>
        </label>
        <label class="flex items-center gap-2 text-sm text-neutral-400">
            {{ __('ui.public.distance') }}
            <select wire:model.live="race" class="rounded-lg border border-neutral-700 bg-neutral-900 px-2 py-1.5 text-neutral-100">
                <option value="">{{ __('ui.public.all') }}</option>
                @foreach ($this->races() as $raceName)
                    <option value="{{ $raceName }}">{{ $raceName }}</option>
                @endforeach
            </select>
        </label>
    </div>

    <div wire:loading delay class="mb-2 text-sm text-neutral-500" wire:target="refresh">
        {{ __('ui.public.updating') }}
    </div>

    <div class="overflow-x-auto rounded-2xl border border-neutral-800">
        <table class="w-full text-left text-sm sm:text-base">
            <thead class="bg-neutral-900/80 text-xs uppercase tracking-wider text-neutral-400">
                <tr>
                    <th scope="col" class="px-4 py-3">{{ __('ui.public.header_pos') }}</th>
                    <th scope="col" class="px-4 py-3">{{ __('ui.public.header_athlete') }}</th>
                    <th scope="col" class="hidden px-4 py-3 sm:table-cell">{{ __('ui.public.header_club') }}</th>
                    <th scope="col" class="hidden px-4 py-3 md:table-cell">{{ __('ui.public.header_race') }}</th>
                    <th scope="col" class="hidden px-4 py-3 lg:table-cell">{{ __('ui.public.header_category') }}</th>
                    <th scope="col" class="px-4 py-3 text-right">{{ __('ui.public.header_time') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-800/70">
                @forelse ($this->visibleRows() as $row)
                    @php($isNew = in_array((int) $row['bib'], $newBibs))
                    <tr class="{{ $isNew ? 'bg-success-500/15 transition-colors duration-1000' : 'hover:bg-neutral-900/60' }}">
                        <td class="px-4 py-2.5 font-semibold font-mono tabular-nums {{ $row['ranks']['general'] === 1 ? 'text-accent-300' : '' }}">
                            {{ $row['ranks']['general'] }}
                        </td>
                        <td class="px-4 py-2.5">
                            <span class="font-medium">{{ $row['name'] }}</span>
                            <span class="ms-2 text-xs font-mono tabular-nums text-neutral-500">#{{ $row['bib'] }}</span>
                        </td>
                        <td class="hidden px-4 py-2.5 text-neutral-400 sm:table-cell">{{ $row['org'] ?? '—' }}</td>
                        <td class="hidden px-4 py-2.5 text-neutral-400 md:table-cell">{{ $row['race'] }}</td>
                        <td class="hidden px-4 py-2.5 text-neutral-400 lg:table-cell">{{ $row['category'] }}</td>
                        <td class="px-4 py-2.5 text-right font-mono tabular-nums">
                            {{ \App\Support\FormatDuration::fromMs($row['net_ms'], true) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-10 text-center text-neutral-500">
                            {{ __('ui.public.no_arrivals') }}{{ $category !== '' || $race !== '' ? __('ui.public.with_filters') : '' }}…
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <p class="mt-4 text-xs text-neutral-600">
        {{ __('ui.public.footer') }}
    </p>
</div>
