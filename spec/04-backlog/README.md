# Backlog de issues de TDChronos 2.0 (Laravel 13 + Livewire/TALL)

> **Fecha:** 2026-10-19 · **Estado:** backlog vigente · Documento de especificación de TDChronos 2.0
>
> Backlog de la reconstrucción: **4 épicas nuevas (EP-24…EP-27)** con las issues **ISSUE-105…158**, que materializan la
> decisión **Livewire/TALL** para toda la UI web. El backlog heredado del corpus de origen (EP-01…EP-23, ISSUE-001…104,
> SAAS-1xx…5xx; repositorio del sistema, sin URL) **no se reproduce aquí**: se cita por su ID únicamente como referencia
> de trazabilidad. La numeración continúa la del corpus de origen (ISSUE-105+, EP-24+).
> **Las piezas heredadas que necesita el primer corte ya viven en este repositorio**: el endpoint idempotente de marcas
> (ISSUE-156, primer corte de la heredada ISSUE-067), las migraciones del núcleo con pruebas de esquema (ISSUE-157) y el
> motor de resultados del servidor (ISSUE-158, primer corte de la heredada ISSUE-074) — lo que una IA o una persona que
> lea el repo necesita para construir el camino completo de la meta está completo aquí.
>
> Documentos hermanos (índice en [../README.md](../README.md)): casos de uso (numeración heredada CU-01…26, sin cambios),
> decisiones de arquitectura en [../03-arquitectura/](../03-arquitectura/), DoD en [../05-dod.md](../05-dod.md) y fases
> en [../08-roadmap.md](../08-roadmap.md). Los CU-01…26 y RF-01…77 heredados no cambian.

## Resumen

| Métrica | Valor |
|---|---|
| Issues nuevas | **57** — ISSUE-105…161 |
| Épicas nuevas | 4 — EP-24…EP-27 |
| P0 nuevas | 21 (ver §6) |
| Issues heredadas ajustadas por la decisión TALL | ~20 (§3) — ninguna pierde alcance de dominio |
| Esfuerzo máx. de las nuevas (orden de magnitud) | ~292 días-persona (misma metodología del backlog heredado) |

---

## 1. Estrategia del backlog

- **Dominio tal cual el modelo to-be.** El dominio (eventos, inscripciones, cronometraje, resultados, liga, imports,
  operación) se construye según el modelo to-be: el modelo de datos, la API y las reglas de negocio no cambian con la
  decisión de UI. Las issues de dominio heredadas se mantienen y las pantallas nuevas las consumen tal cual.
- **Toda la UI web es Livewire/TALL sobre el monolito** (ADR-011): Livewire 4 + Volt + Alpine + Tailwind. **Sin SPA ni
  repositorio web separado**: el monolito sirve panel, portal público y estación PWA.
- **La API `/api/v1` queda para clientes no-web** (ADR-012): la estación offline como cliente de primera parte, la app
  móvil, el visor de llegadas y las integraciones. La web no consume una API propia: usa acciones Livewire.
- **La migración de datos heredados está fuera de alcance** (enmienda a ADR-015, 2026-10-20): base de datos nueva creada
  íntegramente por migraciones desde cero; seeders con datos sintéticos; usuarios nuevos (admin bootstrap + seeders);
  sin conexión ni migración de datos heredados.
- Se añaden las épicas nuevas EP-24…EP-27 (fundaciones TALL, las 14 pantallas del panel, tiempo real con las pantallas,
  estación offline) y las transversales ISSUE-153…155 (§5).

**Precedencia de fuentes** para decidir cualquier conflicto:

1. Código de este repositorio y sus pruebas.
2. ADRs de `spec/03-arquitectura/` — prevalecen sobre cualquier documento previo.
3. SRS ([../02-srs.md](../02-srs.md)), PRD ([../01-prd.md](../01-prd.md)) y roadmap ([../08-roadmap.md](../08-roadmap.md)).
4. Documentación del corpus de origen (repositorio del sistema) — solo como contexto del backlog heredado.

**Numeración.** Continúa la del corpus de origen: las issues nuevas van de **ISSUE-105** en adelante (ISSUE-001…104
tomadas; los rangos **SAAS-1xx…5xx quedan reservados** y no se tocan). Las épicas nuevas continúan en **EP-24**. Las
decisiones de arquitectura se citan según `spec/03-arquitectura/`.

