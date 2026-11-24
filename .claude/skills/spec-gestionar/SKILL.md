---
name: spec-gestionar
description: >-
  Control de cambios de la especificación de TDChronos 2.0 (carpeta spec/): aplica cambios
  controlados y trazables sobre PRD, SRS, ADRs, backlog, DoD, invariantes, pruebas y roadmap;
  mantiene la numeración estable de los identificadores y valida que ningún cambio contradiga
  un ADR aceptado. Activar con "cambiar la spec", "actualizar la especificación", "añadir un
  issue al backlog", "tocar una invariante" o antes de cualquier cambio de regla de negocio.
allowed-tools: Read, Grep, Glob, Bash, Edit, Write
---

# Gestión de la especificación (spec/) — TDChronos 2.0

Modifica `spec/` de forma **controlada y trazable**. La spec es la fuente única de verdad del
producto: los cambios son deliberados, nunca side-effects.

## Mapa de artefactos

| Artefacto | Archivo |
|---|---|
| Producto, personas, HU | `spec/01-prd.md` |
| Requisitos (RF/RNF), modelo de datos, contrato API | `spec/02-srs.md` |
| Decisiones de arquitectura (ADR-010…) | `spec/03-arquitectura/` |
| Épicas e issues (EP-24…, ISSUE-105…) | `spec/04-backlog/` |
| Definition of Done (G1–G15) | `spec/05-dod.md` |
| Invariantes de dominio (INV-01…14) | `spec/06-invariantes.md` |
| Estrategia de pruebas | `spec/07-tests.md` |
| Roadmap (F1–F4, M0–M6) | `spec/08-roadmap.md` |
| Documento de entrega | `readme.md` (raíz) |
| Guía de agentes | `CLAUDE.md` (raíz) |

## Reglas duras

1. **Contradicción = stop:** si el cambio contradice un ADR aceptado o una invariante,
   DETENTE y repórtalo — se necesita un ADR nuevo o una **enmienda registrada** en el propio
   ADR (como la de arranque limpio de ADR-015, 2026-10-20). Nunca edites en silencio.
2. **Los identificadores nunca se renumeran** (RF/RNF/HU/CU/EP/ISSUE/ADR/G/M/O/INV): las
   series continúan las del corpus de origen; lo obsoleto se marca, no se elimina ni se
   reordena.
3. **Un cambio = una intención:** no mezclar secciones no relacionadas en la misma edición.
4. **Criterios medibles:** toda issue o criterio nuevo debe ser observable en una prueba,
   una revisión o una medición; si no lo es, se reescribe hasta que lo sea.
5. **Lo que no está en el repo no existe:** cualquier dependencia del "corpus de origen"
   heredado que el primer corte necesite se trae como issue nueva de `spec/04-backlog/`
   (patrón ISSUE-156…158).

## Protocolo de cambio

### 1. Registrar la intención
Anota en el commit y en `CHANGELOG.md`: **qué cambia, de qué documento viene y por qué**
(feedback de entrega, issue, ADR).

### 2. Qué archivo toca cada cambio

| Tipo de cambio | Archivo principal | Cascada |
|---|---|---|
| Requisito funcional/nuevo comportamiento | `spec/02-srs.md` §3 | PRD si cambia alcance; issue de backlog si hay que construirlo |
| Regla no funcional (SLA/límites) | `spec/02-srs.md` §4 | — |
| Modelo de datos / contrato API | `spec/02-srs.md` §5 + `readme.md` §3–4 | ADR-015 si toca principios; invariantes si toca reglas |
| Decisión de arquitectura | ADR nuevo en `spec/03-arquitectura/` | SRS §2, readme §2, CLAUDE.md |
| Issue nueva | `spec/04-backlog/` (épica o §5 del README) | Actualizar contadores/resumen del README del backlog |
| Invariante | `spec/06-invariantes.md` | Requiere ADR/enmienda; readme y SRS citándola |
| Fases/hitos | `spec/08-roadmap.md` | README del backlog (secuencia) |

### 3. Cascada obligatoria tras editar
- [ ] `readme.md` — el documento de entrega siempre refleja la spec (o la discrepancia es un bug).
- [ ] `CLAUDE.md` — si cambia arquitectura, reglas de oro o estado del repo.
- [ ] `CHANGELOG.md` + `VERSION` — el cambio de spec viaja con el versionado del repo.
- [ ] `prompts.md` — solo si el cambio responde a un prompt nuevo del curso.

### 4. Validaciones de cierre

```bash
# los IDs citados existen y no quedaron duplicados
grep -rn "ISSUE-15[6-9]" spec/ | head
# los enlaces relativos de spec/ resuelven
grep -rn "](\.\./" spec/ | head
# ADRs citados no contradichos (revisión manual de los tocados)
```

## Salida

```markdown
# Cambio de spec aplicado
- spec: <archivos + secciones + resumen>
- Identificadores: <ninguno renumerado; nuevos: …>
- Cascada: <readme/CLAUDE/CHANGELOG actualizados | pendientes (owner)>
- ADR/enmienda: <no aplica | <cuál y por qué>
```
