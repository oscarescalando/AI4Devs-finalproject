<x-layouts.public :title="__('ui.public.landing_title')">
    <div class="mx-auto max-w-3xl px-4 py-16">
        <h1 class="text-3xl font-semibold tracking-tight">TDChronos <span class="text-neutral-500">2.0</span></h1>
        <p class="mt-2 text-neutral-400">{{ __('ui.public.landing_intro') }}</p>

        <ul class="mt-8 space-y-3">
            @forelse ($events as $event)
                <li>
                    <a href="/e/{{ $event->slug }}"
                       class="flex items-center justify-between rounded-2xl border border-neutral-800 px-5 py-4 transition hover:border-success-500/60 hover:bg-neutral-900">
                        <span>
                            <span class="font-medium">{{ $event->name }}</span>
                            <span class="ms-2 text-sm text-neutral-500">{{ \App\Support\FormatDate::date($event->date) }}</span>
                        </span>
                        <span class="inline-flex items-center gap-1 text-sm font-medium text-success-400">
                            <span class="size-2 animate-pulse rounded-full bg-success-400"></span>
                            {{ $event->status === 'official' ? __('ui.public.status_results') : __('ui.public.status_live') }} →
                        </span>
                    </a>
                </li>
            @empty
                <li class="rounded-2xl border border-dashed border-neutral-800 px-5 py-8 text-center text-neutral-500">
                    {{ __('ui.public.landing_empty_prefix') }} <code class="text-neutral-400">php artisan migrate:fresh --seed</code> {{ __('ui.public.landing_empty_suffix') }}
                </li>
            @endforelse
        </ul>
    </div>
</x-layouts.public>
