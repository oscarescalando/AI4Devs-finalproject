---
id: TQ-012
titulo: "Captura de llegadas en vivo (ISSUE-130)"
tipo: feature
prioridad: P0
estimacion: L
estado: en-curso
refiere_a: ISSUE-130
parte_de: TQ-011
rama: feature/entrega-2-ol-gt
creado: 2026-11-27
actualizado: 2026-11-27
---

# TQ-012 — Captura de llegadas en vivo (ISSUE-130)

## Objetivo

Pantalla operativa para registrar llegadas desde teclado (dorsal + Enter): el juez/cronometrador
marca llegadas manualmente, cada una dispara el recálculo y la clasificación se mueve — sin
duplicados y con huérfanas visibles para su resolución.

## Contexto

- ISSUE-130 (EP-25): captura de llegada en vivo, mínima verificable de F1.
- Reutiliza el conducto existente: `RecordTimingMarkBatch` (idempotencia por `client_mark_id`,
  assigned/orphan/duplicate) + job `RecalculateEventResultsJob` `afterCommit` (INV-07/08).
- Rutas por evento y permisos ya montados (TQ-004); kit `x-ui.*` (TQ-003); guía §5/§6/§8.

## Contratos públicos

- Nuevos: ruta `events.arrivals` (`/events/{event}/arrivals`, auth + `permission:marks.manage`),
  Volt funcional `events/arrival-capture`, Action `RegisterManualArrival` (envuelve el lote de
  una marca con `client_mark_id` UUIDv7 del servidor y source manual).
- Modificados: lista de llegadas recientes en la propia pantalla; nada más.

## Tareas

- [ ] Action RegisterManualArrival: valida evento/inscripción, delega en RecordTimingMarkBatch
      (una marca), devuelve estado + datos para la UI.
- [ ] Pantalla Volt: input de dorsal grande y autofocus (solo teclado, Enter envía), foco
      recupera tras cada envío, bloqueo de doble envío, `wire:loading` acotado.
- [ ] Llegadas recientes (últimas 15): dorsal mono tabular, nombre, hora del día, badge de
      estado; resaltado de la última.
- [ ] Huérfanas visibles (dorsal sin inscripción) con aviso accionable y duplicados rechazados
      sin crear fila (INV-02).
- [ ] Textos por lang/ y sin PII más allá de nombre/club.
- [ ] Pruebas: assigned dispara el job (Queue::fake), orphan y duplicate reportados, 403 sin
      marks.manage, render por rol, foco/input en la respuesta.
- [ ] Verificar: ./vendor/bin/pint && ./vendor/bin/phpstan analyse && ./vendor/bin/pest
- [ ] DETENERSE: presentar los cambios para revisión y sugerir mensaje de commit;
      no continuar con otra actividad sin aprobación

## Criterios de aceptación

- [ ] ISSUE-130 (versión mínima F1): captura por teclado idempotente, huérfanas visibles,
      recálculo en cola sin bloquear la captura, usable con una mano durante la carrera.

## Bitácora

| Fecha | Estado | Nota |
|-------|--------|------|
| 2026-11-27 | abierto | Creado como fase 1 del plan TQ-011 |
| 2026-11-27 | en-curso | Delegado a subagent implementador (orquestador) |
