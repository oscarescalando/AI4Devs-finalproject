<?php

declare(strict_types=1);

/**
 * ISSUE-118 (TQ-007) — Cero literales de interfaz en vistas: todo texto que
 * se pinta vive en lang/es/ui.php. Esta prueba escanea resources/views/**
 * buscando nodos de texto en español (fuera de {{ }}) y falla señalando
 * archivo:línea:texto; lo que deba quedarse como literal se documenta en
 * excepciones-literales.txt (una línea por texto, con su motivo en un #
 * previo).
 */
it('ninguna vista Blade trae literales de texto en español', function () {
    $exceptions = i18nExceptions();
    $violations = [];

    $pattern = '/>(\s*[A-ZÁÉÍÓÚÜÑ][a-záéíóúüñ]+[^<>{}]{5,})</u';

    foreach (viewFilesWithLines() as $file) {
        // Se analiza solo el marcado: comentarios Blade y bloques PHP (el
        // código Volt de cabecera) se sustituyen conservando los saltos de
        // línea para que los números de línea del reporte sigan siendo reales.
        $clean = stripBladeSideCode((string) file_get_contents($file));

        preg_match_all($pattern, $clean, $matches, PREG_OFFSET_CAPTURE);

        foreach ($matches[1] as [$text, $offset]) {
            $trimmed = trim((string) $text);

            if ($trimmed === '' || in_array($trimmed, $exceptions, true)) {
                continue;
            }

            $line = substr_count(substr($clean, 0, (int) $offset), "\n") + 1;
            $violations[] = sprintf(
                '%s:%d: %s',
                str_replace(base_path().DIRECTORY_SEPARATOR, '', $file),
                $line,
                mb_substr($trimmed, 0, 100),
            );
        }
    }

    if ($violations !== []) {
        $this->fail(
            "Literales de texto en vistas (migrar a lang/es/ui.php o documentar en\n"
            .'tests/Feature/I18n/excepciones-literales.txt con su motivo):'
            ."\n\n".implode("\n", $violations),
        );
    }

    expect($violations)->toBe([]);
});

/**
 * @return list<string>
 */
function viewFilesWithLines(): array
{
    $files = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(resource_path('views'), FilesystemIterator::SKIP_DOTS),
    );

    foreach ($iterator as $file) {
        if ($file instanceof SplFileInfo && $file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
            $files[] = $file->getPathname();
        }
    }

    sort($files);

    return $files;
}

/**
 * Comentarios {{-- --}} y bloques <?php ?> / @php @endphp fuera del marcado,
 * sustituidos por saltos de línea equivalentes (calco de líneas).
 */
function stripBladeSideCode(string $blade): string
{
    $preserve = static fn (array $matches): string => str_repeat("\n", substr_count((string) $matches[0], "\n"));

    $blade = (string) preg_replace_callback('/\{\{--.*?--\}\}/s', $preserve, $blade);
    $blade = (string) preg_replace_callback('/<\?php.*?\?>/s', $preserve, $blade);

    return (string) preg_replace_callback('/@php\b.*?@endphp\b/s', $preserve, $blade);
}

/**
 * Textos permitidos como literales, leídos del archivo de excepciones
 * (las líneas con # son el motivo de la excepción que sigue).
 *
 * @return list<string>
 */
function i18nExceptions(): array
{
    $path = __DIR__.'/excepciones-literales.txt';

    $exceptions = [];

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $exceptions[] = $line;
    }

    return $exceptions;
}
