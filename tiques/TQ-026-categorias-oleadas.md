---
id: TQ-026
titulo: "Editor de categorías y oleadas + reprogramación en bloque"
tipo: feature
prioridad: P1
estimacion: L
estado: en-curso
refiere_a: ISSUE-160
parte_de: TQ-024
rama: feature/entrega-2-ol-gt
creado: 2026-11-28
actualizado: 2026-11-28
---

# TQ-026 — Editor de categorías y oleadas + reprogramación en bloque

## Objetivo

Sustituir los placeholders category-list y wave-list por editores reales dentro del evento:
categorías por rango (validación de coherencia) y oleadas con hora programada (datetime ms),
más la reprogramación en bloque de oleadas (hora inicial + intervalo) con una sola operación.

## Contexto

- Columnas EXACTAS en migración 000002: categories (name, rangos/género según esquema real)
  y waves (name, scheduled_start_at datetime(3), por carrera). Inspeccionar ANTES de escribir.
- Patrón EventList (TQ-025) como referencia de CRUD con kit y Actions; rutas events.categories
  y events.waves ya existen con events.manage.
- DEFER: cierre de evento como máquina de estados (ISSUE-124 completo) y clonado.

## Contratos públicos

- Nuevos: Actions CRUD de categorías y oleadas + ReprogramWaves (bloque); Volt/clase reales
  en las dos pantallas.
- Modificados: nada fuera de ellas.

## Tareas

- [ ] Categorías: lista + modal (nombre, rango según columnas reales), validaciones de
      coherencia (solapamientos/sin huecos según reglas del esquema), 403 por rol.
- [ ] Oleadas: lista por carrera + modal (nombre, hora programada con ms), alta/baja
      (baja bloqueada con inscripciones).
- [ ] Reprogramación en bloque: hora inicial + intervalo en minutos → recalcula las oleadas
      de UNA carrera en orden, UNA sola operación transaccional (ISSUE-050).
- [ ] Pruebas por rol, validaciones, bloqueos y bloque coherente.
- [ ] Verificar: ./vendor/bin/pint && ./vendor/bin/phpstan analyse && ./vendor/bin/pest
- [ ] DETENERSE: presentar los cambios para revisión y sugerir mensaje de commit;
      no continuar con otra actividad sin aprobación

## Criterios de aceptación

- [ ] ISSUE-160 completa: evento configurable de punta a punta desde el panel.

## Bitácora

| Fecha | Estado | Nota |
|-------|--------|------|
| 2026-11-28 | abierto | Creado como fase 2 del plan TQ-024 |
| 2026-11-28 | en-curso | Delegado a subagent (orquestador); noche autónoma |
