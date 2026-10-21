# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Estado del repositorio (Entrega 1)

TDChronos 2.0: plataforma de cronometraje, resultados en vivo y liga de carreras de calle, trail y OCR. Proyecto final de AI4Devs de Oscar Lobo y Gabriel Toro.

**Este repositorio aún no contiene código de aplicación.** La Entrega 1 es especificación pura (Specification-Driven Development); la construcción de código arranca con la fase F1 del roadmap. Lo que existe:

- `spec/` — fuente única de verdad del producto y la arquitectura: PRD (01), SRS (02), ADRs 010–018 (03-arquitectura/), épicas EP-24…27 con issues ISSUE-105…155 (04-backlog/), DoD G1–G15 (05), invariantes INV-01…14 (06), estrategia de pruebas (07), roadmap fases F1–F4 e hitos M0–M6 (08).
- `readme.md` — documento de entrega con la plantilla AI4Devs (arquitectura, modelo de datos, OpenAPI de `/api/v1`, HU y tickets de ejemplo).
- `prompts.md` — prompts del curso, uno por sección del readme.

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

En esta rama no hay nada que compilar, lintar ni probar: no existen composer.json, artisan ni suites ejecutables. El tooling exacto (invocación de Pest, de un test concreto y de Playwright) se fija en F1. Los comandos comprometidos en la documentación de entrega son:

```bash
# Instalación limpia prometida en readme.md (cuando exista el código)
composer install && npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed        # BD desde cero + datos sintéticos + usuarios nuevos
npm run dev                       # o npm run build
docker compose up -d              # stack completo: app, MySQL 8.4, Redis, Reverb, Horizon

# Demo reproducible
php artisan migrate:fresh --seed  # deja un entorno demo utilizable
```

## Git y commits

- Una rama `feature/entrega-N-<iniciales>` por entrega (actual: `feature/entrega-1-ol-gt`), integrada a `main` por PR; el código seguirá una PR por épica. Toda PR debe cumplir la DoD G1–G15.
- Conventional Commits **en español y sin metadatos de autoría** (nada de Co-Authored-By, Signed-off-by ni «Generated with»). Un hook PreToolUse (`.claude/hooks/validate-commit.sh`) bloquea cualquier `git commit` con esos trailers y escanea el stage en busca de secretos.
- El flujo completo de commit (verificar cambios, escanear secretos, redactar mensaje, bump SemVer, CHANGELOG, push) está automatizado en el skill `commit`.

## Nota sobre `.claude/`

Los agentes (`agente-python`, `agente-laravel`, `agente-documentacion`, `orquestador`) y los skills `arq-planificar` y `docs-*` proceden del proyecto anterior del curso (microservicio de pagos) y mencionan rutas que aquí no existen (`payment_service/`, `docs/python`, `docs/laravel`): no apliques esas referencias de archivos. Vigente de esa configuración: el hook `validate-commit.sh`, el skill `commit` y los permisos de git de `settings.json`.
