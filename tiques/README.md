# Tiques — registro de trabajo de TDChronos 2.0

Este repositorio no crea issues en el forge, así que el registro y control del desarrollo vive
aquí: **un tique por actividad**, en archivo, con plantilla única.

| Pieza | Ruta |
|---|---|
| Tiques | `TQ-NNN-<slug>.md` (esta carpeta) |
| Índice de control | [INDEX.md](INDEX.md) |
| Plantilla canónica | `.claude/skills/tiques-gestionar/references/plantilla-tique.md` |
| Skill que los gestiona | `.claude/skills/tiques-gestionar/SKILL.md` (`/tiques-gestionar`) |

## Cómo funciona

1. **Antes de trabajar**, la actividad abre su tique (o verifica que ya lo tiene).
2. **Durante el trabajo**, el tique se actualiza: estado, casillas de tareas y bitácora
   (decisiones, bloqueos, hash de cada commit).
3. **Al terminar**, el tique se cierra en `hecho` con sus criterios de aceptación verificados.

## Convenciones

- **IDs TQ-NNN** consecutivos, jamás renumerados. Un tique **no es** una issue del backlog:
  la materializa y la referencia en `refiere_a` (las ISSUE-… viven en `spec/04-backlog/`).
- **Estados**: `abierto` → `en-curso` → `en-revision` → `hecho`; `cancelado` con motivo en bitácora.
- **Tipos**: los ocho del backlog (`feature`, `bug`, `chore`, `docs`, `test`, `spike`,
  `tech-debt`, `security`) más `plan` para los tiques que agrupan fases hijas (`parte_de`).
- **Prioridades**: P0 crítico · P1 necesario para MVP · P2 v1 · P3 deseable.
- Sin metadatos de autoría, sin URL externas, sin PII ni secretos; en español.
