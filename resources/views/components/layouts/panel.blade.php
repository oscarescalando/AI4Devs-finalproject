@props(['title' => null, 'subtitle' => null])
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TDChronos · {{ $title ?? 'Panel' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
        // Tema persistido; el panel arranca en oscuro salvo preferencia (§1.6).
        try {
            if (window.localStorage.getItem('tc.theme') !== 'light') {
                document.documentElement.classList.add('dark');
            }
        } catch (e) { document.documentElement.classList.add('dark'); }
    </script>
</head>
<body class="tc-shell">
    @php($account = auth()->user())
    <div class="panel-shell" x-data="{ menu: false }">
        <div id="tc-sidebar-backdrop" onclick="window.tcToggleSidebar()" hidden></div>
        <x-ui.sidebar />

        <div class="panel-main">
            <header class="header">
                <button type="button" class="icon-btn lg:hidden" onclick="window.tcToggleSidebar()" aria-label="Abrir menú">
                    <x-ui.icon name="menu" />
                </button>

                <div class="min-w-0">
                    @if ($title)
                        <p class="header-title truncate">{{ $title }}</p>
                    @endif
                    <nav class="header-crumb" aria-label="Miga de pan">
                        <a href="{{ route('panel.home') }}">Panel</a>
                        @if ($title)
                            <span aria-hidden="true">›</span>
                            <span class="truncate">{{ $title }}</span>
                        @endif
                    </nav>
                </div>

                <div class="ml-auto flex items-center gap-1.5">
                    <button type="button" class="icon-btn" onclick="window.tcToggleTheme()" aria-label="Cambiar tema">
                        <x-ui.icon name="moon-star" />
                    </button>

                    <div class="relative" x-data="{ open: false }" @keydown.escape="open = false">
                        <button type="button" class="icon-btn" @click="open = !open" aria-label="Menú de cuenta" aria-haspopup="menu" :aria-expanded="open ? 'true' : 'false'">
                            <span class="avatar">{{ mb_substr($account?->person?->first_name ?? 'U', 0, 1) }}{{ mb_substr($account?->person?->last_name ?? '', 0, 1) }}</span>
                        </button>
                        <div class="menu-dd" x-cloak x-show="open" @click.outside="open = false">
                            <div class="dd-header">
                                <p class="dd-name">{{ $account?->person?->first_name }} {{ $account?->person?->last_name }}</p>
                                <p class="dd-mail">{{ $account?->email }}</p>
                            </div>
                            <form method="POST" action="{{ route('logout') }}" onsubmit="return confirm('¿Cerrar sesión?')">
                                @csrf
                                <button type="submit" class="dd-item">
                                    <x-ui.icon name="log-out" />
                                    <span>Cerrar sesión</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            <main class="content">
                @if ($title)
                    <div class="mb-4">
                        <h1 class="page-title">{{ $title }}</h1>
                        @if ($subtitle)
                            <p class="page-subtitle">{{ $subtitle }}</p>
                        @endif
                    </div>
                @endif

                {{ $slot }}
            </main>
        </div>
    </div>
    @livewireScripts
</body>
</html>
