# Especificación de TDChronos 2.0 (SDD)

> **Fecha:** 2026-10-20 · **Estado:** vigente · **Alcance:** Entrega 1 del proyecto final de AI4Devs.
> Autores: Oscar Ramon Lobo Altuve (oscarescalando@gmail.com) · Gabriel Toro (gabrieltoro77@gmail.com).
> Repositorio: https://github.com/oscarescalando/AI4Devs-finalproject (rama `feature/entrega-1-ol-gt`).

Esta carpeta contiene la **documentación de especificación (Specification-Driven Development)** de TDChronos 2.0: el sistema de cronometraje de carreras de calle, trail y OCR con clasificación en vivo, liga entre clubes y estación de meta offline. Es la fuente única de verdad del producto y de la arquitectura; el código se construye contra ella.

## 1. Índice de la especificación

| Documento | Contenido |
|---|---|
| [01-prd.md](01-prd.md) | PRD: problema, visión y objetivos (O1–O8), usuarios y personas, alcance con las historias de usuario (HU-01…HU-52), métricas de éxito, experiencia de usuario, plan de releases, riesgos y preguntas abiertas |
| [02-srs.md](02-srs.md) | SRS: requisitos funcionales (RF) y no funcionales (RNF), actores y permisos, modelo de datos to-be y contratos de tiempo real y API (§5), interfaces y pantallas (§6), trazabilidad de requisitos, preguntas abiertas |
| [03-arquitectura/](03-arquitectura/README.md) | Log de decisiones de arquitectura: índice + ADR-010…ADR-018 (stack, UI TALL, API, tiempo real, estación offline, modelo de datos, SaaS posterior, PDF, E2E) |
| [04-backlog/](04-backlog/README.md) | Backlog de la reconstrucción: índice + épicas EP-24…EP-27 con sus issues ISSUE-105…155 (fundaciones, pantallas, tiempo real, estación offline) |
| [05-dod.md](05-dod.md) | Definition of Done: criterios globales G1–G15, DoD por tipo de entrega, DoD de hitos M0–M6, checklist de paridad |
| [06-invariantes.md](06-invariantes.md) | Invariantes de dominio (INV-01…INV-14) con enunciado, fuente y forma de verificación |
| [07-tests.md](07-tests.md) | Estrategia de pruebas: pirámide, suites E2E, caos, carga, gates de CI y trazabilidad prueba ↔ requisito |
| [08-roadmap.md](08-roadmap.md) | Roadmap de construcción: fases, camino crítico, riesgos, hitos y dependencias |

## 2. Convención de identificadores

Los identificadores son la traza de trazabilidad de la especificación y **no se renumeron**: la numeración continúa la del corpus de origen del cual procede esta spec.

| Prefijo | Significado | Rangos |
|---|---|---|
| `RF-nn` | Requisito funcional | RF-01…RF-86 |
| `RNF-nn` | Requisito no funcional | RNF-01…RNF-29+ |
| `HU-nn` | Historia de usuario | HU-01…HU-52 |
| `CU-nn` | Caso de uso | CU-01…CU-26 |
| `EP-nn` | Épica | EP-24…EP-27 (continúan la serie heredada EP-01…EP-23 del corpus de origen) |
| `ISSUE-nnn` | Issue / task | ISSUE-105…ISSUE-155 (continúan ISSUE-001…104 del corpus de origen) |
| `ADR-nnn` | Decisión de arquitectura | ADR-010…ADR-018 |
| `G1…G15` | Criterios de la Definition of Done | [05-dod.md](05-dod.md) |
| `M0…M6` | Hitos del roadmap | [08-roadmap.md](08-roadmap.md) y [05-dod.md](05-dod.md) |
| `O1…O8` | Objetivos del producto | [01-prd.md](01-prd.md) §2 |
| `INV-nn` | Invariante de dominio | [06-invariantes.md](06-invariantes.md) (numeración propia de este repositorio) |

## 3. Proceso SDD

Cada pieza del sistema se construye en un ciclo de cinco pasos, **por historia de usuario o por épica**:

```text
        ┌────────────────────────────────────────────────────────────┐
        │  1. SPEC      qué y por qué: HU/épica + requisitos +       │
        │               invariantes que aplican                      │
        │               (01-prd.md §4 · 02-srs.md · 06-invariantes)  │
        │  2. PLAN      cómo: decisiones de arquitectura aplicables, │
        │               enfoque técnico y touchpoints                │
        │               (03-arquitectura/ADR-*)                      │
        │  3. TASKS     descomposición verificable: issues con       │
        │               dependencias y criterios de aceptación       │
        │               (04-backlog/)                                │
        │  4. BUILD     implementación en PRs: Livewire → Action →   │
        │               policy, siguiendo los ADRs                   │
        │  5. TEST      pruebas según 07-tests.md; CI en verde       │
        └────────────────────────────────────────────────────────────┘
```

