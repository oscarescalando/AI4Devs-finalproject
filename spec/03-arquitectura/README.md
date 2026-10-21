# 13 — Decisiones de arquitectura (ADR) del nuevo sistema TDChronos

> **Fecha:** 2026-10-19 · **Estado:** índice vigente · Documento de especificación de TDChronos 2.0.
> Los ADR-010 en adelante son **decisiones aceptadas** del product owner y rigen la reconstrucción (Laravel 13 + Livewire).
> Las decisiones previas (ADR-001…009, propuesta base, y ADR-S01…S16, anexo de plataforma SaaS) permanecen en el
> repositorio del sistema **y no se reescriben**: la sección 3 registra lo que sigue vigente.

## 1. Formato de los ADR nuevos

Cada archivo (`ADR-0XX-titulo-kebab.md`) contiene, en este orden: Título · Estado · Contexto ·
Decision drivers · Opciones consideradas (pros/contras) · Decisión · Consecuencias (positivas / negativas / neutrales) ·
ADRs y documentos afectados (sustituye / adapta / hereda, con IDs exactos) · Cumplimiento verificable (cómo se comprueba en
el nuevo repositorio). Estado estándar: **Aceptada — decisión del product owner 2026-10-19**.

## 2. Índice de ADR nuevos

| ADR | Título | Archivo | Estado | Resumen |
|---|---|---|---|---|
| ADR-010 | Stack base: Laravel 13 + PHP 8.4+ | [ADR-010-laravel-13-php-84.md](ADR-010-laravel-13-php-84.md) | Aceptada | Versión nueva estable con máxima ventana de soporte; Reverb y Horizon nativos de serie |
| ADR-011 | UI web TALL (Livewire 4 + Volt + Alpine.js + Blade) | [ADR-011-ui-tall-livewire.md](ADR-011-ui-tall-livewire.md) | Aceptada | Una sola capa de estado y permisos en servidor; la UI vive dentro del monolito |
| ADR-012 | API REST `/api/v1` solo para consumidores no-web | [ADR-012-api-solo-no-web.md](ADR-012-api-solo-no-web.md) | Aceptada | La UI web consume cero API: Livewire llama Actions del dominio |
| ADR-013 | Tiempo real: Reverb + canales por evento; persistir antes de emitir | [ADR-013-tiempo-real-reverb.md](ADR-013-tiempo-real-reverb.md) | Aceptada | Canales por evento con autorización; canal público de solo lectura sin PII; emisión en cola tras commit |
| ADR-014 | Estación offline como PWA con Livewire + IndexedDB | [ADR-014-estacion-offline-pwa-livewire.md](ADR-014-estacion-offline-pwa-livewire.md) | Aceptada | Captura local-first que funciona sin Internet; requisito duro O5 |
| ADR-015 | Modelo de datos to-be (arranque limpio) | [ADR-015-modelo-datos.md](ADR-015-modelo-datos.md) | Aceptada | Modelo canónico del dominio (12 principios, ERD, restricciones); enmienda 2026-10-20: migración de histórico fuera de alcance |
| ADR-016 | Plataforma SaaS multitenant como fase posterior al núcleo | [ADR-016-saas-fase-posterior.md](ADR-016-saas-fase-posterior.md) | Aceptada | Núcleo mono-organizador; la plataforma multi-tenant llega en F4 con las decisiones del corpus de origen (R-a…R-i) |
| ADR-017 | Generación de PDF con dompdf (planillas de inscripción) | [ADR-017-pdf-dompdf.md](ADR-017-pdf-dompdf.md) | Aceptada | Planilla de inscripción por inscripción y en blanco, con plantilla por organizador (RF-36); PHP puro, sin runtimes extra |
| ADR-018 | Pruebas E2E con Playwright | [ADR-018-e2e-playwright.md](ADR-018-e2e-playwright.md) | Aceptada | Suites smoke / paridad / estación offline con gates de CI y de merge |

## 3. Decisiones previas del corpus de origen

Los ADR-001…009 (propuesta base) y los ADR-S01…S16 (anexo de plataforma SaaS, fase posterior) permanecen en el
repositorio del sistema con su estado original **y no se reescriben**: este índice y la serie ADR-010 en adelante son la
capa de decisiones del nuevo sistema. Del corpus de origen siguen **vigentes en el núcleo** estas conclusiones, que la
serie nueva formaliza o amplía:

- **Monolito modular** (sin microservicios; ADR-001, con versión e UI actualizadas por ADR-010/011).
- **Reverb como servidor WebSocket** (ADR-003, formalizado por ADR-013).
- **Marcas inmutables + correcciones como eventos** (ADR-004; constancia en ADR-015).
- **Tiempos en milisegundos** (`bigint`; ADR-008; constancia en ADR-015).
- **ULID como identificador público del núcleo** (ADR-009; el `client_mark_id` de la estación es UUIDv7 generado por el
  dispositivo, ADR-014).
- **Nube como fuente de verdad en la fase SaaS** (ADR-S12, con el nodo de sede como relé de sincronización).
- **Sanctum: cookie de sesión para web y tokens con abilities para dispositivos** (ADR-S06; tokens de dispositivo para
  la estación, la app y el visor).

## 4. Regla de precedencia

Orden de autoridad para contradicciones entre fuentes:

1. **`spec/03-arquitectura/` (ADR-010 en adelante)** — manda siempre para el stack y la interfaz (framework, UI, papel
   de la API, tiempo real, estación offline, PDF, E2E).
2. **Documentación de plataforma del corpus de origen** (anexo SaaS: documento rector, resoluciones R-a…R-i,
   ADR-S01…S16) — manda para la plataforma multi-tenant de la fase posterior, salvo lo que ADR-010, ADR-011, ADR-012,
   ADR-013 y ADR-016 modifiquen explícitamente.
3. **Propuesta base del corpus de origen** (ADR-001…009) — manda para dominio, datos y despliegue del núcleo, salvo lo
   anterior.
4. **Análisis del sistema anterior incluido en el corpus de origen** — contexto y trazabilidad, nunca decisivo.

Para el dominio y los datos no hay excepciones: **ADR-015** fija el modelo canónico con su enmienda de arranque limpio;
cualquier desviación exige un ADR nuevo o una enmienda registrada aquí.

## 5. Relación con el resto de la especificación

| Documento | Relación |
|---|---|
| [../05-dod.md](../05-dod.md) | Convierte cada ADR en checks verificables (DoD por tipo, hito y paridad) |
| [../08-roadmap.md](../08-roadmap.md) | Ordena la construcción: ADR-010/011/012/013 en F1, ADR-014 en F1-F2, ADR-017/018 transversales, ADR-016 en F4 |
| [../02-srs.md](../02-srs.md) | Especifica los requisitos funcionales; este índice es su capa de decisiones de arquitectura |
| [../01-prd.md](../01-prd.md) | Fija objetivos y alcance de producto (O1-O8); los ADR los respetan |