---

## 2. Tabla maestra de épicas

### Nuevas (EP-24…EP-27)

| Épica | Título | Issues | Resumen |
|---|---|---|---|
| [EP-24](EP-24-fundaciones-livewire.md) | Fundaciones Livewire/TALL | 14 (ISSUE-105…118) | Esqueleto Laravel 13/PHP 8.4, base TALL, layout con menú por permisos reales del servidor, auth por sesiones, kit CRUD con validación servidor, estados de carga/error, PWA base, pruebas y CI |
| [EP-25](EP-25-pantallas-livewire.md) | Reconstrucción de las 14 pantallas del panel como componentes Livewire | 22 (ISSUE-119…140) | Mapeo pantalla del panel → componente → CU → RF; paridad funcional con los requisitos de calidad de la nueva capa visual |
| [EP-26](EP-26-tiempo-real-reverb.md) | Tiempo real con Reverb en las pantallas | 6 (ISSUE-141…146) | Contrato de eventos por pantalla, puente Livewire↔Echo (re-expresa el alcance heredado de ISSUE-087), resync `v`/`seq`, canales por evento autorizados, pruebas de caos, ráfagas |
| [EP-27](EP-27-estacion-offline-pwa.md) | Estación de cronometraje offline-first (PWA) | 6 (ISSUE-147…152) | Shell PWA (amplía el alcance heredado de ISSUE-069), captura IndexedDB con `client_mark_id`, sync idempotente, reloj con offset, doble estación, piloto |

### Heredadas (EP-01…EP-23)

Documentadas en el corpus de origen (repositorio del sistema); se listan solo como referencia de trazabilidad:

- EP-01 — Estabilización del sistema actual
- EP-02 — Privacidad, datos personales y secretos
- EP-03 — Fundaciones del nuevo proyecto
- EP-04 — Identidad, autenticación y autorización
- EP-05 — Configuración de eventos
- EP-06 — Directorio: personas, organizaciones y equipos
- EP-07 — Inscripciones y dorsales
- EP-08 — Cronometraje, marcas y correcciones
- EP-09 — Resultados, clasificación y cierre
- EP-10 — Liga y campeonatos
- EP-11 — Tiempo real
- EP-12 — Importaciones e integraciones
- EP-13 — Migración de datos *(fuera de alcance en la reconstrucción: enmienda a ADR-015)*
- EP-14 — Operación y observabilidad
- EP-15 — Plataforma distribuida
- EP-16 — Multi-tenant e identidad
- EP-17 — Planes y derechos de uso
- EP-18 — Pagos y preventas
- EP-19 — Portal público e inscripciones
- EP-20 — App móvil offline-first (B3)
- EP-21 — Protocolo de sincronización (B5)
- EP-22 — Visor de llegadas (B4)
- EP-23 — Transversal: seguridad y cumplimiento

---

## 3. Ajustes por la decisión TALL

Issues heredadas cuyo alcance cambia (las que **no** aparecen aquí quedan intactas: todo lo de API, datos, pagos, móvil,
visor backend, sync y transversal de seguridad).

