# Changelog

Todos los cambios notables de este proyecto se documentan aquí.
Formato basado en [Keep a Changelog](https://keepachangelog.com/es/1.1.0/); versionado SemVer.

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
