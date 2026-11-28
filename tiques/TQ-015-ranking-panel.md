---
id: TQ-015
titulo: "Ranking del panel: clasificación del evento"
tipo: feature
prioridad: P1
estimacion: M
estado: en-curso
refiere_a: ISSUE-137, ISSUE-158
parte_de: TQ-011
rama: feature/entrega-2-ol-gt
creado: 2026-11-27
actualizado: 2026-11-27
---

# TQ-015 — Ranking del panel: clasificación del evento

## Objetivo

Clasificación operativa dentro del panel (`events.results`, results.view): posiciones calculadas
SOLO por el backend leyendo la tabla derivada `results` (INV-07), filtros por carrera y categoría,
diferencia con el primero y estado provisional/oficial — la vista del organizador, complemento de
la proyección pública sin sesión (ISSUE-137, ya viva en /e/{slug}).

## Contexto

- `results` solo la escribe `RecalculateEventResults` (regla Pest Arch de TQ-008) con rangos
  general/género/categoría y net_ms — fuente de esta pantalla.
- Placeholder `events.results` a sustituir (TQ-004); la variante pública no se toca.
- Diferidos: actualización por delta del canal < 2 s (EP-26, ISSUE-142/146; interino
  wire:poll.10s), filas DSQ rojas (penalización/descalificación no construida aún — ISSUE
  heredada del juez), exportaciones (ISSUE-079/F3).

## Contratos públicos

- Nuevos: Action `ListEventClassification` (lee results con joins públicos, jamás calcula
  posiciones), Volt `events/result-board` real.
- Modificados: nada fuera de la pantalla.

## Tareas

- [ ] Action ListEventClassification: paginación server-side (25) + filtros carrera/categoría;
      columnas pos general/categoría/género, dorsal mono tabular, nombre, club, net
      (FormatDuration), diferencia con el primero, estado provisional/oficial del evento.
- [ ] Volt result-board: contadores de llegadas/pendientes, tabla del kit con filtros
      (selects), badge de estado, wire:poll.10s, empty-state sin resultados.
- [ ] Conciliación: lo mostrado coincide con el output del motor tras recalcular (prueba).
- [ ] Permisos: results.view (403 para timekeeper, 200 organizer/official — matriz ISSUE-159).
- [ ] Textos por lang/; sin PII más allá de nombre/club.
- [ ] Verificar: ./vendor/bin/pint && ./vendor/bin/phpstan analyse && ./vendor/bin/pest
- [ ] DETENERSE: presentar los cambios para revisión y sugerir mensaje de commit;
      no continuar con otra actividad sin aprobación

## Criterios de aceptación

- [ ] Posiciones/diferencias 100 % del backend (results), conciliación probada contra el motor.
- [ ] Filtros por carrera y categoría operativos; estado provisional/oficial visible.

## Bitácora

| Fecha | Estado | Nota |
|-------|--------|------|
| 2026-11-27 | abierto | Creado como fase del plan TQ-011 |
| 2026-11-27 | en-curso | Alcance fijado (variante panel de ISSUE-137); diferidos documentados; delegado a subagent |
