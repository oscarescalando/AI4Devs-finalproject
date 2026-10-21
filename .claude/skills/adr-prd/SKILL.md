---
name: adr-prd
description: >-
  Genera ADRs y PRDs/mini-planes a partir de una decisión o bug/feature/refactor: valida
  los 3 criterios de ADR, usa la plantilla canónica del repo, corta el trabajo en issues
  con DoD (slices verticales por implementación, patrón H8.x) y los deja listos para el
  Agente Orquestador. Activar con "crear ADR", "generar PRD", "planificar corrección",
  "nueva funcionalidad plan" o "refactorización plan".
allowed-tools: Read, Grep, Glob, Bash, Write
---

# Generación de ADR / PRD (planificación de cambio)

Convierte una decisión o un trabajo entrante (bug / feature / refactor) en artefactos
planificables: **ADR** si hay decisión, **PRD/mini-plan con issues+DoD** siempre.

## Paso 1 — Clasificar la petición

| Petición | Artefacto |
|---|---|
| Bug con causa clara y fix local | Issue simple (sin PRD) |
| Bug de comportamiento/regla (postmortem) | Mini-PRD + issues (patrón residual-dust / EP-8) |
| Feature nueva | PRD + issues |
| Refactorización | Mini-PRD + issues |
| Decisión difícil de revertir | **ADR** (+ issues si ejecuta algo) |

## Paso 2 — ¿Amerita ADR? (3 criterios, TODOS)

1. **Difícil de revertir** (costo alto de vuelta atrás).
2. **Sorprendente sin contexto** (un recién llegado preguntaría "¿por qué así?").
3. **Trade-off real** (hay al menos una alternativa seria que se descarta).

Si no cumple los tres → NO es ADR: va al SPEC/PRD/issue. Los ADRs aceptados son
**inmutables** (se revierten con un ADR nuevo).

## Paso 3 — Plantilla ADR (docs/adr/NNNN-<slug>.md)

Numera continuación de la serie (hoy `0001..0008`). Estructura canónica del repo:

```markdown
# ADR-NNNN — <título (decisión, no tema)>

**Fecha:** YYYY-MM-DD · **Estado:** Propuesto | Aceptado
**Especificación de origen:** <doc/spec si deriva de uno>

## Contexto
<fuerzas + restricciones; qué fuerza la decisión. Citar evidencia archivo:línea si hay>

## Decisión
<lo que se decide, en indicativo y concreto>

## Alternativas consideradas
| Alternativa | Por qué se descarta |
(incluir "no hacer nada")

## Consecuencias
### Positivas / Negativas / Neutras
<impacto en SC-1..SC-4, paridad ADR-0002, ops>

## Espejo Python ↔ Laravel
| Concern | Python | Laravel |
(si la decisión ejecuta código en ambas impls)
```

## Paso 4 — PRD / mini-plan (docs/<plan-slug>/ con SPEC propio si es grande)

Siguiendo el patrón probado de EP-8 (`docs/plan-fecha-tasa/`):

```
docs/<plan-slug>/
├── SPEC.md        # reglas numeradas R-1..R-N (testeables una a una)
├── issues.md      # slices H<N>.x con DoD
└── (evidencia: sql/, prueba-emulada.md según el caso)
```

**Reglas del SPEC del plan:** reglas numeradas, atómicas, verificables; cada regla cita la
sección del SPEC canónico que extiende (o la nueva sección que añadirá).

**Reglas de los issues (patrón H8.x):**
- **Slices verticales por implementación** — cada issue es Python O Laravel, con su DoD
  propio (`pytest tests/unit -q` + ruff / `php artisan test` + pint), para que dos
  sub-agentes avancen **en paralelo sin bloquearse**.
- Dependencias explícitas: `H1 ∥ H2 (API) → H3 ∥ H4 (job) → H5 (paridad/obs) → H6 (docs)`.
- Migraciones dentro del slice de cada impl (cada una gestiona las suyas).
- **Siempre un issue final de documentación** (SPEC/CLAUDE/READMEs/CHANGELOG/runbook) y un
  issue de **paridad** (verificación cruzada + prueba emulada si aplica).
- Cada issue incluye la **matriz de casos de prueba** que ambas suites deben cubrir espejo.

## Paso 5 — Checklist de salida

- [ ] ADR (si aplica) numerado, plantilla completa, sin contradecir ADRs previos
- [ ] PRD/issues con slices paralelizables por impl + DoD verificable
- [ ] Matriz de casos de prueba definida (espejo Py↔Lar)
- [ ] Versionado estimado (SemVer: fix=patch, feature=minor, breaking=major)
- [ ] Docs afectados identificados (para `/docs-actualizar` al cerrar)
- [ ] Recomendación de ejecución: despachar al **Agente Orquestador** con los issues

**Nunca** ejecutar los issues aquí — este skill planifica; la ejecución es del orquestador
y sus sub-agentes.
