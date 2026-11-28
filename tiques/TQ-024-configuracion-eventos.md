---
id: TQ-024
titulo: "Configuración de eventos (F2 · EP-05/ISSUE-160)"
tipo: plan
prioridad: P1
estimacion: XL
estado: hecho
refiere_a: ISSUE-160
parte_de: —
rama: feature/entrega-2-ol-gt
creado: 2026-11-28
actualizado: 2026-11-28
---

# TQ-024 — Configuración de eventos (F2 · EP-05/ISSUE-160)

## Objetivo

CRUD real de la configuración: eventos con carreras, oleadas y categorías gestionables desde el
panel (adiós seeder para nacer eventos), con reprogramación de oleadas en bloque y validación de
servidor.

## Contexto

- ISSUE-160 nueva en el backlog (trae ISSUE-048…051 heredadas); placeholders ya enrutados.
- Esquema ya rico (races/waves/categories con FKs y horas ms); kit x-ui.* y patrón ManageOrganizations
  como referencia de CRUD.

## Fases

- [x] [TQ-025](TQ-025-crud-eventos-carreras.md) — CRUD de eventos y carreras
- [x] [TQ-026](TQ-026-categorias-oleadas.md) — Editor de categorías y oleadas + reprogramación en bloque

## Siguiente paso

Plan COMPLETO (2/2): ISSUE-160/EP-05 cerrada en su versión panel. Siguiente: EP-06 directorio
(paso 5 de la noche).

## Criterios de aceptación

- [x] Un evento nuevo nace completo desde el panel (evento + carreras) y se edita.
- [x] Categorías y oleadas editables con validaciones; reprogramación en bloque probada.
- [x] Suite completa en verde (350/350); permisos events.manage con 403 probado.

## Bitácora

| Fecha | Estado | Nota |
|-------|--------|------|
| 2026-11-28 | abierto | Creado en la noche autónoma; ISSUE-160 traída al backlog |
| 2026-11-28 | hecho | Cerrado: TQ-025 (0.24.0) + TQ-026 (0.25.0). DEFERs: columnas distance/type en races (cambio de esquema con spec), cierre de evento como máquina de estados (ISSUE-124), borrado (se cierran) |