- **ISSUE-033 → sustituida por ISSUE-105** (Laravel 13 / PHP 8.4).
- **ISSUE-035 → re-expresada en ISSUE-117** (CI del monolito: Pint/PHPStan/Pest/build Vite; TS solo para clientes de API).
- **ISSUE-036 → obsoleta: sustituida por ISSUE-106** (la UI web es TALL; sin SPA ni store de cliente).
- **ISSUE-038 → ISSUE-118** (enums PHP con etiquetas Blade; la generación TS queda solo para móvil y visor).
- **ISSUE-039 → ISSUE-118** (i18n en `lang/` PHP + filtros Blade; formato de duraciones desde ms).
- **ISSUE-042 → ISSUE-108** (login web por sesión Livewire; los tokens Sanctum con *abilities* quedan para dispositivos/estaciones).
- **ISSUE-043 → ISSUE-107, ISSUE-109** (policies intactas; menú y visibilidad cumplidos en el servidor: `@can` y menú generado desde permisos reales).
- **ISSUE-069 → EP-27 (ISSUE-147…152)** (amplía su alcance; sus criterios se conservan: ≥ 500 marcas offline, < 50 ms, doble estación, reloj con offset).
- **ISSUE-071 → ISSUE-134, ISSUE-142** (alcance intacto; pantalla Livewire con la integración realtime del puente nuevo).
- **ISSUE-076 → ISSUE-137** (alcance intacto; pantalla de proyección Livewire con estilos aislados, RF-55).
- **ISSUE-087 → ISSUE-142, ISSUE-143** (el estado vive en el componente servidor; se conserva su contrato: suscripción/desuscripción, `seq`, resync, polling de respaldo 15 s).
- **SAAS-103 → obsoleta** (el monolito sirve panel, portal y estación; no hay repositorio web aparte — EP-24).
- **SAAS-105 → alcance reducido** a los clientes de `/api/v1`: móvil, visor (Python) e integraciones.
- **SAAS-107 → ISSUE-108** (el panel usa la sesión propia del monolito; móvil/visor conservan sesión/token por superficie).
- **SAAS-121 → obsoleta: ISSUE-117** (CI/CD único del monolito).
- **SAAS-208 → ajuste de alcance** (gates server-side — middleware/policies — más render Livewire condicionado; nada se decide en cliente).
- **SAAS-266, SAAS-267, SAAS-268 → fase posterior** (páginas Livewire/Blade del monolito; OAuth/sesiones de atleta en la fase SaaS, ADR-016).
- **SAAS-272, SAAS-275, SAAS-278 → fase posterior** (re-expresadas como pantallas Livewire; se conservan sus reglas: borrador, QR firmado, `.ics`).
- **SAAS-281, SAAS-282 → ISSUE-115, ISSUE-147** (pantallas Livewire; portal/panel/estación como secciones del monolito).
- **SAAS-283 → ajuste de alcance** (SEO intacto: JSON-LD, sitemap, Open Graph; render server-side Blade + caché HTTP).
- **SAAS-369 → ajuste de alcance** (el panel Livewire consume el dominio directamente; la API queda para móvil/visor).
- **SAAS-416 → ajuste de alcance** (componente Livewire del monolito; pantalla operativa más que del panel).

**Queda expresamente intacto:** el dominio y la operación (EP-01, EP-02, EP-05…EP-07, EP-09…EP-14), multitenancy
(EP-16), pagos (EP-18), móvil (EP-20), sync (EP-21), transversal de seguridad (EP-23), y de EP-15/EP-22 todo lo de API,
dispositivos, webhooks, sync, rate limiting, infraestructura y visor backend (SAAS-104, 106, 108–120, 124, 125, 400–415,
417–426). Ninguna issue de datos o pagos cambia.

---

## 4. Épicas nuevas

| Épica | Detalle |
|---|---|
| **[EP-24 — Fundaciones Livewire/TALL](EP-24-fundaciones-livewire.md)** | ISSUE-105…118: esqueleto Laravel 13/PHP 8.4, base TALL, layout con menú generado desde los **permisos reales del servidor**, auth por sesiones, kit de componentes CRUD (tabla+formulario) con validación en servidor, estados de carga/error unificados, contexto por render explícito, PWA base, pruebas TALL y CI del monolito. Sustituye el alcance heredado de ISSUE-033/ISSUE-036. |
| **[EP-25 — Reconstrucción de las 14 pantallas del panel como componentes Livewire](EP-25-pantallas-livewire.md)** | ISSUE-119…140: mapeo completo pantalla del panel → componente → CU → RF: Login, Home/consola, Personas, Organizaciones, Equipos, Eventos (hub y cierre), Categorías, Oleadas (+modal de reprogramación), Distancias, Inscripciones (listado, formulario con categoría automática, captura de llegada, recálculo de categorías), Salidas (reloj, confirmaciones, marcar salida), Registros (bitácora, corrección con operaciones 1/2/3, foto vía proxy), Ranking (clasificación/llegadas/puntos, reactividad) y liga. Paridad funcional firmada con checklist por pantalla y E2E por rol. |
| **[EP-26 — Tiempo real con Reverb en las pantallas](EP-26-tiempo-real-reverb.md)** | ISSUE-141…146: contrato de eventos de dominio por pantalla (`v`/`seq`), puente Livewire↔Echo (re-expresa el alcance heredado de ISSUE-087), resync y estado de conexión, canales por evento autorizados, pruebas de caos (0 marcas perdidas o duplicadas; la marca nunca falla por un fallo de broadcast), ráfagas y estabilidad de proyección. |
| **[EP-27 — Estación de cronometraje offline-first (PWA)](EP-27-estacion-offline-pwa.md)** | ISSUE-147…152: shell PWA (amplía el alcance heredado de ISSUE-069), captura en IndexedDB con `client_mark_id`, sync idempotente por lotes contra `/api/v1` (ISSUE-067 heredada), reloj con offset, solo teclado, doble estación, evacuación de cola y piloto en evento real. |

