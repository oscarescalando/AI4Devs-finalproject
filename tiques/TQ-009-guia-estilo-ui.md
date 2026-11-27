---
id: TQ-009
titulo: "Guía de estilo de la interfaz: Blade/Livewire/Tailwind"
tipo: docs
prioridad: P1
estimacion: M
estado: hecho
refiere_a: ISSUE-107, ISSUE-110
parte_de: TQ-001
rama: feature/entrega-2-ol-gt
creado: 2026-11-26
actualizado: 2026-11-26
---

# TQ-009 — Guía de estilo de la interfaz: Blade/Livewire/Tailwind

## Objetivo

Crear la guía de estilo única para desarrollar las vistas del sistema (panel y pantallas públicas):
principios, tokens, layout, inventario de componentes `x-ui.*` con su API, estados, iconos,
formato de datos de cronometraje, accesibilidad e i18n. TQ-002 (layout) y TQ-003 (kit CRUD)
implementan contra esta guía en vez de improvisar.

## Contexto

- No existía guía: solo convenciones estructurales (CLAUDE.md, ADR-011) y la pantalla pública
  como lenguaje de facto mínimo (slate oscuro + Instrument Sans + ámbar/emerald).
- **Base evaluada**: el sistema de diseño del proyecto previo del equipo (mismo autor): spec
  `DISENO.md`, tokens `@theme` + variables `--vn-*` en `theme.css`, 30 componentes `x-ui.*`,
  prototipo HTML. Se adopta la **arquitectura** (tokens en dos capas, CSS plano sin `@apply`,
  componentes anónimos con API de props, layout sidebar/header, convención de iconos, colores
  por CLAVE de token) re-expresada con la identidad de TDChronos; sin marcas ni nombres ajenos.
- DoD que la guía debe recoger: G11 (WCAG 2.1 AA), G13 (estados de conexión), P4 (carga/error),
  P5 (responsive 360px→TV), INV-06 (públicas sin PII).

## Contratos públicos

- Nuevo: `spec/09-ui-estilo.md` (pieza nueva de la serie spec) registrado en el mapa de
  artefactos de `spec/README.md` §5.
- Sin código: los tokens CSS (`app.css`/`theme.css`) y los componentes llegan con TQ-002/TQ-003.

## Tareas

- [x] Evaluar la base del sistema previo (spec, tokens, componentes, vistas, CSS).
- [x] Verificar el lenguaje de facto de la pantalla pública (slate/emerald/ámbar, Instrument Sans).
- [x] Redactar `spec/09-ui-estilo.md` con identidad TDChronos (chrono sky, live ámbar, mono
      tabular para tiempos/dorsales, panel dual oscuro-por-defecto, públicas siempre oscuras).
- [x] Registrar la pieza en `spec/README.md` §5 y actualizar este tique + TQ-001 + índice.
- [x] Revisión del usuario y commit (skill `commit`).

## Criterios de aceptación

- [x] La guía define: principios, stack, tokens con valores, layout, inventario de componentes
      con API de props, estados, iconos, formato de datos, accesibilidad, i18n y verificación.
- [x] No contradice ADR-011/012 ni la DoD G11/G13/P4/P5; no introduce PII en públicas.
- [x] TQ-002 y TQ-003 pueden implementarse contra ella sin decisiones de diseño abiertas.

## Bitácora

| Fecha | Estado | Nota |
|-------|--------|------|
| 2026-11-26 | abierto | Creado: la noche arranca con la guía antes de tocar código |
| 2026-11-26 | en-curso | Base evaluada: tokens de dos capas + x-ui.* + layout del sistema previo |
| 2026-11-26 | en-revision | Guía redactada (spec/09-ui-estilo.md) y registrada; pendiente revisión + commit |
| 2026-11-26 | hecho | Aprobada por el usuario; publicada con el commit de esta versión |
