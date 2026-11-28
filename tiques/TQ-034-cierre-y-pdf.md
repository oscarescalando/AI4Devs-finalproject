---
id: TQ-034
titulo: "Cierre/publicación del evento + planilla PDF por inscripción"
tipo: feature
prioridad: P1
estimacion: L
estado: hecho
refiere_a: ISSUE-163
parte_de: TQ-033
rama: feature/entrega-2-ol-gt
creado: 2026-11-28
actualizado: 2026-11-28
---

# TQ-034 — Cierre/publicación del evento + planilla PDF por inscripción

## Objetivo

Acción «Cerrar evento» con guardas y resultado oficial (el motor ya oficializa por estado), y
descarga de planilla PDF por inscripción con dompdf (ADR-017) desde el listado.

## Contexto

- EventStatus::STATUSES gobierna los estados; el motor marca oficial al cerrar (conciliación ya
  probada en RecalculateResultsTest). Reusar.
- dompdf vía composer (permitido); PDF sin PII sensible (documento/nacimiento NUNCA).
- DEFER: diplomas/fotos, PDF en blanco masivo, exportaciones Excel/CSV (F3).

## Tareas

- [x] Action CloseEvent: guardas (toda oleada con salida real, ≥1 llegada; eventos sin carreras
      → error accionable), estado → cerrado, recálculo oficial, evento event.closed tras commit
      (ops+public sin PII); botón en el hub con confirmación mostrando consecuencias.
- [x] Planilla PDF por inscripción: ruta firmada o autenticada (entries.view), dompdf, vista
      blade dedicada (dorsal, atleta público, categoría, carrera, estado, net/pos si llegò);
      icon-button download en el listado.
- [x] Pruebas: cierre con guardas (ok y rechazos), oficialización (results estado oficial),
      emisión event.closed, PDF 200 con content-type pdf y contenido sin PII (assert texto),
      403 por rol.
- [x] Verificar: ./vendor/bin/pint && ./vendor/bin/phpstan analyse && ./vendor/bin/pest
- [ ] DETENERSE: presentar los cambios para revisión y sugerir mensaje de commit;
      no continuar con otra actividad sin aprobación

## Criterios de aceptación

- [x] ISSUE-163 rebanada: cierre seguro + PDF funcional sin PII.

## Bitácora

| Fecha | Estado | Nota |
|-------|--------|------|
| 2026-11-28 | abierto | Creado como fase del plan TQ-033 |
| 2026-11-28 | en-curso | Delegado a subagent (orquestador); noche autónoma |
| 2026-11-28 | hecho | Verificado por el orquestador: Pint ✓ · Larastan 8 con 0 errores · Pest 457/457 (24 nuevas) · PDF real generado (24,5 KB, %PDF, sin documento/nacimiento en el binario) · humo 302/404. Decisiones: cierre con results.publish (organizer/superadmin; official 403), PreviewCloseEvent como única fuente de guardas (SIN mutar), oficialización síncrona en transacción con conciliación provisional==official, event.closed dual sin PII y pública a OFICIAL al instante. NOTA v1: cierre irreversible (sin reopen — documentar en runbook) |
