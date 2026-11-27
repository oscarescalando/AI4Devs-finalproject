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
        @unless (config('tc-ui.brand.logo'))
            <div class="guest-brand" aria-hidden="true">
                <div class="text-center select-none">
                    <div class="brand-mark mx-auto" style="width:64px;height:64px;border-radius:16px;background:linear-gradient(135deg,#0284C7,#0C4A6E);color:#fff;display:grid;place-items:center;font-weight:800;font-size:1.75rem">T</div>
                    <p class="mt-4 text-lg font-semibold" style="color:#F1F5F9">{{ config('tc-ui.brand.name') }}</p>
                </div>
            </div>
        @endunless
        <div class="guest-form-col">
            <main class="guest-card">
                {{ $slot }}
            </main>
        </div>
    </div>
    @livewireScripts
</body>
</html>
