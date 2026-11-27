{{-- Sidebar del panel: menú desde config filtrado por permisos reales (ISSUE-107). --}}
@php
    $nav = \App\Support\Ui\PanelNav::visible(auth()->user());
    $current = request()->routeIs('*') ? request()->route()?->getName() : null;
@endphp
<aside id="tc-sidebar" class="sidebar" aria-label="Navegación del panel">
    <div class="sidebar-brand">
        <span class="brand-mark" aria-hidden="true">T</span>
        <span>
            <span class="brand-name block leading-tight">{{ config('tc-ui.brand.name') }}</span>
            <span class="brand-season">{{ config('tc-ui.brand.season') }}</span>
        </span>
    </div>

    <nav class="sidebar-nav">
        @foreach ($nav as $group)
            <div class="nav-group">
                <span class="nav-group-label">{{ $group['label'] }}</span>
                @foreach ($group['items'] as $item)
                    <x-ui.nav-link
                        :href="route($item['route'])"
                        :icon="$item['icon']"
                        :active="$current === $item['route']"
                    >{{ $item['label'] }}</x-ui.nav-link>
                @endforeach
            </div>
        @endforeach
    </nav>

    <div class="sidebar-footer">
        <span class="badge badge-neutral">Sesión iniciada</span>
    </div>
</aside>
