---
id: TQ-016
titulo: "Tiempo real con Reverb en las pantallas (EP-26, rebanada F1)"
tipo: plan
prioridad: P0
estimacion: XL
estado: hecho
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
- [x] [TQ-019](TQ-019-caos-minimo.md) — Caos mínimo: fallos de broadcast no bloquean; ráfaga simple

## Siguiente paso

Rebanada EP-26 COMPLETA (3/3). Siguiente según roadmap F1: EP-27 (estación offline PWA) o el
PR consolidado de la entrega.

## Criterios de aceptación

- [x] Las tres fases cerradas; una llegada se refleja en bitácora/pública sin sondeo cuando el
      WS vive, y el sistema sigue funcionando (degradado a sondeo) con Reverb caído.
- [x] Canales autorizados: anónimo solo en `event.{ulid}.public` sin PII (RF-75, probado).
- [x] La marca nunca falla por el broadcast (INV-08, prueba de caos en dos niveles + enmienda ADR-013).

## Bitácora

| Fecha | Estado | Nota |
|-------|--------|------|
| 2026-11-27 | abierto | Creado al continuar la sesión tras cerrar TQ-011 |
| 2026-11-27 | hecho | EP-26 cerrado en su rebanada F1: TQ-017 infra (0.18.0), TQ-018 eventos+pantallas (0.19.0), TQ-019 caos con bug real corregido y enmienda ADR-013 (0.20.0). Pendientes de la versión completa: kiosco por push, reloj pública con wave.started, resync por gap de seq, suites nocturnas con WS real |
