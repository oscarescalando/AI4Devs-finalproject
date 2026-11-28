---
id: TQ-022
titulo: "Sync idempotente por lotes + reloj con offset"
tipo: feature
prioridad: P0
estimacion: L
estado: hecho
refiere_a: ISSUE-149, ISSUE-150
parte_de: TQ-020
rama: feature/entrega-2-ol-gt
creado: 2026-11-28
actualizado: 2026-11-28
---

# TQ-022 — Sync idempotente por lotes + reloj con offset

## Objetivo

La estación sincroniza su cola local contra `POST /api/v1/events/{ulid}/timing-marks` en lotes
idempotentes (reintentos con backoff, marca a `synced` solo con respuesta definitiva), y su
reloj se corrige por offset contra `GET /time` (estilo NTP: medición al emparejar y
periódica, alerta si el desfase supera 500 ms).

## Contexto

- API idempotente por client_mark_id ya probada (ISSUE-156 + caos TQ-019); la cola IndexedDB
  tiene índice `synced` listo.
- ADR-014/ISSUE-149: la sync nunca pierde marcas — la marca pasa a synced SOLO tras respuesta
  (assigned/orphan/duplicate son definitivas); los errores de red dejan la marca pendiente.
- Reloj: ISSUE-150 — offset = server_time − local, aplicado al mostrar y al capturar
  (captured_at = hora corregida).

## Contratos públicos

- Modificados: resources/js/station.js (sync + reloj), la vista (estados de sync y desfase).
- La API NO se toca (ya sirve; hueco del bearer cerrado en TQ-021).

## Tareas

- [x] Sync: botón manual «Sincronizar» + auto al volver online (evento online) + cada 30 s si
      hay pendientes; lotes de 50 en orden captured_at; por marca: synced=true y estado final
      (assigned/orphan/duplicate) visible en la cola; error de red ⇒ queda pendiente con
      attempts++ y backoff; 401 ⇒ desempareja con aviso claro.
- [x] Reloj: offset medido contra /time (3 muestras, mediana) al emparejar y cada 5 min;
      display con hora corregida mono tabular; aviso persistente si |offset| > 500 ms.
- [x] Aviso beforeunload con pendientes sin sincronizar.
- [x] Pruebas Pest posibles (comando/lógica PHP afectada si hubiera); la lógica JS se valida
      en TQ-023 con Playwright (online/offline/reenvío).
- [x] Verificar: ./vendor/bin/pint && ./vendor/bin/phpstan analyse && ./vendor/bin/pest
- [ ] DETENERSE: presentar los cambios para revisión y sugerir mensaje de commit;
      no continuar con otra actividad sin aprobación

## Criterios de aceptación

- [x] ISSUE-149 mínima: idempotencia extrema a extremo — reenviar todo el lote tras fallo no
      duplica nada y cada marca acaba synced con su estado final.
- [x] ISSUE-150 mínima: reloj con desfase corregido y alerta > 500 ms.

## Bitácora

| Fecha | Estado | Nota |
|-------|--------|------|
| 2026-11-28 | abierto | Creado como fase 2 del plan TQ-020 |
| 2026-11-28 | en-curso | Delegado a subagent (orquestador) |
| 2026-11-28 | hecho | Verificado por el orquestador: Pint ✓ · Larastan 8 con 0 errores · Pest 289/289 (2411 aserciones) · build ✓. Verificación REAL en Chromium del agente: offline→capturas→online→flush con estados [Asignada/Huérfana/Asignada], reenvío idéntico ⇒ duplicate (INV-02), 401 ⇒ desempareje, migración v1→v2 de la BD IndexedDB. HALLAZGO REAL corregido: los booleanos no son claves IndexedDB (el índice synced era inconsultable) → synced 0/1 con migración. Wire: device_time_ms + clock_offset_ms para que el servidor reconstruya la hora oficial sin doble corrección. La BD dev quedó con ~9 marcas sintéticas del humo (renace con migrate:fresh --seed) |
