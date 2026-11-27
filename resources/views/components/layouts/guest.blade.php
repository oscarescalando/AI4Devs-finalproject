<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>TDChronos · {{ $title ?? 'Acceso' }}</title>
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
    <div class="guest-shell">
        <div class="guest-brand" aria-hidden="true">
            <div class="text-center select-none">
                @if (config('tc-ui.brand.logo'))
                    <img src="{{ config('tc-ui.brand.logo') }}" alt="" class="mx-auto h-16">
                @else
                    <div class="brand-mark brand-mark-hero mx-auto">T</div>
                @endif
                <p class="mt-4 text-lg font-semibold text-neutral-100">{{ config('tc-ui.brand.name') }}</p>
            </div>
        </div>
        <div class="guest-form-col">
            <main class="guest-card">
                {{ $slot }}
            </main>
        </div>
    </div>
    @livewireScripts
</body>
</html>
