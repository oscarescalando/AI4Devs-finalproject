---
name: arq-planificar
description: >-
  Directrices de planificación y arquitectura para la optimización continua de este
  microservicio y el desarrollo escalable de nuevos: evalúa impacto de un cambio contra
  los ADRs y SC-1..4, detecta riesgos estructurales (deuda técnica, single points of
  failure, divergencias de paridad) y produce hojas de ruta priorizadas. Activar con
  "planificar optimización", "revisión de arquitectura", "deuda técnica", "hoja de ruta"
  o "cómo estructurar <nuevo microservicio>".
allowed-tools: Read, Grep, Glob, Bash
---

# Planificación y arquitectura (optimización continua)

Marco de decisión para cambios estructurales y evolución del servicio. **Read-only**:
produce análisis y hoja de ruta; la ejecución va al Agente Orquestador (con `/adr-prd`).

## 1. Cargar el marco (antes de opinar)

`CLAUDE.md` (mapa espejo + reglas de dominio) · `docs/MAPA-ARQUITECTURA.md` (estatus y
riesgos vigentes) · `docs/adr/0001..0008.md` · `docs/SPEC.md` §5/§6 · último CHANGELOG.

## 2. Evaluación de impacto (todo cambio propuesto)

Puntúa cada dimensión 0-3 (0 sin impacto, 3 crítico):

| Dimensión | Pregunta clave |
|---|---|
| **SC-1 exactitud** | ¿Puede alterar el monto cobrado? (cualquier duda → 3) |
| **SC-2 idempotencia** | ¿Puede duplicar/reabrir un pago? |
| **SC-3 completitud** | ¿Puede abortar notas hermanas o perder reintentos? |
| **SC-4 trazabilidad** | ¿Rompe la reconstrucción de qué pasó (2-TX, logs, auditoría)? |
| **Paridad (ADR-0002)** | ¿Requiere espejo? ¿alguien lo está olvidando? |
| **Migraciones** | ¿Toca esquema? (⚠️ grafo Alembic roto: revisar `docs/MAPA-ARQUITECTURA.md` R-1) |
| **Operación** | ¿Cambia .env/proceso/runbook? ¿requiere ventana? |
| **Rendimiento** | ¿Afecta inflight Aradial, pools BD, cola? |

**Umbral:** cualquier 3 en SC-* → requiere ADR + plan con prueba emulada antes de código.
Cualquier 2 → mini-PRD (`/adr-prd`). Sólo 0-1 → issue simple.

## 3. Catálogo de riesgos estructurales (vigilar)

Mantén sincronizado con `docs/MAPA-ARQUITECTURA.md` §7. Actualmente:

1. **Grafo Alembic roto** (dos `0004`) — bloquea migraciones Python (workaround Laravel/SQL).
2. **`make conform` inexistente** — paridad sólo por suites espejo (Fase 4 ADR-0002).
3. **Desync Aradial nota/concepto** — residual dust; ticket capa 2 pendiente de envío.
4. **Divergencia 409 terminal Py↔Lar** — diferida, documentada.
5. **Redis como dependencia dura de la cola** — `POST /pay` 503 sin Redis (aceptado;
   considerar DL de solicitudes si empeora).

## 4. Directrices de escala y optimización (principios no negociables)

1. **El paralelismo externo se acota global** (semáforo `pay:sem:aradial` compartido entre
   réplicas), el interno por solicitud (`NOTAS_MAX_CONCURRENCY`). No añadir llamadas
   externas en bucle sin pasar por el semáforo.
2. **Escalar es añadir réplicas de worker, no engordar el proceso** (`--scale worker=N`;
   1 unidad systemd por réplica). El pool BD escala con las réplicas
   (`DB_POOL_SIZE` × réplicas).
3. **Toda llamada externa: timeout + reintentos + 2-TX.** Sin excepciones.
4. **La BD interna es una y RW**; lecturas de negocio externas van por HTTP al hermano.
5. **La auditoría jamás está en el camino del pago** (post-TX2, best-effort).
6. **Nuevo trabajo pesado → nueva cola/worker dedicado**, no mezclar con `payment`.
7. **Dominio puro sin I/O** (`payment_math`, taxonomía): si un cambio de dominio necesita
   un mock de red para testearse, la capa está mal cortada.

## 5. Nuevo microservicio (checklist de partida)

1. Partir de `docs/context/` (plantillas SPEC/glosario/invariantes + guías por pila).
2. Elegir pila(s) con ADR de paridad si son dos (patrón ADR-0002) o una sola.
3. Cumplir invariantes mínimos: tokens HMAC+pepper (6 pasos), admin CIDR fail-closed,
   envelope uniforme de errores, `/health`+`/ready`, namespace Redis propio, 2-TX por
   externo, colas con dead-letter si hay async, tests puros.
4. Estructura de directorios según `docs/context/python/01-*.md` o `docs/context/laravel/01-*.md`.
5. Registro en el ecosistema: si consulta a un hermano → token interno propio (ADR-0003).

## 6. Salida — hoja de ruta priorizada

```markdown
# Revisión de arquitectura — <fecha>
## Estado (sincronizado con MAPA-ARQUITECTURA §3/§7)
## Hallazgos
| # | Hallazgo | Dimensión crítica | Score | Acción recomendada |
## Hoja de ruta (orden por riesgo/impacto)
| Prioridad | Ítem | Artefacto de entrada | Ejecutor |
| P1 | ... | ADR nuevo (/adr-prd) | Orquestador + 2 impl agents |
## No-hacer (explícito)
<lo que se evaluó y NO se va a hacer, con la razón — para no re-litigarlo>
```

**Nunca** ejecutes cambios aquí, ni contradigas un ADR sin proponer su reemplazo formal.
