{{-- Sidebar del panel: menú desde config filtrado por permisos reales (ISSUE-107). --}}
@php
    $nav = \App\Support\Ui\PanelNav::visible(auth()->user());
    $current = request()->routeIs('*') ? request()->route()?->getName() : null;
@endphp
<aside id="tc-sidebar" class="sidebar" aria-label="{{ __('ui.panel.nav_aria') }}">
    <div class="sidebar-brand">
        @if (config("tc-ui.brand.logo"))
            <img src="{{ config("tc-ui.brand.logo") }}" alt="" class="h-8 w-8 rounded-lg object-cover">
        @else
            <span class="brand-mark" aria-hidden="true">T</span>
        @endif
        <span>
            <span class="brand-name block leading-tight">{{ config('tc-ui.brand.name') }}</span>
            <span class="brand-season">{{ config('tc-ui.brand.season') }}</span>
        </span>
    </div>

    <nav class="sidebar-nav">
        @foreach ($nav as $group)
            <div class="nav-group">
                <span class="nav-group-label">{{ __($group['label']) }}</span>
                @foreach ($group['items'] as $item)
                    <x-ui.nav-link
                        :href="\App\Support\Ui\PanelNav::href($item)"
                        :icon="$item['icon']"
                        :active="$current === $item['route']"
                    >{{ __($item['label']) }}</x-ui.nav-link>
                @endforeach
            </div>
        @endforeach
    </nav>

    <div class="sidebar-footer">
        <span class="badge badge-neutral">{{ __('ui.panel.session_started') }}</span>
    </div>
</aside>
