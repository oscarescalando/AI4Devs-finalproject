---
id: TQ-003
titulo: "Kit CRUD (tabla + formulario) y estados de carga/error unificados"
tipo: feature
prioridad: P0
estimacion: XL
estado: abierto
refiere_a: ISSUE-110, ISSUE-111
parte_de: TQ-001
rama: feature/entrega-2-ol-gt
creado: 2026-11-26
actualizado: 2026-11-26
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

- [ ] Tabla: paginación server-side con ventana, ordenación por lista blanca, filtros
      componibles, columna de acciones condicionada por permisos, estado en query string.
- [ ] Formulario: error por campo desde validación de servidor, foco al primer inválido,
      bloqueo de doble envío.
- [ ] Confirmación previa para toda acción destructiva.
- [ ] Subida multipart con límite por configuración y mensajes claros (si la demo lo ejercita;
      si no, dejar el contrato del componente probado con un caso sintético).
- [ ] Estados unificados: `wire:loading` con esqueletos acotados al área, barra global, toasts
      accesibles (rol `status`, cierre automático, español), interceptor de errores con id de
      correlación, 419 → login conservando destino.
- [ ] CRUD demo Organizaciones solo con el kit, sin CSS/JS ad-hoc, con acciones → Actions.
- [ ] Pruebas de componente: validación por campo, orden fuera de lista blanca (422 o ignorada),
      doble clic no duplica, fallo de servidor → toast (nunca silencio).
- [ ] Verificar: ./vendor/bin/pint && ./vendor/bin/phpstan analyse && ./vendor/bin/pest
- [ ] DETENERSE: presentar los cambios para revisión y sugerir mensaje de commit;
      no continuar con otra actividad sin aprobación

## Criterios de aceptación

- [ ] ISSUE-110: demo construida solo con el kit; todo error de servidor aparece junto a su
      campo; orden fuera de lista blanca rechazado; confirmación en destructivas; doble envío
      bloqueado.
- [ ] ISSUE-111: ninguna acción queda con spinner colgado (prueba forzando fallo); 401/419/500
      siempre muestran toast con correlación; páginas de error propias y coherentes.

## Bitácora

| Fecha | Estado | Nota |
|-------|--------|------|
| 2026-11-26 | abierto | Creado como fase 2 del plan TQ-001 |
