---
id: TQ-024
titulo: "Configuración de eventos (F2 · EP-05/ISSUE-160)"
tipo: plan
prioridad: P1
estimacion: XL
estado: abierto
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
- [ ] [TQ-026](TQ-026-categorias-oleadas.md) — Editor de categorías y oleadas + reprogramación en bloque

## Siguiente paso

Ejecutar TQ-026 (categorías, oleadas y reprogramación en bloque).

## Criterios de aceptación

- [ ] Un evento nuevo nace completo desde el panel (evento + carreras) y se edita.
- [ ] Categorías y oleadas editables con validaciones; reprogramación en bloque probada.
- [ ] Suite completa en verde; permisos events.manage con 403 probado.

## Bitácora

| Fecha | Estado | Nota |
|-------|--------|------|
| 2026-11-28 | abierto | Creado en la noche autónoma; ISSUE-160 traída al backlog |
