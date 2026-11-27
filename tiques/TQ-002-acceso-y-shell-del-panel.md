---
id: TQ-002
titulo: "Acceso y shell del panel: login por sesiones + layout con menú por permisos"
tipo: feature
prioridad: P0
estimacion: L
estado: en-revision
refiere_a: ISSUE-108, ISSUE-107, ISSUE-109, ISSUE-112
parte_de: TQ-001
rama: feature/entrega-2-ol-gt
creado: 2026-11-26
actualizado: 2026-11-27
---

# TQ-002 — Acceso y shell del panel: login por sesiones + layout con menú por permisos

## Objetivo

Una cuenta activa inicia sesión (documento **o** email + contraseña) y aterriza en el panel con
el menú renderizado desde sus permisos reales en el servidor; logout por POST; sesión expirada
tratada sin pantallas en blanco. Es la rebanada que hace el panel navegable: todo lo demás de
EP-24 cuelga de aquí.

## Contexto

- `spec/04-backlog/EP-24-fundaciones-livewire.md`: ISSUE-108 (auth por sesiones), ISSUE-107
  (layout + menú por permisos), ISSUE-109 (policies hasta la UI — aquí la parte de layout/menú),
  ISSUE-112 (contexto por render explícito).
- SRS §3.15 (autorización en servidor); ADR-011 (UI TALL); ADR-012 (componente → Action, la UI
  no consume la API).
- Ya existe: `accounts` (persona ≠ cuenta, ADR-015), sesión database, Sanctum solo para
  dispositivos, seeders sintéticos con `admin@tdchronos.test`.
- Dependencia heredada: la matriz de roles/permisos (ISSUE-041/043 del corpus) no está en este
  repo — si al instalar spatie se confirma el hueco, abrir issue nueva en `spec/04-backlog/`
  siguiendo el patrón ISSUE-156…158 (skill `spec-gestionar`).
- Convención local: pantalla = componente Volt funcional en `resources/views/livewire/`.

## Contratos públicos

- Nuevos: componente Volt de login; layout `panel` (Blade) en
  `resources/views/components/layouts/`; partial de menú por permisos; middleware de permisos;
  migraciones y seeder de roles/permisos (spatie/laravel-permission); páginas 419/403 coherentes.
- Modificados: `routes/web.php` (grupo auth + protegidas), `tests/` (pruebas por rol).

## Tareas

- [x] Instalar spatie/laravel-permission; roles/permisos base y seeder sintético; prueba mínima
      de la matriz (y issue de backlog nueva si el hueco heredado se confirma).
- [x] Login Volt: documento o email + contraseña, `remember`, errores 422 bajo el campo,
      mensaje genérico ante credenciales inválidas, bloqueo 5 intentos/min por usuario+IP.
- [x] Redirect intended tras login; solo cuentas activas inician sesión.
- [x] Layout del panel: cabecera con cuenta y salir (POST con confirmación), sidebar,
      breadcrumbs y título por página, branding desde configuración (nada fijo en código).
- [x] Menú renderizado solo desde permisos reales del servidor; rutas protegidas server-side
      (sin permiso → 403 con prueba, no solo enlace oculto).
- [x] Contexto por render explícito (ISSUE-112): cuenta/permisos desde sesión; `@json` solo
      para datos no sensibles; sin variables globales JS de sesión.
- [x] 419 → login con mensaje conservando la ruta destino.
- [x] Pruebas por rol: login ok/rechazado, menú visible/invisible, 403 en ruta protegida.
- [x] Verificar: ./vendor/bin/pint && ./vendor/bin/phpstan analyse && ./vendor/bin/pest
- [ ] DETENERSE: presentar los cambios para revisión y sugerir mensaje de commit;
      no continuar con otra actividad sin aprobación

## Criterios de aceptación

- [x] ISSUE-108: login/logout con errores por campo; bloqueo 5/min probado; redirect intended;
      419 a login con mensaje; solo cuentas activas; tokens de dispositivo intactos por `/api/v1`.
- [x] ISSUE-107 (núcleo): cada entrada del menú exige permiso (invisible **y** 403 server-side,
      probado); breadcrumbs y título por página; branding de nombre/temporada por configuración
      (logo y entradas completas del menú completan con TQ-004/EP-25 al existir sus rutas).
- [x] ISSUE-109 (parte): permisos por gate en el render del menú; la UI oculta, el servidor
      autoriza.
- [x] ISSUE-112 (parte): arranque probado con sesión válida, expirada y sin sesión; sin
      inyección de variables JS (chequeo de ausencia de paquete queda para TQ-008).

## Bitácora

| Fecha | Estado | Nota |
|-------|--------|------|
| 2026-11-26 | abierto | Creado como fase 1 del plan TQ-001 |
| 2026-11-26 | en-curso | Noche autorizada por el usuario (encadenar fases); arranque tras aprobar TQ-009 |
| 2026-11-27 | en-revision | Implementado: 67/67 tests, Larastan 8 y Pint en verde, build OK. Hallazgos: (1) clone sin vendor/.env → bootstrap completo del entorno local (Herd, MySQL, BDs tdchronos y tdchronos_testing, site linked); (2) 5 advisories de symfony en el lock → actualizados, `composer audit` verde; (3) spatie teams exige team_id → convención equipo 0 = plataforma; (4) `Route::has` no ve rutas con nombre fluido registradas tras el arranque → `PanelNav::routeExists` refresca el índice; (5) ISSUE-159 abierta en el backlog (matriz de permisos base + accounts.active). Pendiente: revisión del usuario en la mañana |
