---
id: TQ-028
titulo: "Personas y equipos con PII por rol"
tipo: feature
prioridad: P1
estimacion: L
estado: en-curso
refiere_a: ISSUE-161
parte_de: TQ-027
rama: feature/entrega-2-ol-gt
creado: 2026-11-28
actualizado: 2026-11-28
---

# TQ-028 — Personas y equipos con PII por rol

## Objetivo

directory.persons y directory.teams reales: personas (documento único, nombre, nacimiento,
género) y equipos (nombre + organización), búsqueda parcial, PII visible solo con
directory.view completo (RNF-13: enmascarada en listados para roles sin el permiso ampliado
— hoy todos los que entran lo tienen, así que el enmascarado aplica a futuros roles
restringidos y a las pantallas públicas), borrados bloqueados por FK (inscripciones/cuentas).

## Contexto

- Patrón EventList/CategoryManager (clases Livewire + Actions + kit); placeholders ya enrutados
  con directory.view; Person tiene publicName() para menores.
- Inspeccionar columnas de persons/teams/team_user antes de escribir; sin migraciones.

## Contratos públicos

- Nuevos: Actions CRUD personas y equipos; componentes y vistas reales.
- Modificados: rutas a las clases (nombres intactos).

## Tareas

- [ ] Personas: lista paginada con búsqueda (nombre o documento parcial — documento
      enmascarado en la lista: V-1•••••99), modal alta/edición con validaciones (documento
      formato y único, nacimiento pasada, género del esquema), borrado bloqueado con
      inscripciones/cuentas.
- [ ] Equipos: lista por organización + modal (nombre, organización), borrado bloqueado con
      inscripciones.
- [ ] Pruebas por rol, unicidad de documento, enmascarado, bloqueos, búsqueda.
- [ ] Verificar: ./vendor/bin/pint && ./vendor/bin/phpstan analyse && ./vendor/bin/pest
- [ ] DETENERSE: presentar los cambios para revisión y sugerir mensaje de commit;
      no continuar con otra actividad sin aprobación

## Criterios de aceptación

- [ ] ISSUE-161 completa: CRUD + PII enmascarada en listados + bloqueos probados.

## Bitácora

| Fecha | Estado | Nota |
|-------|--------|------|
| 2026-11-28 | abierto | Creado como fase del plan TQ-027 |
| 2026-11-28 | en-curso | Delegado a subagent (orquestador); noche autónoma |