---

## 5. Nuevas issues destacadas transversales

Issues nuevas que no pertenecen a una sola pantalla (continúan la numeración ISSUE-105+).

| ID | Título | Tipo | Prio | Est. | Resumen | Depende de |
|---|---|---|---|---|---|---|
| ISSUE-153 | dompdf y planillas de inscripción PDF | feature | P2 | M | Planillas de inscripción en PDF (RF-36): planilla por inscripción y en blanco, plantilla por organizador, descarga desde la inscripción; dompdf como dependencia declarada | ISSUE-129 |
| ISSUE-154 | Importador XLSX transaccional con previsualización | feature | P1 | L | Ejecución **transaccional por lotes** con mapeo de columnas, previsualización, informe por fila y sin contraseñas por defecto (alineado con ISSUE-028 heredada); UI Livewire de carga y resultado sobre el motor de ISSUE-089/090 heredadas (que se mantienen); cubre CU-12/RF-64 | ISSUE-089, ISSUE-090, ISSUE-110 |
| ISSUE-155 | Checklist de arranque limpio | docs | P1 | S | Lista verificable de que la base de datos nace de migraciones desde cero, los seeders son sintéticos sin datos reales de personas, no hay secretos en el repositorio (`.env.example` con marcadores, gitleaks) y no queda código muerto ni rutas fantasma | — |
| ISSUE-156 | Endpoint idempotente de marcas del servidor | feature | P0 | M | `POST /api/v1/events/{e}/timing-marks`: lotes transaccionales, deduplicación por `client_mark_id`, asignación a inscripción por `(event_id, bib)`, huérfanas y respuesta por marca. **Trae al repo el primer corte de ISSUE-067 heredada** | ISSUE-157 |
| ISSUE-157 | Migraciones del núcleo con pruebas de esquema | feature | P0 | M | Migraciones del núcleo to-be (ADR-015) con **pruebas de esquema escritas antes**: UNIQUE dorsal-evento y persona-evento, CHECK persona XOR equipo, `client_mark_id` UNIQUE, `timing_marks` append-only, FKs físicas | ISSUE-105 |
| ISSUE-158 | Motor de resultados del servidor | feature | P0 | L | Cálculo de la clasificación en jobs del servidor (`RANK()` sobre `net_ms = llegada − salida + Σ ajustes`), resultados derivados nunca editados a mano y conciliación en vivo ↔ oficial. **Trae al repo el primer corte de ISSUE-074 heredada** | ISSUE-157 |
| ISSUE-161 | Directorio: gestión de personas y equipos con PII por rol | feature | P1 | L | CRUD de personas (documento único, nombre, nacimiento, género) y equipos (por organización) sustituyendo los placeholders del panel (ISSUE-055…058 heredadas). PII enmascarada para roles sin permiso (RNF-13), búsqueda y validación de servidor | ISSUE-105, ISSUE-157 |
| ISSUE-160 | Configuración de eventos: CRUD de eventos, carreras, oleadas y categorías | feature | P1 | L | CRUD del evento y su configuración (ISSUE-048…051 heredadas): crear/editar evento con carreras (distancia/tipo), oleadas con hora programada y categorías por rango, reprogramación de oleadas en bloque. Sustituye los placeholders del panel | ISSUE-105, ISSUE-157 |
| ISSUE-159 | Base de autenticación y matriz de permisos del panel | feature | P0 | M | spatie teams (equipo 0 = plataforma), `accounts.active`, matriz base de roles/permisos del SRS §2 y middleware `permission:` con 403 probado. **Trae al repo las heredadas ISSUE-041/043** | ISSUE-105, ISSUE-157 |

