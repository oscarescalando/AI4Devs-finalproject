---
id: TQ-014
titulo: "Registros: bitácora en vivo y corrección 1/2/3"
tipo: feature
prioridad: P1
estimacion: XL
estado: en-curso
refiere_a: ISSUE-134, ISSUE-135
parte_de: TQ-011
rama: feature/entrega-2-ol-gt
creado: 2026-11-27
actualizado: 2026-11-27
---

# TQ-014 — Registros: bitácora en vivo y corrección 1/2/3

## Objetivo

Bitácora del juez con estados derivados y el modal de corrección con las tres operaciones
(RF-46…48): (1) ajustar tiempo ±1 s/±0,1 s, (2) reasignar a otro dorsal, (3) anular — motivo
siempre, marca original intacta (append-only) y estado efectivo derivado de la ÚLTIMA
corrección, con recálculo transaccional.

## Contexto

- `timing_mark_corrections` ya existe: operation (adjust|reassign|void), reason (INV-11),
  account_id, before_after JSON — mapea 1:1 con las operaciones del ISSUE-135.
- **Hallazgo**: `RecalculateEventResults` hoy toma MIN(captured_at) de marcas con entry_id y NO
  considera correcciones; `timing_marks` no admite UPDATE (trigger) → el motor debe derivar la
  marca EFECTIVA: última corrección por marca gana (void excluye, reassign re-atribuye al
  entry del dorsal destino, ajusta desplaza captured_at).
- Pantalla placeholder `events.marks` (marks.view) a sustituir; corrección exige
  `corrections.manage` (matriz ISSUE-159).
- Diferidos: canal privado < 2 s y liberación de listeners (EP-26, ISSUE-142/144; interino
  wire:poll.5s), paginación por cursor para miles de marcas (nota), foto vía proxy (ISSUE-136).

## Contratos públicos

- Nuevos: Action `CorrectTimingMark` (adjust|reassign|void con validaciones y confirmación
  explícita en reasignación sobre dorsal con llegada), `ListMarkLog` (paginada con estados
  derivados y contadores), extensión del motor para estado efectivo; Volt `events/mark-log`
  real con modal de corrección + historial por marca.
- Modificados: `RecalculateEventResults` (marcas efectivas) y sus pruebas.

## Tareas

- [ ] Motor: marcas efectivas desde la última corrección (void fuera, reassign re-atribuido,
      adjust con delta) — pruebas nuevas en RecalculateResultsTest.
- [ ] Action CorrectTimingMark: motivo obligatorio, validaciones (dorsal 1-3 dígitos, distinto,
      existe; destino con llegada → requiere confirmación y deja la anterior como duplicada),
      before_after de auditoría, job de recálculo afterCommit.
- [ ] Bitácora: paginación server-side, estados (asignada/huérfana/duplicada/anulada/corregida)
      derivados, contadores por estado coherentes, wire:poll.5s.
- [ ] Modal de corrección (kit): tres operaciones, motivo siempre, confirmación previa,
      historial de correcciones de la marca.
- [ ] Huérfana asignable desde la bitácora (reassign sobre huérfana, sin error).
- [ ] Permisos: ver = marks.view; corregir = corrections.manage (403 probado por rol).
- [ ] Pruebas completas de las tres operaciones + integridad (marca intacta, recálculo origen
      y destino, estados derivados).
- [ ] Verificar: ./vendor/bin/pint && ./vendor/bin/phpstan analyse && ./vendor/bin/pest
- [ ] DETENERSE: presentar los cambios para revisión y sugerir mensaje de commit;
      no continuar con otra actividad sin aprobación

## Criterios de aceptación

- [ ] ISSUE-135: tres operaciones funcionan; la marca original queda intacta; el estado
      efectivo se deriva de la última corrección; reasignación con confirmación deja la
      anterior como duplicada; recálculo en transacción; huérfana asignable.
- [ ] ISSUE-134 mínima: bitácora paginada con contadores por estado coherentes y actualización
      por sondeo (canal privado y cursor diferidos con nota en EP-25).

## Bitácora

| Fecha | Estado | Nota |
|-------|--------|------|
| 2026-11-27 | abierto | Creado como fase del plan TQ-011 |
| 2026-11-27 | en-curso | Alcance fijado tras hallar que el motor no deriva estado efectivo; diferidos documentados; delegado a subagent |
