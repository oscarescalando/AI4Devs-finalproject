---
id: TQ-019
titulo: "Caos mínimo del tiempo real (la marca jamás falla por el broadcast)"
tipo: test
prioridad: P1
estimacion: M
estado: abierto
refiere_a: ISSUE-145, ISSUE-146
parte_de: TQ-016
rama: feature/entrega-2-ol-gt
creado: 2026-11-27
actualizado: 2026-11-27
---

# TQ-019 — Caos mínimo del tiempo real

## Objetivo

Pruebas de caos del contrato esencial de ISSUE-145 en su versión F1: (1) un fallo del broadcast
NUNCA impide persistir ni rompe la petición (INV-08/RF-74); (2) ráfaga simple de marcas mantiene
idempotencia y el recálculo absorbe el lote (ISSUE-146 mínima: sin duplicados, clasificación
estable).

## Contexto

- Depende de TQ-018 (eventos emitiéndose tras commit). Las suites `cu-paridad` y
  `estacion-offline` nocturnas completas quedan para el CI nocturno (defer).
- Emisores: RecordTimingMarkBatch (API estación + captura manual), motor vía job.

## Contratos públicos

- Nuevos: tests/Feature/Broadcasting/ChaosTest.php (o similar) con los casos de abajo.
- Sin cambios de aplicación esperados; si el caos destapa un bug, se corrige con prueba roja
  primero (VG-4 análoga).

## Tareas

- [ ] Fallo del broadcast: broadcaster/cola lanzando excepción → la marca persiste, la
      respuesta HTTP es la normal y nada se pierde (prueba doble: fallo al encolar y fallo
      del worker).
- [ ] Ráfaga: N lotes concurrentes/seguidos (>= 100 marcas mezcladas con reenvíos duplicados
      por client_mark_id) → cero duplicados, una pasada de recálculo coherente, resultados
      conciliados.
- [ ] Pantalla con WS muerto + cola viva: la degradación no encola eventos fantasma ni rompe
      la UI (assert liviano).
- [ ] Verificar: ./vendor/bin/pint && ./vendor/bin/phpstan analyse && ./vendor/bin/pest
- [ ] DETENERSE: presentar los cambios para revisión y sugerir mensaje de commit;
      no continuar con otra actividad sin aprobación

## Criterios de aceptación

- [ ] ISSUE-145 mínima: 0 marcas perdidas por fallos de broadcast (probado en dos niveles).
- [ ] ISSUE-146 mínima: ráfaga sin duplicados y clasificación estable.

## Bitácora

| Fecha | Estado | Nota |
|-------|--------|------|
| 2026-11-27 | abierto | Creado como fase 3 del plan TQ-016; se ejecuta tras TQ-018 |
