# Changelog

Todos los cambios notables de este proyecto se documentan aquí.
Formato basado en [Keep a Changelog](https://keepachangelog.com/es/1.1.0/); versionado SemVer.

## [0.4.0] - 2026-11-23
### 🚀 Agregado
- Motor de resultados del servidor (ISSUE-158): `RecalculateEventResults` computa `net_ms = primera llegada − salida real + Σ ajustes` con `RANK() OVER` por carrera, género y categoría (aritmética SQL a prueba de desbordes), reemplaza la tabla derivada `results` en una transacción y marca provisional/official según el estado del evento. Job en cola `afterCommit` disparado por cada lote de marcas — la respuesta de la marca nunca espera al recálculo (INV-07/INV-08).
- Leaderboard público `GET /api/v1/public/events/{ulid}/leaderboard`: sin login ni PII (INV-06), menores con iniciales, contrato `v`/`seq` para el sondeo de la pantalla en vivo y `Cache-Control` corto.
- Precisión de milisegundos garantizada en `timing_marks.captured_at` y las horas de oleada (`dateFormat` con `.v`).
- Tests del motor (net con ajustes, empates RANK, clasificación por carrera, > 24 h, disparo por marca, conciliación en vivo ↔ oficial) y del leaderboard (PII, menores, seq creciente); regla de arquitectura: `results` solo desde el motor. Suite: 36 tests.

## [0.3.0] - 2026-11-23
### 🚀 Agregado
- Esqueleto Laravel 13 / PHP 8.4 del monolito en la raíz del repo (ISSUE-105): Sanctum con abilities para dispositivos, aliases `ability`/`abilities`, sesión database sobre `accounts`, timezone `America/Caracas`, locale de Faker `es_VE`.
- Migraciones del núcleo con pruebas de esquema (ISSUE-157): directorio (persona ≠ cuenta), eventos/carreras/oleadas/categorías, inscripciones con `UNIQUE (event_id, bib)` y `(event_id, person_id)` + CHECK persona XOR equipo, `timing_marks` append-only con triggers que rechazan UPDATE/DELETE en MySQL, correcciones auditadas y `results` derivado. PK `bigint` + ULID público (trait `HasPublicUlid`).
- API `/api/v1` (ISSUE-156): `GET /time` (hora oficial), `POST /events/{ulid}/timing-marks` (lote transaccional idempotente por `client_mark_id`, respuesta assigned/orphan/duplicate) y `GET /events/{ulid}/entries` (roster de la estación con solo campos públicos; menores con iniciales).
- Seeders sintéticos (ADR-015): evento demo `vuela-caracas-21k` con 114 inscritos, 4 menores para INV-06 y admin bootstrap.
- Calidad: Pest 26 tests (esquema + API + arquitectura Pest Arch) contra MySQL real, Larastan nivel 8 limpio, Pint aplicado, assets Vite compilados.

## [0.2.0] - 2026-11-23
### 📝 Documentación
- Correcciones del feedback de la entrega 1 (revisión LIDR): alcance recortado a un **primer corte** explícito en `readme.md` (evento sembrado → estación offline → clasificación en servidor → pantalla pública), con cada funcionalidad marcada como [Corte 1]/[v2]/[Simulado], y bocetos de la pantalla de estación y de la clasificación pública.
- Modelo de datos: clave primaria unificada en el diagrama (`bigint` PK + ULID `char(26)` público UNIQUE, el entero nunca sale al exterior) y liga movida a un diagrama v2 sin la relación "vía standings".
- API: endpoints nuevos de emparejamiento de la estación (`POST /station/pairing`) y descarga del roster de inscritos (`GET /events/{e}/entries`) en `readme.md` y `spec/02-srs.md` §5.3.
- Invariante INV-06 precisada: nombre y club públicos por diseño, iniciales/pseudónimo para menores.
- Backlog: issues nuevas ISSUE-156 (endpoint idempotente de marcas), ISSUE-157 (migraciones del núcleo con pruebas de esquema) e ISSUE-158 (motor de resultados) — traen al repo las piezas heredadas que necesita el primer corte; ISSUE-149 reclasificada como cliente de la estación.
- `.claude/` limpiado: eliminados agentes y skills del proyecto anterior de pagos; `spec-gestionar` reescrito para las rutas de `spec/`.

## [0.1.1] - 2026-10-20
### 📝 Documentación
- `readme.md`: línea de autores en el encabezado del documento, alineada con `spec/README.md` (Oscar Ramon Lobo Altuve · Gabriel Toro, con sus emails).

## [0.1.0] - 2026-10-20
### 🚀 Agregado
- Especificación de la Entrega 1 de TDChronos 2.0 en `spec/`: PRD, SRS, ADR-010…018, épicas EP-24…27 (issues ISSUE-105…155), DoD G1–G15, invariantes INV-01…14, estrategia de pruebas y roadmap F1–F4 (hitos M0–M6).
- `readme.md` de la entrega con la plantilla AI4Devs (ficha, arquitectura, modelo de datos, API `/api/v1`, historias y tickets de ejemplo, política de PRs) y `prompts.md` del curso.
- Base del repositorio: `.gitignore`, `CLAUDE.md` y configuración `.claude/` (hook de validación de commits, skills y agentes).
