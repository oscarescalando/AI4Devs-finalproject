> Detalla en esta sección los prompts principales utilizados durante la creación del proyecto, que justifiquen el uso de asistentes de código en todas las fases del ciclo de vida del desarrollo. Esperamos un máximo de 3 por sección, principalmente los de creación inicial o los de corrección o adición de funcionalidades que consideres más relevantes.
Puedes añadir adicionalmente la conversación completa como link o archivo adjunto si así lo consideras

**Nota de contexto (honestidad sobre la fase actual):** este proyecto está en la fase de **especificación (Entrega 1)**. Los prompts realmente ejecutados hasta hoy fueron de **orquestación y curación de la especificación** (carpeta `spec/`), y de **redacción de este readme y de prompts.md**. Las fases de construcción aún no ejecutadas se documentan como **PROMPT GUÍA** del flujo SDD (spec → plan → tasks → implementación → tests, ver [spec/README.md](spec/README.md) §3): cada uno cita los archivos de `spec/` que lo alimentarán cuando se ejecute.

**Proyecto:** TDChronos 2.0 · **Autores:** Oscar Ramon Lobo Altuve (oscarescalando@gmail.com) y Gabriel Toro (gabrieltoro77@gmail.com) · **Repositorio:** https://github.com/oscarescalando/AI4Devs-finalproject (rama `feature/entrega-1-ol-gt`).

## Índice

