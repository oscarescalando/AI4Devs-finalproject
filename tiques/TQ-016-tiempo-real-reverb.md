---
id: TQ-016
titulo: "Tiempo real con Reverb en las pantallas (EP-26, rebanada F1)"
tipo: plan
prioridad: P0
estimacion: XL
estado: abierto
refiere_a: ISSUE-141, ISSUE-142, ISSUE-143, ISSUE-144, ISSUE-145, ISSUE-146
parte_de: —
rama: feature/entrega-2-ol-gt
creado: 2026-11-27
actualizado: 2026-11-27
---

# TQ-016 — Tiempo real con Reverb en las pantallas (EP-26, rebanada F1)

## Objetivo

Sustituir los `wire:poll` interinos por tiempo real de primera parte (ADR-013): eventos de
dominio emitidos `afterCommit` (INV-08/RF-74), canales por evento autorizados (RF-75), puente
Livewire↔Echo con resync `v`/`seq` y polling de respaldo (RF-76/77), y las pruebas de caos
mínimas de ISSUE-145 (la marca jamás falla por el broadcast).

## Contexto

- ADR-013 (Reverb + afterCommit en cola), ADR-010 (Redis + Horizon); pantallas ya construidas
  por TQ-011 (captura, salidas, bitácora, ranking, pública) escuchando hoy por sondeo.
- phpredis disponible (Herd); broadcasting=log y queue=sync hoy.
- Base de trabajo: feature/entrega-2-ol-gt, sin tocar main.

## Fases

- [x] [TQ-017](TQ-017-infra-reverb.md) — Infra: Reverb + Redis/Horizon + canales autorizados + Echo base + live-indicator
- [ ] [TQ-018](TQ-018-eventos-y-pantallas.md) — Eventos de dominio afterCommit y pantallas escuchando (con respaldo)
- [ ] [TQ-019](TQ-019-caos-minimo.md) — Caos mínimo: fallos de broadcast no bloquean; ráfaga simple

## Siguiente paso

Ejecutar TQ-019 (caos mínimo): última fase de la rebanada EP-26.

## Criterios de aceptación

- [ ] Las tres fases cerradas; una llegada se refleja en bitácora/pública sin sondeo cuando el
      WS vive, y el sistema sigue funcionando (degradado a sondeo) con Reverb caído.
- [ ] Canales autorizados: anónimo solo en `event.{ulid}.public` sin PII (RF-75, probado).
- [ ] La marca nunca falla por el broadcast (INV-08, prueba de caos).

## Bitácora

| Fecha | Estado | Nota |
|-------|--------|------|
| 2026-11-27 | abierto | Creado al continuar la sesión tras cerrar TQ-011 |
