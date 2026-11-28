---
name: tiques-gestionar
description: >-
  Registro y control del trabajo de desarrollo de TDChronos 2.0 mediante tiques (issues locales
  en archivo, carpeta tiques/): crea un tique por actividad con la plantilla única, los actualiza
  durante la implementación (tareas, estado, bitácora), los cierra al terminar y mantiene el
  índice tiques/INDEX.md sincronizado. Activar con "crear tique", "nuevo tique", "abrir tique",
  "actualizar tique", "cerrar tique", "estado de los tiques" o antes de empezar cualquier
  actividad de desarrollo que no tenga tique.
allowed-tools: Read, Grep, Glob, Write, Edit
---

# Gestión de tiques (tiques/) — TDChronos 2.0

Cada actividad de desarrollo se registra como un **tique**: un issue local en archivo que nace
antes de trabajar, se actualiza durante el trabajo y queda como historial al cerrarse. Como este
repositorio no crea issues en el forge, los tiques son la trazabilidad del desarrollo.

## Mapa del sistema

| Pieza | Ruta |
|---|---|
| Tiques (instancias) | `tiques/TQ-NNN-<slug>.md` |
| Índice de control | `tiques/INDEX.md` |
| Guía del sistema | `tiques/README.md` |
| Plantilla canónica | `references/plantilla-tique.md` (dentro de este skill) |

## Reglas duras

1. **Nada de trabajo sin tique**: antes de implementar cualquier actividad, verifica que exista
   su tique; si no existe, créalo. El trabajo que aparece en medio de otra tarea abre tique
   propio: nunca se amplía en silencio el de otra actividad.
2. **Numeración TQ-NNN**: consecutiva desde 001; jamás se renumera ni se reutiliza un id. Los
   tiques NO son issues del backlog: referencian las ISSUE-… que materializan (`refiere_a`) y
   nunca las sustituyen. La numeración del backlog (ISSUE-105…) vive solo en `spec/04-backlog/`.
3. **Plantilla única**: todo tique sigue `references/plantilla-tique.md` sin omitir secciones;
   las que no apliquen se dejan con "—" o "Sin cambios".
4. **Sin autoría ni marcas ajenas**: sin metadatos de autor/modelo/herramienta, sin URL externas,
   sin nombres o mascotas de otros proyectos; en español; sin PII ni secretos.
5. **El índice manda**: `tiques/INDEX.md` refleja siempre el estado real; se actualiza en la
   misma edición que el tique.
6. **Estado honesto**: las casillas se marcan solo cuando la tarea está hecha y verificada.

## Ciclo de vida

`abierto` → `en-curso` → `en-revision` → `hecho` · `cancelado` como salida lateral (siempre con
motivo en la bitácora).

## Crear un tique

1. Lee `references/plantilla-tique.md` y `tiques/INDEX.md` (para determinar el siguiente id
   libre y evitar duplicar una actividad ya registrada).
2. **Tique simple** (una actividad concreta): redacta y crea el archivo directamente; informa al
   usuario con un resumen de una línea.
3. **Tique plan** (varias fases/hijos): **propón primero el corte en rebanadas verticales y
   espera la aprobación del usuario antes de crear archivo alguno**. Reglas del corte:
   - Cada fase entrega algo navegable, ejecutable o verificable por sí misma (rebanada vertical:
     UI + lógica + pruebas felices juntas; nunca capas técnicas separadas).
   - Cada fase se commitea sin romper el build; la verificación queda en verde.
   - Una responsabilidad por fase; las dependencias (paquetes, migraciones base) van en su
   propia fase o al inicio de la primera que las necesite.
   Cada fase es un tique hijo con `parte_de` al plan; el plan lista sus fases como casillas y
   mantiene la sección "Siguiente paso".
4. Guarda como `tiques/TQ-NNN-<slug-kebab>.md`, añade la fila al índice y registra "Creado" en
   la bitácora del tique.

## Actualizar durante el trabajo

- Al empezar: `estado: en-curso` + entrada de bitácora.
- Al completar tareas: marca las casillas, ajusta `actualizado` y anota en la bitácora las
  decisiones, bloqueos y hallazgos que un futuro lector necesite.
- Al commitear (skill `commit`): agrega a la bitácora el hash y el alcance del commit.
- Nunca edites Objetivo o Contratos en silencio: si el alcance cambia, se anota en la bitácora;
  y si el cambio toca una regla de `spec/`, detente y usa el skill `spec-gestionar`.

## Cerrar

1. Verifica que todos los criterios de aceptación estén marcados y la verificación completa en
   verde: `./vendor/bin/pint` → `./vendor/bin/phpstan analyse` → `./vendor/bin/pest`.
2. `estado: hecho`, entrada final de bitácora, fila del índice actualizada.
3. Si el tique es fase de un plan: marca su casilla en el tique plan, actualiza su "Siguiente
   paso"; si era la última fase, cierra también el plan.
4. Si el tique materializa issues del backlog, marca en `spec/04-backlog/` los criterios de esas
   issues que quedaron cubiertos (la spec se sincroniza en el mismo cambio, con su commit).

## Implementar una actividad

Un tique se implementa de **una actividad por invocación**: sigue sus Tareas en orden, verifica
(pint → phpstan → pest) y **DETENTE para revisión del usuario** antes de dar la actividad por
cerrada. No encadenes tiques sin aprobación explícita.
