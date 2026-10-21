---
name: memoria-gestionar
description: >-
  Gestiona la memoria persistente del proyecto para sesiones de desarrollo eficientes:
  decide qué merece guardarse en el auto-memory (~/.claude/.../memory/), lo escribe con
  el formato canónico, poda lo obsoleto y mantiene el índice MEMORY.md sincronizado.
  También prepara handoffs de sesión (estado, siguiente paso exacto). Activar con
  "guardar en memoria", "actualizar memoria", "recordar esto", "handoff de sesión" o
  "qué recuerdas del proyecto".
allowed-tools: Read, Grep, Glob, Bash, Write, Edit
---

# Gestión de memoria persistente

Mantiene el contexto entre sesiones: qué debe recordarse, con qué formato, y qué podar.

## Dónde vive la memoria

```
~/.claude/projects/-Users-oscarlobo-DesarrolloIA-specMicroServicioPago/memory/
├── MEMORY.md          # índice (1 línea por memoria) — lo único cargado cada sesión
└── <slug>.md          # una memoria = un hecho = un archivo
```

## Tipos válidos (frontmatter `metadata.type`)

| Tipo | Qué guarda | Ejemplo |
|---|---|---|
| `user` | Quién es el usuario: rol, expertise, preferencias | "Prefiere docs en español con tablas" |
| `feedback` | Guía dada sobre CÓMO trabajar, con el por qué | "Nunca Co-Authored-By — el hook lo bloquea" |
| `project` | Trabajo/goal/restricción NO derivable de código ni git | "Ticket Aradial pendiente de envío" |
| `reference` | Punteros a recursos externos | dashboards, tickets, URLs |

## Qué SÍ guardar (el filtro)

Guarda sólo lo que **no es derivable** del repo (código, git history, CLAUDE.md, CHANGELOG)
y que **importa más allá de esta conversación**:

- ✅ Estado de pendientes externos ("rama Laravel pendiente de merge", "ticket X sin enviar").
- ✅ Divergencias conocidas y su por qué (409 Py↔Lar diferido).
- ✅ Preferencias de trabajo confirmadas por el usuario.
- ✅ Decisiones de proceso del equipo (política de commits, ciclos de revisión).

**NO guardar** (ya vive en el repo o es efímero): estructura de carpetas, fixes pasados
(git log lo dice), contenido de CLAUDE.md/SPEC, tareas de la sesión actual.

## Formato canónico

```markdown
---
name: <slug-kebab-case>
description: <una línea — es lo que decide si se recuerda; sé específico>
metadata:
  type: user | feedback | project | reference
---

<el hecho; para feedback/project añade:>
**Why:** <por qué importa>
**How to apply:** <cómo aplicarlo la próxima vez>

Relacionados: [[otra-memoria]]
```

## Protocolos

### Guardar / actualizar
1. Busca memoria existente que cubra el hecho (`Read MEMORY.md` + glob del dir).
   Existe → **actualízala**; si el hecho resultó falso/obsoleto → **bórrala**.
2. Escribe el archivo con el formato canónico (convierte fechas relativas a absolutas:
   "hace 2 semanas" → "2026-08-13").
3. Añade/actualiza UNA línea en `MEMORY.md`: `- [Título](slug.md) — gancho breve`.
   Nunca contenido de la memoria en el índice.

### Verificar antes de recomendar
Si una memoria recordada nombra archivo/flag/comando: verifica que siga existiendo antes
de usarla (`ls`/grep). Las memorias reflejan el pasado, no el presente.

### Poda (mantenimiento)
- Memoria cuya descripción ya no coincide con la realidad → corregir o borrar.
- Dos memorias que dicen lo mismo → fusionar en una.
- Al cerrar un ciclo (p. ej. "rama pendiente de merge" ya mergeada) → actualizar la
  memoria con el cierre, no dejarla mintiendo.

### Handoff de sesión
Genera un bloque de continuidad (en chat, no en memoria):

```markdown
## Handoff — <fecha>
**Rama:** <actual> · **Estado:** <limpio / cambios sin commit>
**Hecho esta sesión:** <3-5 bullets con archivo:línea clave>
**Siguiente paso exacto:** <la PRÓXIMA acción concreta, no un tema>
**Contexto vivo:** <memorias/skills/agentes relevantes para retomar>
```

## Salida estándar

```markdown
# Memoria actualizada
| Acción | Archivo | Contenido |
|---|---|---|
| creada/actualizada/borrada | <slug>.md | <una línea> |
Índice MEMORY.md: N entradas.
```
