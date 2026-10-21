# 06 — Invariantes de dominio de TDChronos 2.0

> **Fecha:** 2026-10-20 · **Estado:** vigente · Documento de especificación de TDChronos 2.0.
> Complementa a [01-prd.md](01-prd.md), [02-srs.md](02-srs.md), [03-arquitectura/](03-arquitectura/README.md) y [05-dod.md](05-dod.md).

## 1. Qué son y cómo se usan

Las invariantes son propiedades que deben cumplirse **en todo estado del sistema**, sin excepción ni configuración que las desactive. No son requisitos puntuales sino reglas estructurales del dominio de cronometraje: cualquier historia de usuario, issue o tarea que las toque debe demostrar que las preserva.

Reglas de uso:

- Cada issue nueva declara qué invariantes toca (y con qué prueba las protege) en su descripción.
- La Definition of Done ([05-dod.md](05-dod.md), criterios G1–G15) las verifica: la revisión de una PR puede exigir la prueba que respalda una invariante.
- La estrategia de pruebas ([07-tests.md](07-tests.md)) mantiene la trazabilidad prueba ↔ invariante.
- Una invariante solo puede modificarse con un ADR nuevo o una enmienda registrada en [03-arquitectura/](03-arquitectura/README.md).

## 2. Invariantes

### INV-01 — Las marcas son append-only

**Enunciado.** `timing_marks` solo admite inserciones: ninguna operación actualiza ni borra una marca. Toda corrección (ajustar hora, reasignar a otro dorsal, anular) crea una fila nueva en `timing_mark_corrections`; la marca original conserva su valor para siempre.

**Fuente.** [02-srs.md](02-srs.md) §3.9–§3.10 (RF-84) y §5.1 (principio 2); [03-arquitectura/ADR-015-modelo-datos.md](03-arquitectura/ADR-015-modelo-datos.md).

**Verificación.** Prueba de feature: corregir una marca y comprobar que la original no cambió y existe la fila de corrección; regla de arquitectura (Pest Arch) que prohíbe escritura de actualización sobre el modelo de marca.

### INV-02 — Idempotencia por `client_mark_id`

**Enunciado.** Cada marca lleva un `client_mark_id` (UUIDv7 generado por el dispositivo). Reenviar la misma marca — suelta o dentro de un lote — no crea un segundo registro y devuelve el mismo resultado (`assigned` / `orphan` / `duplicate`).

**Fuente.** [02-srs.md](02-srs.md) §3.9 y §5.3 (contrato `/api/v1`); [03-arquitectura/ADR-012-api-solo-no-web.md](03-arquitectura/ADR-012-api-solo-no-web.md) y [ADR-014-estacion-offline-pwa-livewire.md](03-arquitectura/ADR-014-estacion-offline-pwa-livewire.md); EP-27, ISSUE-149; HU-47.

**Verificación.** Prueba E2E de reenvío del mismo lote sin duplicados; prueba de caos con red intermitente: la cola local se drena completa al volver, 0 duplicadas.

### INV-03 — Unicidad de dorsal por evento

**Enunciado.** No existen dos inscripciones activas con el mismo dorsal en un evento: `UNIQUE (event_id, bib)` en base de datos, no solo en la aplicación.

**Fuente.** [02-srs.md](02-srs.md) §3.8 y §5.1; [03-arquitectura/ADR-015-modelo-datos.md](03-arquitectura/ADR-015-modelo-datos.md).

**Verificación.** Restricción UNIQUE en la migración (revisión G6); prueba de feature: alta duplicada rechazada con mensaje claro.

### INV-04 — Unicidad persona-evento

**Enunciado.** Una persona no puede inscribirse dos veces en el mismo evento: `UNIQUE (event_id, person_id)`.

**Fuente.** [02-srs.md](02-srs.md) §3.8 y §5.1.

**Verificación.** Restricción UNIQUE en la migración; prueba de feature de inscripción repetida rechazada.

### INV-05 — Persona o equipo, nunca ambos (XOR)

**Enunciado.** Toda inscripción (`entries`) es individual (`person_id`) o de equipo (`team_id`), nunca ambas ni ninguna: `CHECK` en base de datos.

**Fuente.** [02-srs.md](02-srs.md) §3.8 y §5.1 (principio 7); [03-arquitectura/ADR-015-modelo-datos.md](03-arquitectura/ADR-015-modelo-datos.md).

**Verificación.** CHECK en la migración; pruebas del formulario individual/equipo (ISSUE-129) cubriendo los tres casos (individual, equipo, inválido).

