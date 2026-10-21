---
name: docs-actualizar
description: >-
  Sincroniza la documentación con un cambio ya realizado en el código: SPEC/CONTEXT/ADR,
  READMEs de implementaciones, CLAUDE.md (mapa espejo), docs por plataforma, HANDOFF y
  CHANGELOG. Acepta un alcance (commits, rama o archivos) o una lista de hallazgos de
  /docs-evaluar. No implementa código — si falta, lo reporta. Activar con "actualizar
  documentación", "sincronizar docs", "documentar este cambio".
allowed-tools: Read, Grep, Glob, Bash, Edit, Write
---

# Actualización de documentación (sincronización)

Refleja en los artefactos documentales un cambio de código **ya existente** (commits,
working tree o hallazgos de `/docs-evaluar`). El código no se toca.

## Entrada

1. El usuario da alcance: `git log <base>..HEAD`, archivos, o informe de `/docs-evaluar`.
2. Si no hay alcance: derívalo de `git status` + `git log -5` y confírmalo en una línea.

## Protocolo (orden de cascada documental)

Para cada comportamiento nuevo/cambiado detectado en el diff:

| Si el cambio es… | Actualiza |
|---|---|
| Regla de negocio | `docs/SPEC.md` (sección §3/§4) + `CLAUDE.md` "Domain rules" si aplica |
| Término nuevo | `docs/CONTEXT.md` (formato del glosario) |
| Concern/mapa nuevo | Fila del mapa espejo en `CLAUDE.md` + entrada en `docs/MAPA-ARQUITECTURA.md` |
| Decisión difícil de revertir | Proponer ADR nuevo → encadenar `/adr-prd` (NO editar ADRs aceptados) |
| Proceso tasa/caché/deuda/errores | `docs/python/<proceso>.md` **y** `docs/laravel/<proceso>.md` (espejo) |
| Endpoint/variable de entorno | `.env.example` ya debe estarlo (verifícalo); READMEs de las impls |
| Fase/estado | `docs/HANDOFF.md` (sección nueva con fecha) + `docs/TASKS.md` checkboxes |
| Versión | `CHANGELOG.md` bajo la versión vigente (o crea la siguiente según SemVer) |

## Reglas

1. **Evidencia antes de escribir:** lee el diff real (`git show <sha>`) — nunca documentes
   de oídas. Toda afirmación debe poder citar `archivo:línea` del código actual.
2. **Español + glosario:** usa los términos canónicos de `docs/CONTEXT.md`.
3. **Paridad documental:** un cambio que tocó ambas impls se documenta en ambos lados
   (`docs/python/` + `docs/laravel/` + ambos READMEs) en el mismo ciclo.
4. **Append-only en histórico:** CHANGELOG (bajo versión vigente) y ADRs aceptados no se
   reescriben; HANDOFF/MAPA sí se actualizan en sus secciones de estado.
5. **Sin código:** si para que la doc sea verdad falta un cambio de código, repórtalo como
   pendiente — no lo implementes aquí.

## Verificación final

```bash
grep -rn 'docs/SPEC.md\|../SPEC.md' --include='*.md' . | grep -v '.claude/worktrees' # rutas
ls docs/python docs/laravel          # espejos presentes
git diff --stat                      # resumen de docs tocados
```

## Salida

```markdown
# Sincronización documental — <alcance>
| Artefacto | Cambio | Motivo (commit/línea) |
## Pendientes detectados (requieren código u decisión)
## Sugerido: /commit para versionar · /docs-evaluar para re-auditar
```
