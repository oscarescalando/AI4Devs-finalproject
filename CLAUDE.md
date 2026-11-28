# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Estado del repositorio (Entrega 2 — fundaciones y núcleo del primer corte)

TDChronos 2.0: plataforma de cronometraje, resultados en vivo y liga de carreras de calle, trail y OCR. Proyecto final de AI4Devs de Oscar Lobo y Gabriel Toro.

**Ya hay código**: el esqueleto Laravel 13 + PHP 8.4 está montado (ISSUE-105) con el núcleo del primer corte:

- `spec/` — fuente única de verdad: PRD (01), SRS (02), ADRs 010–018 (03-arquitectura/), épicas EP-24…27 con issues ISSUE-105…158 (04-backlog/), DoD G1–G15 (05), invariantes INV-01…14 (06), estrategia de pruebas (07), roadmap F1–F4 (08).
- **Migraciones del núcleo (ISSUE-157)**: `persons`+`accounts` (persona ≠ cuenta), `events`/`races`/`waves`/`categories`, `entries` con UNIQUE dorsal/persona-evento y CHECK XOR, `timing_marks` append-only con triggers que rechazan UPDATE/DELETE, `entry_adjustments`, `timing_mark_corrections`, `results`. PK `bigint` + ULID `char(26)` público (trait `App\Models\Concerns\HasPublicUlid`); sin tablas de liga (v2).
- **API `/api/v1` (ISSUE-156)**: `GET /time`, `POST /events/{ulid}/timing-marks` (idempotente por `client_mark_id`, transaccional por lote) y `GET /events/{ulid}/entries` (roster público). Auth Sanctum con abilities `device:marks`/`device:roster`; controladores → Actions (ADR-012).
- **UI TALL (ISSUE-106)**: Livewire 4 + Volt + Alpine + Tailwind 4 vía Vite. Convención: **pantallas = componentes Volt funcionales** en `resources/views/livewire/`; **CRUD con acciones = clases Livewire** en `app/Livewire/`. Layouts anónimos en `resources/views/components/layouts/`.
- **Pantalla pública `/e/{slug}`** (ISSUE-137, corte 1): clasificación en vivo con sondeo `wire:poll.4s` al leaderboard, filtros por categoría/distancia, resaltado de llegadas nuevas, reloj, modo kiosco y sin PII (INV-06). Landing `/` con los eventos.
- **Motor de resultados (ISSUE-158)**: `RecalculateEventResults` + job `afterCommit` por cada lote de marcas; `results` solo se escribe desde el motor (regla Pest Arch).
- Seeders 100 % sintéticos: evento demo `vuela-caracas-21k` con 114 inscritos y admin bootstrap (`admin@tdchronos.test`).
- **Panel operativo (noche 27/11, plan TQ-001 completo)**: login por sesiones (documento o correo, bloqueo 5/min, intended, 419→login), roles/permisos spatie teams con **equipo 0 = plataforma** (ISSUE-159), layout con menú por permisos reales y tema oscuro, kit `x-ui.*` completo con toasts/confirmación, CRUD demo Organizaciones, mapa de rutas to-be (`/events/{ulid}/…` con `permission:`), perfil con invalidación de sesiones, PWA base (manifest + SW + offline), i18n es total (`lang/es/ui.php`, `FormatDate`, chequeo anti-literales) y CI de GitHub Actions con los 8 gates + Playwright smoke. **Día de carrera (rebanada crítica EP-25, TQ-011 completa)**: captura de llegadas `/events/{ulid}/arrivals` (solo teclado, idempotente, huérfanas visibles), tablero de salidas con reloj resincronizado y salida real/deshacer, bitácora `/marks` con estados derivados y corrección 1/2/3 (`EffectiveMarks`: la última corrección decide; marca original intacta), ranking del panel `/results` leyendo `results` (INV-07). Entorno: Herd (`ai4devs-finalproject.test`) o Laragon.
- Calidad: Pest (**253 tests** contra MySQL real: esquema + API + resultados + pantallas + arquitectura + rutas + PWA + i18n + captura/salidas/correcciones), Larastan nivel 8 limpio, Pint aplicado, Playwright smoke 3/3, `composer audit` limpio.
- **Registro de trabajo**: carpeta `tiques/` — un tique `TQ-NNN` por actividad (plan + fases), con plantilla única, bitácora e índice en `tiques/INDEX.md`; lo gestiona el skill `tiques-gestionar`. Nada de trabajo sin tique.

`spec/README.md` §5 mapea cada tipo de artefacto al archivo donde vive; §3 define el ciclo SDD; §4 recoge las reglas de oro del arranque limpio.

## Arquitectura objetivo (ADRs aceptados, no implementada aún)

Monolito modular: Laravel 13 + PHP 8.4, MySQL 8.4, Redis 7, Horizon, Reverb, Sanctum y spatie/laravel-permission (ADR-010). Reglas estructurales que cruzan todos los ADRs:

