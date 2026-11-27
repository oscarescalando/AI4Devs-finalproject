---
id: TQ-004
titulo: Rutas to-be y navegación por evento en el path
tipo: feature
prioridad: P1
estimacion: M
estado: abierto
refiere_a: ISSUE-115
parte_de: TQ-001
rama: feature/entrega-2-ol-gt
creado: 2026-11-26
actualizado: 2026-11-26
---

# TQ-004 — Rutas to-be y navegación por evento en el path

## Objetivo

Mapa de rutas del panel con el evento en el **path** (`/events/{event}/registrations`,
`/events/{event}/start`, …), nombres únicos, middleware de permisos por ruta y 404 coherente:
sin identificadores propagados a mano por query string ni rutas fantasma.

## Contexto

- `spec/04-backlog/EP-24-fundaciones-livewire.md` ISSUE-115; mapeo de las 14 pantallas en
  `spec/04-backlog/EP-25-pantallas-livewire.md`.
- Requiere TQ-002 (middleware de permisos) y TQ-003 (estado de tablas en query string).
- `routes/web.php` actual solo tiene la landing y `/e/{slug}` públicas.

## Contratos públicos

- Modificados: `routes/web.php` (grupo por evento con binding ULID, nombres de ruta, middleware
  de permisos), navegación/breadcrumbs del layout.
- Nuevos: placeholder navegable por pantalla del panel (hub de evento con aviso accionable si
  falta configuración), prueba estática de "sin rutas sin implementación".

## Tareas

- [ ] Definir y registrar las rutas nombradas que cubren las 14 pantallas, con el evento en el
      path y binding por ULID.
- [ ] Middleware de permisos por ruta alineado con la matriz de permisos.
- [ ] Vista operativa por evento: Inscripciones/Salidas/Registros/Clasificación accesibles si
      existe el permiso; aviso accionable si falta configuración del evento (no enlace ausente).
- [ ] 404 coherente para evento/slug inexistente.
- [ ] Prueba del recorrido de rutas: usuario sin permisos no alcanza ninguna ruta operativa.
- [ ] Verificar: ./vendor/bin/pint && ./vendor/bin/phpstan analyse && ./vendor/bin/pest
- [ ] DETENERSE: presentar los cambios para revisión y sugerir mensaje de commit;
      no continuar con otra actividad sin aprobación

## Criterios de aceptación

- [ ] ISSUE-115: rutas nombradas que cubren las 14 pantallas; sin `Route::resource` sin
      implementación (chequeo estático); estado de tablas en query string compartible;
      aviso accionable ante configuración faltante.

## Bitácora

| Fecha | Estado | Nota |
|-------|--------|------|
| 2026-11-26 | abierto | Creado como fase 3 del plan TQ-001 |
