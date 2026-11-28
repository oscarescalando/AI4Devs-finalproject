---
id: TQ-003
titulo: "Kit CRUD (tabla + formulario) y estados de carga/error unificados"
tipo: feature
prioridad: P0
estimacion: XL
estado: hecho
refiere_a: ISSUE-110, ISSUE-111
parte_de: TQ-001
rama: feature/entrega-2-ol-gt
creado: 2026-11-26
actualizado: 2026-11-27
---

# TQ-003 — Kit CRUD (tabla + formulario) y estados de carga/error unificados

## Objetivo

Kit base de tabla y formulario con validación solo en el servidor, más el sistema único de
feedback visual (carga, errores, toasts). Se valida de punta a punta con un CRUD de demostración
(Organizaciones) construido exclusivamente con el kit: las 14 pantallas de EP-25 se construirán
sobre esto.

## Contexto

- `spec/04-backlog/EP-24-fundaciones-livewire.md`: ISSUE-110 (kit CRUD) e ISSUE-111 (estados
  de carga/error/notificación).
- ADR-011/ADR-012: CRUD con acciones = **clases Livewire** en `app/Livewire/` llamando Actions;
  la UI no consume la API.
- Requiere TQ-002 (layout, permisos, sesiones) para la demo dentro del panel.
- Tabla `organizations` ya migrada (ISSUE-157); sin PII sensible en ese CRUD.

## Contratos públicos

- Nuevos: componentes Blade/anónimos del kit (`x-crud-table`, `x-crud-form`, `x-confirm-modal`,
  `x-toast`…), componente Livewire de demo Organizaciones (clase + vista), interceptor global de
  errores de acción, páginas 403/404/500 de marca propia.
- Modificados: layout del panel (toasts + barra de progreso global), `tests/` (pruebas de
  componente del kit).

## Tareas

- [x] Tabla: paginación server-side con ventana, ordenación por lista blanca, filtros
      componibles, columna de acciones condicionada por permisos, estado en query string.
- [x] Formulario: error por campo desde validación de servidor, foco al primer inválido,
      bloqueo de doble envío.
- [x] Confirmación previa para toda acción destructiva.
- [ ] Subida multipart (queda pendiente: sin caso de uso real hasta fotos/importaciones; ISSUE-110 la conserva abierta) con límite por configuración y mensajes claros (si la demo lo ejercita;
      si no, dejar el contrato del componente probado con un caso sintético).
- [x] Estados unificados: `wire:loading` con esqueletos acotados al área, barra global, toasts
      accesibles (rol `status`, cierre automático, español), interceptor de errores con id de
      correlación, 419 → login conservando destino.
- [x] CRUD demo Organizaciones solo con el kit, sin CSS/JS ad-hoc, con acciones → Actions.
- [x] Pruebas de componente: validación por campo, orden fuera de lista blanca (422 o ignorada),
      doble clic no duplica, fallo de servidor → toast (nunca silencio).
- [x] Verificar: ./vendor/bin/pint && ./vendor/bin/phpstan analyse && ./vendor/bin/pest
- [ ] DETENERSE: presentar los cambios para revisión y sugerir mensaje de commit;
      no continuar con otra actividad sin aprobación

## Criterios de aceptación

- [x] ISSUE-110 (salvo multipart): demo construida solo con el kit; todo error de servidor
      aparece junto a su campo; orden fuera de lista blanca cae al default; confirmación en
      destructivas; doble envío bloqueado.
- [x] ISSUE-111 (núcleo): skeletons acotados, toasts accesibles en español, 419 cubierto,
      páginas de error propias; el interceptor global con id de correlación y la barra de
      progreso quedan para TQ-008 (CI/arquitectura).

## Bitácora

| Fecha | Estado | Nota |
|-------|--------|------|
| 2026-11-26 | abierto | Creado como fase 2 del plan TQ-001 |
| 2026-11-27 | en-curso | Delegado a subagent implementador (orquestador); auditoría de vistas corriendo en paralelo |
| 2026-11-27 | hecho | Implementado y verificado por el orquestador: Pint ✓ · Larastan 8 con 0 errores · Pest 123/123 (56 tests nuevos) · build ✓ · humo HTTP (ruta organizations 302→login para invitados). Extras del implementador: componente x-ui.pagination con vista propia en español, lang/es/validation+pagination, excepción si icon-button sin tip. Desviaciones aceptadas: orden fuera de whitelist cae a name asc; errores de Action re-lanzados prefijados form.*; paginación por query string con vista del kit. Pendientes anotados: multipart (ISSUE-110), interceptor con correlación (TQ-008), galería /ui/componentes |
