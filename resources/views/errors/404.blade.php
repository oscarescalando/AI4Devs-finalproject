<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>TDChronos · {{ __('ui.errors.title', ['code' => '404']) }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="tc-shell">
    <div class="error-page">
        <div class="text-center px-8 py-4">
            <p class="error-code">404</p>
            <p class="error-text">{{ __('ui.errors.not_found') }}</p>
            <a href="/" class="error-link">{{ __('ui.errors.back') }}</a>
        </div>
    </div>
</body>
</html>