**Checklist verificable de ISSUE-155:**

- `migrate:fresh` desde cero levanta la base de datos completa; ningún esquema fuera de las migraciones.
- Seeders con datos sintéticos; ningún dato real de personas en el repositorio ni en las semillas.
- Usuarios nuevos: admin bootstrap + seeders; sin datos heredados.
- Sin secretos versionados: `.env.example` solo con marcadores; `gitleaks` en CI (ISSUE-117).
- Sin código muerto ni rutas fantasma: toda ruta declara controlador y acción (chequeo estático en CI, ISSUE-115).
- Se usa como checklist de arranque en cada despliegue inicial de entorno.

**Núcleo del primer corte (ISSUE-156…158).** Las piezas del servidor que el primer corte necesita y que antes solo
existían como dependencias del corpus heredado. Con ellas, el camino de la meta queda definido de principio a fin
dentro de este repositorio.

### ISSUE-156 — Endpoint idempotente de marcas del servidor

| Tipo | Prioridad | Estimación | Labels | Depende de |
|---|---|---|---|---|
| feature | P0 | M | `api`, `backend`, `cronometraje`, `rebuild` | ISSUE-157 |

**Descripción.** El servidor que recibe los lotes de la estación: `POST /api/v1/events/{event}/timing-marks` acepta lotes de hasta 100 marcas dentro de una transacción; cada marca se deduplica por `client_mark_id` (UUIDv7, UNIQUE en base de datos), se asigna a su inscripción por `(event_id, bib)` o queda huérfana si el dorsal no existe, y la respuesta reporta el estado por marca (`assigned` / `orphan` / `duplicate`) con el `entry_id` asignado. Token de dispositivo Sanctum con ability `device:marks`; sin límite de tasa para estaciones autenticadas; 401/403 documentados para que el cliente conserve su cola. Trae al repositorio el alcance del primer corte de la heredada ISSUE-067 (que conserva su alcance v1 completo: consultas, correcciones por API, lectores de chips).

**Criterios de aceptación**
- [ ] Reenviar el mismo lote (o la misma marca suelta) no crea un segundo registro y devuelve el mismo resultado por marca (INV-02, prueba de idempotencia extremo a extremo).
- [ ] Dorsal existente → `assigned` con `entry_id`; dorsal desconocido → `orphan` con `entry_id` null; reenvío → `duplicate`.
- [ ] El lote se aplica en una única transacción: o se persiste completo o nada.
- [ ] Auth por token con `device:marks`; respuesta 401/403 sin efectos secundarios (INV-08: la marca nunca falla por el broadcast).
- [ ] Prueba de flujo completa: lote nuevo → reenvío sin duplicados → huérfana por dorsal desconocido.

### ISSUE-157 — Migraciones del núcleo con pruebas de esquema

| Tipo | Prioridad | Estimación | Labels | Depende de |
|---|---|---|---|---|
| feature | P0 | M | `database`, `backend`, `rebuild` | ISSUE-105 |

**Descripción.** Las migraciones del núcleo del modelo to-be (ADR-015, con la enmienda de arranque limpio) con sus **pruebas de esquema escritas antes que las migraciones**: `regions`, `organizations`, `persons`, `accounts`, `teams`, `team_user`, `events`, `races`, `waves`, `categories`, `entries`, `entry_adjustments`, `timing_marks` (append-only), `timing_mark_corrections` y `results`. Reglas: PK `bigint unsigned` + ULID `char(26)` público UNIQUE (el entero nunca sale al exterior), `datetime(3)` para instantes y `bigint` ms para duraciones, FKs físicas con `ON DELETE` definido, `UNIQUE` y `CHECK` explícitos, `utf8mb4`. Sin tablas de liga (v2).

