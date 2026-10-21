# 05 — Definition of Done (DoD)

> **Fecha:** 2026-10-19 · **Estado:** vigente · Documento de especificación de TDChronos 2.0. Aplica a todo el trabajo
> del nuevo sistema (F1 en adelante del [roadmap](08-roadmap.md)). Herramientas: Pest, Larastan 8, Pint, Pest Arch, k6,
> gitleaks y Playwright (ADR-018).
> Condición transversal (enmienda ADR-015, 2026-10-20): arranque limpio — base de datos nueva creada íntegramente por
> migraciones desde cero; seeders con datos sintéticos; usuarios nuevos (admin bootstrap + seeders); sin conexión ni
> migración de datos heredados.

## 1. DoD global

Aplica a **toda** entrega (feature, bugfix, migración de esquema, pantalla, endpoint, evento). Una PR no se aprueba con
algún ítem sin marcar o justificado por escrito en la propia PR.

| # | Criterio | Detalle / fuente |
|---|----------|------------------|
| G1 | **Criterios de aceptación de la issue cumplidos y verificados E2E** | Los E2E corren en CI (ADR-018) |
| G2 | **Pruebas automatizadas pasando**, incluidas las nuevas del cambio | Pest; CI verde es condición de merge |
| G3 | **Cobertura ≥ 80 % en código nuevo**, con foco en Timing / Results / Registration | O6 y métrica del PRD §5 |
| G4 | **Análisis estático limpio**: Larastan nivel 8 sin errores nuevos, Pint sin diff | — |
| G5 | **Pruebas de arquitectura verdes**: controladores/Livewire → Actions, límites de módulo, prohibiciones de ADR-011/012/015 | Pest Arch / Deptrac |
| G6 | **Migraciones reversibles**: `down()` probado en la PR; nada destructivo sin ADR que lo respalde | — |
| G7 | **Feature flag** si la función altera un flujo operativo en vivo (p. ej., clasificación, estación); el flag tiene plan de retirada | — |
| G8 | **Documentación actualizada**: OpenAPI si toca API (ADR-012), documentación del proyecto si cambia comportamiento documentado, runbook si toca operación | — |
| G9 | **Revisión de seguridad de PII**: ninguna superficie pública nueva expone `identity_number`, `birthdate`, `email`, documento ni foto (test de O2); permisos probados; exportaciones con PII auditadas | O2 |
| G10 | **Sin secretos en el repositorio**: gitleaks verde; credenciales solo en `.env`/gestor; `.env.example` con marcadores | O2 |
| G11 | **Accesibilidad**: las pantallas del panel tocadas cumplen WCAG 2.1 AA verificable (teclado completo, labels asociados, foco visible, contraste, `lang="es"`) | PRD §6.9; en el flujo de inscripción del portal SaaS (fase posterior), axe |
| G12 | **Sin regresiones** del flujo correspondiente: suites `smoke` y `cu-paridad` en verde (ADR-018) | Checklist O3 (§4) |
| G13 | **Estados de conexión visibles** si la pantalla es en vivo («En vivo» / «Reconectando…» / «Sin conexión (N pendientes)») | PRD §6.2 |
| G14 | **Si cambia la UX de meta o de salida**: prueba con un cronometrador/oficial real antes de cerrar | Verificado también en el ensayo M3 |
| G15 | **CI íntegro**: unitarias + estático + arquitectura + gitleaks (+ E2E según alcance) en verde sobre la imagen Docker PHP 8.4 / Laravel 13 | ADR-010 |

## 2. DoD por tipo de entrega

### 2.1 Feature

| # | Criterio |
|---|----------|
| F1 | Los criterios de aceptación de la issue se verifican **E2E** (Playwright o prueba manual documentada si no hay E2E posible) |
| F2 | Pruebas de dominio (Actions/reglas) con casos borde y alternativos (A1, A2… del caso de uso correspondiente) |
| F3 | Cumple la DoD global completa (§1) |
| F4 | Los datos que produce son conformes al modelo de datos (ADR-015): sin columnas fuera del diccionario, enums válidos |
| F5 | La épica/issue del backlog ([04-backlog/](04-backlog/)) queda trazada: PR enlaza issue y RF/CU |

### 2.2 Bugfix

