<?php

use App\Actions\Results\GetPublicLeaderboard;
use App\Models\Event;
use App\Support\FormatDuration;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

/*
| ISSUE-137 (versión mínima del corte 1) — Clasificación pública en vivo.
| Convención (ISSUE-106): las pantallas son componentes Volt funcionales;
| el dato llega por Actions del servidor y nunca se calcula en el cliente
| (INV-07). El sondeo (wire:poll) sustituye a Reverb hasta que el flujo
| completo funcione; el contrato v/seq del leaderboard permite detectar
| cambios y resaltar llegadas nuevas.
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

    public function mount(string $slug): void
    {
        $event = Event::query()->where('slug', $slug)->firstOrFail();

        $this->slug = $slug;
        $this->eventUlid = $event->ulid;
        $this->eventName = $event->name;
        $this->refresh();
    }

    public function refresh(): void
    {
        $payload = (new GetPublicLeaderboard)($this->eventUlid);

        $currentBibs = array_map(static fn (array $r) => (int) $r['bib'], $payload['rows']);
        $previousBibs = array_map(static fn (array $r) => (int) $r['bib'], $this->rows);

        // primeras llegadas tras el primer render: se resaltan como nuevas
        $this->newBibs = $this->seq === 0 ? [] : array_values(array_diff($currentBibs, $previousBibs));
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

<div class="mx-auto max-w-6xl px-4 py-6" wire:poll.4s="refresh" wire:key="leaderboard-{{ $eventUlid }}">
    <header class="mb-6 flex flex-wrap items-center justify-between gap-4"
            x-data="{ now: new Date().toLocaleTimeString('es-VE', { hour12: false }) }"
            x-init="setInterval(() => now = new Date().toLocaleTimeString('es-VE', { hour12: false }), 1000)">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ $eventName }}</h1>
            <p class="mt-1 text-sm text-neutral-400">
                <span class="me-1 inline-flex items-center gap-1 font-medium text-success-400">
                    <span class="size-2 animate-pulse rounded-full bg-success-400"></span>
                    {{ $status === 'official' ? 'OFICIAL' : 'EN VIVO' }}
                </span>
                · {{ count($rows) }} llegadas · actualiza cada 4 s · <span class="font-mono tabular-nums" x-text="now"></span>
            </p>
        </div>
        <button type="button"
                class="rounded-lg border border-neutral-700 px-3 py-1.5 text-sm text-neutral-300 hover:bg-neutral-800"
                x-data
                @click="document.fullscreenElement ? document.exitFullscreen() : document.documentElement.requestFullscreen()">
            Pantalla completa
        </button>
    </header>

    <div class="mb-4 flex flex-wrap gap-3" wire:loading.remove delay>
        <label class="flex items-center gap-2 text-sm text-neutral-400">
            Categoría
            <select wire:model.live="category" class="rounded-lg border border-neutral-700 bg-neutral-900 px-2 py-1.5 text-neutral-100">
                <option value="">Todas</option>
                @foreach ($this->categories() as $cat)
                    <option value="{{ $cat }}">{{ $cat }}</option>
                @endforeach
            </select>
        </label>
        <label class="flex items-center gap-2 text-sm text-neutral-400">
            Distancia
            <select wire:model.live="race" class="rounded-lg border border-neutral-700 bg-neutral-900 px-2 py-1.5 text-neutral-100">
                <option value="">Todas</option>
                @foreach ($this->races() as $raceName)
                    <option value="{{ $raceName }}">{{ $raceName }}</option>
                @endforeach
            </select>
        </label>
    </div>

    <div wire:loading delay class="mb-2 text-sm text-neutral-500" wire:target="refresh">
        Actualizando…
    </div>

    <div class="overflow-x-auto rounded-2xl border border-neutral-800">
        <table class="w-full text-left text-sm sm:text-base">
            <thead class="bg-neutral-900/80 text-xs uppercase tracking-wider text-neutral-400">
                <tr>
                    <th scope="col" class="px-4 py-3">Pos</th>
                    <th scope="col" class="px-4 py-3">Atleta</th>
                    <th scope="col" class="hidden px-4 py-3 sm:table-cell">Club</th>
                    <th scope="col" class="hidden px-4 py-3 md:table-cell">Distancia</th>
                    <th scope="col" class="hidden px-4 py-3 lg:table-cell">Cat</th>
                    <th scope="col" class="px-4 py-3 text-right">Tiempo</th>
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
                            Sin llegadas todavía{{ $category !== '' || $race !== '' ? ' con estos filtros' : '' }}…
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <p class="mt-4 text-xs text-neutral-600">
        TDChronos · datos públicos por diseño: nombre y club; sin documento, contacto ni nacimiento.
    </p>
</div>