**Criterios de aceptación**
- [ ] Prueba de esquema sobre `information_schema`: `UNIQUE (event_id, bib)`, `UNIQUE (event_id, person_id)`, CHECK persona XOR equipo, `client_mark_id` UNIQUE, FKs físicas presentes (INV-03/04/05/09).
- [ ] `timing_marks` es append-only **a nivel base de datos**: UPDATE o DELETE directo es rechazado (trigger o equivalente); la marca nunca se modifica (INV-01).
- [ ] ULID público presente y UNIQUE en cada tabla del núcleo; los ids enteros no aparecen en ninguna salida.
- [ ] `migrate:fresh --seed` y `migrate:rollback` probados en la PR (criterio G6): todas las migraciones reversibles.

### ISSUE-158 — Motor de resultados del servidor

| Tipo | Prioridad | Estimación | Labels | Depende de |
|---|---|---|---|---|
| feature | P0 | L | `backend`, `resultados`, `rebuild` | ISSUE-157 |

**Descripción.** El cálculo de la clasificación en el servidor, del que cuelga la pantalla pública: job que computa `net_ms = llegada − salida + Σ ajustes` y las posiciones general/género/categoría con `RANK() OVER`, escribiendo la tabla derivada `results` que nunca se edita a mano (INV-07). Recalcula al llegar cada marca (en cola) y al cerrar el evento produce el resultado oficial. Trae al repositorio el alcance del primer corte de la heredada ISSUE-074 (que conserva su alcance v1: puntos de liga, deltas parciales, publicación oficial con reversiones).

**Criterios de aceptación**
- [ ] Posiciones y diferencias provienen solo del servidor; prueba de conciliación: lo mostrado en vivo coincide con el resultado oficial al cerrar.
- [ ] `net_ms` incorpora la hora real de salida de la oleada y los ajustes acumulados (incluye caso > 24 h).
- [ ] Recalculo por marca en cola; sin bloqueo de la captura (la marca responde aunque el job espere).
- [ ] `results` jamás se escribe fuera del job (regla de arquitectura Pest Arch).

---

### ISSUE-159 — Base de autenticación y matriz de permisos del panel

| Tipo | Prioridad | Estimación | Labels | Depende de |
|---|---|---|---|---|
| feature | P0 | M | `backend`, `auth`, `identity`, `rebuild` | ISSUE-105, ISSUE-157 |

**Descripción.** Trae al repositorio las piezas heredadas (ISSUE-041/043 del corpus) que el login por sesiones y el menú del panel necesitan: spatie/laravel-permission **con teams** (`team_id = event_id`), la columna `accounts.active` (RF-68: solo cuentas activas inician sesión) y la matriz base de roles/permisos global del SRS §2. **Convención de equipos**: el contexto por defecto es el **equipo 0 («plataforma»)**, donde viven los roles globales (superadmin, admin); los roles operativos por evento (organizer, official, timekeeper) se asignan con `team_id = event_id` y un middleware de evento cambia el contexto (ISSUE-115). Permisos base: `events.{view,manage}`, `directory.view`, `accounts.manage`, `championships.view`, `entries.{view,manage}`, `start.manage`, `marks.{view,manage}`, `corrections.manage`, `results.{view,publish}`, `roles.manage`, `platform.manage`.

**Criterios de aceptación**
- [x] `Account` autentica por sesión web (documento **o** email + contraseña) y porta roles spatie con teams (RF-68/RF-69); los tokens de dispositivo por `/api/v1` siguen intactos.
- [x] Migración `accounts.active` reversible con prueba de esquema; tablas de spatie con `team_id` (prueba de esquema).
- [x] Seeder sintético de roles/permisos + cuentas demo por rol; prueba parametrizada de la matriz base por rol.
- [x] Middleware `permission:`/`role:` registrado; rutas protegidas dan 403 probado por rol.

---


### ISSUE-160 — Configuración de eventos: CRUD de eventos, carreras, oleadas y categorías

| Tipo | Prioridad | Estimación | Labels | Depende de |
|---|---|---|---|---|
| feature | P1 | L | `feature`, `frontend`, `eventos`, `rebuild` | ISSUE-105, ISSUE-157 |

**Descripción.** Trae al repositorio la configuración de eventos de las heredadas ISSUE-048…051 (F2/EP-05): listado y CRUD de eventos (nombre, slug, fecha, zona, estado), carreras por evento (distancia, tipo calle/trail/OCR), oleadas con hora programada (edición y reprogramación en bloque por hito inicial + intervalo) y categorías por rango de edad/género. Sustituye los placeholders del panel (event-list, category-list, wave-list) con pantallas reales construidas sobre el kit, con permiso `events.manage` y validación de servidor.

