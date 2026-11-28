---
id: TQ-035
titulo: "Runbook de operación y backups"
tipo: docs
prioridad: P1
estimacion: M
estado: hecho
refiere_a: ISSUE-163
parte_de: TQ-033
rama: feature/entrega-2-ol-gt
creado: 2026-11-28
actualizado: 2026-11-28
---

# TQ-035 — Runbook de operación y backups

## Objetivo

Comando `ops:backup` (mysqldump verificado en local) y el runbook de operación del sistema:
arranque, día de carrera (estación, captura, correcciones), cierre, backups y reversa básica.

## Tareas

- [x] Comando ops:backup {--database=} → storage/app/backups con nombre fechado, gzip;
      prueba de que genera un archivo SQL válido (contiene CREATE TABLE de una tabla núcleo);
      verificado en local con mysqldump real.
- [x] docs/procesos/runbook-operacion.md: arranque (Herd/Laragon, dev, reverb, horizon),
      día de carrera (orden operativo y pantallas), cierre (guardas, irreversible v1),
      backups (comando + restauración mysqldump), y reversa básica de emergencia.
- [x] Gates completos en verde.
- [x] Verificar: ./vendor/bin/pint && ./vendor/bin/phpstan analyse && ./vendor/bin/pest
- [ ] DETENERSE: presentar los cambios para revisión y sugerir mensaje de commit;
      no continuar con otra actividad sin aprobación

## Criterios de aceptación

- [x] ISSUE-163 completa: runbook con backups probados en local.

## Bitácora

| Fecha | Estado | Nota |
|-------|--------|------|
| 2026-11-28 | abierto | Creado como fase final del plan TQ-033 |
| 2026-11-28 | en-curso | Delegado a subagent (orquestador); noche autónoma |
| 2026-11-28 | hecho | Verificado por el orquestador: Pint ✓ · Larastan 8 con 0 errores · Pest 458/458 · backup REAL generado y verificado (28 CREATE TABLE, gzip, 15,6 KiB; mysqldump 8.0.36 de Herd vía Process seguro — contraseña en MYSQL_PWD, jamás argv). Runbook §1-§5 con URLs/permisos reales; restauración descrita (no ejecutada) |
