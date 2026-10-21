---
name: docs-evaluar
description: >-
  Audita la consistencia técnica de la documentación del repo contra el código real:
  drift spec↔código, enlaces rotos, paridad documental docs/python↔docs/laravel,
  coverage del CHANGELOG. No modifica nada — sólo reporta hallazgos con evidencia
  (archivo:línea). Encadenable con /docs-actualizar (que corrige lo hallado).
  Activar con "evaluar documentación", "auditar docs", "revisar consistencia documental".
allowed-tools: Read, Grep, Glob, Bash
---

# Evaluación de documentación (auditoría de consistencia)

Ejecuta una auditoría **read-only** y entrega un informe con evidencia. No edites archivos.

## Alcance por defecto

Todo el repo documental; si el usuario da un alcance (`--desde <ref>`, un directorio o un
proceso), respétalo.

## Proceso

### 1. Cargar el mapa documental
Raíz sólo tiene `README.md`, `CLAUDE.md`, `CHANGELOG.md`; el resto vive en `docs/`
(ver tabla completa en `.claude/agents/agente-documentacion.md` o `CLAUDE.md`).

### 2. Verificar estructura invariante
- [ ] Raíz sin `.md` fuera de {README, CLAUDE, CHANGELOG}.
- [ ] Existen: `docs/SPEC.md`, `docs/CONTEXT.md`, `docs/TASKS.md`, `docs/PLAN.md`,
      `docs/HANDOFF.md`, `docs/MAPA-ARQUITECTURA.md`, `docs/python/README.md`,
      `docs/laravel/README.md` + 4 procesos por plataforma.

### 3. Drift spec ↔ código (lo más valioso)
Para cada regla de negocio de `docs/SPEC.md` §3 y del bloque "Domain rules" de `CLAUDE.md`:
- Localiza su implementación en AMBAS impls (mapa espejo de `CLAUDE.md`).
- Verifica que el comportamiento descrito coincida con el código (cita `archivo:línea`).
- Reporta: ✅ consistente · ⚠️ drift (doc dice X, código hace Y) · ❌ sin cobertura doc.

Símbolos de alto valor para verificar (grep): `normalizar_fecha`/`normalizarFecha`,
`IGTF_RATE`, `UMBRAL_COMPENSACION`, claves `pay:tokval|pay:revoked|pay:sem`, `retryAfter`,
`fecha_original`, `tasa_de_la_fecha`, allowlist `connection`.

### 4. Enlaces y rutas
- `grep` de referencias a `.md` movidos: todo link a SPEC/CONTEXT/TASKS/PLAN debe apuntar a
  `docs/...` (los READMEs de impls usan `../docs/...`).
- Rutas citadas en docs que existan realmente (muestreo de las citadas en CLAUDE.md).

### 5. Paridad documental
`docs/python/X.md` ↔ `docs/laravel/X.md` para los 4 procesos: cubren los mismos
invariantes; divergencias marcadas como tales (no como omisiones).

### 6. CHANGELOG y estado
- Última versión del CHANGELOG refleja los últimos commits (`git log --oneline -10`).
- `docs/HANDOFF.md` y `docs/MAPA-ARQUITECTURA.md` §estatus/§riesgos sin pendientes
  obsoletos (p. ej. algo marcado "pendiente" que ya se mergeó).

## Formato de salida (informe)

```markdown
# Informe de evaluación documental — <fecha> [<alcance>]
## ✅ Consistente
## ⚠️ Drift (doc ≠ código) — con archivo:línea por hallazgo
## ❌ Faltante (cubierto en código, ausente en docs) / huérfano (doc sin código)
## 🔗 Enlaces/estructura
## 📊 Score: N/M checks OK
## Recomendación: encadenar /docs-actualizar con los hallazgos ⚠️/❌
```

**Nunca:** modificar archivos, ni reportar sin evidencia (`archivo:línea`), ni ejecutar
tests de código (eso es de los sub-agentes de implementación).
