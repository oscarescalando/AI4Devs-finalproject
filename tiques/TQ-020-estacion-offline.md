---
id: TQ-020
titulo: "Estación de cronometraje offline-first (EP-27, rebanada F1)"
tipo: plan
prioridad: P0
estimacion: XL
estado: abierto
refiere_a: ISSUE-147, ISSUE-148, ISSUE-149, ISSUE-150, ISSUE-152
parte_de: —
rama: feature/entrega-2-ol-gt
creado: 2026-11-28
actualizado: 2026-11-28
---

# TQ-020 — Estación de cronometraje offline-first (EP-27, rebanada F1)

## Objetivo

La estación de meta como PWA local-first (ADR-014): funciona SIN Internet capturando dorsales
en IndexedDB con `client_mark_id` generado en cliente, sincroniza de forma idempotente contra
`/api/v1` cuando hay red, y su reloj se corrige por offset contra la hora oficial.

## Contexto

- PWA base ya montada (TQ-006: manifest/SW/offline); API idempotente por lotes lista (ISSUE-156,
  con assigned/orphan/duplicate); `GET /time` da la hora oficial para el offset.
- ADR-014: captura local Alpine + IndexedDB; SIN sesión web — la estación se autentica con
  token de dispositivo Sanctum (`device:marks`) guardado localmente tras el emparejamiento.
- Diferidos F1: doble estación con evacuación de cola (ISSUE-151), piloto en evento real y
  medición M3 (ISSUE-152 completo), modo solo-teclado avanzado.

## Fases

- [ ] [TQ-021](TQ-021-shell-y-captura.md) — Shell de la estación: emparejamiento + captura local en IndexedDB
- [ ] [TQ-022](TQ-022-sync-idempotente.md) — Sync idempotente por lotes + reloj con offset
- [ ] [TQ-023](TQ-023-e2e-offline.md) — E2E offline (Playwright) y doble envío

## Siguiente paso

Ejecutar TQ-021 (shell + captura local).

## Criterios de aceptación

- [ ] Captura ≥ 500 marcas offline sin pérdida; sync sin duplicados (idempotencia EXTREMA a
      extremo probada); reloj con desfase corregido (< 1 s).
- [ ] La estación funciona con red caída de punta a punta (E2E).

## Bitácora

| Fecha | Estado | Nota |
|-------|--------|------|
| 2026-11-28 | abierto | Creado al continuar tras cerrar EP-26 |
