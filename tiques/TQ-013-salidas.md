---
id: TQ-013
titulo: "Salidas: tablero con reloj y salida real de oleada"
tipo: feature
prioridad: P1
estimacion: M
estado: en-curso
refiere_a: ISSUE-132, ISSUE-133
parte_de: TQ-011
rama: feature/entrega-2-ol-gt
creado: 2026-11-27
actualizado: 2026-11-27
---

# TQ-013 — Salidas: tablero con reloj y salida real de oleada

## Objetivo

Tablero de salidas del evento ( ISSUE-132/133 en versión mínima F1): reloj vivo resincronizado
con el servidor, widgets por oleada (programada/inscritos/estado) y la acción «Marcar salida»
que guarda la hora real sin tocar la programada, con deshacer confirmado.

## Contexto

- `waves` ya tiene `scheduled_start_at` + `actual_start_at` datetime(3) — ISSUE-133 soportado
  por el esquema; `GetServerTime` (Actions/Timing) da la hora oficial para el reloj con offset.
- Ruta placeholder `events.start` (start.manage) ya montada (TQ-004); kit y guía como siempre.
- **Diferido documentado**: check-in confirmar/desmarcar y contadores de confirmados exige
  columna que `entries` no tiene — es ISSUE-052 heredada (F2, tocaría spec/esquema). El
  broadcast `wave.started` afterCommit y la propagación < 2 s son EP-26 (ISSUE-143/145);
  interino: `wire:poll`. La auditoría del deshacer (ISSUE-045) no tiene tabla aquí: llega con
  F2; se registra el cambio vía `updated_at` y bitácora de sesión.

## Contratos públicos

- Nuevos: Actions `MarkWaveStart` y `UndoWaveStart` (final readonly), `ListWaveBoard` (oleadas
  con contadores sobre el TOTAL de la oleada, no la página — RF-16), `GetEventClock` (offset
  del servidor); Volt `events/start-board` real sustituyendo el placeholder.
- Modificados: nada fuera de la pantalla y su ruta (la ruta ya existe).

## Tareas

- [ ] ListWaveBoard: oleadas con inscritos totales, programada, real y estado derivado
      (Pendiente/Iniciada) — contadores sobre el total (RF-16).
- [ ] Reloj vivo: hora del servidor vía offset (GetServerTime/GetEventClock), re-resincroniza
      cada 60 s, deriva < 1 s/h (RF-42), mono tabular.
- [ ] «Marcar salida» SOLO en la primera oleada sin real; confirmación que muestra la hora de
      sistema; guarda actual_start_at sin tocar scheduled (RF-15).
- [ ] Deshacer salida con confirmación (tcConfirm) y ventana de aviso; autorización server-side
      (permission start.manage + 403 probado).
- [ ] Textos por lang/; toasts en cada acción; wire:poll.5s del tablero.
- [ ] Pruebas: contadores sobre total con > 100 inscritos paginados, marcar/deshacer con sus
      guardas (solo primera sin marcar; no sobrescribe programada), 403 sin permiso, reloj
      (offset calculado), render por rol.
- [ ] Verificar: ./vendor/bin/pint && ./vendor/bin/phpstan analyse && ./vendor/bin/pest
- [ ] DETENERSE: presentar los cambios para revisión y sugerir mensaje de commit;
      no continuar con otra actividad sin aprobación

## Criterios de aceptación

- [ ] ISSUE-132 mínima: tablero con reloj resincronizado y contadores sobre el total
      (check-in y broadcast diferidos con nota en EP-25).
- [ ] ISSUE-133 mínima: programada ≠ real como columnas distintas, deshacer confirmado,
      autorización probada (broadcast/auditoría diferidos a EP-26/F2).

## Bitácora

| Fecha | Estado | Nota |
|-------|--------|------|
| 2026-11-27 | abierto | Creado como fase del plan TQ-011 |
| 2026-11-27 | en-curso | Alcance fijado tras leer ISSUE-132/133 y el esquema; diferidos documentados; delegado a subagent |
