# 02 — SRS — Especificación de requisitos — TDChronos 2.0

> **Fecha:** 2026-10-19 · **Estado:** vigente · Documento de especificación de TDChronos 2.0.
> Decisiones de stack formalizadas como **ADR-010…016 (ver [03-arquitectura/](03-arquitectura/))**; mapa provisional en §1.5.
> Producto y visión en [01-prd.md](01-prd.md). Enmienda ADR-015 (2026-10-20): arranque limpio — base de datos nueva
> creada íntegramente por migraciones desde cero; seeders con datos sintéticos; usuarios nuevos (admin bootstrap +
> seeders); sin conexión ni migración de datos heredados. Sin código de aplicación: los nombres de componentes y tablas sí se citan.

## Índice

1. [Propósito, alcance y convenciones](#1-propósito-alcance-y-convenciones)
2. [Actores y permisos](#2-actores-y-permisos)
3. [Requisitos funcionales (3.1–3.16 núcleo + 3.17 Livewire)](#3-requisitos-funcionales)
4. [Requisitos no funcionales](#4-requisitos-no-funcionales)
5. [Modelo y contratos](#5-modelo-y-contratos)
6. [Interfaces](#6-interfaces)
7. [Trazabilidad de requisitos](#7-trazabilidad-de-requisitos)
8. [Preguntas abiertas](#8-preguntas-abiertas)

---

## 1. Propósito, alcance y convenciones

### 1.1 Propósito

Especificar el sistema **TDChronos 2.0**: cronometraje y resultados de carreras (trail, obstáculos/OCR, ultras,
acuatlón, enduro) construido sobre **Laravel 13 (PHP 8.4+) + MySQL 8.4 + Redis/Horizon**, con toda la interfaz web en
**Livewire 4 + Volt + Alpine.js + Blade (stack TALL)** y tiempo real con **Laravel Reverb**. Alcance funcional: el
núcleo de cronometraje y resultados (77 RF, módulos 3.1–3.16) más el módulo de interfaz Livewire (3.17).

### 1.2 Dentro del alcance

- Núcleo funcional de cronometraje y resultados (§3, módulos 3.1–3.16).
- Separación persona/cuenta, bitácora inmutable de marcas, resultados calculados en backend, liga configurable,
  importaciones/exportaciones, estación de cronometraje tolerante a cortes de red, tiempo real robusto.
- **Por decisión de stack:** toda la UI web es Livewire (no hay SPA propia); la estación de cronometraje es una
  **PWA Livewire offline** (service worker + IndexedDB); la API REST `/api/v1` se reserva a consumidores **no-web**.

### 1.3 Fuera del alcance

- Inscripción pública con pago en línea (decisión en la pregunta abierta Q1).
- Lectura de chips RFID como fuente de tiempo (v2).
- App móvil nativa (bloque **B3**, vía `/api/v1`, en v2 — [01-prd.md §7](01-prd.md#7-plan-de-releases)).
- **Plataforma SaaS multi-tenant** (planes, pagos, portal, visor B4): fase posterior; el alcance del núcleo es el
  sistema de un organizador (ADR-016). Ver la documentación de plataforma del corpus de origen (fase posterior).
- **Migración de datos históricos: fuera de alcance** (enmienda ADR-015, 2026-10-20). Base de datos nueva creada
  íntegramente por migraciones desde cero; seeders con datos sintéticos; usuarios nuevos (admin bootstrap + seeders);
  sin conexión ni migración de datos heredados.

### 1.4 Convenciones

| Convención | Uso |
|---|---|
| **Decisión de stack** | Columna de las tablas de §3-§4 que indica qué decisión de arquitectura (ADR-010…016) afecta al requisito; «—» = sin nota particular |
| **P (prioridad)** | M = Must, S = Should, C = Could, W = Won't (por ahora), consistente con el PRD ([01-prd.md §4](01-prd.md#4-alcance)) |
| Numeración | RF-01…RF-86 y RNF-01…RNF-29. HU-01…HU-46 del alcance funcional y HU-47+ nuevas ([01-prd.md §4](01-prd.md#4-alcance)) |
| Módulos | 3.1–3.16: núcleo funcional de cronometraje y resultados; 3.17: interfaz Livewire |
| Preguntas abiertas | Listadas y numeradas en §8 |
| Referencias | Tablas en `backticks`; ULID para identificadores públicos; ADR-xx en [03-arquitectura/](03-arquitectura/); documentos hermanos por su ruta `spec/` |

### 1.5 Decisiones de stack (ADR-010…016, ver [03-arquitectura/](03-arquitectura/))

Mapa — el texto definitivo vive en [03-arquitectura/](03-arquitectura/):

| ADR | Decisión |
|---|---|
| ADR-010 | **Laravel 13 (PHP 8.4+), MySQL 8.4, Redis + Horizon** para colas reales |
| ADR-011 | **Frontend TALL: Livewire 4 + Volt + Alpine.js + Blade.** Sin SPA propia: consola, cronometraje y pantallas públicas son Livewire. Incluye la estación PWA offline |
| ADR-012 | **API REST `/api/v1` solo para consumidores no-web**: app móvil (B3), visor (B4), integraciones. La UI web NO la consume: Livewire llama Actions directamente |
| ADR-013 | **Tiempo real con Laravel Reverb** (WebSocket de primera parte) + eventos Livewire; canales privados por evento; broadcast **afterCommit** que nunca rompe la petición HTTP |
| ADR-014 | **Estación de cronometraje offline-first**: PWA Livewire + service worker + IndexedDB; captura con `client_mark_id` UUIDv7 y sync idempotente por lotes |
| ADR-015 | **Modelo de datos to-be.** Enmienda 2026-10-20 (arranque limpio): base de datos nueva creada íntegramente por migraciones desde cero; seeders con datos sintéticos; usuarios nuevos (admin bootstrap + seeders); sin conexión ni migración de datos heredados |
| ADR-016 | **Multitenancy SaaS como fase posterior**; planes/pagos/modelo SaaS vigentes, B2 sustituido por Livewire |

---

## 2. Actores y permisos

Modelo (ADR-011; autenticación según ADR-S06, vigente del corpus de origen): autenticación por **sesión de Laravel**
(cookie HttpOnly + CSRF) para la UI;
**spatie/laravel-permission con teams** — `team_id = event_id`, así un permiso vale solo dentro del evento asignado —
más **Policies** por recurso; los dispositivos (estaciones no-web, lectores) usan tokens Sanctum con *abilities* (§5.3).
La UI solo **oculta** lo que el servidor prohíbe (RF-79): toda restricción se aplica en policy/Action.

| Rol (spatie) | Quién | Qué puede | Pantallas Livewire principales |
|---|---|---|---|
| **Público** (sin sesión) | Espectador, prensa, atleta en el móvil | Clasificación en vivo y resultados oficiales **sin PII**; tablero de liga; ficha del evento | `Public\Leaderboard`, `Public\Arrivals`, `Public\Standings` |
| **Atleta** (`athlete`, cuenta opcional) | Persona con cuenta vinculada | Ver sus inscripciones y resultados; editar sus datos de contacto (v2) | `Portal\MyEntries` |
| **Cronometrador** (`timekeeper`, por evento) | Operador de la estación de meta | Registrar marcas (online y offline), ver bitácora de su evento | `Timing\Station` (PWA) |
| **Juez** (`official`, por evento) | Juez / oficial de salida | Check-in, dar salida, bitácora, correcciones (ajustar hora, reasignar, anular), llegada manual, penalizar/descalificar | `Events\StartList`, `Events\MarksLog` |
| **Organizador** (`organizer`, dueño de sus eventos) | Admin de uno o varios eventos | CRUD del evento y su configuración, inscripciones, importaciones/exportaciones, cierre y publicación, planillas PDF | `Events\*` (panel, formulario, inscripciones, resultados, imports) |
| **Administrador** (`admin`) | Administra la plataforma | Personas, organizaciones, equipos, ligas, cuentas (sin crear superadmins) | `Directory\*`, `Championships\*`, `Accounts\*` |
| **Superadmin** (`superadmin`) | Dueño técnico | Todo, incluidos roles, permisos y configuración de plataforma | Consola de administración |

Reglas: nadie asigna un rol superior al propio (RF-22); un cronometrador de un evento no opera otro (ámbito por team);
PII (documento, nacimiento, contacto) solo visible para organizador/admin y enmascarada en el resto de casos (RNF-13).

---

## 3. Requisitos funcionales

**86 requisitos funcionales** (RF-01…RF-77, módulos 3.1–3.16, núcleo de cronometraje y resultados · RF-78…RF-86,
módulo 3.17, interfaz Livewire). Columnas: **P** (prioridad) y **Decisión de stack** (nota de la decisión de
arquitectura que afecta al requisito).

### 3.1 Eventos

| ID | P | Requisito | Especificación (entradas → salidas · reglas) | Decisión de stack |
|---|---|---|---|---|
| RF-01 | M | Crear y editar eventos | nombre, descripción, fecha (obligatoria), fase, zona horaria → evento con ULID y slug · nombre único entre no borrados | — |
| RF-02 | M | Listar y buscar eventos | texto, página, orden → lista paginada con contadores (distancias, categorías, oleadas, inscripciones) · orden solo por columnas de lista blanca | tablas server-side: ver RF-83 |
| RF-03 | M | Borrar evento | id → confirmación o rechazo motivado · solo sin hijos; borrado lógico; nunca respuesta vacía 200 | — |
| RF-04 | M | Ciclo de vida del evento | transición de fase → `draft → registration → live → finished → closed` validadas; `finished → live` solo organizador y auditada · cierre en job transaccional | — |
| RF-05 | S | Branding del evento | logos, colores, patrocinadores, textos de cabecera → `events.branding` (JSON) aplicado a pantallas públicas · nada fijo en código | — |
| RF-06 | C | Clonar evento como plantilla | evento origen → nuevo con distancias, categorías y oleadas · fechas/horas desplazadas a la nueva fecha | — |

### 3.2 Categorías

| ID | P | Requisito | Especificación | Decisión de stack |
|---|---|---|---|---|
| RF-07 | M | CRUD de categorías por evento | nombre, modalidad (individual/equipo), edad mín./máx., distancia y género opcionales → categoría · `min_age ≤ max_age`; sin solapes por (distancia, modalidad, género); editable solo si el evento no está finalizado | — |
| RF-08 | M | Categoría automática al inscribir | persona, evento → categoría sugerida · edad de competencia = año del evento − año de nacimiento (pregunta abierta Q4); sin categoría → informar y no inscribir, o elegir | — |
| RF-09 | M | Recalcular categorías (masivo) | evento → cambios aplicados e incidencias · previsualización (dry-run) + confirmación; transaccional; reporta personas sin categoría | — |
| RF-10 | S | Categoría de equipo seleccionable | equipo, evento → categoría elegible entre las de equipo (mixta, femenina…) | — |

### 3.3 Distancias

| ID | P | Requisito | Especificación | Decisión de stack |
|---|---|---|---|---|
| RF-11 | M | CRUD de distancias | `races.distance_km` decimal(6,2), nombre, orden, estado → distancia · numérica, no texto | — |
| RF-12 | C | Precios y cupos por distancia | precio, preventa, cupo → tablas propias (no JSON); se activa con la fase SaaS | — |

### 3.4 Oleadas (waves) y salidas

| ID | P | Requisito | Especificación | Decisión de stack |
|---|---|---|---|---|
| RF-13 | M | CRUD de oleadas | hora programada `scheduled_start_at` datetime(3), observación, número, orden → oleada | — |
| RF-14 | M | Reprogramar oleadas escalonadas | hora inicial, intervalo (min) → oleadas activas reprogramadas en orden · una sola notificación agregada | — |
| RF-15 | M | Dar la salida de una oleada | oleada → hora real `actual_start_at` guardada **junto a** la programada · el tiempo neto usa la real; deshacer salida con auditoría | — |
| RF-16 | M | Pasar lista (check-in) | inscripción, confirmado → estado con hora · contadores sobre el **total** de la oleada, en vivo | tiempo real: ver RF-80 |

### 3.5 Organizaciones

| ID | P | Requisito | Especificación | Decisión de stack |
|---|---|---|---|---|
| RF-17 | M | CRUD de organizaciones | nombre, región, logo, estado → organización · nombre único normalizado; borrar solo sin miembros ni inscripciones | — |
| RF-18 | M | Catálogo de regiones | — → lista de estados de Venezuela (`regions`, código ISO 3166-2) | — |

### 3.6 Equipos

| ID | P | Requisito | Especificación | Decisión de stack |
|---|---|---|---|---|
| RF-19 | M | CRUD de equipos con miembros | nombre, organización, logo, miembros → equipo · PK compuesta `(team_id, person_id)`; tamaño mín./máx. por categoría | — |
| RF-20 | S | Equipos por evento | evento, equipo, miembros del día → alineación en `entry_members` · los miembros pueden variar entre eventos | — |

### 3.7 Atletas y cuentas

| ID | P | Requisito | Especificación | Decisión de stack |
|---|---|---|---|---|
| RF-21 | M | CRUD de personas | nombre, apellido, documento, nacimiento, género, contacto, organización, foto → persona · documento único; nacimiento plausible (≥ 1920); **persona ≠ cuenta**; foto con nombre UUID | — |
| RF-22 | M | Gestionar cuentas, roles e invitaciones | persona o email, rol, ámbito → cuenta invitada · nadie asigna rol superior al propio; sin contraseñas por defecto | roles vía spatie teams (ADR-011; ADR-S06) |
| RF-23 | M | Buscar personas para inscribir | texto, evento → coincidencias mínimas (nombre, documento enmascarado, edad) · requiere rol operativo; debounce; excluye ya inscritos | — |
| RF-24 | S | Detectar y fusionar duplicados | documento, nombre, nacimiento → candidatos; fusión auditada | — |
| RF-25 | C | Portal del atleta | cuenta de atleta → mis inscripciones y resultados, solo datos propios | — |

### 3.8 Inscripciones y dorsales

| ID | P | Requisito | Especificación | Decisión de stack |
|---|---|---|---|---|
| RF-26 | M | Inscribir atleta individual | persona, distancia, oleada, categoría, organización, dorsal, chip → inscripción · distancia/oleada/categoría del **mismo evento** (FK compuesta); `UNIQUE (event_id, person_id)` | — |
| RF-27 | M | Inscribir equipo | equipo, distancia, oleada, categoría de equipo, dorsal → inscripción · persona **xor** equipo (CHECK en BD) | — |
| RF-28 | M | Dorsal único por evento | — · `UNIQUE (event_id, bib)` **en la BD**, liberado al borrar | — |
| RF-29 | S | Dorsales automáticos | rangos por distancia/categoría (`bib_ranges`) → siguiente libre, sin huecos forzados | — |
| RF-30 | S | Chip único por evento | — · `UNIQUE (event_id, chip_code)` | — |
| RF-31 | M | Editar inscripción | cambios, estado → inscripción · cambiar categoría/oleada/distancia; permitir quitar la organización | — |
| RF-32 | M | Penalizar / bonificar | segundos, motivo → ajuste en `entry_adjustments` · **acumulables** con motivo y autor; neto = bruto + Σ penalizaciones − Σ bonificaciones | — |
| RF-33 | M | Cambiar estado de inscripción | `registered, checked_in, started, finished, dns, dnf, dsq, withdrawn` con motivo · DSQ conserva la marca y la excluye del ranking; reversible con auditoría | — |
| RF-34 | M | Borrar inscripción | id → confirmación · solo sin marcas asignadas; libera dorsal; borrado físico para no chocar con los UNIQUE | — |
| RF-35 | M | Listar y filtrar inscritos | 10 filtros + orden → lista paginada (persona, género, equipo, categoría, oleada, distancia, dorsal, organización, estado) | tablas server-side: ver RF-83 |
| RF-36 | C | Planilla / constancia PDF | inscripción → PDF con plantilla por organizador | materializado por RF-85 (dompdf en Livewire) |

### 3.9 Cronometraje y marcas

| ID | P | Requisito | Especificación | Decisión de stack |
|---|---|---|---|---|
| RF-37 | M | Registrar llegada por dorsal | evento, dorsal (o vacío), observación, estación → marca + resultado provisional · **toda pulsación crea una marca**; si el dorsal existe y no tiene llegada, se asigna; confirmación < 300 ms p95 (RNF-01) | la estación captura **offline** en IndexedDB y sincroniza por lotes (RF-82); `POST /api/v1/.../timing-marks` solo para dispositivos no-web (ADR-012) |
| RF-38 | M | Marca sin dorsal (huérfana) | evento → marca huérfana asignable después · no exige enviar `0` | — |
| RF-39 | M | Primera marca gana | — · las marcas repetidas del dorsal quedan «duplicadas» visibles · regla configurable `finish_rule` (primera/última) | — |
| RF-40 | M | Idempotencia de marcas | `client_mark_id` (UUIDv7) → misma respuesta en reintentos · `UNIQUE (client_mark_id)`; un reintento nunca duplica | — |
| RF-41 | M | Estación tolerante a cortes | marcas en cola local → sincronización posterior · hora del dispositivo corregida con desfase del servidor; cola en IndexedDB; indicador de pendientes | **PWA Livewire + service worker + IndexedDB** (ADR-014); sync por lotes idempotente |
| RF-42 | M | Reloj del servidor en pantallas | — → hora sincronizada · offset calculado y resincronizado periódicamente (`GET /time`), sin deriva | — |
| RF-43 | C | Foto de meta asociada a la marca | id de marca → miniatura vía proxy autenticado HTTPS | — |
| RF-44 | C | Lectura de chips / dispositivos externos | lecturas del lector → marcas `source=chip` · token de dispositivo; mismo pipeline de marcas | — |

### 3.10 Correcciones de marcas

| ID | P | Requisito | Especificación | Decisión de stack |
|---|---|---|---|---|
| RF-45 | M | Bitácora de marcas del evento | evento, filtros → lista paginada en vivo · estados: asignada, huérfana, duplicada, anulada, ignorada (`timing_mark_states`) | en vivo: ver RF-80 |
| RF-46 | M | Ajustar la hora de una marca | marca, nueva hora, motivo → corrección · la marca original **no se modifica** (`timing_mark_corrections`) | — |
| RF-47 | M | Reasignar una marca a otro dorsal | marca, dorsal destino, motivo → corrección · si el destino ya tiene llegada, confirmación explícita; transaccional | — |
| RF-48 | M | Anular una marca | marca, motivo → corrección `void` · recalcula el resultado del dorsal afectado | — |
| RF-49 | S | Llegada manual sin marca | inscripción, hora, motivo → marca `source=manual_entry` · solo juez/organizador | — |
| RF-50 | M | Historial de correcciones | marca o inscripción → línea de tiempo (quién, cuándo, qué) · inmutable | consolidado en RF-84 |

### 3.11 Clasificación en vivo

| ID | P | Requisito | Especificación | Decisión de stack |
|---|---|---|---|---|
| RF-51 | M | Clasificación pública | evento, filtros (distancia, categoría, género, oleada, dorsal) → posiciones general/género/categoría, tiempo neto, diferencia · **calculada en el backend** (job con `RANK() OVER`); primero llegados por tiempo neto, luego resto por dorsal; empates comparten posición (pregunta abierta Q5) | render reactivo Livewire: el cliente recibe deltas y pinta, **nunca calcula** (ADR-011/012) |
| RF-52 | M | Acceso sin sesión y sin PII | — → vista pública · sin documento ni nacimiento; solo nombre, organización, categoría, tiempo | — |
| RF-53 | M | Modo «últimas llegadas» | — → tarjetas ordenadas por llegada · resaltado configurable | — |
| RF-54 | M | Actualización en vivo | eventos de dominio → pantalla actualizada < 2 s · deltas por evento, sin recargar todo | Livewire + Reverb (ADR-013) |
| RF-55 | S | Modo proyección / kiosco | — → pantalla completa con autodesplazamiento · estilos aislados del panel | ruta/layout público Blade dedicado (sin SPA) |

### 3.12 Cierre del evento y puntos

| ID | P | Requisito | Especificación | Decisión de stack |
|---|---|---|---|---|
| RF-56 | M | Calcular resultados oficiales al cerrar | evento → posiciones y puntos · por distancia × categoría × género (individual) o distancia × categoría (equipo); solo activos con llegada; **job transaccional idempotente** con `RANK() OVER` | — |
| RF-57 | M | Asignar puntos según esquema | posición, esquema → puntos · **configurable por liga** (`scoring_schemes`/`scoring_rules`); esquemas de puntos como semilla en seeders | — |
| RF-58 | M | Puntos por organización por evento | evento → nº y puntos de individuales y equipos por organización (`organization_event_points`) | — |
| RF-59 | M | Publicar y exportar resultados oficiales | evento → página pública, CSV/XLSX/PDF · versión y fecha de publicación (`result_publications`); exportación asíncrona en cola | colas Redis/Horizon explícitas (ADR-010) |
| RF-60 | S | Notificar posiciones finales en vivo | — → evento `results.published` en canal público | — |

### 3.13 Liga / campeonato

| ID | P | Requisito | Especificación | Decisión de stack |
|---|---|---|---|---|
| RF-61 | M | Configurar campeonato | nombre, temporada, eventos, esquema de puntos, desempates → campeonato · **cero IDs de evento en código** | — |
| RF-62 | M | Tabla acumulada de organizaciones | campeonato → ranking con total · suma de eventos cerrados; `total` correcto | — |
| RF-63 | S | Tabla acumulada individual | campeonato, categoría, género → ranking de atletas · descarte opcional de peores resultados | — |

### 3.14 Importaciones y exportaciones

| ID | P | Requisito | Especificación | Decisión de stack |
|---|---|---|---|---|
| RF-64 | M | Importar personas desde Excel/CSV | archivo con encabezados, mapeo de columnas → reporte (creados, actualizados, errores por fila) · plantilla descargable; validación por fila; previsualización; transaccional; sin contraseñas | materializado por RF-86 |
| RF-65 | M | Importar inscripciones | archivo o API externa de inscripción → inscripciones · deduplicación por documento | — |
| RF-66 | S | Importar equipos | archivo (4 integrantes por fila) → equipos + personas | — |
| RF-67 | M | Exportar inscritos y resultados | filtros → CSV/XLSX · columnas configurables; PII solo para organizador/admin y exportación registrada | — |

### 3.15 Autenticación y autorización

| ID | P | Requisito | Especificación | Decisión de stack |
|---|---|---|---|---|
| RF-68 | M | Iniciar y cerrar sesión | usuario (email o documento), contraseña → sesión · solo cuentas activas; bloqueo por intentos | auth de sesión estándar de Laravel; tokens Sanctum solo dispositivos (ADR-012; ADR-S06) |
| RF-69 | M | Autorizar por rol y por evento | — → 403 correctos · **Policies en servidor** + spatie teams por evento; la UI solo oculta | ADR-011; ADR-S06 |
| RF-70 | M | Recuperar / establecer contraseña | email → enlace de un solo uso · invitación para cuentas nuevas | correo SMTP transaccional |
| RF-71 | M | Cambiar la propia contraseña | actual, nueva → confirmación · exigir la actual en backend | — |
| RF-72 | W | Registro público de cuentas | — · deshabilitado (salvo portal del atleta, v2) | — |
| RF-73 | C | 2FA TOTP para roles administrativos | TOTP → segundo factor | — |

### 3.16 Tiempo real

| ID | P | Requisito | Especificación | Decisión de stack |
|---|---|---|---|---|
| RF-74 | M | Emitir eventos de dominio | llegada, corrección, salida, inscripción, resultados publicados → mensajes por canal del evento · **después del commit** (`afterCommit`, en cola); un fallo de Reverb **nunca** impide persistir ni rompe la petición | **Reverb** de primera parte + colas Redis/Horizon (ADR-010/013) |
| RF-75 | M | Canales por evento con autorización | evento, rol → suscripción · `event.{ulid}.public` (público, sin PII), `event.{ulid}.ops` (privado, roles operativos del evento), `account.{ulid}` (privado) | nomenclatura unificada; autorización de canales de Reverb (§5.2) |
| RF-76 | M | Reconexión y resincronización | estado de conexión → indicador + resync · secuencia `seq` por evento, detección de huecos, **polling de respaldo (wire:poll) cada 10–30 s** | wire:poll como degradación (ADR-011) |
| RF-77 | M | Liberar suscripciones al salir de la vista | — · sin fugas de listeners ni intervalos | gestión en el ciclo de vida del componente Livewire (desmontaje) |

**Total: 86 RF (RF-01…RF-86).**

### 3.17 Requisitos de la interfaz Livewire

Transversal a todos los módulos del núcleo.

| ID | P | Requisito | Especificación | Decisión de stack |
|---|---|---|---|---|
| RF-78 | M | Componentes CRUD Livewire reutilizables | tabla/formulario reutilizables (Volt) con **validación en servidor** y errores inline (todos a la vez, foco en el primero) · una sola fuente de verdad de reglas: las Actions/Requests | nuevo (ADR-011) |
| RF-79 | M | Toda restricción de UI respaldada por policy en servidor | ocultar botones/menús con Blade/Alpine es solo presentación · la garantía vive en Policies + Actions; nunca solo CSS/JS ni cabeceras ad-hoc | nuevo (ADR-011) |
| RF-80 | M | Reactividad sin recargar página | clasificación, check-in, bitácora y salidas se actualizan por eventos Livewire + Reverb · sin recarga completa ni cálculo de ranking en el cliente | nuevo (ADR-011/013) |
| RF-81 | M | Estados de carga y feedback de error unificados | `wire:loading` en toda acción; mensajes en español con acción a seguir; **sin overlays bloqueantes**; componente de alerta único | nuevo |
| RF-82 | M | Estación de cronometraje solo-teclado y offline | pantalla dedicada operable 100 % con teclado (dorsal + Enter; Enter vacío = huérfana) · captura local en **IndexedDB** con `client_mark_id` UUIDv7; **sincronización por lotes idempotente**; contador de pendientes y desfase de reloj visibles; PWA instalable con service worker (RNF-04/29) | nuevo (ADR-011/014); el requisito más exigente de la reconstrucción |
| RF-83 | M | Tablas server-side seguras en Livewire | paginación, filtros y orden en el servidor · lista blanca de columnas ordenables; **sin construcción dinámica de consultas desde el cliente** | nuevo |
| RF-84 | M | Corrección de marcas como flujo de operaciones transaccional | operaciones (1) ajustar hora, (2) reasignar, (3) anular — como filas en `timing_mark_corrections` dentro de una transacción con motivo obligatorio · confirmación explícita si el dorsal destino ya tiene llegada; historial inmutable y auditable | nuevo (consolida RF-46…RF-48, RF-50) |
| RF-85 | M | Planillas PDF desde Livewire | generación de planillas/constancias de inscripción con **dompdf** desde componente Livewire, plantilla por organizador (ADR-017) | nuevo |
| RF-86 | M | Importación XLSX/CSV transaccional | asistente Livewire: subir archivo → mapear columnas → previsualizar → confirmar · transacción por lote; **reporte de filas rechazadas** (fila, columna, motivo); plantilla descargable | nuevo |

---

## 4. Requisitos no funcionales

**29 requisitos no funcionales (RNF-01…RNF-29).**

| ID | Categoría | Requisito y métrica / criterio de aceptación | Nota |
|---|---|---|---|
| RNF-01 | Rendimiento en meta | Registrar una marca: **p95 < 300 ms** en LAN; captura local **< 50 ms** aunque no haya red | captura local = IndexedDB en el navegador (RF-82) |
| RNF-02 | Rendimiento en meta | Ráfagas: ≥ 60 marcas/min por estación, ≥ 4 estaciones simultáneas sin degradación | — |
| RNF-03 | Rendimiento clasificación | Delta en pantalla < 2 s p95; resync < 500 ms con 2 000 inscritos; ≥ 200 clientes conectados | clientes = Livewire + Reverb |
| RNF-04 | Disponibilidad | La estación sigue capturando sin red o sin servidor y sincroniza al volver, sin pérdidas ni duplicados | — |
| RNF-05 | Disponibilidad | El sistema completo corre en un **nodo local** (portátil/mini-PC) sin Internet; sincroniza con la nube cuando hay conexión | nodo incluye Reverb + Horizon |
| RNF-06 | Disponibilidad | Si el servidor WebSocket cae, la operación sigue (degradación a **wire:poll** cada 10–30 s) | polling vía Livewire |
| RNF-07 | Integridad | FKs físicas, `UNIQUE (event_id,bib)`, `(event_id,person_id)`, `(event_id,chip_code)`, CHECK persona xor equipo | — |
| RNF-08 | Integridad | Toda operación que toque marcas, resultados o cierres es **atómica** (transacciones + RF-84) | — |
| RNF-09 | Seguridad | **Sesión de Laravel** (cookie HttpOnly + CSRF); contraseñas Argon2id/bcrypt; sin contraseñas por defecto; bloqueo por intentos; tokens Sanctum con abilities solo para dispositivos | ADR-013/014 |
| RNF-10 | Seguridad | Policies por recurso y ámbito de evento; pruebas automáticas de la matriz de permisos | spatie teams (ADR-014) |
| RNF-11 | Seguridad | Superficie de API pública mínima; ordenación con lista blanca; rate limiting (lectura pública 120/min/IP, escritura 60/min/cuenta, marcas sin límite por estación autenticada) | la API solo expone lo no-web (ADR-013) |
| RNF-12 | Seguridad | Ningún secreto en el repositorio; escaneo en CI (gitleaks); rotación documentada | — |
| RNF-13 | Privacidad | PII (documento, nacimiento, teléfono, email) **nunca** en endpoints ni canales públicos; enmascarada en listados (`V-12.***.678`); acceso completo solo organizador/admin; exportaciones con PII registradas | — |
| RNF-14 | Privacidad | Sin datos reales en seeders ni volcados versionados; retención de `failed_jobs` 30 días, sesiones 30 días; derecho de supresión (anonimizar persona conservando resultado) | seeders 100 % sintéticos (enmienda ADR-015, 2026-10-20) |
| RNF-15 | Privacidad | Archivos con nombre aleatorio (UUID), nunca el documento de identidad | — |
| RNF-16 | Auditoría | Registro inmutable de: marcas, correcciones, ajustes, cambios de estado, salidas, cierres, cambios de rol, exportaciones con PII (quién, cuándo, antes/después, IP/estación) | — |
| RNF-17 | Precisión | Tiempo en **milisegundos** (`bigint` / `datetime(3)`); > 24 h soportado; zona horaria del evento explícita (`America/Caracas`) | — |
| RNF-18 | i18n | UI en español (es-VE) con archivos de traducción; preparado para inglés; fechas `dd/mm/aaaa`, tiempos `hh:mm:ss(.d)` | — |
| RNF-19 | Charset | `utf8mb4` / `utf8mb4_0900_ai_ci` en todas las tablas; JSON nativo | — |
| RNF-20 | Accesibilidad | WCAG 2.1 AA en formularios (labels, foco, contraste); estación operable solo con teclado | ver RNF-28 |
| RNF-21 | Usabilidad | Estación de meta: «teclear dorsal + Enter» sin ratón; feedback sonoro/visual por resultado (asignada, duplicada, desconocida) | — |
| RNF-22 | Mantenibilidad | Cobertura ≥ 80 % en dominios Timing/Results/Registration; **PHPStan nivel 8 + Laravel Pint**; CI obligatorio; pruebas Feature de Livewire incluidas | herramientas TALL explícitas |
| RNF-23 | Mantenibilidad | Versiones con soporte: **PHP 8.4+, Laravel 13, Livewire 4, MySQL 8.4 LTS, Redis**; actualización semestral | ADR-010/011 |
| RNF-24 | Observabilidad | Logs estructurados JSON, errores a tracker, métricas (latencia de marca, cola de broadcast, conexiones Reverb, marcas pendientes de sincronizar), health checks | WS = Reverb |
| RNF-25 | Portabilidad | `docker compose up` levanta app, MySQL, Redis, **Reverb** y worker Horizon; variables por `.env` | Reverb incluido en el nodo de sede |
| RNF-26 | Recuperación | Backup automático cifrado antes/después de cada evento y diario; restauración probada; RPO ≤ 5 min durante carrera (binlog o réplica en sede) | — |
| **RNF-27** | Rendimiento Livewire | Acciones de meta (guardar marca sincronizada, corrección): **p95 < 300 ms**; primera respuesta de las pantallas de gestión (render Livewire inicial): **< 500 ms** | nuevo |
| **RNF-28** | Accesibilidad | WCAG 2.1 AA en el stack TALL/Alpine: foco gestionado en actualizaciones Livewire, estados `wire:loading` anunciados (aria-live), contraste y navegación por teclado | nuevo |
| **RNF-29** | Compatibilidad | Estación offline soportada en **Chrome/Chromium actual y anterior sobre Android tablet** y escritorio (PWA + IndexedDB); pantallas públicas desde móvil 360 px a TV 4K | nuevo |

---

## 5. Modelo y contratos

### 5.1 Modelo de datos to-be (ADR-015)

Detalle completo (ERD, columnas, restricciones, índices) en la documentación de arquitectura
([03-arquitectura/](03-arquitectura/), ADR-015). Esta spec **no altera nada estructural**. Arranque limpio (enmienda
ADR-015, 2026-10-20): base de datos nueva creada íntegramente por migraciones desde cero; seeders con datos
sintéticos; usuarios nuevos (admin bootstrap + seeders); sin conexión ni migración de datos heredados. Principios:

| # | Principio | Tablas / regla |
|---|---|---|
| 1 | Persona ≠ cuenta | `persons` (atleta) y `accounts` (acceso), `accounts.person_id` UNIQUE |
| 2 | Marcas inmutables | `timing_marks` **append-only** + `timing_mark_corrections` (RF-84) |
| 3 | Resultados derivados | `results` calculado desde marcas + oleadas + ajustes; nada editado a mano |
| 4 | Ajustes como filas acumulables | `entry_adjustments` (penalty/bonus/historical) |
| 5 | Oleadas con hora programada **y** real | `waves.scheduled_start_at` + `waves.actual_start_at` |
| 6 | Liga configurable | `championships`, `scoring_schemes`, `scoring_rules` — cero IDs en código |
| 7 | Integridad en la BD | FKs físicas, UNIQUE, CHECK (persona xor equipo, rangos de edad) |
| 8 | Identificadores | PK `bigint unsigned` + **ULID** `char(26)` público; `client_mark_id` UUIDv7 |
| 9 | Tiempo en milisegundos | `datetime(3)` instantes, `bigint` ms duraciones (> 24 h OK) |
| 10 | `utf8mb4` / `utf8mb4_0900_ai_ci` en todo, JSON nativo | — |
| 11 | Enums explícitos respaldados por backed enums PHP | `entries.status`, `timing_mark_states.status`, … |
| 12 | Nombres consistentes en inglés | `races`, `waves`, `regions`, `entries` |

Tablas de soporte: `roles`, `permissions`, `model_has_roles` (Spatie, `team_id = event_id`), `activity_log`,
`import_batches`/`import_rows`, `exports`, `personal_access_tokens`, `sessions`, `jobs`/`failed_jobs`.
Cálculo: `net_ms = finish − start + Σ ajustes`, posiciones con `RANK() OVER` **en job**, nunca en cliente (RF-51/RF-56).

### 5.2 Contrato de tiempo real (Reverb, ADR-013)

El broadcast se encola y se publica **después de confirmar la transacción** (`afterCommit`); un fallo de Reverb nunca
afecta la petición HTTP (RF-74). Payloads versionados: `v` (versión de contrato) y `seq` (secuencia monótona por
evento; un hueco dispara resync, RF-76).

| Canal | Visibilidad | Suscriptores | Eventos (`broadcastAs`) | Payload |
|---|---|---|---|---|
| `event.{ulid}.public` | **público**, solo lectura | `Public\Leaderboard`, `Public\Arrivals`, kiosco | `finish.recorded`, `result.changed`, `wave.started`, `results.published` | `{v, seq, bib, name, org, race, category, net_ms, ranks}` — **sin PII** |
| `event.{ulid}.ops` (privado; prefijo `private-` a nivel de suscripción Reverb) | privado | Consola del organizador, `Timing\Station`, `Events\MarksLog`, `Events\StartList` | `mark.created`, `mark.corrected`, `entry.changed`, `checkin.changed`, `wave.started`, `sync.status` | `{v, seq, …}` con ids y detalle operativo |
| `account.{ulid}` (privado) | privado | panel del usuario autenticado | `export.ready`, `import.finished` | notificaciones |

Autorización: `POST /broadcasting/auth` (sesión para humanos; token de dispositivo con abilities para estaciones).
En Livewire, los componentes operativos se suscriben al montar y liberan la suscripción al desmontar (RF-77);
las pantallas públicas usan el listener de eventos Livewire sobre el canal público.

### 5.3 Contrato API `/api/v1` (resumido, ADR-012)

**La UI web NO consume la API**: los componentes Livewire invocan Actions de dominio directamente. La API existe para
consumidores **no-web**: app móvil **B3** (v2), visor de llegadas **B4** (v2) e integraciones externas. Estilo: REST
JSON, OpenAPI 3.1 generada (Scramble) y publicada en `/docs/api`; errores `application/problem+json`; identificadores
ULID; paginación por cursor; `?sort=` y `?filter[]` con listas blancas. Los endpoints CRUD privados dejan de ser
obligatorios para la web: solo se construyen los que B3/B4/integraciones requieran (protocolo de sincronización, v2).

| Grupo | Endpoints | Auth | Consumidor |
|---|---|---|---|
| Público, sin PII, cacheable | `GET /public/events`, `/public/events/{event}`, `.../races`, `.../leaderboard`, `.../arrivals?since=`, `.../results`, `GET /public/championships/{c}/standings`, `GET /time` | ninguno | pantallas externas, integraciones, B4 |
| Marcas de dispositivos | `POST /events/{e}/timing-marks` (lote, **idempotente por `client_mark_id`**), `GET /events/{e}/timing-marks?cursor=&status=`, `POST /events/{e}/timing-marks/{m}/corrections` | token Sanctum con abilities (`device:marks`) | estación no-web, lector de chips, B3/B4 |
| Dispositivos | CRUD `/timing-devices`, `POST /timing-devices/{d}/token`, `POST /broadcasting/auth` | organizador (web) emite el token | administración de dispositivos |
| Sync (v2, plataforma) | `/api/v1/sync/pull`, `/api/v1/sync/push` (protocolo de sincronización del corpus de origen, fase posterior) | token de dispositivo | B3, B4 |

---

## 6. Interfaces

### 6.1 Pantallas to-be: componentes Livewire ↔ pantallas del panel (14 vistas)

Los componentes Livewire cubren las 14 pantallas del panel y añaden las vistas nuevas (estación de cronometraje,
publicación de resultados, asistente de importaciones, salud del sistema). La paridad pantalla a pantalla se verifica
con el DoD de pantalla ([05-dod.md §2.4](05-dod.md)) y el checklist O3 ([05-dod.md §4](05-dod.md)).

| Componente Livewire | Usuarios | Notas |
|---|---|---|
| `Auth\Login` | todos con cuenta | sesión Laravel; bloqueo por intentos |
| `Dashboard\EventIndex` | organizador | panel de eventos + accesos por evento (CU-03) |
| `Events\EventForm`, `Events\EventPhases` | organizador | fases RF-04; branding RF-05 |
| `Events\RacesManager` | organizador | RF-11 |
| `Events\CategoriesManager` | organizador | RF-07…RF-10, dry-run RF-09 |
| `Events\WavesManager` | organizador | RF-13, RF-14 |
| `Directory\OrganizationsManager` | admin | RF-17, RF-18 |
| `Public\Standings` (organizaciones) | público | liga configurable RF-61…RF-63 |
| `Directory\TeamsManager` | admin | RF-19, RF-20 |
| `Directory\PersonsManager` + `Directory\AccountsManager` | admin | persona ≠ cuenta (RF-21, RF-22) |
| `Events\EntriesManager` | organizador | RF-26…RF-35; ajustes RF-32; planillas RF-85 |
| `Events\MarksLog` | juez | bitácora en vivo + correcciones RF-84 |
| `Events\StartList` | juez (oficial de salida) | check-in en vivo RF-16 + salida RF-15 |
| `Public\Leaderboard` (incluye modo kiosco) | público | sin login, sin PII, RF-51…RF-55, RF-80 |
| **`Timing\Station`** (PWA offline) | cronometrador | **nueva**: RF-37…RF-42, RF-82; IndexedDB |
| `Events\ResultsPublish` | organizador | nueva: cierre, revisión y publicación RF-56…RF-60 |
| `Imports\Wizard` | organizador | nueva: RF-86, RF-64…RF-66 |
| `Admin\HealthPanel` | admin/superadmin | nueva: cola, Reverb, backups (HU-45) |

Rutas web: `/events/{ulid}/{configuracion|inscripciones|salida|meta|bitacora|resultados}`; públicas bajo `/e/{slug}/…`.

### 6.2 Sistemas externos

| Sistema | Dirección | Integración to-be |
|---|---|---|
| Sistema externo de inscripción pública (pagos, PDF, correo) | bidireccional | base propia; importación de inscripciones o integración por API (pregunta abierta Q1) |
| Servidor WebSocket | TDChronos → clientes | **Laravel Reverb**, broadcast afterCommit en cola (ADR-013) |
| Servidor de fotos de meta | TDChronos → fotos | v2: canal privado/servicio con token, proxy HTTPS |
| Lectores de chips | lector → TDChronos | `POST /api/v1/events/{e}/timing-marks` con token de dispositivo (v2) |
| Correo | TDChronos → personas | SMTP transaccional (invitaciones, reset de contraseña) |
| Pasarelas de pago / SaaS | — | fuera del núcleo; bloque B1 en fase SaaS (ADR-016) |

### 6.3 Hardware

- Estación de cronometraje: **tablet Android con Chrome/Chromium o portátil**, teclado físico recomendado (RNF-29).
- Pantallas/TV para kiosco y clasificación (360 px → 4K).
- Red: LAN/WiFi local en sede; el nodo de sede (portátil/mini-PC con Docker: app, MySQL, Redis, Reverb, Horizon) actúa
  de servidor; UPS y router propios (riesgo R7 del PRD). Opcional: lector de chips con salida TCP/HTTP.

---

## 7. Trazabilidad de requisitos

Mapeo entre los grupos de requisitos de esta especificación, las épicas del backlog ([04-backlog/](04-backlog/)) y las
historias de usuario del PRD ([01-prd.md §4](01-prd.md#4-alcance)) que los recogen.

| Grupo de requisitos | Épica | Contenido | HU de ejemplo |
|---|---|---|---|
| Interfaz TALL: kit CRUD, permisos reales en servidor, estados de carga/error, PWA base (RF-78, RF-79, RF-81, RF-83; RNF-27, RNF-28) | **EP-24 — Fundaciones Livewire/TALL** (ISSUE-105…118) | Esqueleto Laravel 13, layout con menú por permisos reales, auth por sesiones, componentes reutilizables, pruebas y CI | HU-48 |
| Pantallas del panel con paridad funcional (RF-01…RF-86 aplicados en UI; RF-84…RF-86 como flujos nuevos) | **EP-25 — Las 14 pantallas como componentes Livewire** (ISSUE-119…140) | Paridad pantalla a pantalla contra §6.1; checklist CU-01…26 ([05-dod.md §4](05-dod.md)) | HU-49, HU-50, HU-51 |
| Tiempo real (RF-74…RF-77, RF-54, RF-80; RNF-03, RNF-06) | **EP-26 — Tiempo real con Reverb** (ISSUE-141…146) | Contrato de eventos `v`/`seq` por pantalla, canales por evento autorizados, resincronización, pruebas de caos, ráfagas | HU-52 |
| Estación offline (RF-37…RF-44, RF-82; RNF-01, RNF-04, RNF-29) | **EP-27 — Estación offline PWA** (ISSUE-147…152) | Captura en IndexedDB con `client_mark_id`, sync idempotente por lotes, reloj con offset, doble estación | HU-47 |

Nota: el corpus de hallazgos del análisis del sistema anterior permanece en el repositorio de origen (sin URL en estos
entregables) y no forma parte de esta especificación.

---

## 8. Preguntas abiertas

Las preguntas **Q1…Q14** del análisis de origen siguen abiertas (resumen en
[01-prd.md §10](01-prd.md#10-preguntas-abiertas)). Se añaden **Q15…Q21**, surgidas de la decisión de stack y del diseño
de plataforma. Las cuestiones ligadas a la migración del histórico quedan **resueltas** por la enmienda ADR-015
(2026-10-20): arranque limpio sin datos heredados.

| # | Pregunta / contradicción | Por qué importa | Propuesta por defecto |
|---|---|---|---|
| Q15 | **Resultados públicos vs. solo registrados**: RF-52/HU-34 exigen clasificación pública sin login; la documentación de plataforma del corpus de origen (fase posterior) plantea «resultados solo para usuarios autenticados» | Define el modelo de permisos de pantallas públicas del núcleo | Núcleo: público sin login y sin PII (RF-52); el modelo SaaS re-evaluará con enlace firmado |
| Q16 | **Hora oficial de la marca**: hora de recepción en servidor vs. hora de captura corregida con el desfase del dispositivo | Cambia el cálculo de `captured_at` y la tolerancia offline | Hora de captura corregida por offset (`clock_offset_ms`), como define el modelo to-be |
| Q17 | **Ventana anti-duplicados entre estaciones** (propuesta: 2 s) y regla «primera por `captured_at`» vs. «la de la estación principal» | Doble estación (HU-26) puede crear duplicados legítimos | Configurable por evento; por defecto primera por `captured_at`, ventana 2 s |
| Q18 | **Enum `source` de `timing_marks`**: modelo base (`station, chip, manual_entry`) vs. ampliado en plataforma (`keypad, tap, qr, chip, blind, vision, web, import`) | El modelo to-be se declara sin cambios, pero la fase SaaS lo ampliará | Núcleo: enum del modelo base; ampliar en fase SaaS sin romper valores existentes |
| Q19 | **¿Una persona en varias carreras del mismo evento?** (plataforma: no por defecto, `events.allow_multiple_races`) | La UNIQUE `(event_id, person_id)` lo prohíbe en firme | Mantener prohibido por defecto; bandera por evento si el negocio lo pide |
| Q20 | **Particionado de `timing_marks`/`audit_logs`** (plataforma: solo `audit_logs` por mes) | Volumen crece con la plataforma | Núcleo sin particionar (volumen bajo en el arranque); revisar en fase SaaS |
| Q21 | **Requerimiento del «5.º bloque» B5** (sync/tiempo real dentro del backend) sigue sin confirmar formalmente | Condiciona la forma de `/api/v1/sync` en v2 | B5 como procesos del propio backend (Reverb + workers), igual que en el núcleo |