### INV-06 — Cero PII en superficies y payloads públicos

**Enunciado.** Las pantallas públicas, los endpoints públicos de `/api/v1` y los payloads del canal público de tiempo real no contienen documento de identidad, fecha de nacimiento, email ni teléfono. Los listados internos enmascaran el documento. Las exportaciones con PII quedan auditadas.

**Fuente.** [02-srs.md](02-srs.md) §4 (RNF-13) y §5.2 (payload del canal público); criterio G9 de [05-dod.md](05-dod.md); [03-arquitectura/ADR-013-tiempo-real-reverb.md](03-arquitectura/ADR-013-tiempo-real-reverb.md); HU-52.

**Verificación.** Prueba automática de ausencia de PII sobre respuestas públicas y payloads de broadcast (en CI); suite Playwright de pantallas públicas sin sesión.

### INV-07 — Posiciones y puntos calculados en el servidor

**Enunciado.** Posiciones (general, género, categoría), diferencias y puntos se calculan en jobs del servidor (`RANK() OVER` sobre `net_ms = llegada − salida + Σ ajustes`); `results` es una tabla derivada que nunca se edita a mano; el cliente solo renderiza.

**Fuente.** [02-srs.md](02-srs.md) §3.11–§3.12 y §5.1 (principios 3 y 6); [03-arquitectura/ADR-011-ui-tall-livewire.md](03-arquitectura/ADR-011-ui-tall-livewire.md) y [ADR-012-api-solo-no-web.md](03-arquitectura/ADR-012-api-solo-no-web.md); ISSUE-074 (dominio) e ISSUE-137 (pantalla).

**Verificación.** Prueba de conciliación: lo publicado en vivo coincide con el resultado oficial al cerrar el evento; regla Pest Arch que prohíbe cálculo de posiciones en componentes o controladores.

### INV-08 — Broadcast después del commit

**Enunciado.** Todo evento de dominio se emite en cola con `afterCommit`: primero se confirma la transacción, después se publica. Un fallo del broker de tiempo real nunca afecta la respuesta de la operación — la marca responde 200 aunque Reverb esté caído.

**Fuente.** [03-arquitectura/ADR-013-tiempo-real-reverb.md](03-arquitectura/ADR-013-tiempo-real-reverb.md); [02-srs.md](02-srs.md) §5.2 (RF-74).

**Verificación.** Prueba de caos con Reverb detenido: la petición de marca responde 200 y el broadcast se recupera; prueba unitaria del orden transacción → broadcast en los Actions que emiten.

### INV-09 — Integridad referencial declarada en la base de datos

**Enunciado.** Las relaciones, unicidades y reglas del dominio viven en el esquema: FKs físicas con política `ON DELETE` definida, `UNIQUE` y `CHECK` explícitos. La consistencia no depende solo del código de la aplicación.

**Fuente.** [03-arquitectura/ADR-015-modelo-datos.md](03-arquitectura/ADR-015-modelo-datos.md); [02-srs.md](02-srs.md) §5.1 (principio 7).

**Verificación.** Revisión de migraciones en la PR (G6); prueba de esquema que recorre `information_schema` y falla si una relación de dominio carece de FK o una regla carece de UNIQUE/CHECK.

### INV-10 — Tiempo en milisegundos

**Enunciado.** Los instantes se almacenan con precisión de milisegundos (`datetime(3)`) y las duraciones como `bigint` en ms, con soporte de tiempos > 24 h. La hora oficial de una marca es la de captura en la estación corregida por el offset contra el reloj del servidor.

**Fuente.** [03-arquitectura/ADR-015-modelo-datos.md](03-arquitectura/ADR-015-modelo-datos.md) (principios 8–9) y [ADR-014-estacion-offline-pwa-livewire.md](03-arquitectura/ADR-014-estacion-offline-pwa-livewire.md); [02-srs.md](02-srs.md) §5.1.

**Verificación.** Convención de migraciones revisada en la PR; pruebas unitarias con tiempos > 24 h y con offset de reloj aplicado (ISSUE-150).

### INV-11 — Toda corrección con motivo y auditoría

**Enunciado.** Ninguna corrección se aplica sin motivo escrito. Cada corrección registra quién, cuándo, el valor antes/después y la IP/estación; la auditoría es inmutable y toda corrección puede revertirse dejando la misma constancia.

**Fuente.** [02-srs.md](02-srs.md) §3.10 (RF-46…RF-50, RF-84); HU-49 ([01-prd.md](01-prd.md) §4).

**Verificación.** Prueba de feature: corrección sin motivo rechazada; corrección válida crea registro de auditoría completo; prueba de reversión.