**Criterios de aceptación**
- [ ] Crear/editar evento con carreras, oleadas y categorías desde el panel (sin seeder).
- [ ] Reprogramación de oleadas en bloque (hora inicial + intervalo) con una sola notificación.
- [ ] Validaciones de servidor (slugs únicos, rangos de categoría coherentes) y 403 por rol.

---

### ISSUE-161 — Directorio: gestión de personas y equipos con PII por rol

| Tipo | Prioridad | Estimación | Labels | Depende de |
|---|---|---|---|---|
| feature | P1 | L | `feature`, `frontend`, `identity`, `rebuild` | ISSUE-105, ISSUE-157 |

**Descripción.** Trae las heredadas ISSUE-055…058 (F2/EP-06): gestión del directorio desde el panel — personas (documento único, nombre, nacimiento, género) y equipos por organización — sustituyendo los placeholders directory.persons/teams. La PII (documento, nacimiento) solo visible con el permiso de directorio (RNF-13); para el resto, enmascarada o ausente. Búsqueda por nombre/documento (parcial, sin enumeración), validación de servidor y borrado bloqueado por inscripciones/cuentas.

**Criterios de aceptación**
- [ ] CRUD de personas y equipos operativo con validaciones (documento único) y 403 por rol.
- [ ] PII enmascarada para roles sin permiso (prueba de render por rol).
- [ ] Búsqueda parcial sin enumeración (documento enmascarado en resultados).

---

## 6. Priorización y secuencia

**P0 del backlog nuevo (21).** Lo sin lo cual la reconstrucción no arranca o el día de carrera no es seguro:

| Grupo | Issues |
|---|---|
| Fundaciones (EP-24) | ISSUE-105 (esqueleto), ISSUE-106 (base TALL), ISSUE-108 (auth sesiones), ISSUE-110 (kit CRUD), ISSUE-111 (carga/error) |
| Núcleo del primer corte (§5) | ISSUE-156 (endpoint idempotente de marcas), ISSUE-157 (migraciones del núcleo), ISSUE-158 (motor de resultados) |
| Tiempo real (EP-26) | ISSUE-141 (contrato), ISSUE-142 (puente Echo), ISSUE-144 (canales autorizados), ISSUE-145 (caos) |
| Estación offline (EP-27) | ISSUE-148 (captura IndexedDB), ISSUE-149 (sync idempotente), ISSUE-150 (reloj offset) |
| Pantallas críticas (EP-25) | ISSUE-130 (captura de llegada), ISSUE-132/133 (salidas), ISSUE-134/135 (registros y corrección), ISSUE-137 (ranking) |

**Secuencia por fases** (detalle temporal en [../08-roadmap.md](../08-roadmap.md)):

1. **Fase F1 — fundaciones y operación crítica.** EP-24 completa → **núcleo del primer corte (ISSUE-157 migraciones → ISSUE-156 endpoint de marcas → ISSUE-158 motor de resultados)** → EP-26 completa (afterCommit + caos) → EP-27 completa (estación offline). De EP-25 se **adelanta la rebanada de paridad crítica** (ISSUE-130, 132, 133, 134, 135, 137) en su versión mínima verificable, porque el caos de EP-26 y el piloto de EP-27 necesitan pantallas reales contra las que medir. Completa F1: captura de marca tolerante a caídas de WS y de red, con pantallas operativas mínimas.
2. **Fase F2 — paridad completa y MVP heredado.** Resto de EP-25 (ISSUE-119…129, 131, 136, 138…140) + el MVP del backlog heredado (EP-05: ISSUE-048…052; EP-06: ISSUE-055…058; EP-07: ISSUE-060…062 y 064…066; EP-08: ISSUE-067…068 — el primer corte de la 067 ya vive aquí como ISSUE-156 — y 070…071; EP-09: ISSUE-074…077 — el motor del primer corte ya vive aquí como ISSUE-158 —; EP-11: ISSUE-084…086 y 088; ISSUE-099, 101, 102) + ISSUE-153…155. Cierre con ISSUE-140 (paridad firmada) e ISSUE-145/152 (caos y piloto) como puertas de salida.
3. **Fase F3 — v1.** Importaciones (ISSUE-154 + ISSUE-091 heredada), liga (ISSUE-081…082), operación (runbook, backups, observabilidad) y las P2 heredadas (053, 059, 063, 078, 079, 088 ya en F2, 100, 103, 104). La migración de datos heredados queda **fuera de alcance** (enmienda a ADR-015, 2026-10-20): base de datos nueva creada íntegramente por migraciones desde cero; seeders con datos sintéticos; usuarios nuevos (admin bootstrap + seeders); sin conexión ni migración de datos heredados.
4. **Fase posterior — SaaS multitenant (ADR-016).** EP-15…EP-23 en el orden del roadmap heredado del anexo SaaS, con los ajustes de §3 aplicados (portal público como área Livewire del monolito).

