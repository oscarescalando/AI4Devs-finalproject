---
id: TQ-002
titulo: "Acceso y shell del panel: login por sesiones + layout con menú por permisos"
tipo: feature
prioridad: P0
estimacion: L
estado: abierto
refiere_a: ISSUE-108, ISSUE-107, ISSUE-109, ISSUE-112
parte_de: TQ-001
rama: feature/entrega-2-ol-gt
creado: 2026-11-26
actualizado: 2026-11-26
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

- [ ] Instalar spatie/laravel-permission; roles/permisos base y seeder sintético; prueba mínima
      de la matriz (y issue de backlog nueva si el hueco heredado se confirma).
- [ ] Login Volt: documento o email + contraseña, `remember`, errores 422 bajo el campo,
      mensaje genérico ante credenciales inválidas, bloqueo 5 intentos/min por usuario+IP.
- [ ] Redirect intended tras login; solo cuentas activas inician sesión.
- [ ] Layout del panel: cabecera con cuenta y salir (POST con confirmación), sidebar,
      breadcrumbs y título por página, branding desde configuración (nada fijo en código).
- [ ] Menú renderizado solo desde permisos reales del servidor; rutas protegidas server-side
      (sin permiso → 403 con prueba, no solo enlace oculto).
- [ ] Contexto por render explícito (ISSUE-112): cuenta/permisos desde sesión; `@json` solo
      para datos no sensibles; sin variables globales JS de sesión.
- [ ] 419 → login con mensaje conservando la ruta destino.
- [ ] Pruebas por rol: login ok/rechazado, menú visible/invisible, 403 en ruta protegida.
- [ ] Verificar: ./vendor/bin/pint && ./vendor/bin/phpstan analyse && ./vendor/bin/pest
- [ ] DETENERSE: presentar los cambios para revisión y sugerir mensaje de commit;
      no continuar con otra actividad sin aprobación

## Criterios de aceptación

- [ ] ISSUE-108: login/logout con errores por campo; bloqueo 5/min probado; redirect intended;
      419 a login con mensaje; solo cuentas activas; tokens de dispositivo intactos por `/api/v1`.
- [ ] ISSUE-107: cada entrada del menú exige permiso (invisible **y** 403 server-side, probado);
      breadcrumbs, título y branding configurable (prueba con dos configuraciones).
- [ ] ISSUE-109 (parte): `@can`/gate en el render del menú; la UI oculta, el servidor autoriza.
- [ ] ISSUE-112: arranque probado con sesión válida, expirada y sin sesión; sin inyección de
      variables JS.

## Bitácora

| Fecha | Estado | Nota |
|-------|--------|------|
| 2026-11-26 | abierto | Creado como fase 1 del plan TQ-001 |
