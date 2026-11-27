<?php

use App\Actions\Directory\GetPanelOverview;
use App\Models\Event;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

/*
| ISSUE-107 — Consola del panel (versión mínima de TQ-002): el aterrizaje
| tras el login con el estado del sistema. Las rutas por evento y el resto
| de pantallas crecen con ISSUE-115 y EP-25.
*/

new #[Layout('components.layouts.panel')] #[Title('Consola')] class extends Component
{
    /** @var array<string, int> */
    public array $overview = [];

    /** @var list<array<string, mixed>> */
    public array $events = [];

    public function mount(): void
    {
        $this->overview = app(GetPanelOverview::class)();

        $this->events = Event::query()
            ->orderByDesc('date')
            ->limit(5)
            ->get(['ulid', 'slug', 'name', 'date', 'status'])
            ->map(fn (Event $event): array => [
                'ulid' => $event->ulid,
                'slug' => $event->slug,
                'name' => $event->name,
                'date' => $event->date->format('d/m/Y'),
                'status' => $event->status,
            ])
            ->all();
    }
}

?>

<div class="flex flex-col gap-5" wire:poll.30s>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach ([
            ['label' => 'Eventos', 'value' => $overview['events'], 'icon' => 'calendar-days'],
            ['label' => 'Personas', 'value' => $overview['persons'], 'icon' => 'users'],
            ['label' => 'Inscripciones', 'value' => $overview['entries'], 'icon' => 'clipboard-list'],
            ['label' => 'Marcas', 'value' => $overview['marks'], 'icon' => 'timer'],
        ] as $kpi)
            <div class="card">
                <div class="card-body flex items-center gap-3">
                    <span class="grid place-items-center w-9 h-9 rounded-lg" style="background: var(--tc-primary-soft); color: var(--tc-primary)">
                        <x-ui.icon :name="$kpi['icon']" />
                    </span>
                    <span>
                        <span class="block mono text-xl font-bold" style="color: var(--tc-text)">{{ number_format($kpi['value'], 0, ',', '.') }}</span>
                        <span class="block text-xs uppercase tracking-wide text-muted">{{ $kpi['label'] }}</span>
                    </span>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Próximos eventos</h2>
        </div>
        <div class="card-body">
            @if ($events === [])
                <div class="text-center py-8 flex flex-col items-center gap-2">
                    <x-ui.icon name="calendar-off" class="w-8 h-8 text-subtle" />
                    <p class="text-sm text-muted">Sin eventos cargados todavía.</p>
                </div>
            @else
                <ul class="flex flex-col divide-y" style="border-color: var(--tc-border)">
                    @foreach ($events as $event)
                        <li class="flex items-center gap-3 py-2.5">
                            <span class="badge badge-primary">{{ $event['date'] }}</span>
                            <span class="text-sm font-medium" style="color: var(--tc-text)">{{ $event['name'] }}</span>
                            <span class="badge badge-neutral ml-auto">{{ $event['status'] }}</span>
                            <a href="{{ route('public.event', $event['slug']) }}" class="btn btn-ghost btn-sm">
                                <x-ui.icon name="eye" />
                                <span>Ver pública</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>