- **La UI no consume la API.** UI TALL (Livewire 4 + Volt + Alpine + Blade) dentro del monolito: los componentes Livewire llaman a Actions de dominio. Flujo obligatorio: componente/endpoint → Action de dominio → modelo (ADR-011, ADR-012, verificada con pruebas de arquitectura Pest Arch).
- **API REST `/api/v1` solo para consumidores no-web** (estación de cronometraje, app móvil): token de dispositivo y lotes idempotentes por `client_mark_id`; el leaderboard público no expone PII (ADR-012, INV-06).
- **Tiempo real con Reverb**: canales `event.{ulid}.public` (solo lectura, sin PII) y `event.{ulid}.ops` (privado); persistir antes de emitir; broadcast en cola con `afterCommit` (ADR-013, INV-08).
- **Estación de meta offline local-first**: PWA con IndexedDB que funciona sin Internet (ADR-014); espiga técnica obligatoria en F1.
- **Modelo de datos** (ADR-015, SRS §5): ULID como identificador público del núcleo, tiempos en milisegundos (`bigint`), marcas inmutables append-only (nada actualiza `timing_marks`; las correcciones son eventos con motivo y auditoría), posición nunca calculada en cliente.
- PDF con dompdf (ADR-017); E2E con Playwright en las suites `smoke`, `cu-paridad` y `estacion-offline` (ADR-018); SaaS multitenant pospuesto a F4 (ADR-016).

## Proceso SDD y puertas de calidad

- Ciclo por historia/épica: SPEC → PLAN → TASKS → BUILD → TEST (spec/README.md §3). **Cualquier desviación de la especificación exige un ADR nuevo o una enmienda** registrada en spec/03-arquitectura/.
- Los identificadores (RF/RNF/HU/CU/EP/ISSUE/ADR/G/M/O/INV) **nunca se renumeron**: las series continúan las del corpus de origen (spec/README.md §2).
- Condición de merge: DoD G1–G15 (spec/05-dod.md) más los 8 gates de CI en orden (readme §2, spec/07-tests.md §3): Pint → Larastan nivel 8 → Pest con cobertura ≥ 80 % en Timing/Results/Registration → Pest Arch → gitleaks → `composer audit` → Playwright smoke → build de la imagen Docker. Las suites `cu-paridad` y `estacion-offline` corren en CI nocturno.

## Reglas de oro del arranque limpio

- La base de datos nace íntegramente de migraciones versionadas y reversibles; **no se migra nada del sistema legacy** (enmienda a ADR-015, 2026-10-20).
- Seeders 100 % sintéticos con usuarios nuevos (admin bootstrap por seeder); nunca datos reales de personas en el repo ni en desarrollo.
- Código e identificadores en inglés; UI y documentación en español. Zona horaria de evento: `America/Caracas`.
- Sin secretos en el repositorio: `.env.example` con marcadores, credenciales solo en `.env`; gitleaks en CI. Cero URLs de repositorios externos en los entregables.

## Comandos

Entorno local (Laragon, PHP 8.4 en `C:\laragons\bin\php\php-8.4.16-Win32-vs17-x64`):

```bash
composer install && npm install
cp .env.example .env            # y ajustar credenciales MySQL
php artisan key:generate
php artisan migrate --seed      # BD desde cero + evento demo (114 inscritos) + admin bootstrap
npm run build                   # o npm run dev

# Calidad (en este orden, como los gates de CI)
./vendor/bin/pint               # estilo
./vendor/bin/phpstan analyse    # Larastan nivel 8 (app + database)
./vendor/bin/pest               # 26 tests contra MySQL real (crea tdchronos_testing)
```

La BD de desarrollo es `tdchronos` (MySQL 8.0 de Laragon); Pest usa `tdchronos_testing`. Admin demo: `admin@tdchronos.test` / `tdchronos-admin-2026` (sintético).

```bash
php artisan serve          # y abrir /  o  /e/vuela-caracas-21k
```

## Git y commits

- Una rama `feature/entrega-N-<iniciales>` por entrega (actual: `feature/entrega-2-ol-gt`), integrada a `main` por PR; el código seguirá una PR por épica. Toda PR debe cumplir la DoD G1–G15.
- Conventional Commits **en español y sin metadatos de autoría** (nada de Co-Authored-By, Signed-off-by ni «Generated with»). Un hook PreToolUse (`.claude/hooks/validate-commit.sh`) bloquea cualquier `git commit` con esos trailers y escanea el stage en busca de secretos.
- El flujo completo de commit (verificar cambios, escanear secretos, redactar mensaje, bump SemVer, CHANGELOG, push) está automatizado en el skill `commit`.

## Nota sobre `.claude/`

Vigente y adaptado a este repositorio: el hook `validate-commit.sh` (bloquea autoría y secretos en commits), el skill `commit` (flujo completo de commit), el skill `spec-gestionar` (control de cambios de `spec/` con su mapa de artefactos), el skill `tiques-gestionar` (registro y control del trabajo en `tiques/` con plantilla única) y el skill `memoria-gestionar`. El agente `orquestador` (`.claude/agents/orquestador.md`) coordina el trabajo por tiques: valida la fase cerrada, delega implementación a subagentes, corre los gates de calidad y commitea. Los agentes y skills del proyecto anterior del curso (microservicio de pagos) fueron eliminados en la entrega 2 tras el feedback de la entrega 1.