1. [Descripción general del producto](#1-descripción-general-del-producto)
2. [Arquitectura del sistema](#2-arquitectura-del-sistema)
3. [Tipo de dato / Modelo de datos](#3-modelo-de-datos)
4. [Especificación de la API](#4-especificación-de-la-api)
5. [Historias de usuario](#5-historias-de-usuario)
6. [Tickets de trabajo](#6-tickets-de-trabajo)
7. [Pull requests](#7-pull-requests)

---

## 1. Descripción general del producto

**Prompt 1 (real — Entrega 1):**

> Eres el orquestador de la Entrega 1 de TDChronos 2.0. Construye la carpeta `spec/` del repositorio de entregas a partir del corpus de documentación to-be (PRD, SRS, ADR-010…018, épicas EP-24…27, DoD, roadmap): copia los documentos a la estructura `spec/` y conserva los identificadores (RF/RNF/HU/CU/EP/ISSUE/ADR/G/M/O) como traza de trazabilidad. Aplica las reglas de arranque limpio: (a) la migración de datos históricos queda fuera de alcance — la base de datos se crea 100 % con migraciones y seeders sintéticos con usuarios nuevos (admin bootstrap); (b) elimina toda narrativa del sistema anterior y reformúlala en positivo (integridad de marcas, privacidad, operación offline); (c) reescribe las referencias internas a las rutas de `spec/`; (d) cero URLs de repositorios externos distintos del repositorio de entregas. Añade un README índice con el proceso SDD y las reglas de oro, un documento de invariantes de dominio (INV-nn con enunciado, fuente y verificación) y una estrategia de pruebas consolidada con gates de CI y trazabilidad.

Ref: crea `spec/01-prd.md`, `spec/02-srs.md`, `spec/03-arquitectura/`, `spec/04-backlog/`, `spec/05-dod.md`, `spec/06-invariantes.md`, `spec/07-tests.md`, `spec/08-roadmap.md`, `spec/README.md`.

**Prompt 2 (real — Entrega 1):**

> Redacta `readme.md` con la plantilla AI4Devs (secciones 0–7) para TDChronos 2.0 como sistema nuevo: ficha con los dos autores y sus emails, descripción to-be sin métricas ni defectos de versiones anteriores, instalación limpia (requisitos, docker compose, `.env.example`, `key:generate`, `migrate --seed` con nota explícita de que no se migra nada de versiones anteriores), arquitectura con diagrama mermaid y tabla de componentes, modelo de datos to-be con erDiagram nuevo basado en ADR-015 y SRS §5, API `/api/v1` con OpenAPI (`/time`, leaderboard público sin PII, `POST /events/{e}/timing-marks` idempotente), tres historias de usuario (HU-47, HU-52, HU-49), tres tickets reales del backlog —sustituyendo el ticket de migración ETL por ISSUE-105 de fundaciones— y política de PRs con la DoD G1–G15 como puerta. Enlaza los documentos de `spec/` con rutas relativas y menciona prompts.md.

Ref: `readme.md`; fuentes: `spec/01-prd.md`, `spec/02-srs.md`, `spec/03-arquitectura/`, `spec/04-backlog/`, `spec/05-dod.md`, `spec/06-invariantes.md`, `spec/07-tests.md`.

**Prompt 3 (guía — próximas iteraciones del producto):**

> Dada la historia HU-nn de [spec/01-prd.md](spec/01-prd.md) §4 y los requisitos que la acotan en [spec/02-srs.md](spec/02-srs.md) §3-§4, revisa y actualiza el PRD y el SRS: completa o corrige el texto de la historia, su prioridad MoSCoW, los RF/RNF que la cubren y su épica; verifica que ninguna invariante de [spec/06-invariantes.md](spec/06-invariantes.md) quede contradicha; y registra en §8 del SRS las preguntas abiertas que surjan. Devuelve el diff por archivo.

Ref: `spec/01-prd.md`, `spec/02-srs.md`, `spec/06-invariantes.md`.

---

## 2. Arquitectura del Sistema

### **2.1. Diagrama de arquitectura:**

**Prompt 1 (guía — fase de diseño):**

> Genera el diagrama de arquitectura (mermaid flowchart) de TDChronos 2.0 conforme a los ADR-010…014: monolito Laravel 13 + PHP 8.4 con UI TALL (Livewire 4 + Volt + Alpine), API `/api/v1` solo para consumidores no-web, Reverb con canales `event.{ulid}.public` (público, sin PII) y `event.{ulid}.ops` (privado), Horizon sobre Redis, MySQL 8.4, estación de meta PWA offline con IndexedDB y app móvil (v2). Muestra clientes, nodo de sede (Docker), flujos con protocolo (sesión HTTP+CSRF para el panel; `/api/v1` con token de dispositivo y lotes idempotentes para la estación) y el broadcast en cola con `afterCommit`. La UI no consume la API: los componentes Livewire llaman a Actions de dominio.

*Ref:* [spec/03-arquitectura/](spec/03-arquitectura/README.md) (ADR-010…014) · alimenta `readme.md` §2.1.

### **2.2. Descripción de componentes principales:**

**Prompt 1 (guía — fase de diseño):**

> A partir de los ADR-010…014 y del contrato de tiempo real de [spec/02-srs.md](spec/02-srs.md) §5.2, produce la tabla de componentes del sistema (aplicación, UI, tiempo real, datos, procesos, API pública, estación offline, calidad, infraestructura): para cada uno, tecnología exacta, responsabilidad y restricción verificable (p. ej. broadcast en cola con afterCommit; estación con captura < 50 ms; cobertura ≥ 80 % en dominio crítico).

*Ref:* [spec/03-arquitectura/](spec/03-arquitectura/README.md) (ADR-010…014), [spec/02-srs.md](spec/02-srs.md) §5.2 · alimenta `readme.md` §2.2.

### **2.3. Descripción de alto nivel del proyecto y estructura de ficheros**

**Prompt 1 (guía — antes de EP-24):**

> Define la estructura de ficheros objetivo del monolito modular (app/Actions, app/Livewire, app/Models, app/Policies, app/Events, app/Jobs, database/migrations, lang/es, resources/views, routes, tests, docker, docs) de modo que: Livewire → Action → policy sea la única ruta de negocio, las migraciones definan el esquema completo y las pruebas (Unit, Feature, Arch, Browser) vivan separadas por nivel. Declara las reglas que Pest Arch debe imponer sobre esa estructura (sin lógica de negocio en componentes, sin cálculo de resultados en cliente).

*Ref:* [spec/03-arquitectura/](spec/03-arquitectura/README.md) (ADR-011, ADR-012, ADR-015), [spec/04-backlog/EP-24-fundaciones-livewire.md](spec/04-backlog/EP-24-fundaciones-livewire.md) · alimenta `readme.md` §2.3.

### **2.3.bis — (real) Curación de la estructura de la spec y sus referencias:**

> Revisa la carpeta `spec/` recién creada: valida que las referencias internas usen rutas relativas existentes, que los encabezados/índices cuadren con el contenido y que el mapa "tipo de artefacto → archivo" de `spec/README.md` no deje artefactos sin destino. Reporta rotos y corrígelos.

*Ref:* `spec/README.md` y toda `spec/`.

### **2.4. Infraestructura y despliegue**

**Prompt 1 (guía — antes de EP-24, ISSUE-105/ISSUE-117):**

> Genera el `docker-compose.yml` (app, MySQL 8.4, Redis, Reverb, Horizon, Caddy) y el pipeline de CI del monolito con los gates en orden (Pint → Larastan 8 → Pest + cobertura ≥ 80 % en dominio crítico → Pest Arch → gitleaks → composer audit → Playwright smoke → build de imagen Docker), siguiendo el roadmap [spec/08-roadmap.md](spec/08-roadmap.md) (hito M1) y la DoD [spec/05-dod.md](spec/05-dod.md) (G15). La base de datos debe nacer de migraciones: `migrate:fresh --seed` con seeders sintéticos y admin bootstrap; sin datos heredados.

*Ref:* [spec/08-roadmap.md](spec/08-roadmap.md) (M1), [spec/05-dod.md](spec/05-dod.md) (G15), [spec/04-backlog/EP-24-fundaciones-livewire.md](spec/04-backlog/EP-24-fundaciones-livewire.md) (ISSUE-105, ISSUE-117) · alimenta `readme.md` §2.4.

### **2.5. Seguridad**

**Prompt 1 (real — Entrega 1):**

> Consolida las invariantes de seguridad y privacidad del sistema en un documento de invariantes de dominio con formato INV-nn (enunciado, fuente en la spec y forma de verificación): autorización siempre en el servidor con spatie (equipo = evento), cero PII en superficies y payloads públicos, sin secretos ni datos personales reales en el repositorio, correcciones con motivo y auditoría inmutable, transacciones + broadcast afterCommit. Cada invariante debe poder fallar en CI si se viola.

*Ref:* produce `spec/06-invariantes.md` (INV-06, INV-08, INV-11, INV-13, INV-14); fuentes: `spec/02-srs.md` §3.15/§4, `spec/05-dod.md` (G9, G10).

**Prompt 2 (guía — fase de implementación, EP-24):**

> Implementa la autorización de [spec/02-srs.md](spec/02-srs.md) §3.15 conforme al ADR-011/ADR-012: roles y permisos spatie con teams = evento, policies por recurso, matriz rol × acción probada automáticamente en Pest, menú y botones generados desde permisos del servidor (`@can`), tokens Sanctum con abilities solo para dispositivos/estaciones, rate limiting por superficie y prueba automática de ausencia de PII en respuestas públicas. 401/403 con semántica correcta.

*Ref:* [spec/02-srs.md](spec/02-srs.md) §3.15, [spec/03-arquitectura/](spec/03-arquitectura/README.md) (ADR-011/012), [spec/06-invariantes.md](spec/06-invariantes.md) (INV-06, INV-14), ISSUE-107/ISSUE-108/ISSUE-109 de [spec/04-backlog/EP-24-fundaciones-livewire.md](spec/04-backlog/EP-24-fundaciones-livewire.md).

### **2.6. Tests**

**Prompt 1 (real — Entrega 1):**

> Consolida la estrategia de pruebas del proyecto en un documento único con: pirámide (Pest unit/feature, Pest Arch, Playwright E2E), suites E2E `smoke` / `cu-paridad` / `estacion-offline`, escenarios de caos con criterio 0 marcas perdidas / 0 duplicadas, umbrales k6, gates de CI en orden y tabla de trazabilidad prueba → requisito/HU/épica/invariante. Cada suite debe citar los RF/HU/épicas que verifica.

*Ref:* produce `spec/07-tests.md`; fuentes: `spec/02-srs.md` §3–§4, `spec/03-arquitectura/ADR-018-e2e-playwright.md`, `spec/05-dod.md` (G1–G15), `spec/04-backlog/` (EP-26/EP-27).

**Prompt 2 (guía — fase de implementación):**

> Para la issue ISSUE-nnn de [spec/04-backlog/](spec/04-backlog/README.md), escribe las pruebas antes del código (unitarias de Action, feature de flujo, Pest Arch si toca estructura) según [spec/07-tests.md](spec/07-tests.md): lista los casos de prueba con su criterio observable, identifica las invariantes de [spec/06-invariantes.md](spec/06-invariantes.md) que la issue toca y asegura la cobertura ≥ 80 % del dominio crítico (Timing/Results/Registration). El CI (G15) debe quedar en verde solo con las pruebas nuevas más las existentes.

*Ref:* [spec/04-backlog/](spec/04-backlog/README.md), [spec/07-tests.md](spec/07-tests.md), [spec/03-arquitectura/ADR-018-e2e-playwright.md](spec/03-arquitectura/ADR-018-e2e-playwright.md), [spec/06-invariantes.md](spec/06-invariantes.md).

---

### 3. Modelo de Datos

**Prompt 1 (real — Entrega 1):**

> A partir del modelo to-be de [spec/02-srs.md](spec/02-srs.md) §5.1 y del ADR-015, registra la enmienda de arranque limpio (2026-10-20) en el ADR-015 —la migración de datos históricos queda fuera de alcance; la base de datos nace de migraciones desde cero, con seeders sintéticos y usuarios nuevos— y reescribe el readme §3: erDiagram mermaid nuevo del modelo to-be (persons+accounts, events, races, waves, categories, entries+entry_adjustments, timing_marks append-only + timing_mark_corrections, results derivado, championships+scoring_schemes+scoring_rules, regions, teams/team_user, organizations), tabla de entidades clave y principios estructurales (ULID, milisegundos, FKs/UNIQUE/CHECK).

*Ref:* `spec/03-arquitectura/ADR-015-modelo-datos.md`, `spec/02-srs.md` §5.1, `spec/06-invariantes.md` · alimenta `readme.md` §3.

**Prompt 2 (guía — ISSUE-105 y épica EP-07, dominio de inscripciones):**

> Genera las migraciones Laravel 13 (con `down()` reversible) del modelo canónico de [spec/02-srs.md](spec/02-srs.md) §5.1: todas las FKs físicas con `ON DELETE` definido, `UNIQUE (event_id, bib)` y `(event_id, person_id)`, `CHECK` persona XOR equipo, `client_mark_id` con UNIQUE y UUIDv7, instantes `datetime(3)` y duraciones `bigint` ms, `utf8mb4`, enums respaldados por backed enums PHP. Después genera los seeders sintéticos (sin datos reales de personas) y el admin bootstrap. `php artisan migrate:fresh --seed` debe dejar un entorno demo utilizable y las pruebas de esquema de [spec/06-invariantes.md](spec/06-invariantes.md) (INV-03/04/05/09) deben pasar.

*Ref:* [spec/02-srs.md](spec/02-srs.md) §5.1, [spec/03-arquitectura/ADR-015-modelo-datos.md](spec/03-arquitectura/ADR-015-modelo-datos.md), [spec/06-invariantes.md](spec/06-invariantes.md), [spec/04-backlog/EP-24-fundaciones-livewire.md](spec/04-backlog/EP-24-fundaciones-livewire.md) (ISSUE-105).

**Prompt 3 (guía — dominio de resultados):**

> Implementa el cálculo de resultados derivado: Action de dominio que computa `net_ms = llegada − salida + Σ entry_adjustments` y posiciones con `RANK() OVER` (general/género/categoría) en job, escribiendo `results` como tabla derivada no editable a mano. Nada de cálculo en cliente; prueba de conciliación entre lo publicado en vivo y el oficial al cerrar; regla Pest Arch que prohíbe el cálculo fuera del job.

*Ref:* [spec/02-srs.md](spec/02-srs.md) §3.11–§3.12 y §5.1, [spec/06-invariantes.md](spec/06-invariantes.md) (INV-07), ISSUE-074 (backlog heredado) / ISSUE-137 de [spec/04-backlog/EP-25-pantallas-livewire.md](spec/04-backlog/EP-25-pantallas-livewire.md).

---

### 4. Especificación de la API

**Prompt 1 (guía — fase de implementación, EP-27):**

> Implementa el contrato `/api/v1` de [spec/02-srs.md](spec/02-srs.md) §5.3 conforme al ADR-012 (la UI no consume API): `POST /events/{e}/timing-marks` en lote con idempotencia por `client_mark_id` (reenviar devuelve el mismo resultado: assigned/orphan/duplicate), `GET /public/events/{e}/leaderboard` público, cacheable y sin PII, y `GET /time` para el offset de la estación. Sanctum con abilities (`device:marks`), errores `application/problem+json`, ULIDs, paginación por cursor y OpenAPI 3.1 publicada con Scramble. Prueba E2E de idempotencia (reenvío del mismo lote no duplica) y prueba automática de ausencia de PII.

*Ref:* [spec/02-srs.md](spec/02-srs.md) §5.3, [spec/03-arquitectura/ADR-012-api-solo-no-web.md](spec/03-arquitectura/ADR-012-api-solo-no-web.md), [spec/03-arquitectura/ADR-014-estacion-offline-pwa-livewire.md](spec/03-arquitectura/ADR-014-estacion-offline-pwa-livewire.md), [spec/06-invariantes.md](spec/06-invariantes.md) (INV-02, INV-06) · alimenta `readme.md` §4.

---

### 5. Historias de Usuario

**Prompt 1 (real — Entrega 1):**

> Construye el mapa de trazabilidad de historias de usuario: verifica que HU-01…HU-52 de [spec/01-prd.md](spec/01-prd.md) §4 cubran todos los módulos y que cada HU cite sus RF/RNF y su épica; selecciona las tres HU representativas de la entrega (HU-47 estación offline, HU-52 clasificación en vivo, HU-49 correcciones auditadas) y redáctalas con flujo principal, criterios de aceptación y referencias a épicas del backlog de `spec/04-backlog/`.

*Ref:* `spec/01-prd.md` §4, `spec/02-srs.md`, `spec/04-backlog/` · alimenta `readme.md` §5.

**Prompt 2 (guía — del requisito a la historia):**

> Deriva nuevas historias de usuario desde el PRD: para cada cambio de producto propuesto, redacta la HU (como <rol>, quiero <capacidad>, para <beneficio>) con prioridad MoSCoW, RF/RNF asociados y épica destino; numérala continuando la serie HU-52+ y añádela a [spec/01-prd.md](spec/01-prd.md) §4. Si la HU contradice una invariante de [spec/06-invariantes.md](spec/06-invariantes.md), detén y registra pregunta abierta en [spec/02-srs.md](spec/02-srs.md) §8.

*Ref:* `spec/01-prd.md` §4, `spec/02-srs.md` §8, `spec/06-invariantes.md`.

**Prompt 3 (guía — de la HU al slice de épica):**

> Convierte la HU-nn en un slice verificable dentro de su épica de [spec/04-backlog/](spec/04-backlog/README.md): descompón en issues con el formato estándar del backlog (Tipo/Prioridad/Estimación/Labels/Depende de + criterios observables), declara las invariantes tocadas y las suites de [spec/07-tests.md](spec/07-tests.md) que las verificarán.

*Ref:* `spec/01-prd.md` §4, `spec/04-backlog/`, `spec/07-tests.md`, `spec/06-invariantes.md`.

---

### 6. Tickets de Trabajo

**Prompt 1 (real — Entrega 1):**

> Sustituye el ticket de migración ETL del histórico del readme §6 —fuera de alcance por el arranque limpio— por un ticket to-be del backlog: toma la ISSUE-105 (esqueleto Laravel 13/PHP 8.4 + base de datos por migraciones con seeders sintéticos y usuarios nuevos) de [spec/04-backlog/EP-24-fundaciones-livewire.md](spec/04-backlog/EP-24-fundaciones-livewire.md), ajústala al formato de ticket del readme (tabla Tipo/Prioridad/Estimación/Labels/Depende de, descripción y criterios de aceptación) y enlaza su definición completa. Conserva ISSUE-149 (sincronización idempotente) e ISSUE-137 (clasificación en vivo) limpios de referencias al sistema anterior.

*Ref:* `readme.md` §6; fuentes: `spec/04-backlog/EP-24…27`.

**Prompt 2 (guía — descomposición de épicas en issues):**

> Descompón la épica EP-nn de [spec/04-backlog/](spec/04-backlog/README.md) en issues con dependencias y DoD: cada issue con el formato estándar (tabla + descripción + criterios observables), el grafo de dependencias explícito (qué issue precede a cuál, dominio antes que pantalla), las invariantes de [spec/06-invariantes.md](spec/06-invariantes.md) que toca y la estimación en tallas S/M/L/XL. Verifica que ninguna issue dependa de otra de otra épica sin declararlo.

*Ref:* `spec/04-backlog/`, `spec/05-dod.md`, `spec/06-invariantes.md`.

**Prompt 3 (guía — plan de tareas de una issue):**

> Para la ISSUE-nnn, genera el plan de tareas técnicas: archivos a crear/modificar, Actions/políticas/componentes implicados, migraciones si toca el esquema, pruebas por nivel según [spec/07-tests.md](spec/07-tests.md) y orden de ejecución. Cada tarea debe poder verificarse contra un criterio de aceptación de la issue y contra la DoD G1–G15 de [spec/05-dod.md](spec/05-dod.md).

*Ref:* `spec/04-backlog/`, `spec/07-tests.md`, `spec/05-dod.md`.

---

### 7. Pull Requests

**Prompt 1 (real — Entrega 1):**

> Define la política de ramas y PRs del repositorio de entregas: rama `feature/entrega-N-<iniciales>` → PR a `main`, una PR por entrega/épica, con la DoD G1–G15 de [spec/05-dod.md](spec/05-dod.md) como puerta y CI en verde; documenta en readme §7 la PR 1 de esta entrega (readme.md, prompts.md, spec/) y las PRs planificadas (EP-24 fundaciones, EP-27 estación offline).

*Ref:* `readme.md` §7; fuentes: `spec/05-dod.md`, `spec/07-tests.md` §3.

**Prompt 2 (guía — plantilla de PR con checklist DoD):**

> Crea la plantilla de PR del proyecto con la checklist de la DoD ([spec/05-dod.md](spec/05-dod.md)): qué cambia y qué requisitos (RF/HU/épica) toca; invariantes de [spec/06-invariantes.md](spec/06-invariantes.md) afectadas y prueba que las protege; pruebas nuevas y cobertura ≥ 80 % del dominio tocado; migraciones reversibles (G6); revisión de PII (G9); sin secretos (G10); accesibilidad (G11); suites de regresión en verde (G12); estados de conexión si la pantalla es en vivo (G13); prueba con cronometrador real si toca UX de meta (G14); CI íntegro (G15).

*Ref:* `spec/05-dod.md` (G1–G15), `spec/07-tests.md` §3, `spec/06-invariantes.md`.
