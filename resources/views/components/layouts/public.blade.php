<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>TDChronos · {{ $title ?? 'Cronometraje en vivo' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="public-body min-h-screen bg-neutral-950 font-sans text-neutral-100 antialiased">
    {{ $slot }}
    @livewireScripts
</body>
</html>