**Regla de dependencia con el backlog heredado:** una pantalla de EP-25 nunca precede a su issue de dominio heredada (p. ej. ISSUE-129 requiere ISSUE-060; ISSUE-137 requiere ISSUE-158, ahora en este backlog). Las dependencias exactas están en cada issue de las épicas nuevas.

---

## 7. Convenciones

Unificadas para todo el backlog (heredado + nuevo). Cuando un campo difiera entre los corpus heredados, manda la convención unificada de esta sección.

**Tipos**

| Tipo | Uso |
|---|---|
| `bug` | Comportamiento incorrecto verificable |
| `feature` | Funcionalidad nueva o de reconstrucción |
| `tech-debt` | Deuda estructural que limita evolución |
| `security` | Vulnerabilidad o endurecimiento |
| `chore` | Infraestructura, tooling, build, dependencias |
| `test` | Suites o pruebas de caos/e2e como entregable principal |
| `spike` | Exploración con decisión de salida (documento/ADR) |
| `docs` | Documentación como entregable principal |

(El backlog heredado usaba cinco tipos; el anexo SaaS añadió `test`, `spike` y `docs` — se unifican los ocho.)

**Prioridad.** **P0** crítico: se corrige ya (pérdida de datos, exposición de PII o secretos, escalada de privilegios, bloqueo del arranque de la reconstrucción) · **P1** necesario para el MVP o para operar con seguridad · **P2** v1 · **P3** v2 / deseable.

**Estimación — talla unificada.** Se adopta la escala del anexo SaaS para todo el backlog, resolviendo la inconsistencia de escalas del backlog heredado:

| Talla | Esfuerzo (días-persona) |
|---|---|
| S | ≤ 2 |
| M | ≤ 5 |
| L | ≤ 10 |
| XL | > 10 |

Las issues heredadas conservan la talla impresa en su documento; al re-planificarlas se convierten a esta escala (S↔S, M↔M, L↔L/XL según esfuerzo real). La suma "esfuerzo máximo" de los resúmenes usa el límite superior de cada talla.

**Etiquetas.** Ámbito: `rebuild`. Capa: `backend`, `frontend`, `database`, `devops`, `ci`, `api`, `tests`. Dominio: las del backlog heredado (`auth`, `identity`, `eventos`, `categorias`, `salidas`, `inscripciones`, `cronometraje`, `correcciones`, `resultados`, `clasificacion`, `liga`, `imports`, `tiempo-real`, `migration`) más las nuevas de esta decisión: `livewire`, `tall`, `pwa`, `offline`, `architecture`. Transversales: `security`, `privacy`, `secrets`, `audit`, `performance`, `ux`, `i18n`, `business-rule`, `data-quality`, `observability`, `backup`, `integration`, `docs`.

**Formato de issue** (el del backlog heredado, usado en EP-24…27):

```markdown
### ISSUE-NNN — Título
| Tipo | Prioridad | Estimación | Labels | Depende de |
**Descripción.** …con la justificación y las referencias (RF/CU, issues heredadas) que apliquen…
**Criterios de aceptación**
- [ ] …criterio medible y verificable…
```

En el tracker: título = `ISSUE-NNN — <título>`, milestone/épica = EP-NN, etiquetas = columna Labels, cuerpo = la sección de la issue. Cada criterio de aceptación debe ser observable en una prueba, una revisión o una medición; si no lo es, se reescribe hasta que lo sea.
