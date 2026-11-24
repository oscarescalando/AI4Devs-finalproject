<x-layouts.public title="TDChronos · Eventos">
    <div class="mx-auto max-w-3xl px-4 py-16">
        <h1 class="text-3xl font-semibold tracking-tight">TDChronos <span class="text-slate-500">2.0</span></h1>
        <p class="mt-2 text-slate-400">Cronometraje y clasificación en vivo. Eventos disponibles:</p>

        <ul class="mt-8 space-y-3">
            @forelse ($events as $event)
                <li>
                    <a href="/e/{{ $event->slug }}"
                       class="flex items-center justify-between rounded-2xl border border-slate-800 px-5 py-4 transition hover:border-emerald-500/60 hover:bg-slate-900">
                        <span>
                            <span class="font-medium">{{ $event->name }}</span>
                            <span class="ms-2 text-sm text-slate-500">{{ $event->date->format('d/m/Y') }}</span>
                        </span>
                        <span class="inline-flex items-center gap-1 text-sm font-medium text-emerald-400">
                            <span class="size-2 animate-pulse rounded-full bg-emerald-400"></span>
                            {{ $event->status === 'official' ? 'Resultados' : 'En vivo' }} →
                        </span>
                    </a>
                </li>
            @empty
                <li class="rounded-2xl border border-dashed border-slate-800 px-5 py-8 text-center text-slate-500">
                    Sin eventos. Corre <code class="text-slate-400">php artisan migrate:fresh --seed</code> para sembrar el evento demo.
                </li>
            @endforelse
        </ul>
    </div>
</x-layouts.public>
