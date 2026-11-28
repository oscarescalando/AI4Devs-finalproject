---
id: TQ-025
titulo: "CRUD de eventos y carreras"
tipo: feature
prioridad: P1
estimacion: L
estado: hecho
refiere_a: ISSUE-160
parte_de: TQ-024
rama: feature/entrega-2-ol-gt
creado: 2026-11-28
actualizado: 2026-11-28
---

# TQ-025 — CRUD de eventos y carreras

## Objetivo

Listado real de eventos con crear/editar (nombre, slug único, fecha, zona, estado) y carreras
del evento (nombre, distancia km, tipo calle/trail/OCR) gestionadas dentro del formulario del
evento — validación de servidor, permiso events.manage, sin tocar lo operativo.

## Contexto

- Placeholder event-list a sustituir; patrón ManageOrganizations (TQ-003) como referencia.
- Events con getRouteKeyName ulid; carreras con race_type? — inspeccionar migración para
  columnas exactas (distance, type) antes de escribir.
- Borrado de evento: DEFER (con inscripciones rompe FKs — se desactiva/cierra, no se borra;
  documentar).

## Contratos públicos

- Nuevos: Actions Create/Update/Find/List para eventos y carreras; Volt event-list real y
  formulario de evento (modal o página) con repeater de carreras.
- Modificados: hub enlaza al editador.

## Tareas

- [x] ListEvent real (paginado, búsqueda por nombre, orden whitelist).
- [x] Create/Update evento con validaciones (slug único, fecha) + carreras anidadas.
- [x] Permisos y 403; toasts; estados de carga del kit.
- [x] Pruebas por rol + validaciones + slug duplicado rechazado.
- [x] Verificar: ./vendor/bin/pint && ./vendor/bin/phpstan analyse && ./vendor/bin/pest
- [ ] DETENERSE: presentar los cambios para revisión y sugerir mensaje de commit;
      no continuar con otra actividad sin aprobación

## Criterios de aceptación

- [x] Evento nuevo nace desde el panel con sus carreras y es editable; slug duplicado
      rechazado en servidor.

## Bitácora

| Fecha | Estado | Nota |
|-------|--------|------|
| 2026-11-28 | abierto | Creado como fase 1 del plan TQ-024 |
| 2026-11-28 | en-curso | Delegado a subagent (orquestador); noche autónoma |
| 2026-11-28 | hecho | Verificado por el orquestador: Pint ✓ · Larastan 8 con 0 errores · Pest 313/313 (24 nuevas) · humo HTTP. HALLAZGO de esquema: races solo tiene name — distancia/tipo viven en el nombre (documentado en la migración); el CRUD valida nombre distinct y no se tocaron migraciones. Si F2 quiere columnas propias de distancia/tipo, es cambio de esquema con spec (anotado para ISSUE-160 completa). Decisiones: clase Livewire (convención CRUD), slug autogenerado en servidor, carreras en la misma transacción con retiro bloqueado por inscripciones/oleadas (rollback total), EventStatus::STATUSES única fuente, bug de colisión ui.events.status corregido |
