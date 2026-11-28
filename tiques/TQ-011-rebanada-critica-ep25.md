---
id: TQ-011
titulo: "Rebanada crítica de EP-25: pantallas operativas del día de carrera"
tipo: plan
prioridad: P0
estimacion: XL
estado: abierto
refiere_a: ISSUE-130, ISSUE-132, ISSUE-133, ISSUE-134, ISSUE-135, ISSUE-137
parte_de: —
rama: feature/entrega-2-ol-gt
creado: 2026-11-27
actualizado: 2026-11-27
---

# TQ-011 — Rebanada crítica de EP-25: pantallas operativas del día de carrera

## Objetivo

Construir la rebanada de paridad crítica de EP-25 que F1 exige: captura de llegadas, salidas,
bitácora con corrección y ranking del panel — las pantallas reales contra las que el caos de
EP-26 (Reverb) y el piloto de EP-27 (estación) se miden.

## Contexto

- F1 (backlog §6): la rebanada se adelanta «porque el caos de EP-26 y el piloto de EP-27
  necesitan pantallas reales contra las que medir».
- Base lista: núcleo (ISSUE-156/158), rutas por evento con `permission:` (TQ-004), kit `x-ui.*`
  (TQ-003), guía `spec/09-ui-estilo.md`.
- Base de trabajo de la sesión: `feature/entrega-2-ol-gt` (acuerdo: sin envíos a main).

## Fases

- [x] [TQ-012](TQ-012-captura-de-llegadas.md) — Captura de llegadas en vivo (ISSUE-130)
- [x] [TQ-013](TQ-013-salidas.md) — Salidas: tablero con reloj y salida real de oleada (ISSUE-132/133)
- [x] [TQ-014](TQ-014-bitacora-y-correccion.md) — Registros: bitácora en vivo y corrección 1/2/3 (ISSUE-134/135)
- [ ] [TQ-015](TQ-015-ranking-panel.md) — Ranking del panel: clasificación del evento (ISSUE-137, variante panel)

## Siguiente paso

Ejecutar TQ-015 (Ranking del panel): última fase de la rebanada.

## Criterios de aceptación

- [ ] Las cuatro fases cerradas con sus criterios verificados y suite completa en verde.
- [ ] Flujo punta a punta manual posible: dar salida → capturar llegadas → corregir una marca →
      ver la clasificación en panel y en la pantalla pública.
- [ ] Sin PII beyond nombre/club en pantallas operativas (INV-06/RNF-13) y todo texto por lang/.

## Bitácora

| Fecha | Estado | Nota |
|-------|--------|------|
| 2026-11-27 | abierto | Creado tras PR #1 verde; usuario fijó la sesión sobre entrega-2 sin tocar main |
