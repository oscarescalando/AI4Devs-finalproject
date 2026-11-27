---
id: TQ-001
titulo: Cierre de EP-24: fundaciones del panel
tipo: plan
prioridad: P0
estimacion: XL
estado: abierto
refiere_a: ISSUE-107, ISSUE-108, ISSUE-109, ISSUE-110, ISSUE-111, ISSUE-112, ISSUE-113, ISSUE-114, ISSUE-115, ISSUE-116, ISSUE-117, ISSUE-118
parte_de: —
rama: feature/entrega-2-ol-gt
creado: 2026-11-26
actualizado: 2026-11-26
---

# TQ-001 — Cierre de EP-24: fundaciones del panel

## Objetivo

Completar lo pendiente de EP-24 (ISSUE-107…118) para dejar la fase F1 en condiciones de pasar a
EP-26 (tiempo real con Reverb) y EP-27 (estación offline PWA) sobre una base estable: login por
sesiones, layout con menú por permisos, kit CRUD, estados de carga/error, rutas to-be, perfil,
PWA base, i18n y CI.

## Contexto

- Backlog de la épica: `spec/04-backlog/EP-24-fundaciones-livewire.md` (ISSUE-107…118 con sus
  criterios).
- Secuencia de F1 y criterios de salida M0/M1: `spec/08-roadmap.md` §2.
- Reglas de UI: ADR-011 (UI TALL, no consume la API), ADR-012 (flujo componente → Action),
  `CLAUDE.md` §Estado del repositorio (convención Volt funcional vs clase Livewire).
- Ya hecho de esta épica: ISSUE-105 (esqueleto), ISSUE-106 (base TALL) y ISSUE-118 parcial
  (`FormatDuration` con pruebas).
- Los tiques de fase viven en esta carpeta y se listan abajo.

## Fases

- [x] [TQ-009](TQ-009-guia-estilo-ui.md) — Guía de estilo de la interfaz `spec/09-ui-estilo.md`
  (base: arquitectura del sistema previo del equipo, identidad TDChronos)
- [x] [TQ-002](TQ-002-acceso-y-shell-del-panel.md) — Acceso y shell del panel: login por
  sesiones + layout con menú por permisos (ISSUE-108, 107, 109 parcial, 112)
- [x] [TQ-003](TQ-003-kit-crud-y-estados.md) — Kit CRUD (tabla + formulario) y estados de
  carga/error unificados con demo Organizaciones (ISSUE-110, 111)
- [ ] [TQ-004](TQ-004-rutas-y-navegacion-to-be.md) — Rutas to-be y navegación por evento en el
  path (ISSUE-115)
- [ ] [TQ-005](TQ-005-perfil-y-cambio-de-contrasena.md) — Perfil propio y cambio de contraseña
  con la actual (ISSUE-113)
- [ ] [TQ-006](TQ-006-pwa-base-instalable.md) — PWA base instalable (ISSUE-114)
- [ ] [TQ-007](TQ-007-i18n-es-ve-completo.md) — i18n es-VE completo (ISSUE-118, resto)
- [ ] [TQ-008](TQ-008-pruebas-tall-y-ci.md) — Pruebas de la capa TALL y CI del monolito
  (ISSUE-116, 117; cierra la regla de arquitectura de ISSUE-109)

## Siguiente paso

Ejecutar TQ-004 (rutas to-be y navegación por evento): completa el mapa de rutas con permisos
de producción y el hub de evento.

## Criterios de aceptación

- [ ] Las siete fases cerradas en `hecho` con sus criterios verificados.
- [ ] Los criterios de ISSUE-107…118 quedan marcados en `spec/04-backlog/EP-24-fundaciones-livewire.md`
      conforme se cierran las fases (spec sincronizada en el mismo cambio).
- [ ] `php artisan migrate:fresh --seed` + `npm run build` dejan el panel utilizable de punta a
      punta con el admin demo.
- [ ] Suite completa en verde (pint → Larastan 8 → Pest).

## Bitácora

| Fecha | Estado | Nota |
|-------|--------|------|
| 2026-11-26 | abierto | Creado: corte en 7 fases verticales, primera TQ-002 |