### INV-12 — Operación offline sin pérdida

**Enunciado.** La cola local de la estación de meta sobrevive a recargas, cierres del navegador y cortes de red; al restablecerse la conexión se drena completa. Criterio global del proyecto: **0 marcas perdidas y 0 marcas duplicadas**, con cualquier combinación de fallos de red, cierre del navegador o doble estación.

**Fuente.** [02-srs.md](02-srs.md) §4 (RNF-01, RNF-04); [03-arquitectura/ADR-014-estacion-offline-pwa-livewire.md](03-arquitectura/ADR-014-estacion-offline-pwa-livewire.md); EP-27 (ISSUE-148, ISSUE-149, ISSUE-152); HU-47.

**Verificación.** Suite E2E `estacion-offline` (nocturna, Playwright): ≥ 500 marcas sin red, recarga y cierre del navegador, sincronización completa; pruebas de caos con red intermitente y doble estación ([07-tests.md](07-tests.md)).

### INV-13 — Sin secretos ni datos personales reales en el repositorio

**Enunciado.** Cero credenciales versionadas: `.env.example` con marcadores y valores reales solo en `.env`/gestor de secretos. Los seeders cargan únicamente datos sintéticos (sin datos reales de personas) y crean los usuarios nuevos, incluido el admin bootstrap; la base de datos nace de migraciones, sin conexión ni migración de datos heredados.

**Fuente.** Criterios G10 y G6 de [05-dod.md](05-dod.md); reglas de oro de [README.md](README.md); enmienda de [03-arquitectura/ADR-015-modelo-datos.md](03-arquitectura/ADR-015-modelo-datos.md) (2026-10-20).

**Verificación.** gitleaks y `composer audit` en CI; revisión de seeders en la PR (sintéticos, sin PII real).

### INV-14 — Autorización siempre en el servidor

**Enunciado.** Todo acceso y toda acción pasan por policies y permisos en el servidor (spatie/laravel-permission, equipo = evento); el menú y los botones solo reflejan permisos calculados en el servidor; ni el cliente ni el payload deciden autoridad alguna. 401/403 con semántica correcta.

**Fuente.** [02-srs.md](02-srs.md) §3.15; [03-arquitectura/ADR-011-ui-tall-livewire.md](03-arquitectura/ADR-011-ui-tall-livewire.md) y [ADR-012-api-solo-no-web.md](03-arquitectura/ADR-012-api-solo-no-web.md); HU-48 ([01-prd.md](01-prd.md) §4).

**Verificación.** Matriz rol × acción probada automáticamente (Pest); regla Pest Arch que obliga al flujo Livewire → Action → policy.

## 3. Resumen

| ID | Invariante (una línea) | Fuente principal |
|---|---|---|
| INV-01 | Marcas append-only; correcciones como filas nuevas | SRS §3.9–3.10, §5.1; ADR-015 |
| INV-02 | Idempotencia por `client_mark_id` | SRS §3.9, §5.3; ADR-012/014; ISSUE-149 |
| INV-03 | `UNIQUE (event_id, bib)` | SRS §3.8, §5.1; ADR-015 |
| INV-04 | `UNIQUE (event_id, person_id)` | SRS §3.8, §5.1 |
| INV-05 | `CHECK`: persona XOR equipo en la inscripción | SRS §3.8, §5.1; ADR-015 |
| INV-06 | Cero PII en superficies y payloads públicos | SRS §4 (RNF-13), §5.2; G9; ADR-013 |
| INV-07 | Posiciones y puntos calculados en el servidor | SRS §3.11–3.12, §5.1; ADR-011/012 |
| INV-08 | Broadcast después del commit (`afterCommit`) | ADR-013; SRS §5.2 (RF-74) |
| INV-09 | Integridad referencial declarada (FK/UNIQUE/CHECK) | ADR-015; SRS §5.1 |
| INV-10 | Tiempo en milisegundos; hora oficial corregida por offset | ADR-015, ADR-014; SRS §5.1 |
| INV-11 | Corrección con motivo y auditoría inmutable | SRS §3.10 (RF-84); HU-49 |
| INV-12 | Offline sin pérdida: 0 marcas perdidas / 0 duplicadas | SRS §4 (RNF-01/04); ADR-014; EP-27 |
| INV-13 | Sin secretos ni datos personales reales en el repo | G10, G6; DoD; enmienda ADR-015 |
| INV-14 | Autorización en el servidor (policies spatie) | SRS §3.15; ADR-011/012; HU-48 |
