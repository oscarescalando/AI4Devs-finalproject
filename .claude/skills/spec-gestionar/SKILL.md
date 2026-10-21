---
name: spec-gestionar
description: >-
  Control de cambios y versionado de las especificaciones (docs/SPEC.md y docs/CONTEXT.md):
  aplica cambios controlados con trazabilidad (quién/cuándo/por qué), versiona el spec,
  mantiene la igualdad semántica del glosario y valida que ningún cambio contradiga
  ADRs existentes. Activar con "cambiar el spec", "actualizar SPEC", "añadir término al
  glosario", "versionar spec" o antes de un cambio de regla de negocio.
allowed-tools: Read, Grep, Glob, Bash, Edit, Write
---

# Gestión de Specs (control de cambios y versionado)

Modifica `docs/SPEC.md` y `docs/CONTEXT.md` de forma **controlada y trazable**. El SPEC es
la verdad: los cambios son deliberados, nunca side-effects.

## Reglas duras

1. **Contradicción = stop:** si el cambio contradice un ADR aceptado o una SC-1..SC-4,
   DETENTE y repórtalo (se necesita ADR nuevo o decisión del usuario).
2. **Glosario puro:** `CONTEXT.md` no contiene implementación ni decisiones — sólo
   términos (definición, contexto, sinónimos a evitar, ejemplo, relacionados).
3. **Glosario antes del SPEC:** todo término nuevo del cambio se define PRIMERO en
   `CONTEXT.md`; el SPEC usa sólo vocabulario del glosario (igualdad semántica).
4. **Un cambio = una intención:** no mezclar secciones no relacionadas en la misma edición.
5. **Numérico/moneda en el SPEC siempre con el vocabulario canónico** (monto, nota de
   cobro, solicitud, consumer…) — nunca sinónimos.

## Protocolo de cambio de SPEC

### 1. Registrar la intención
Al inicio de la edición, anota en el propio cambio (commit message + entrada de CHANGELOG):
**qué regla cambia, de qué versión del SPEC viene, y por qué** (issue/incidente/ADR).

### 2. Sección + severidad
| Tipo de cambio | Sección | Efecto |
|---|---|---|
| Nuevo comportamiento observable | §3 Funcionalidades | minor (feature) |
| Cambio de flujo/pasos | §4 Flujos | minor o patch |
| Cambio de regla de negocio | §3 + "Domain rules" de CLAUDE.md | minor; **breaking** si invalida flujos legítimos previos → major |
| Cambio de no funcional (SLA/límites) | §6 | según impacto |
| Actor nuevo | §2 Actores | minor |
| Visión/alcance | §1 | mayor — requiere decisión explícita |

**Caso especial documentado:** un fix que cambia comportamiento que era defecto (p. ej.
EP-8: fechas >60 d antes liquidaban a tasa histórica) se marca expresamente como
**"cambio intencional de comportamiento (es el fix)"** en CHANGELOG — no es breaking
silencioso, es corrección con comunicado.

### 3. Versionado del SPEC
El SPEC no lleva SemVer propio: **viaja con el SemVer del servicio** (CHANGELOG). Para
trazabilidad, el changelog de cada versión lista las secciones del SPEC tocadas
(«`SPEC.md` §2.2/§3.1/§4»), como hace la entrada v2.7.0.

### 4. Cascada obligatoria tras editar
- [ ] `CLAUDE.md` — regla de dominio / fila del mapa espejo si aplica.
- [ ] `docs/TASKS.md` — checklist si añade funcionalidad.
- [ ] READMEs de AMBAS impls (mismo comportamiento, ejemplos).
- [ ] `docs/python/` + `docs/laravel/` si toca tasa/caché/deuda/errores.
- [ ] Tests: la matriz de casos del nuevo comportamiento debe existir en **ambas suites**
      (verificado por el orquestador).

## Protocolo de glosario

Nuevo término (`docs/CONTEXT.md`, sección correcta Actores/Entidades/Técnicos/Dominino):

```markdown
### <Término>
**Definición:** <una frase>
**Contexto:** <dónde se usa>
**Sinónimos a evitar:** <...>
**Ejemplo:** <concreto>
**Relacionados:** <...>
```

- Término con significado cambiado: marcar el viejo `(obsoleto, usar <nuevo>)` y crear el
  nuevo — nunca redefinir en silencio.
- Verificar contra el SPEC que no quede el sinónimo viejo vivo (grep).

## Validaciones de cierre

```bash
# sin sinónimos vetados en el SPEC
grep -niE '<sinónimos a evitar del cambio>' docs/SPEC.md
# secciones citadas existen
grep -n '^## ' docs/SPEC.md
# ADRs no contradichos (revisión manual de los citados)
```

## Salida

```markdown
# Cambio de spec aplicado
- SPEC: <secciones + resumen>
- Glosario: <términos añadidos/obsoletos>
- SemVer estimado: <tipo> — razón
- Cascada: <artefactos actualizados / pendientes (owner)>
```
