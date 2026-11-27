# Changelog

Todos los cambios notables de este proyecto se documentan aquí.
Formato basado en [Keep a Changelog](https://keepachangelog.com/es/1.1.0/); versionado SemVer.

## [0.13.0] - 2026-11-27
### 🚀 Agregado
- **Pruebas de la capa TALL y CI** (TQ-008 · ISSUE-116/117/109): reglas Pest Arch (Livewire sin BD ni modelos: componente → Action), inspección de auth/permission por ruta, pruebas de autorización del kit y del CRUD por rol (Gate `directory.write` = admin/superadmin como convención hasta `directory.manage`), **Playwright smoke** (landing, login→consola, pantalla pública; `npm run test:e2e`) y **pipeline de GitHub Actions** con los gates en orden: Pint → Larastan 8 → Pest con MySQL 8 → Pest Arch → gitleaks → composer audit → build Vite + verificación de manifest → Playwright (PRs a main).
- Con esto **cierra el plan TQ-001** (EP-24 al día): 8 fases en la noche autónoma. Suite final **209 tests**.
- DEFER documentado: imagen OCI al etiquetar (registry/runtime) y cobertura con umbral — decisión de la mañana, anotada en el workflow y las bitácoras.

## [0.12.0] - 2026-11-27
### 🚀 Agregado
- **i18n es-VE completo** (TQ-007 · ISSUE-118): 199 claves en `lang/es/ui.php` por dominio (panel, auth, nav, roles, events, directory, kit, profile, públicas y errores) con los textos renderizados idénticos; el config entrega claves y las vistas traducen (evita congelar traducciones con `config:cache`).
- `FormatDate` (dd/mm/yyyy en UI, ISO en BD) con pruebas de bisiesto/fin de mes, y chequeo automatizado **NoInlineTextTest** que falla con archivo:línea:texto ante literales de interfaz en vistas (excepciones documentadas).
- Suite a **191 tests**. Pint, Larastan 8 y humo HTTP en verde.

## [0.11.0] - 2026-11-27
### 🚀 Agregado
- **PWA base instalable** (TQ-006 · ISSUE-114): manifest (standalone, tema chrono, iconos 192/512 + maskable placeholder), service worker a mano (precache del shell e iconos, assets por hash, navegaciones network-first con fallback a `/offline.html`, `/livewire*` y `/api/` nunca interceptados), pantalla offline en español y registro en los layouts de panel e invitado (el público queda siempre fresco). Ajuste del orquestador: el estático del manifest bajaba como `octet-stream` → ruta canónica `/pwa/manifest` con `application/manifest+json`.
- Suite a **183 tests** (9 nuevas de PWA). Pint, Larastan 8, build y humo HTTP en verde.

## [0.10.0] - 2026-11-27
### 🚀 Agregado
- **Perfil propio** (TQ-005 · ISSUE-113/RF-71): `/profile` con dos tarjetas — Cuenta (nombre, correo y roles en badges, solo lectura: sin selector de rol ni control de estado) y Contraseña (actual obligatoria, nueva + confirmación con política mínima `min:8` documentada, campos vacíos tras guardar y toast). `UpdateOwnPassword` valida en servidor, cifrá con el cast `hashed` e **invalida las demás sesiones** (borra en `sessions` las filas ajenas a la sesión activa). Entradas desde el dropdown del avatar y el grupo Principal del menú.
- Clave `validation.confirmed` en español. Suite a **174 tests** (12 nuevas). Pint, Larastan 8 y humo HTTP en verde.

## [0.9.0] - 2026-11-27
### 🚀 Agregado
- **Mapa de rutas to-be del panel** (TQ-004 · ISSUE-115): el evento en el path con binding por ULID (patrón de ruta; id entero o ULID inexistente → 404 propia) y middleware `permission:` en todas: `events.index`/`events.show` (hub con KPIs, tarjetas operativas por permiso y aviso accionable si falta configuración), `events.entries|start|marks|results`, `events.categories|waves`, `directory.persons|teams`, `championships.index` — placeholders navegables con aviso «Llega con EP-25» y sin rutas fantasma (chequeo estático anti-`Route::resource`).
- **Navegación por evento**: `PanelNav::href` enlaza las operativas con el ULID activo en sesión (`tc.event_ulid`, fijado por el hub) y cae al listado de eventos si no hay; lista real de inscripciones con dorsales en mono tabular y sin documento (INV-06).
- Actions `ListEvents`, `GetEventHub`, `ListEventEntries`; `EventStatus` (etiqueta+badge sin hex); 11 Volt con el kit.
- Suite a **162 tests** (RouteMapTest: 26 casos — nombres como código, invitado→login en todas, 403/200 por rol, 404s, sesión de evento, aviso del hub). Pint, Larastan 8 y humo HTTP en verde.

## [0.8.0] - 2026-11-27
### 🚀 Agregado
- **Kit de componentes `x-ui.*`** (TQ-003 · ISSUE-110/111): button, icon-button (tip obligatorio), form-group (foco al primer inválido), table (cabeceras ordenables por enlaces compartibles, compact, footer), badge, alert, modal, empty-state, card, kpi, page-header y pagination con vista propia en español; `tcToast` (rol `status`, cierre automático) y `tcConfirm` (Promise) en `app.js` con listener del evento `toast` de Livewire.
- **CRUD demo Organizaciones** en `/directory/organizations` (auth + `permission:directory.view`, la entrada del menú aparece sola): búsqueda, orden solo por lista blanca (columna o dirección inválidas caen al default), paginación con skeleton, modal de alta/edición con validación de servidor bajo el campo, eliminación con confirmación y rechazo de organizaciones con equipos; escrituras/lecturas vía Actions (`List/Find/Create/Update/DeleteOrganization`, `ListRegions`).
- Datos sintéticos ampliados: `RegionFactory` + `OrganizationFactory`, 3 regiones y 22 organizaciones en el seeder.
- `lang/es/validation.php` y `lang/es/pagination.php`; locale por defecto `es`.
- Suite a **123 tests** (56 nuevas: kit + CRUD por rol). Pint, Larastan 8 y build en verde.
### 🎨 Corregido (auditoría contra `spec/09-ui-estilo.md`)
- Sin hex ni `style` inline en vistas: hero del login, páginas de error (clases `.error-*`), cabecera del menú de cuenta, consola (`.kpi-icon/.kpi-value/.text-strong/.list-divide`); `<style>` del layout público → `.public-body`.
- Pantalla pública: **mono tabular en Pos, dorsal y reloj** (§1.3), `scope="col"` en cabeceras y paleta cruda → tokens (`emerald→success`, `amber→accent`, `slate→neutral`) también en la landing.
- Panel: confirmación al cerrar sesión, `aria-haspopup`/`:aria-expanded` del menú de cuenta, logo configurable (`TC_LOGO`) con fallback al monograma; eliminada `welcome.blade.php` muerta.

## [0.7.2] - 2026-11-27
### 📝 Documentación
- Agente `orquestador` (`.claude/agents/`): coordina el desarrollo por tiques — valida la fase cerrada, delega implementación a subagentes con briefs completos, corre los gates (Pint → Larastan 8 → Pest → build), commitea cada fase y mantiene el registro; documentado en `CLAUDE.md`.

## [0.7.1] - 2026-11-27
### 🧹 Otros
- Se ignora `public/fonts-manifest.dev.json` (artefacto del dev server de Vite que se coló en el commit anterior) y se saca del repositorio.

## [0.7.0] - 2026-11-27
### 🚀 Agregado
- **Acceso y shell del panel** (TQ-002 · ISSUE-108 completa, ISSUE-159 nueva en el backlog): login por sesiones en Volt (documento **o** correo + contraseña, remember, bloqueo 5/min por usuario+IP con mensaje de reintentos, redirect intended, solo cuentas activas), logout por POST con invalidación de sesión y **419 → login con mensaje conservando el destino** (HttpException capturado tras la conversión de TokenMismatch).
- **Roles y permisos** (RF-69): spatie/laravel-permission con teams — convención **equipo 0 = plataforma** para roles globales (superadmin, admin, organizer, official, timekeeper, athlete; los por evento usarán `team_id = event_id`) —, migración `accounts.active` reversible, `RolesPermissionsSeeder` con la matriz base del SRS §2 y cuentas demo por rol; middleware `role:`/`permission:` registrados.
- **Layout del panel** (ISSUE-107, núcleo): sidebar 264px con menú generado desde `config/tc-ui.nav` filtrado por ruta existente y permiso real (`PanelNav`), header sticky con título/breadcrumb/tema/avatar+salir, branding por configuración y modo oscuro persistido (arranque en oscuro). `Account` ahora `Authenticatable` + `Authorizable` + `HasRoles`.
- **Base visual de la guía** (`spec/09-ui-estilo.md`): tokens `@theme` (chrono sky · live ámbar · semánticos) + `theme.css` con variables `--tc-*` (botones, formularios, tarjetas, badges, alertas, skeleton/spinner, shell sidebar/header, login guest); iconos Lucide (`x-ui.icon`, `tcRenderIcons`); páginas de error 403/404/419/500 propias; locale `es` con `lang/es/auth.php`.
- Pruebas: 67 (21 nuevas — login completo, menú por permisos, 403 por rol, matriz SRS, esquema de permisos); ISSUE-108 marcada en EP-24.
- Entorno local macOS/Herd documentado en bitácora del tique: dependencias symfony actualizadas (`composer audit` verde).

## [0.6.1] - 2026-11-26
### 📝 Documentación
- Guía de estilo de la interfaz `spec/09-ui-estilo.md` (TQ-009, ISSUE-107/110): principios, stack, tokens con valores (chrono sky · live ámbar · mono tabular para cronometraje), layout del panel, inventario del kit `x-ui.*` con API de props y componentes de dominio (`time-cell`, `bib`, `delta`, `live-indicator`), estados, iconos Lucide, formato de datos, WCAG 2.1 AA, i18n y pantallas públicas. Arquitectura adaptada del sistema de diseño previo del equipo con identidad TDChronos; registrada en el mapa de artefactos de `spec/README.md` §5.
- TQ-009 cerrado (hecho) y plan TQ-001/índice actualizados.

## [0.6.0] - 2026-11-26
### 📝 Documentación
- Sistema de tiques (registro de trabajo por actividad): carpeta `tiques/` con índice de control (`INDEX.md`), plan **TQ-001** (cierre de EP-24: fundaciones del panel) y sus siete fases **TQ-002…008** — login por sesiones + layout con menú por permisos, kit CRUD + estados de carga/error, rutas to-be, perfil, PWA base, i18n es-VE y pruebas TALL + CI.
- Skill `tiques-gestionar` (.claude/skills/): plantilla única de tique (frontmatter con serie TQ-NNN, estados, tipos del backlog), ciclo de vida, corte en rebanadas verticales con aprobación, bitácora por commit y regla "nada de trabajo sin tique".
- `CLAUDE.md` documenta el registro de trabajo y la regla de tique obligatorio.

## [0.5.0] - 2026-11-23
### 🚀 Agregado
- Base TALL (ISSUE-106): Livewire 4 + Volt + Alpine sobre el Tailwind 4 del esqueleto, `VoltServiceProvider` y convención documentada (pantallas = Volt funcional; CRUD = clases Livewire).
- **Pantalla pública de clasificación** `/e/{slug}` (ISSUE-137, versión mínima del corte 1): componente Volt con sondeo `wire:poll.4s` al leaderboard, filtros por categoría y distancia, resaltado de llegadas nuevas, reloj en vivo, modo kiosco, cero PII y landing `/` con los eventos del calendario.
- `FormatDuration` (ISSUE-118 parcial): formato único `h:mm:ss` desde ms con soporte > 24 h, décimas opcionales y diferencias negativas, con pruebas.
- Suite a 46 tests (esquema + API + resultados + pantalla pública + arquitectura).

## [0.4.0] - 2026-11-23
### 🚀 Agregado
- Motor de resultados del servidor (ISSUE-158): `RecalculateEventResults` computa `net_ms = primera llegada − salida real + Σ ajustes` con `RANK() OVER` por carrera, género y categoría (aritmética SQL a prueba de desbordes), reemplaza la tabla derivada `results` en una transacción y marca provisional/official según el estado del evento. Job en cola `afterCommit` disparado por cada lote de marcas — la respuesta de la marca nunca espera al recálculo (INV-07/INV-08).
- Leaderboard público `GET /api/v1/public/events/{ulid}/leaderboard`: sin login ni PII (INV-06), menores con iniciales, contrato `v`/`seq` para el sondeo de la pantalla en vivo y `Cache-Control` corto.
- Precisión de milisegundos garantizada en `timing_marks.captured_at` y las horas de oleada (`dateFormat` con `.v`).
- Tests del motor (net con ajustes, empates RANK, clasificación por carrera, > 24 h, disparo por marca, conciliación en vivo ↔ oficial) y del leaderboard (PII, menores, seq creciente); regla de arquitectura: `results` solo desde el motor. Suite: 36 tests.

## [0.3.0] - 2026-11-23
### 🚀 Agregado
- Esqueleto Laravel 13 / PHP 8.4 del monolito en la raíz del repo (ISSUE-105): Sanctum con abilities para dispositivos, aliases `ability`/`abilities`, sesión database sobre `accounts`, timezone `America/Caracas`, locale de Faker `es_VE`.
- Migraciones del núcleo con pruebas de esquema (ISSUE-157): directorio (persona ≠ cuenta), eventos/carreras/oleadas/categorías, inscripciones con `UNIQUE (event_id, bib)` y `(event_id, person_id)` + CHECK persona XOR equipo, `timing_marks` append-only con triggers que rechazan UPDATE/DELETE en MySQL, correcciones auditadas y `results` derivado. PK `bigint` + ULID público (trait `HasPublicUlid`).
- API `/api/v1` (ISSUE-156): `GET /time` (hora oficial), `POST /events/{ulid}/timing-marks` (lote transaccional idempotente por `client_mark_id`, respuesta assigned/orphan/duplicate) y `GET /events/{ulid}/entries` (roster de la estación con solo campos públicos; menores con iniciales).
- Seeders sintéticos (ADR-015): evento demo `vuela-caracas-21k` con 114 inscritos, 4 menores para INV-06 y admin bootstrap.
- Calidad: Pest 26 tests (esquema + API + arquitectura Pest Arch) contra MySQL real, Larastan nivel 8 limpio, Pint aplicado, assets Vite compilados.

## [0.2.0] - 2026-11-23
### 📝 Documentación
- Correcciones del feedback de la entrega 1 (revisión LIDR): alcance recortado a un **primer corte** explícito en `readme.md` (evento sembrado → estación offline → clasificación en servidor → pantalla pública), con cada funcionalidad marcada como [Corte 1]/[v2]/[Simulado], y bocetos de la pantalla de estación y de la clasificación pública.
- Modelo de datos: clave primaria unificada en el diagrama (`bigint` PK + ULID `char(26)` público UNIQUE, el entero nunca sale al exterior) y liga movida a un diagrama v2 sin la relación "vía standings".
- API: endpoints nuevos de emparejamiento de la estación (`POST /station/pairing`) y descarga del roster de inscritos (`GET /events/{e}/entries`) en `readme.md` y `spec/02-srs.md` §5.3.
- Invariante INV-06 precisada: nombre y club públicos por diseño, iniciales/pseudónimo para menores.
- Backlog: issues nuevas ISSUE-156 (endpoint idempotente de marcas), ISSUE-157 (migraciones del núcleo con pruebas de esquema) e ISSUE-158 (motor de resultados) — traen al repo las piezas heredadas que necesita el primer corte; ISSUE-149 reclasificada como cliente de la estación.
- `.claude/` limpiado: eliminados agentes y skills del proyecto anterior de pagos; `spec-gestionar` reescrito para las rutas de `spec/`.

## [0.1.1] - 2026-10-20
### 📝 Documentación
- `readme.md`: línea de autores en el encabezado del documento, alineada con `spec/README.md` (Oscar Ramon Lobo Altuve · Gabriel Toro, con sus emails).

## [0.1.0] - 2026-10-20
### 🚀 Agregado
- Especificación de la Entrega 1 de TDChronos 2.0 en `spec/`: PRD, SRS, ADR-010…018, épicas EP-24…27 (issues ISSUE-105…155), DoD G1–G15, invariantes INV-01…14, estrategia de pruebas y roadmap F1–F4 (hitos M0–M6).
- `readme.md` de la entrega con la plantilla AI4Devs (ficha, arquitectura, modelo de datos, API `/api/v1`, historias y tickets de ejemplo, política de PRs) y `prompts.md` del curso.
- Base del repositorio: `.gitignore`, `CLAUDE.md` y configuración `.claude/` (hook de validación de commits, skills y agentes).
