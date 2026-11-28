# Runbook de operación — TDChronos 2.0

> TQ-035 · ISSUE-163 (fase final). Operación del sistema el día a día y el día de carrera: arranque del entorno, captura, cierre/publicación, backups y emergencias. Escrito para el operador del cronometraje; el contrato completo vive en `spec/` (SRS §5, ADR-012/013/014/015) y las pantallas en `tiques/` (TQ-012/021/030/033/034).

## 1. Arranque del entorno

Requisitos: PHP 8.4, MySQL 8 (con el cliente `mysqldump`), Redis 7, Node. Credenciales solo en `.env` (jamás en el repo).

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate   # solo la primera vez
php artisan migrate:fresh --seed                   # BD desde cero + evento demo + admin
npm run build                                      # o npm run dev en desarrollo
```

- **Herd (macOS)**: el sitio ya está servido en `http://<carpeta>.test` — no hace falta `artisan serve`. Herd trae MySQL, `mysqldump` y Redis como servicios (menú de servicios).
- **Laragon (Windows)**: mismo esquema, `http://<proyecto>.test` con `mysqldump` en la instalación de MySQL; alternativa directa `php artisan serve`.
- Admin demo (seeder, sintético): `admin@tdchronos.test` / `tdchronos-admin-2026`. Acceso en `/login` (documento o correo + contraseña, RF-68); el panel arranca en `/home`.

**Un solo proceso — `php artisan dev`**: levanta juntos el servidor (`serve`), las colas (`horizon`, que sustituye al `queue:listen` cuando está instalado), el websocket (`reverb:start`), el log en vivo (`pail`) y Vite (`npm run dev`). Es la forma recomendada de trabajar el día de carrera.

Alternativas individuales (cuando solo falta una pieza):

```bash
php artisan serve            # HTTP
php artisan horizon          # colas Redis (broadcast y recálculo en vivo, INV-08)
php artisan reverb:start     # websocket tiempo real, puerto 8080 (ADR-013)
npm run dev                  # assets en caliente
```

Con `QUEUE_CONNECTION=redis` y `BROADCAST_CONNECTION=reverb`, sin Horizon/Reverb corriendo no se pierde nada persistido — el vivo degrada a sondeo (ver §5).

## 2. Día de carrera (orden operativo)

El evento vive en el path por su ULID público (`{ulid}`, 26 caracteres — ADR-015). El estado del evento se gestiona desde el editor (`/events`, lápiz del evento); un evento nuevo nace `live`.

| # | Paso | Dónde | Permiso | Qué pasa |
|---|------|-------|---------|----------|
| 1 | Emparejar la estación | `php artisan station:token {ulid}` → pegar el token en `/station` | — (token con ability `device:marks`) | El token se imprime UNA vez (ISSUE-147). `/station` lo valida contra `GET /api/v1/time` y queda lista para capturar. La captura es local-first (Alpine + IndexedDB, ADR-014): funciona sin Internet y sincroniza por lotes idempotentes (`client_mark_id`, ISSUE-156). |
| 2 | Inscripciones tardías | `/events/{ulid}/entries` | `entries.view` / `entries.manage` | Gestor de inscritos (EntryManager, ISSUE-162). Cada inscripción descarga su planilla PDF en `/entries/{ulid}/planilla` (ADR-017). |
| 3 | Salidas por oleada | `/events/{ulid}/start` | `start.manage` | Tablero de salidas (ISSUE-132): al partir cada oleada se marca su salida real; se puede deshacer. **Sin salida real en toda oleada no se puede cerrar el evento** (§3). |
| 4 | Captura de llegadas | `/events/{ulid}/arrivals` y/o la estación `/station` | `marks.manage` | Dorsal + Enter por el conducto del lote (ISSUE-130), o la estación que sincroniza sola. Toda marca es append-only (ADR-015). |
| 5 | Bitácora y correcciones | `/events/{ulid}/marks` | `marks.view`; correcciones `corrections.manage` | Log de marcas con su historia: las marcas NO se editan — la corrección es un evento con motivo (ISSUE-133), reasignación de dorsal incluida. |
| 6 | Ranking en vivo | `/events/{ulid}/results` (operativo) y `/e/{slug}` (pública/kiosco) | `results.view` / pública | Push por Reverb + sondeo de respaldo cada 15 s (RF-76). La pantalla pública no expone PII (INV-06). |

Recomendado: `/e/{slug}` proyectada (modo kiosco) y el result-board operativo en la pantalla del operador.

