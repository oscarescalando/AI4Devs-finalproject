<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>TDChronos · Error 403</title>
</head>
<body style="min-height:100vh;display:grid;place-items:center;background:#020617;color:#F1F5F9;font-family:'Instrument Sans',system-ui,sans-serif">
    <div style="text-align:center;padding:2rem">
        <p class="mono" style="font-size:3rem;font-weight:700;color:#38BDF8">403</p>
        @if (403 === 403)
            <p style="margin:.5rem 0 1.5rem">No tienes permiso para acceder a esta pantalla.</p>
        @elseif (403 === 404)
            <p style="margin:.5rem 0 1.5rem">La página que buscas no existe.</p>
        @elseif (403 === 419)
            <p style="margin:.5rem 0 1.5rem">Tu sesión expiró.</p>
        @else
            <p style="margin:.5rem 0 1.5rem">Ocurrió un error inesperado.</p>
        @endif
        <a href="/" style="display:inline-flex;height:40px;align-items:center;padding:0 1rem;border-radius:8px;background:#0284C7;color:#fff;font-weight:600;text-decoration:none">Volver al inicio</a>
    </div>
</body>
</html>
