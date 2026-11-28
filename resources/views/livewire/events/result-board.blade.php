<?php

use App\Actions\Results\ListEventClassification;
use App\Models\Event;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

/*
| ISSUE-137/TQ-015 — Clasificación operativa del panel: la variante con
| sesión de la proyección pública /e/{slug}. Cero cálculo en pantalla
| (INV-07): posiciones, netos y diferencias salen de la tabla derivada
| results vía ListEventClassification (los escribió el motor). Filtros de
| carrera/categoría por ULID en la URL (#[Url], compartible) y sondeo
| interino wire:poll.10s — el push < 2 s por el canal ops llega con EP-26.
| Ver exige results.view (ruta, matriz ISSUE-159); las filas DSQ rojas y
| las exportaciones quedan diferidas (tique TQ-015).
*/

new #[Layout('components.layouts.panel')] #[Title('Clasificación')] class extends Component
{
    use WithPagination;

    public Event $event;

    #[Url(except: '')]
    public string $race = '';

    #[Url(except: '')]
    public string $category = '';

    public function mount(Event $event): void
    {
        $this->event = $event;
    }

    public function updatedRace(): void
    {
        // Cambiar el filtro devuelve al primer bloque de la clasificación.
        $this->resetPage();
    }

    public function updatedCategory(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('race', 'category');
        $this->resetPage();
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return app(ListEventClassification::class)($this->event, $this->race, $this->category);
    }
}

?>

<div class="flex flex-col gap-4" wire:poll.10s>
    <x-ui.page-header :title="__('ui.events.results_title')" subtitle="{{ $event->name }} · {{ __('ui.events.results_text') }}">
        <x-slot:actions>
            <x-ui.badge :variant="$status === 'official' ? 'success' : 'warning'" dot>
                {{ $status === 'official' ? __('ui.events.results_status_official') : __('ui.events.results_status_provisional') }}
            </x-ui.badge>
            <x-ui.button variant="ghost" icon="eye" href="{{ route('public.event', $event->slug) }}">
                {{ __('ui.panel.view_public') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Contadores del encabezado: llegadas con resultado vs pendientes del
         evento (withdrawn fuera, como el motor) — misma fuente que la tabla. --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <x-ui.kpi :label="__('ui.events.results_kpi_arrived')" :value="$counters['arrived']" icon="medal" />
        <x-ui.kpi :label="__('ui.events.results_kpi_pending')" :value="$counters['pending']" icon="hourglass" />
    </div>

    <x-ui.card padding="none">
        <div class="toolbar">
            <label class="flex items-center gap-2">
                <span class="text-sm text-muted">{{ __('ui.events.results_filter_race') }}</span>
                <select id="result-race" class="form-control" wire:model.live="race">
                    <option value="">{{ __('ui.events.results_filter_all') }}</option>
                    @foreach ($races as $item)
                        <option value="{{ $item['ulid'] }}" @selected($race === $item['ulid'])>{{ $item['name'] }}</option>
                    @endforeach
                </select>
            </label>

            <label class="flex items-center gap-2">
                <span class="text-sm text-muted">{{ __('ui.events.results_filter_category') }}</span>
                <select id="result-category" class="form-control" wire:model.live="category">
                    <option value="">{{ __('ui.events.results_filter_all') }}</option>
                    @foreach ($categories as $item)
                        <option value="{{ $item['ulid'] }}" @selected($category === $item['ulid'])>{{ $item['name'] }}</option>
                    @endforeach
                </select>
            </label>

            @if ($race !== '' || $category !== '')
                <x-ui.button variant="ghost" size="sm" icon="x" wire:click="clearFilters">
                    {{ __('ui.events.results_filter_clear') }}
                </x-ui.button>
            @endif
        </div>

        {{-- Carga: esqueleto acotado a la tabla mientras consulta (P4/G13). --}}
        <div wire:loading.delay wire:target="gotoPage,previousPage,nextPage,race,category" class="flex flex-col gap-2 p-4">
            @foreach (range(1, 6) as $skeleton)
                <div class="skeleton h-9" wire:key="skeleton-{{ $skeleton }}"></div>
            @endforeach
        </div>

        <div wire:loading.remove.delay wire:target="gotoPage,previousPage,nextPage,race,category">
            @if ($rows->isEmpty())
                <x-ui.empty-state
                    icon="medal"
                    :title="$counters['arrived'] === 0 ? __('ui.events.results_none_title') : __('ui.events.results_empty_filtered_title')"
                    :text="$counters['arrived'] === 0 ? __('ui.events.results_none_text') : __('ui.events.results_empty_filtered_text')"
                />
            @else
                <x-ui.table :headers="[
                    ['label' => __('ui.events.header_pos'), 'align' => 'right', 'class' => 'mono'],
                    ['label' => __('ui.events.results_header_pos_cat'), 'align' => 'right', 'class' => 'mono hidden md:table-cell'],
                    ['label' => __('ui.events.results_header_pos_gen'), 'align' => 'right', 'class' => 'mono hidden lg:table-cell'],
                    ['label' => __('ui.events.header_bib'), 'align' => 'right', 'class' => 'mono'],
                    __('ui.events.header_athlete'),
                    ['label' => __('ui.events.header_club'), 'class' => 'hidden md:table-cell'],
                    ['label' => __('ui.events.results_header_race'), 'class' => 'hidden lg:table-cell'],
                    __('ui.events.header_cat'),
                    ['label' => __('ui.events.results_header_net'), 'align' => 'right', 'class' => 'mono'],
                    ['label' => __('ui.events.results_header_diff'), 'align' => 'right', 'class' => 'mono'],
                ]">
                    @foreach ($rows as $row)
                        @php($top = $row['rank_general'] <= 3)
                        <tr wire:key="result-{{ $row['bib'] }}">
                            <td class="td-right mono {{ $row['rank_general'] === 1 ? 'font-semibold text-primary-700 dark:text-primary-300' : ($top ? 'font-semibold text-strong' : '') }}">
                                {{ $row['rank_general'] }}
                            </td>
                            <td class="td-right mono text-muted hidden md:table-cell">{{ $row['rank_category'] }}</td>
                            <td class="td-right mono text-muted hidden lg:table-cell">{{ $row['rank_gender'] ?? '—' }}</td>
                            <td class="td-right mono">{{ $row['bib'] }}</td>
                            <td class="font-medium text-strong">{{ $row['athlete'] }}</td>
                            <td class="hidden md:table-cell">{{ $row['club'] ?? '—' }}</td>
                            <td class="hidden lg:table-cell">{{ $row['race'] }}</td>
                            <td>{{ $row['category'] }}</td>
                            <td class="td-right mono font-semibold">{{ $row['net'] }}</td>
                            <td class="td-right mono {{ $top ? 'text-strong' : 'text-muted' }}">{{ $row['diff'] ?? '—' }}</td>
                        </tr>
                    @endforeach

                    <x-slot:footer>
                        {{ $rows->links('components.ui.pagination') }}
                    </x-slot:footer>
                </x-ui.table>
            @endif
        </div>
    </x-ui.card>
</div>
