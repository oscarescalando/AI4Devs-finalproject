# EP-24 — Fundaciones Livewire/TALL

> **Fecha:** 2026-10-19 · **Estado:** backlog vigente · Documento de especificación de TDChronos 2.0

**Objetivo:** esqueleto completo de la aplicación Laravel 13 + PHP 8.4 con **toda la UI web en TALL** (Livewire 4 + Volt + Alpine + Tailwind): layout, autenticación por sesiones, autorización real del servidor hasta la interfaz, kit de componentes CRUD reutilizables, estados de carga/error unificados y base PWA. Es la capa sobre la que se reconstruyen las 14 pantallas del panel ([EP-25](EP-25-pantallas-livewire.md)).

Decisiones que esta épica materializa (ver [../03-arquitectura/](../03-arquitectura/)): ADR-010 (stack Laravel 13 + PHP 8.4), ADR-011 (UI TALL para toda la web), autorización en servidor ([../02-srs.md](../02-srs.md) §3.15). El backend de dominio sigue el modelo to-be de ADR-015 (persona ≠ cuenta, ULID, tiempos en ms).

**Relación con el backlog heredado** (corpus de origen, repositorio del sistema):

| Issue heredada | Destino |
|---|---|
| ISSUE-033 (fundaciones Laravel 12 / PHP 8.3) | **Sustituida** por ISSUE-105 (Laravel 13 / PHP 8.4) |
| ISSUE-036 (front base SPA) | **Obsoleta** (asumía un front SPA): sustituida por ISSUE-106 |
| ISSUE-039 (i18n de SPA) | **Ajustada**: re-expresada en ISSUE-118 (Blade + `lang/`) |
| ISSUE-042 (login SPA + Sanctum) | **Ajustada**: re-expresada en ISSUE-108; los tokens con *abilities* para dispositivos se conservan |
| ISSUE-035 (CI con etapas de SPA) | **Ajustada**: re-expresada en ISSUE-117 |
| ISSUE-034, ISSUE-037, ISSUE-038, ISSUE-040, ISSUE-041, ISSUE-043…047 | **Se mantienen** (sin cambios de alcance) |

