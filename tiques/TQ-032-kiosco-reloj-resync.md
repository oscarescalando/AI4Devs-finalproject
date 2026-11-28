---
id: TQ-032
titulo: "Kiosco por push + reloj pública con wave.started + resync por gap"
tipo: feature
prioridad: P1
estimacion: M
estado: hecho
refiere_a: ISSUE-141, ISSUE-142, ISSUE-143
parte_de: TQ-031
rama: feature/entrega-2-ol-gt
creado: 2026-11-28
actualizado: 2026-11-28
---

# TQ-032 — Kiosco por push + reloj pública con wave.started + resync por gap

## Objetivo

La pantalla pública consuma el canal `.public` por push: resaltado inmediato de llegadas nuevas
(mark.recorded), reloj que se alinea a la oleada iniciada (wave.started emitido también a public
sin PII) y resincronización por gap de seq al reconectar (pide deltas, no la colección completa).

## Contexto

- Infra TQ-017/018 viva: tcBridge, mark.recorded@public {eventUlid,seq,bib,capturedAtMs},
  results.updated; leaderboard pública ya escucha tc-results. WaveStarted hoy solo a .ops.
- DEFER: suites nocturnas con WS real (runner del usuario); contador de pendientes del
  live-indicator; medición k6 de M3.

## Tareas

- [x] WaveStarted también a event.{ulid}.public (payload sin PII) con prueba.
- [x] Pública: escuchar mark.recorded → resaltado/fila inmediata sin esperar el refresh del
      sondeo (delta sobre lo visible; poll 15 s queda de respaldo).
- [x] Reloj de la pública: offset contra /time + alineación al recibir wave.started
      (hora oficial de salida como base del tiempo de carrera si aplica).
- [x] Resync por gap: al reconectar (o detectar hueco de seq), pedir el leaderboard una vez
      (contrato v/seq ya existente) en lugar de acumular; documentar la estrategia.
- [x] Pruebas: emisión a public sin PII; listeners registrados; gap dispara refetch unitario.
- [x] Verificar: ./vendor/bin/pint && ./vendor/bin/phpstan analyse && ./vendor/bin/pest
- [ ] DETENERSE: presentar los cambios para revisión y sugerir mensaje de commit;
      no continuar con otra actividad sin aprobación

## Criterios de aceptación

- [x] RF-54 en su versión F2: llegada reflejada por delta sin recargar la colección; reconexión
      sin huecos visibles.

## Bitácora

| Fecha | Estado | Nota |
|-------|--------|------|
| 2026-11-28 | abierto | Creado como fase del plan TQ-031 |
| 2026-11-28 | en-curso | Delegado a subagent (orquestador); noche autónoma |
| 2026-11-28 | hecho | Verificado por el orquestador: Pint ✓ · Larastan 8 con 0 errores · Pest 433/433 · build ✓. E2E REAL: push→fila resaltada 758 ms; reconexión tras matar Reverb → EXACTAMENTE 1 refetch (resync); wave.started→cronómetro 0:00:00; 0 errores JS. HALLAZGOS: pusher-js deja canales muertos al reconectar (auto-resuscripción añadida al puente) y bug preexistente en start-board (script antes del div raíz rompe el morph de Livewire 4 — el orquestador lo corrige a continuación). DEFERs: endpoint de deltas, sembrar salida vigente en el contrato, contador de pendientes, suites nocturnas/k6 |