**Puertas de calidad** (ningún paso se considera cerrado sin ellas):

1. **Definition of Done G1–G15 como condición de merge** ([05-dod.md](05-dod.md)): criterios de la issue verificados E2E, pruebas nuevas pasando, migraciones reversibles, revisión de PII, sin secretos, accesibilidad, documentación actualizada.
2. **CI verde**: los 8 gates de [07-tests.md](07-tests.md) §3 en la PR.
3. **Cobertura ≥ 80 %** en el dominio crítico (Timing, Results, Registration).

Cualquier desviación de la especificación exige un ADR nuevo o una enmienda registrada en [03-arquitectura/](03-arquitectura/README.md).

## 4. Reglas de oro del arranque limpio

TDChronos 2.0 es una **reconstrucción desde cero**. Estas reglas rigen todo el proyecto:

1. **La base de datos nace de las migraciones**: se crea íntegramente con migraciones versionadas y reversibles; `php artisan migrate:fresh --seed` deja un entorno demo utilizable. **Sin conexión ni migración de datos heredados**: la migración del histórico queda fuera de alcance (enmienda a ADR-015, 2026-10-20).
2. **Seeders solo con datos sintéticos**: nada de datos reales de personas en el repositorio ni en ningún entorno de desarrollo.
3. **Usuarios nuevos**: admin bootstrap creado por seeder más cuentas de prueba sintéticas para cada rol.
4. **Sin secretos en el repositorio**: `.env.example` con marcadores, credenciales solo en `.env`/gestor de secretos, gitleaks en CI.
5. **Identificadores y código en inglés, UI y documentación en español**; zona horaria del evento `America/Caracas`.
6. **El análisis del sistema anterior (ingeniería inversa) permanece en el repositorio de origen** (sin URL en estos entregables): esta especificación lo aprovecha únicamente como traza de trazabilidad de los identificadores, nunca como narrativa.

## 5. Mapa: tipo de artefacto → dónde vive

| Tipo de artefacto | Archivo |
|---|---|
| **Historias de usuario** (HU-01…HU-52) | [01-prd.md](01-prd.md) **§4 (Alcance)**: HU-01…HU-46 resumidas por módulo y HU-47…HU-52 con texto completo, prioridad y RF/épica asociados. Ejemplos desplegados para lectura rápida: `readme.md` §5 |
| **PRD** (visión, usuarios, alcance, métricas, riesgos) | [01-prd.md](01-prd.md) |
| **Requisitos funcionales y no funcionales** (RF/RNF) | [02-srs.md](02-srs.md) §3 y §4 |
| **Casos de uso** (CU-01…CU-26) | [02-srs.md](02-srs.md) §6.1 (pantallas ↔ componentes) y [04-backlog/EP-25-pantallas-livewire.md](04-backlog/EP-25-pantallas-livewire.md) |
| **Issues y tasks** (ISSUE-105…155, épicas EP-24…27) | [04-backlog/](04-backlog/README.md) (índice + un archivo por épica con sus issues) |
| **Invariantes de dominio** (INV-01…14) | [06-invariantes.md](06-invariantes.md) |
| **Definition of Done** (G1–G15, hitos) | [05-dod.md](05-dod.md) |
| **Estrategia de pruebas** (pirámide, suites, CI) | [07-tests.md](07-tests.md) |
| **Arquitectura y decisiones** (ADRs) | [03-arquitectura/](03-arquitectura/README.md) (índice + ADR-010…018) |
| **Modelo de datos** | [02-srs.md](02-srs.md) §5.1 y [03-arquitectura/ADR-015-modelo-datos.md](03-arquitectura/ADR-015-modelo-datos.md); resumen con diagrama en `readme.md` §3 |
| **API `/api/v1`** | [02-srs.md](02-srs.md) §5.3 (contrato) y §6 (interfaces); decisión en [03-arquitectura/ADR-012-api-solo-no-web.md](03-arquitectura/ADR-012-api-solo-no-web.md); resumen con OpenAPI en `readme.md` §4 |
| **Roadmap, fases e hitos** (M0–M6) | [08-roadmap.md](08-roadmap.md) |
| **Tickets de ejemplo para la entrega** | `readme.md` §6 |
| **Prompts del curso** | `prompts.md` (raíz del repositorio) |

## 6. Mantenimiento

- Los cambios de requisitos se proponen como PR contra esta carpeta, con el identificador afectado (RF/RNF/HU/EP/ISSUE/ADR) en la descripción.
- El roadmap ([08-roadmap.md](08-roadmap.md)) se re-basa al cerrar cada hito.
- Los ADRs son inmutables: una decisión revisada produce un ADR nuevo o una enmienda fechada, nunca una edición silenciosa.