| Issue | Tipo | Prio | Est. | Título |
|---|---|---|---|---|
| [ISSUE-105](#issue-105) | chore | P0 | M | Esqueleto Laravel 13 / PHP 8.4 del monolito modular |
| [ISSUE-106](#issue-106) | chore | P0 | M | Base TALL: Livewire 4 + Volt + Alpine + Tailwind vía Vite |
| [ISSUE-107](#issue-107) | feature | P1 | M | Layout del panel con menú por permisos reales del servidor |
| [ISSUE-108](#issue-108) | feature | P0 | M | Autenticación por sesiones en Livewire |
| [ISSUE-109](#issue-109) | feature | P1 | M | Autorización TALL: policies hasta la interfaz |
| [ISSUE-110](#issue-110) | feature | P0 | L | Kit de componentes CRUD reutilizables (tabla + formulario) con validación servidor |
| [ISSUE-111](#issue-111) | feature | P0 | M | Estados de carga, error y notificación unificados |
| [ISSUE-112](#issue-112) | chore | P1 | S | Contexto servidor → cliente por render explícito |
| [ISSUE-113](#issue-113) | feature | P1 | S | Perfil propio y cambio de contraseña con la actual |
| [ISSUE-114](#issue-114) | chore | P1 | M | PWA base instalable |
| [ISSUE-115](#issue-115) | feature | P1 | S | Rutas y navegación to-be (evento en el path, sin trampas de query string) |
| [ISSUE-116](#issue-116) | test | P1 | L | Pruebas automatizadas de la capa TALL |
| [ISSUE-117](#issue-117) | chore | P1 | M | CI/CD del monolito TALL |
| [ISSUE-118](#issue-118) | chore | P2 | S | i18n es-VE y formato de tiempos en Blade |

---

<a id="issue-105"></a>
### ISSUE-105 — Esqueleto Laravel 13 / PHP 8.4 del monolito modular

| Tipo | Prioridad | Estimación | Labels | Depende de |
|---|---|---|---|---|
| chore | P0 | M | `chore`, `backend`, `rebuild`, `architecture` | — |

**Descripción.** Proyecto nuevo desde cero, sin código heredado. Monolito modular con los módulos de dominio, modelo de datos to-be (ADR-015: `persons` ≠ `accounts`, marcas append-only, resultados derivados, tiempos en ms, ids públicos ULID). Base de datos creada íntegramente por migraciones desde cero; seeders con datos sintéticos; usuarios nuevos (admin bootstrap + seeders); sin conexión ni migración de datos heredados. Amplía y sustituye el alcance heredado de ISSUE-033 (Laravel 13 / PHP 8.4).

**Criterios de aceptación**
- [ ] Laravel 13, PHP ≥ 8.4, `declare(strict_types=1)`, Pint y PHPStan/Larastan al nivel máximo en CI.
- [ ] Módulos de dominio creados con un ejemplo mínimo verificable (health check) y reglas de dependencia entre módulos probadas (Pest Arch o Deptrac).
- [ ] Zona horaria `America/Caracas`, MySQL `utf8mb4`, columnas de tiempo con precisión de milisegundos donde aplique.
- [ ] Ids públicos ULID con *route model binding*; los ids enteros nunca salen al exterior.
- [ ] `README` de arranque en ≤ 5 comandos; `migrate:fresh --seed` produce un entorno demo utilizable (complementa ISSUE-040 heredada).

---

<a id="issue-106"></a>
### ISSUE-106 — Base TALL: Livewire 4 + Volt + Alpine + Tailwind vía Vite

| Tipo | Prioridad | Estimación | Labels | Depende de |
|---|---|---|---|---|
| chore | P0 | M | `chore`, `frontend`, `livewire`, `rebuild` | ISSUE-105 |

**Descripción.** Instala y normaliza la pila TALL como **única** capa web (ADR-011): Livewire 4 con Volt (componentes de página de un archivo), Alpine para interacción ligera, Tailwind para estilos, build con Vite. Sustituye el alcance heredado de ISSUE-036 (front SPA), obsoleto con la decisión TALL. No existe router JS ni store de cliente: el estado vive en el servidor y el navegador solo recibe HTML/Alpine.

**Criterios de aceptación**
- [ ] Livewire 4 + Volt + Alpine + Tailwind operativos con build Vite (assets con hash) y hot-reload en desarrollo.
- [ ] Convención documentada: cuándo un componente Volt funcional (pantallas) y cuándo una clase Livewire (CRUD con acciones); nomenclatura de namespaces única.
- [ ] Página de demostración con tabla + formulario + modal construida solo con el kit (ISSUE-110) para validar la pila de punta a punta.
- [ ] `package.json` sin dependencias de frameworks de SPA ni jQuery; sin kits de estilos ajenos: los estilos provienen únicamente de Tailwind.
- [ ] Reglas de estilo front (ESLint/Prettier para el JS mínimo de Alpine) configuradas.

---

<a id="issue-107"></a>
### ISSUE-107 — Layout del panel con menú por permisos reales del servidor

| Tipo | Prioridad | Estimación | Labels | Depende de |
|---|---|---|---|---|
| feature | P1 | M | `feature`, `frontend`, `livewire`, `auth`, `rebuild`, `ux` | ISSUE-106, ISSUE-109 |

**Descripción.** Layout Blade del panel operativo. El menú se renderiza **desde los permisos reales del usuario en el servidor** (spatie/laravel-permission); ningún rol ni permiso se publica en JS del cliente. Cabecera con cuenta y salir, sidebar, breadcrumbs por página, y branding configurable desde configuración (sin títulos ni logos fijos en código).

**Criterios de aceptación**
- [ ] Cada entrada del menú exige un permiso; un usuario sin el permiso no ve la entrada **ni puede abrir la ruta** (403 server-side, con prueba).
- [ ] Entradas del panel: Eventos, Organizaciones, Personas, Equipos, Liga; entradas operativas por evento: Inscripciones, Salidas, Registros, Clasificación.
- [ ] Breadcrumbs y título por página; logout con POST y confirmación de sesión.
- [ ] Branding (nombre, logo, temporada) desde configuración/BD: cambiar de temporada no exige tocar componentes (prueba con dos temporadas).

---

<a id="issue-108"></a>
### ISSUE-108 — Autenticación por sesiones en Livewire

| Tipo | Prioridad | Estimación | Labels | Depende de |
|---|---|---|---|---|
| feature | P0 | M | `feature`, `backend`, `frontend`, `auth`, `rebuild` | ISSUE-106, ISSUE-041 |

**Descripción.** Re-expresión del login web en TALL: la UI web usa **sesión Laravel** (cookie `HttpOnly` + CSRF de la propia app); Sanctum queda para tokens de dispositivos/estaciones (ISSUE-042 heredada conserva ese alcance). Login por documento **o** email + contraseña, `remember`, bloqueo por intentos. Redirección a la ruta intentada sin recarga del shell.

**Criterios de aceptación**
- [ ] Login/logout funcionales con errores 422 pintados bajo el campo; mensajes genéricos ante credenciales inválidas.
- [ ] Bloqueo: 5 intentos/min por usuario+IP (prueba automatizada).
- [ ] Tras login se respeta la ruta intentada (`redirect intended`); sesión expirada (419) → login con mensaje, nunca pantalla en blanco.
- [ ] Solo cuentas activas inician sesión (persona ≠ cuenta, ADR-015).
- [ ] Los tokens de dispositivo (estación, móvil, visor) se emiten por `/api/v1` y no comparten la sesión web.

---

<a id="issue-109"></a>
### ISSUE-109 — Autorización TALL: policies hasta la interfaz

| Tipo | Prioridad | Estimación | Labels | Depende de |
|---|---|---|---|---|
| feature | P1 | M | `feature`, `frontend`, `livewire`, `auth`, `security`, `rebuild` | ISSUE-107, ISSUE-043 |

**Descripción.** Lleva ISSUE-043 heredada (policies + spatie *teams* por evento, que se mantiene intacta como backend) a la capa TALL: directivas `@can` en Blade, gate de cada acción pública de componente, y regla explícita: **la UI solo oculta; el servidor siempre autoriza** — toda acción se valida con policy en el servidor aunque el cliente la oculte.

**Criterios de aceptación**
- [ ] Ninguna acción/método público de componente sin comprobación de policy (regla verificada en CI con una prueba de arquitectura o chequeo estático).
- [ ] Prueba parametrizada por rol de la matriz de permisos **de la UI** (complementa ISSUE-046 heredada, que cubre la API): cada acción visible/invisible y permitida/rechazada según el rol esperado.
- [ ] Nadie asigna ni edita un rol superior al propio (regla de ISSUE-043, probada también desde la UI).
- [ ] Los permisos se evalúan con ámbito de evento donde corresponde (cronometrador de un evento no opera otro).

---

<a id="issue-110"></a>
### ISSUE-110 — Kit de componentes CRUD reutilizables (tabla + formulario) con validación servidor

| Tipo | Prioridad | Estimación | Labels | Depende de |
|---|---|---|---|---|
| feature | P0 | L | `feature`, `frontend`, `livewire`, `rebuild`, `ux` | ISSUE-106, ISSUE-109 |

**Descripción.** Kit base de tabla y formulario con validación **en el servidor** como única fuente de verdad:

- **Tabla**: paginación server-side con ventana, ordenación solo por columnas de lista blanca (alineada con ISSUE-012 heredada), filtros componibles, columna de acciones condicionada por permisos, estado de URL (query string compartible).
- **Formulario**: campos con error por campo desde la validación del servidor, foco al primer inválido, modal de confirmación para acciones destructivas, bloqueo de doble envío.
- **Subida de archivos**: multipart con límite configurable y mensajes claros; miniaturas servidas por el backend; límites coherentes en todas las pantallas.

**Criterios de aceptación**
- [ ] Un CRUD de demostración (p. ej. Organizaciones) construido exclusivamente con el kit, sin CSS/JS ad-hoc.
- [ ] Todo error de validación del servidor aparece junto a su campo en todos los formularios construidos con el kit (prueba de componente).
- [ ] Ordenación por columna fuera de la lista blanca → 422 o ignorada (prueba).
- [ ] Confirmación previa en toda acción destructiva; doble clic no duplica la acción (prueba).
- [ ] Subida multipart con límite por configuración; errores de tipo/tamaño claros; miniaturas servidas por el backend.

---

<a id="issue-111"></a>
### ISSUE-111 — Estados de carga, error y notificación unificados

| Tipo | Prioridad | Estimación | Labels | Depende de |
|---|---|---|---|---|
| feature | P0 | M | `feature`, `frontend`, `livewire`, `ux`, `rebuild` | ISSUE-106 |

**Descripción.** Sistema único de feedback visual: `wire:loading` con esqueletos para tablas y formularios, barra de progreso global, toasts de resultado, y **interceptor global de errores**: cualquier fallo de acción Livewire o error de render muestra toast con correlación y libera la UI. Complementa ISSUE-017 heredada.

**Criterios de aceptación**
- [ ] Toda acción asíncrona muestra estado de carga acotado al área afectada; ninguna queda con spinner colgado (prueba forzando fallo).
- [ ] Error de servidor → toast con id de correlación; el usuario nunca ve silencio ante 401/419/500 (casos probados).
- [ ] 419 (sesión expirada) → redirección a login conservando destino; el formulario no pierde el dato en curso donde sea recuperable.
- [ ] Páginas 403/404/500 de marca propia, coherentes con el layout.
- [ ] Toasts accesibles (rol `status`, cierre automático) y en español.

---

<a id="issue-112"></a>
### ISSUE-112 — Contexto servidor → cliente por render explícito

| Tipo | Prioridad | Estimación | Labels | Depende de |
|---|---|---|---|---|
| chore | P1 | S | `chore`, `frontend`, `backend`, `rebuild` | ISSUE-107 |

**Descripción.** El contexto de sesión llega por render explícito: el layout Blade recibe la cuenta y sus permisos del servidor, y `@json` solo para datos no sensibles de arranque. Ningún dato de sesión viaja por variables globales JS generadas por paquetes; sin ficheros de variables JS dedicados que puedan convertirse en punto único de fallo del arranque.

**Criterios de aceptación**
- [ ] Ningún dato de sesión viaja por variables globales JS generadas por paquetes; el layout renderiza con la cuenta en sesión o como invitado.
- [ ] No existe ningún paquete de inyección de variables JS ni parcial dedicado a ello (chequeo en CI de que el arranque no depende de ningún archivo de variables generado).
- [ ] Prueba de arranque: la app renderiza correctamente con sesión válida, con sesión expirada y sin sesión.

---

<a id="issue-113"></a>
### ISSUE-113 — Perfil propio y cambio de contraseña con la actual

| Tipo | Prioridad | Estimación | Labels | Depende de |
|---|---|---|---|---|
| feature | P1 | S | `feature`, `frontend`, `auth`, `security`, `rebuild` | ISSUE-108 |

**Descripción.** Pantalla de perfil de la cuenta propia en Livewire: datos no sensibles, cambio de contraseña **exigiendo la actual** (alineado con ISSUE-013 heredada), campo de confirmación de contraseña operativo, y cierre de las demás sesiones al cambiar la contraseña. Complementa ISSUE-044 heredada (invitación y recuperación).

**Criterios de aceptación**
- [ ] Cambio de contraseña sin la actual → 422 (prueba); con la actual → éxito e invalidación de las otras sesiones.
- [ ] Los campos de contraseña quedan vacíos tras guardar; nunca se rellenan con el hash.
- [ ] La propia cuenta no puede cambiarse el rol ni desactivarse a sí misma (prueba).
- [ ] Un usuario `user` edita su perfil sin ver el selector de rol.

---

<a id="issue-114"></a>
### ISSUE-114 — PWA base instalable

| Tipo | Prioridad | Estimación | Labels | Depende de |
|---|---|---|---|---|
| chore | P1 | M | `chore`, `frontend`, `pwa`, `offline`, `rebuild` | ISSUE-106 |

**Descripción.** Manifest, service worker con estrategia offline para el *shell* de la aplicación (layout, assets Vite con hash, pantalla "sin conexión"), y actualización controlada de assets. Es la base sobre la que [EP-27](EP-27-estacion-offline-pwa.md) construye la estación de cronometraje (ADR-014); el resto de pantallas funciona normal online con el mismo service worker.

**Criterios de aceptación**
- [ ] Instalable en Chrome/Edge (criterios de instalabilidad de Lighthouse completos).
- [ ] El shell carga sin red; las rutas operativas muestran la pantalla offline coherente.
- [ ] Los assets versionados se cachean por hash; un despliegue nuevo invalida la caché sin servir mezclas obsoletas.
- [ ] El service worker no interfiere con las peticiones de Livewire (solo assets y shell).

---

<a id="issue-115"></a>
### ISSUE-115 — Rutas y navegación to-be (evento en el path, sin trampas de query string)

| Tipo | Prioridad | Estimación | Labels | Depende de |
|---|---|---|---|---|
| feature | P1 | S | `feature`, `frontend`, `rebuild`, `ux` | ISSUE-107 |

**Descripción.** Define el mapa de rutas del panel: recursos con el evento en el **path** (`/events/{event}/registrations`, `/events/{event}/start`, …) en lugar de identificadores propagados a mano por query string, middleware de permisos por ruta, nombres de ruta únicos y 404 coherente. Toda pantalla operativa tiene vía de entrada cuando existe el permiso; si falta configuración del evento, se muestra aviso accionable.

**Criterios de aceptación**
- [ ] Rutas nombradas que cubren las 14 pantallas del panel (mapeo en [EP-25](EP-25-pantallas-livewire.md)); sin rutas resource sin implementación (chequeo estático en CI).
- [ ] Middleware de permisos por ruta registrado en la matriz de ISSUE-046 heredada.
- [ ] Inscripciones/Salidas/Registros/Clasificación accesibles siempre que exista el permiso; si falta configuración del evento se muestra aviso accionable, no ausencia de enlace.
- [ ] El estado de tablas (página, filtros, orden) vive en query string nombrada y comparte URL.

---

<a id="issue-116"></a>
### ISSUE-116 — Pruebas automatizadas de la capa TALL

| Tipo | Prioridad | Estimación | Labels | Depende de |
|---|---|---|---|---|
| test | P1 | L | `test`, `frontend`, `livewire`, `tests`, `rebuild` | ISSUE-110, ISSUE-111 |

**Descripción.** Estrategia de pruebas de la UI: pruebas de componente (render, autorización, validación de servidor, estados de carga) y pruebas de navegador para los flujos críticos. Complementa ISSUE-046 heredada (matriz de permisos de la API) y deja la UI cubierta por el mismo pipeline de ISSUE-117.

**Criterios de aceptación**
- [ ] Cada componente del kit (ISSUE-110) con pruebas de authorize, validación y estados de error.
- [ ] Recorrido E2E en CI: login → crear evento → categorías/oleadas → inscribir → confirmar y dar salida → registrar llegada → corregir marca → ver ranking.
- [ ] Prueba de que un usuario sin permisos no alcanza ninguna ruta operativa (recorrido de las rutas de ISSUE-115).
- [ ] Cobertura del paquete de UI publicada en CI con umbral definido; las pruebas de navegador corren contra MySQL real.

---

<a id="issue-117"></a>
### ISSUE-117 — CI/CD del monolito TALL

| Tipo | Prioridad | Estimación | Labels | Depende de |
|---|---|---|---|---|
| chore | P1 | M | `chore`, `devops`, `ci`, `rebuild` | ISSUE-105, ISSUE-106 |

**Descripción.** Pipeline del monolito, adaptando el alcance heredado de ISSUE-035: etapas lint (Pint, Prettier), análisis estático (PHPStan), pruebas (Pest con MySQL real + navegador), build de assets Vite, seguridad (`composer audit`, gitleaks) e imagen OCI única (alineada con SAAS-118 heredada). Sin etapas de front SPA que ya no aplican; la generación de tipos TS queda limitada a los clientes que sí consumen `/api/v1` (móvil/visor, ISSUE-038 heredada ajustada).

**Criterios de aceptación**
- [ ] Pipeline < 10 min que bloquea el merge; corre en PRs/merge requests a la rama principal.
- [ ] Build de assets con hash reproducible; falla si el manifest y las vistas quedan desalineados.
- [ ] Gitleaks falla ante cualquier secreto presente en el repositorio.
- [ ] Imagen OCI versionada publicada al etiquetar; despliegue documentado para nodo de sede y nube.

---

<a id="issue-118"></a>
### ISSUE-118 — i18n es-VE y formato de tiempos en Blade

| Tipo | Prioridad | Estimación | Labels | Depende de |
|---|---|---|---|---|
| chore | P2 | S | `chore`, `frontend`, `backend`, `i18n`, `rebuild` | ISSUE-106 |

**Descripción.** Re-expresión del alcance heredado de ISSUE-039 en Blade: textos en archivos `lang/` PHP, locale `es-VE` por defecto, y utilidad única de formato de duraciones desde milisegundos (ADR-015) que soporta > 24 h y décimas opcionales. Textos de interfaz revisados ortográficamente.

**Criterios de aceptación**
- [ ] Sin literales de interfaz en componentes (chequeo en CI); todo texto pasa por `lang/`.
- [ ] `formatDuration(ms)` con pruebas para 0, < 1 h, > 24 h (`54:05:34`) y valores negativos (diferencias).
- [ ] Mensajes de validación en español, incluido "documento o contraseña inválidos".
- [ ] Fechas en formato `dd/mm/yyyy` en la UI y ISO en BD; revisión ortográfica de los textos de interfaz aprobada (checklist o revisión).
