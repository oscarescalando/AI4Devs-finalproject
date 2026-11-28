---
id: TQ-030
titulo: "Alta individual con categoría automática, dorsal y listado"
tipo: feature
prioridad: P0
estimacion: L
estado: hecho
refiere_a: ISSUE-162
parte_de: TQ-029
rama: feature/entrega-2-ol-gt
creado: 2026-11-28
actualizado: 2026-11-28
---

# TQ-030 — Alta individual con categoría automática, dorsal y listado

## Objetivo

Pantalla de inscripciones real en `/events/{event}/entries`: listado con filtros
(carrera/categoría/estado + búsqueda dorsal/nombre) y modal de alta individual con picker de
personas, categoría automática previsualizada por edad/género y dorsal automático editable.

## Contexto

- entry-list de TQ-004 es la base (tabla) → evolucionar a gestor con Actions CRUD.
- CategoryRange ya deriva rangos; ListPersons alimenta el picker (documento enmascarado);
  UNIQUE (event,person)/(event,bib) con CHECK persona XOR equipo (equipo DEFER: alta individual
  solamente).
- DEFER: ajustes acumulables, recategorización en bloque, inscripciones de equipo, importación.

## Tareas

- [x] ListEntries con filtros #[Url] + búsqueda; tabla con dorsal mono, atleta (menores
      iniciales), categoría, carrera, estado badge.
- [x] Action CreateEntry: picker persona (búsqueda parcial, sin duplicar en el evento),
      carrera/oleada del evento, categoría AUTOMÁTICA (edad al día del evento + género por
      token) previsualizada en vivo antes de guardar, dorsal = siguiente libre + 1 (editable,
      único); transacción; CHECK XOR respetado (persona siempre).
- [x] Baja lógica (status cancelled) sin borrado físico; reactivación.
- [x] Pruebas: categoría automática por dataset de edades/géneros (incluye fuera de rango →
      error accionable), dorsal automático consecutivo y choque de uniqueness con error claro,
      duplicado persona/evento rechazado (INV-04), filtros, 403 entries.manage vs entries.view.
- [x] Verificar: ./vendor/bin/pint && ./vendor/bin/phpstan analyse && ./vendor/bin/pest
- [ ] DETENERSE: presentar los cambios para revisión y sugerir mensaje de commit;
      no continuar con otra actividad sin aprobación

## Criterios de aceptación

- [x] ISSUE-162 completa en su rebanada.

## Bitácora

| Fecha | Estado | Nota |
|-------|--------|------|
| 2026-11-28 | abierto | Creado como fase del plan TQ-029 |
| 2026-11-28 | en-curso | Delegado a subagent (orquestador); noche autónoma |
| 2026-11-28 | hecho | Verificado por el orquestador: Pint ✓ · Larastan 8 con 0 errores · Pest 432/432 (30 nuevas) · humo 302. CategoryMatch como regla única compartida entre preview y persistencia (lo visible es lo guardado); caída a categorías sin token para menores; INV-03/04 con errores de campo claros (duplicado de persona cubre canceladas con sugerencia de reactivar); picker con documento enmascarado. DEFERs: ajustes, recategorización en bloque, equipos, importación |