| # | Criterio |
|---|----------|
| B1 | **Test de regresión que fallaba antes del fix y pasa después** (se conserva en la suite para siempre) |
| B2 | La PR verifica que la **clase de error** no se reproduce (no solo el caso concreto); si el bug proviene de un hallazgo catalogado del análisis del sistema anterior (repositorio de origen), la PR lo referencia |
| B3 | Sin cambio de contrato (API/pantalla) salvo documentación explícita en OpenAPI/DoD de pantalla |
| B4 | Causa raíz anotada en la issue (no solo el síntoma) |

### 2.3 Migraciones y seeders

Aplica a toda entrega que toque esquema o datos de arranque. Arranque limpio (enmienda ADR-015, 2026-10-20): base de
datos nueva creada íntegramente por migraciones desde cero; seeders con datos sintéticos; usuarios nuevos (admin
bootstrap + seeders); sin conexión ni migración de datos heredados.

| # | Criterio |
|---|----------|
| S1 | **Migraciones versionadas y reversibles**: `down()` probado en la PR; nada destructivo sin ADR que lo respalde (G6) |
| S2 | **`php artisan migrate:fresh --seed` deja el sistema operativo de cero** en un entorno limpio (dev, CI o nodo de sede) |
| S3 | **Seeders solo con datos sintéticos**: sin datos reales de personas ni PII en fixtures; volumen de prueba representativo (≥ 1 evento completo con inscripciones y marcas de ensayo) |
| S4 | **Usuarios nuevos**: admin bootstrap (credenciales solo en `.env`, sin contraseñas por defecto) + seeders de roles y permisos (spatie, ámbito por evento) |
| S5 | **Sin conexión ni migración de datos heredados**: ninguna herramienta ETL, ningún mapeo de identificadores antiguos, ninguna carga de histórico en el alcance |

### 2.4 Pantalla Livewire