## 3. Cierre y publicación del evento

Botón **Cerrar** en el hub del evento (`/events/{ulid}`), visible solo con `results.publish` (organizer y superadmin, matriz ISSUE-159).

- **Guardas** (las evalúa `PreviewCloseEvent`, única fuente; `CloseEvent` las revalida en el servidor y rechaza con el primer blocker): evento no cerrado ya, estado `live`/`finished`, con oleadas, toda oleada con salida real y al menos una marca de llegada. La confirmación de la pantalla muestra exactamente los blockers que faltan.
- Al confirmar: estado → `closed` y recálculo **síncrono y transaccional** de la clasificación `official` (INV-07); se emite `event.closed` en los canales ops y público — la pantalla pública pasa a modo **OFICIAL** (el sondeo de 15 s también lo recoge, RF-76).
- **IRREVERSIBLE en v1**: no existe «reabrir» un evento cerrado. Verificar llegadas pendientes, estación sincronizada y correcciones ANTES de cerrar; después solo queda la restauración de backup (§4/§5).

## 4. Backups

```bash
php artisan ops:backup                     # BD por defecto (config mysql)
php artisan ops:backup --database=otra_bd  # otra BD de la misma conexión
```

- **Dónde cae**: `storage/app/backups/tdchronos-<bd>-<Ymd-His>.sql.gz`, con ruta y peso impresos. La carpeta está en `.gitignore`: **los backups jamás van al repo**.
- **Requisito**: binario `mysqldump` en el PATH (Herd y Laragon lo traen). El comando lo ejecuta vía Symfony Process (argumentos en array, sin shell) con la contraseña en la variable de entorno `MYSQL_PWD`, y comprime en PHP (zlib) mientras vuelca — un solo proceso, sin dependencia del binario gzip.
- **Restauración** (reemplaza TODO el contenido de la BD destino — hacer backup fresco antes):

```bash
mysql --host=127.0.0.1 --user=root -e "CREATE DATABASE IF NOT EXISTS tdchronos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
gunzip -c storage/app/backups/tdchronos-<bd>-<fecha>.sql.gz | mysql --host=127.0.0.1 --user=root tdchronos
```

- **Frecuencia recomendada**: (1) antes del día de carrera, (2) al cerrar cada evento (los resultados oficiales quedan congelados en archivo), (3) tras cualquier cambio de esquema o migración, y siempre antes de una restauración.
- **Qué NO se respalda**: solo es operación la BD (esquema + datos). El storage de sesiones/cache/logs y los archivos temporales de Vite/Playwright no tienen valor operativo y quedan fuera; en v1 no hay aún assets de evento que preservar.

## 5. Emergencias

- **Reverb caído** — el sistema sigue: la pantalla pública y el result-board degradan al sondeo de 15 s (RF-76, degradación O1); la captura (estación y arrivals) y la persistencia no dependen del websocket (se persiste antes de emitir, ADR-013). Levantarlo de nuevo: `php artisan reverb:start`. Al reconectar, el resync por brecha (EP-26) repone los eventos perdidos del gap.
- **Cola atascada** — revisar el panel de Horizon (`/horizon`, pestaña *Failed*): las emisiones de broadcast van en cola `broadcast` con `afterCommit`. Reintentar lo fallido: `php artisan queue:retry all`; reiniciar el worker: `php artisan horizon:terminate` (el supervisor lo relanza) o reiniciar `php artisan dev`. Mientras la cola no corre, el vivo simplemente degrada a sondeo — nada se pierde.
- **Reversa básica** — restaurar el último backup de §4 (`gunzip -c … | mysql … tdchronos`) tras hacer un backup fresco del estado actual. Es la única vía en v1 para deshacer un cierre o una corrupción de datos: el estado `closed` no se revierte por UI.

## Referencias rápidas

- Cierre: `tiques/TQ-033`/`TQ-034`, `app/Actions/Events/CloseEvent.php` y `PreviewCloseEvent.php` (ISSUE-163).
- Estación offline y token: `tiques/TQ-021`/`TQ-022`, ADR-014, ISSUE-147/149/150.
- Tiempo real y canales: ADR-013, `routes/channels.php` (`event.{ulid}.public` sin PII / `event.{ulid}.ops` privado).
- Backups: `app/Console/Commands/OpsBackup.php` y `tests/Feature/Ops/OpsBackupTest.php` (TQ-035).
