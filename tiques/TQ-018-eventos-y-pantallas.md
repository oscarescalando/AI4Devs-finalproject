---
id: TQ-018
titulo: "Eventos de dominio afterCommit y pantallas escuchando"
tipo: feature
prioridad: P0
estimacion: L
estado: hecho
refiere_a: ISSUE-141, ISSUE-142, ISSUE-143
parte_de: TQ-016
rama: feature/entrega-2-ol-gt
creado: 2026-11-27
actualizado: 2026-11-27
---

# TQ-018 — Eventos de dominio afterCommit y pantallas escuchando

## Objetivo

Las cinco pantallas vivas (captura, salidas, bitácora, ranking, pública) reaccionan por WS a los
eventos de dominio emitidos tras commit (MarkRecorded, WaveStarted/WaveUndone, ResultsUpdated),
con live-indicator de conexión (G13) y sondeo de respaldo degradado a 15 s (RF-76). Contrato v/seq
en su versión mínima: cada evento lleva `seq` (id creciente del dato que lo originó) y las
pantallas refrescan por delta simple (refresco dirigido, sin recargar colecciones completas en
cada tick).

## Contexto

- Infra lista (TQ-017): tcEcho con `tc-echo-status`, canales public/ops, WaveStarted ya emitido
  desde MarkWaveStart a `.ops`.
- Emisores existentes: RecordTimingMarkBatch (marcas, también desde RegisterManualArrival),
  MarkWaveStart/UndoWaveStart, RecalculateEventResultsJob (recálculo).
- Pantallas: arrival-capture, start-board, mark-log, result-board (panel) y leaderboard pública.

## Contratos públicos

- Nuevos: eventos MarkRecorded (ops con detalle + public sin PII: solo bib/hora/seq),
  WaveUndone, ResultsUpdated (public+ops, seq = versión del recálculo).
- Modificados: emisores anteriores (emitir tras commit), pantallas (listeners + live-indicator
  + polls a 15 s), layout panel/público (indicador cuando la pantalla lo pide).

## Tareas

- [x] Eventos nuevos + emisión afterCommit desde batch/motor/deshacer (rollback no emite).
- [x] Pantallas panel: listeners que refrescan SU dato (lista recientes, tablero, bitácora,
      ranking) y live-indicator ligado a tc-echo-status.
- [x] Pantalla pública: ResultsUpdated la refresca sin PII; live-indicator; poll a 15 s.
- [x] Sondeo de respaldo 15 s en todas (RF-76) — hoy 4-10 s.
- [x] Pruebas: emisión encolada por acción (Queue::fake), listeners registrados en los Volt,
      refresco dirigido tras dispatch del evento, sin PII en canal public.
- [x] Verificar: ./vendor/bin/pint && ./vendor/bin/phpstan analyse && ./vendor/bin/pest
- [ ] DETENERSE: presentar los cambios para revisión y sugerir mensaje de commit;
      no continuar con otra actividad sin aprobación

## Criterios de aceptación

- [x] ISSUE-142 mínima: puente operativo — llegada/salida/corrección se reflejan por WS en las
      pantallas abiertas sin intervención, con respaldo por sondeo si el WS cae.
- [x] ISSUE-141 mínima: contrato uniforme {eventUlid, seq, …} y nada de PII en canal public.

## Bitácora

| Fecha | Estado | Nota |
|-------|--------|------|
| 2026-11-27 | abierto | Creado como fase 2 del plan TQ-016 |
| 2026-11-27 | en-curso | Delegado a subagent (orquestador) |
| 2026-11-27 | hecho | Verificado por el orquestador: Pint ✓ · Larastan 8 con 0 errores · Pest 278/278 (13 nuevas) · polls 15 s en las cinco pantallas ✓ · E2E REAL del agente: llegada por tinker visible en pública y bitácora en 1,6 s por WS; Reverb muerto → indicador degrada <1 s y el poll de 15 s mantiene ambas vivas. Contrato: seq = timing_marks.id (mark) y MAX(results.id) (results); duplicate no emite; emisión de ResultsUpdated vive en el job. Fixes a infra TQ-017 documentados (estados de conexión nunca se emitían). Pendientes para TQ-019/EP-26 completo: kiosco de llegadas por push, reloj pública con wave.started, contador de pendientes del indicador, resaltado por push, resync por gap de seq |