Aplica a cada una de las 14 pantallas del panel definidas en [02-srs.md §6.1](02-srs.md#6-interfaces).

| # | Criterio |
|---|----------|
| P1 | **Paridad funcional documentada**: tabla de paridad pantalla a pantalla (columnas, filtros, acciones, mensajes) contra el mapa de [02-srs.md §6.1](02-srs.md#6-interfaces), con desviaciones justificadas (mejoras O3) |
| P2 | **Permisos verificados en servidor**: policy por acción; test que recorre la matriz rol × acción de la pantalla (nada depende de ocultar botones) |
| P3 | **Accesibilidad de teclado**: todo flujo completable sin ratón; foco gestionado al validar y al abrir modales (PRD §6.7/6.9) |
| P4 | **Estados de carga y error**: `wire:loading` en acciones; errores en español claro con acción a seguir, sin overlays bloqueantes (PRD §6.3); ningún silencio ante 401/500 |
| P5 | **Responsive**: verificable de 360 px a pantalla de TV donde aplique (públicas); sin estilos globales que sangren a otras vistas (PRD §6.5) |
| P6 | **Sin regresiones del flujo CU correspondiente**: suite `cu-paridad` en verde para ese CU (§4) |
| P7 | Smoke Playwright de la pantalla en verde por rol (ADR-018); test de feature Livewire de las acciones |
| P8 | Si es pantalla en vivo (salidas, bitácora, clasificación): estado de conexión visible y resincronización por `seq` (ADR-013, G13) |

### 2.5 API endpoint

| # | Criterio |
|---|----------|
| A1 | **OpenAPI actualizado y publicado** en `/docs/api` (Scramble): el endpoint no existe si no está en la spec (ADR-012) |
| A2 | **Tests de contrato**: esquema de respuesta, códigos de estado, `application/problem+json` en errores, paginación/orden/filtros con lista blanca |
| A3 | **Permisos probados**: la misma policy del panel, con matriz rol × endpoint y abilities de dispositivo cuando aplique (`device:marks` para la estación, ADR-014) |
| A4 | **Idempotencia** donde el cliente puede reintentar (lotes de marcas por `client_mark_id`) |
| A5 | Sin PII en `/api/v1/public/*` (test automático de O2); rate limit por IP/plán según corresponda |
| A6 | Identificadores públicos según ADR-015 y ADR-014 (ULID núcleo; UUIDv7 `client_mark_id`) |

### 2.6 Evento / broadcast (tiempo real)

| # | Criterio |
|---|----------|
| E1 | **Persistencia before-emit verificada con test de fallo del WS**: con Reverb detenido (o driver `log`), la marca persiste, el job queda en cola y se entrega al reconectar (ADR-013, regla 1) |
| E2 | Emisión **afterCommit y en cola** (prueba de arquitectura); el request nunca espera al WS |
| E3 | Payload con `v`/`seq`; canal correcto (`event.{ulid}.ops` privado autorizado por policy; `event.{ulid}.public` sin PII) |
| E4 | Sin tormentas de eventos: reprogramación en bloque y correcciones emiten estado agregado |
| E5 | Resincronización probada (E2E): reconexión pide deltas por `seq` sin recargar la colección |

## 3. DoD de hitos (M0-M6)

Fechas orientativas (detalles y supuestos en [08-roadmap.md](08-roadmap.md)). La DoD de cada hito incluye la DoD
global de todo lo entregado en la fase.

| Hito | Fecha orientativa | Criterios de salida (DoD del hito) |
|---|---|---|
| **M0** — Arranque y base de datos limpia | ≈ 2026-11-06 | Repositorio con CI mínimo en verde; `docker compose up` levanta app + MySQL; **`migrate:fresh --seed` deja el sistema instalable de cero** (migraciones reversibles, seeders solo sintéticos, admin bootstrap sin contraseñas por defecto, §2.3); gitleaks verde (G10) |
| **M1** — Base lista | ≈ 2026-12-04 | `docker compose up` levanta todo el stack; CI íntegro (G15) con gitleaks; login + `/me` + matriz de permisos probada; **esqueleto Livewire del panel navegable** con policies; Reverb operativo con canal de prueba; suite E2E `smoke` de 14 pantallas en CI; espiga de estación offline con prototipo revisado; espiga dompdf; pruebas de arquitectura activas (sin `resources/js`; Livewire → Actions) |
| **M2** — MVP completo | ≈ 2027-04-09 | Todas las historias **Must** del PRD con su DoD (§2); **las 14 pantallas con DoD de pantalla (§2.4) completa**; endpoint/operación de estación conforme a §2.5-2.6; checklist de paridad O3 (§4) al 100 % en modo no-ensayo o con desviación justificada; backups y runbook (ISSUE-101, 102) probados con restauración |
| **M3** — Ensayo 1 superado | ≈ 2027-04-16 | Evento simulado: 300 inscritos, 4 estaciones, **corte de red de 10 min con 0 marcas perdidas o duplicadas** (O1/O5), clasificación < 2 s (O4); suites `cu-paridad` y `estacion-offline` en verde; acta del ensayo con incidencias y cronometrador real (G14) |
| **M4** — Piloto en producción | ≈ 2027-05-14 | Primer evento real operado íntegramente con el sistema nuevo; incidencias P0 = 0; feedback del equipo de meta registrado |
| **M5** — v1 en operación | ≈ 2027-09-03 | Importaciones, liga configurable y exportaciones en operación; **prueba de carga (k6) con objetivos RNF-02/RNF-03 verificados**; métricas y observabilidad (RNF-24) activas; retención configurada |
| **M6** — Operación consolidada | 4.º trimestre de 2027 (≈ +2 eventos tras M5) | ≥ 2 eventos reales adicionales sin incidencias P0; capacidad, costes y backups revisados; criterios de entrada de la fase SaaS (ADR-016) definidos |

## 4. Checklist de paridad O3

**O3** ([PRD §2.1](01-prd.md)): paridad funcional del alcance — los 26 casos de uso (CU-01…26) operables en la nueva
UI, con los 77 RF del núcleo cubiertos. Este checklist es la aceptación del MVP. Fuente de paridad por pantalla:
[02-srs.md §6.1](02-srs.md#6-interfaces). Un CU queda ✅ solo cuando su escenario E2E (o ensayo, donde se indica) esté
en verde **y** su DoD de tipo (§2) cumpla.

| CU | Caso de uso | Pantalla to-be (Livewire) | Verificación | ✅ |
|----|-------------|---------------------------|--------------|----|
| CU-01 | Iniciar sesión | Login (email + alternativa por documento) | E2E `cu-paridad` | ⬜ |
| CU-02 | Cerrar sesión | Panel (header) | E2E `cu-paridad` | ⬜ |
| CU-03 | Gestionar eventos | Eventos + panel del evento | E2E `cu-paridad` | ⬜ |
| CU-04 | Gestionar categorías | Categorías por evento | E2E `cu-paridad` | ⬜ |
| CU-05 | Gestionar distancias | Distancias por evento | E2E `cu-paridad` | ⬜ |
| CU-06 | Gestionar oleadas (hits) | Oleadas por evento | E2E `cu-paridad` | ⬜ |
| CU-07 | Reprogramar oleadas en bloque | Oleadas (acción masiva; emite resumen, ADR-013 E4) | E2E `cu-paridad` | ⬜ |
| CU-08 | Gestionar organizaciones (clubes) | Organizaciones | E2E `cu-paridad` | ⬜ |
| CU-09 | Gestionar corredores | Personas (persona ≠ cuenta, ADR-015) | E2E `cu-paridad` | ⬜ |
| CU-10 | Asignar roles | Personas/Cuentas (gate real en servidor) | Test de matriz + E2E | ⬜ |
| CU-11 | Gestionar equipos | Equipos y miembros | E2E `cu-paridad` | ⬜ |
| CU-12 | Importar atletas/equipos | Importaciones con previsualización (asistente, ISSUE-089…091) | Test + E2E de asistente | ⬜ |
| CU-13 | Inscribir atleta | Inscripciones (categoría por edad del evento) | E2E `cu-paridad` | ⬜ |
| CU-14 | Inscribir equipo | Inscripciones (categoría de equipo elegible, HU-13) | E2E `cu-paridad` | ⬜ |
| CU-15 | Editar inscripción: penalizar, bonificar, descalificar | Inscripciones (ajustes acumulables como filas, ADR-015) | E2E `cu-paridad` | ⬜ |
| CU-16 | Depurar categorías | Inscripciones (recategorizar con `dry_run` y previsualización, PRD §6.4) | E2E `cu-paridad` | ⬜ |
| CU-17 | Confirmar asistencia (check-in) | Control de salida (contadores sobre el total real, en vivo) | E2E `cu-paridad` | ⬜ |
| CU-18 | Dar salida a una oleada | Control de salida (conserva programada + marca real; deshacer, HU-20) | E2E `cu-paridad` | ⬜ |
| CU-19 | Registrar llegada | **Estación PWA offline** (ADR-014; idempotente por `client_mark_id`) | E2E `estacion-offline` + ensayo M3 | ⬜ |
| CU-20 | Auditar y corregir marcas | Bitácora y correcciones (marcas inmutables + correcciones como eventos, ADR-015) | E2E `cu-paridad` | ⬜ |
| CU-21 | Ver foto de llegada | Bitácora (canal privado con token, proxy HTTPS) | E2E con mock del servidor de fotos | ⬜ |
| CU-22 | Ver clasificación en vivo | Clasificación pública/kiosco (sin login, sin PII; canal `event.{ulid}.public`) | E2E `tiempo-real` + ensayo M3 | ⬜ |
| CU-23 | Cerrar evento y calcular puntos | Panel del evento (motor de resultados; liga configurable) | E2E `cu-paridad` | ⬜ |
| CU-24 | Ver puntos por club del evento | Clasificación (modo puntos) | E2E `cu-paridad` | ⬜ |
| CU-25 | Ver liga (campeonato) | Liga pública (100 % configurable, sin IDs fijos en código) | E2E `cu-paridad` | ⬜ |
| CU-26 | Eliminar inscripción | Inscripciones (borrado con confirmación y auditoría) | E2E `cu-paridad` | ⬜ |

Reglas de cierre del checklist:

- El 100 % del checklist es criterio de **M2** (§3), con los CU marcados «ensayo M3» verificados además en el
  ensayo; ninguna casilla se marca «parcial».
- La paridad incorpora las mejoras acordadas (O3): cada desviación del flujo de referencia se documenta en la tabla
  de paridad de su pantalla con su verificación.
- El organizador firma el checklist en M2/M3 (O3: «checklist de paridad firmado por el organizador», PRD §2.1).
