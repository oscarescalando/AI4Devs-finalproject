<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * ISSUE-163 (TQ-035) — Respaldo lógico de la BD para operación: mysqldump
 * comprimido con gzip en storage/app/backups (carpeta ignorada por git: los
 * backups JAMÁS van al repo). El procedimiento completo —frecuencia,
 * restauración y reversa de emergencia— vive en docs/procesos/runbook-operacion.md §4.
 *
 * Decisiones (documentadas por ser de operación, no de dominio):
 * · mysqldump VÍA Symfony Process con argumentos en ARRAY: nunca una cadena
 *   de shell — los datos de conexión jamás pasan por un intérprete. Exige el
 *   cliente de MySQL instalado (Herd y Laragon lo traen); se localiza por
 *   PATH con ExecutableFinder y el error es accionable si falta.
 * · La contraseña viaja en la variable de entorno MYSQL_PWD del proceso, no
 *   en argv (invisible a `ps`) y nunca en el log del comando.
 * · Compresión en PHP (zlib: gzopen/gzwrite dentro del callback de Process)
 *   en vez de un pipe de shell a gzip: un solo proceso hijo, sin binario
 *   gzip del que depender y memoria plana — el dump se escribe comprimido a
 *   medida que llega, sin archivo temporal ni volcado completo en RAM.
 * · --single-transaction --quick --no-tablespaces: snapshot consistente sin
 *   bloquear tablas (InnoDB), volcado por streaming y sin exigir el
 *   privilegio PROCESS (requerido por mysqldump 8 para SHOW TABLE STATUS).
 * · Código de salida FIEL: si mysqldump revienta, se devuelve su exit code
 *   real y se borra el archivo parcial (un .sql.gz a medias no es backup).
 */
final class OpsBackup extends Command
{
    /** Nivel de compresión gzip (1-9): 9 por tamaño — la BD local es pequeña. */
    private const GZIP_LEVEL = 9;

    /** Tope generoso: un dump colgado no debe frenar la operación. */
    private const TIMEOUT_SECONDS = 600;

    protected $signature = 'ops:backup
        {--database= : Nombre de la BD a respaldar (por defecto, database.connections.mysql.database)}';

    protected $description = 'Respaldo mysqldump comprimido (.sql.gz) en storage/app/backups — ver docs/procesos/runbook-operacion.md §4';

    public function handle(): int
    {
        $connection = (array) config('database.connections.mysql');

        $database = trim((string) ($this->option('database') ?: ($connection['database'] ?? '')));

        if ($database === '') {
            $this->error('No hay base de datos que respaldar: pasa --database= o define database.connections.mysql.database.');

            return self::FAILURE;
        }

        $binary = (new ExecutableFinder)->find('mysqldump');

        if ($binary === null) {
            $this->error('mysqldump no está en el PATH: instala el cliente de MySQL (Herd lo trae en sus servicios; Laragon, en su instalación).');

            return self::FAILURE;
        }

        $arguments = [
            $binary,
            '--host='.(string) ($connection['host'] ?? '127.0.0.1'),
            '--port='.(string) ($connection['port'] ?? '3306'),
            '--user='.(string) ($connection['username'] ?? 'root'),
            '--default-character-set='.(string) ($connection['charset'] ?? 'utf8mb4'),
            '--single-transaction',
            '--quick',
            '--no-tablespaces',
            $database,
        ];

        $directory = storage_path('app/backups');
        File::ensureDirectoryExists($directory);

        $filename = sprintf(
            'tdchronos-%s-%s.sql.gz',
            $database,
            CarbonImmutable::now(config('app.timezone'))->format('Ymd-His'),
        );
        $path = $directory.DIRECTORY_SEPARATOR.$filename;

        $gzip = gzopen($path, 'wb'.self::GZIP_LEVEL);

        if ($gzip === false) {
            $this->error("No se pudo crear {$path} (¿permisos de storage/app/backups?).");

            return self::FAILURE;
        }

        $stderr = '';

        // Contraseña por entorno, no por argv. Process fusiona este env con el
        // heredado: el PATH (para el binario) sigue disponible para el hijo.
        $process = new Process($arguments, null, [
            'MYSQL_PWD' => (string) ($connection['password'] ?? ''),
        ], null, self::TIMEOUT_SECONDS);

        $exitCode = $process->run(
            function (string $type, string $chunk) use ($gzip, &$stderr): void {
                if ($type === Process::OUT) {
                    gzwrite($gzip, $chunk);
                } else {
                    $stderr .= $chunk;
                }
            },
        );

        gzclose($gzip);

        if ($exitCode !== 0) {
            File::delete($path);

            $this->error("mysqldump falló (exit {$exitCode}) sobre la BD {$database}: ".trim($stderr));
            $this->line('El archivo parcial se descartó: un .sql.gz a medias no es un backup.');

            return $exitCode;
        }

        $bytes = (int) File::size($path);
        $this->info("Backup de {$database}: {$path}");
        $this->line('Peso: '.$this->humanSize($bytes));

        return self::SUCCESS;
    }

    /** KiB/MiB legibles para el operador. */
    private function humanSize(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return number_format($bytes / 1024 / 1024, 2).' MiB';
        }

        return number_format($bytes / 1024, 1).' KiB';
    }
}
