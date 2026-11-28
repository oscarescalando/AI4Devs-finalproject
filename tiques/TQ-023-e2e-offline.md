---
id: TQ-023
titulo: "E2E offline de la estación (Playwright) y doble envío"
tipo: test
prioridad: P0
estimacion: M
estado: en-curso
refiere_a: ISSUE-152
parte_de: TQ-020
rama: feature/entrega-2-ol-gt
creado: 2026-11-28
actualizado: 2026-11-28
---

# TQ-023 — E2E offline de la estación (Playwright) y doble envío

## Objetivo

Suite Playwright `estacion-offline` (ADR-018) que certifica el flujo completo en navegador:
emparejar → capturar SIN red → volver online → sync idempotente con estados → reenvío sin
duplicados → 401 desempareja. Deja el flujo certificado como suite separada del smoke.

## Contexto

- La lógica ya está verificada manualmente en Chromium (TQ-022); esto la convierte en suite
  reproducible. En http://.test el SW no registra (contexto no seguro): la suite usa
  emulación de red de Playwright (route abort / setOffline), documentado.
- Corre aparte del smoke (`npm run test:e2e:station`) para no ensuciar el pipeline de humo;
  integrarla a CI nocturno queda DEFER (decisión de runner del paso 3 del usuario).

## Contratos públicos

- Nuevos: tests/e2e/station-offline.spec.js, script npm test:e2e:station, seed del token de
  estación determinista para E2E (comando con --token fijo o seed propio documentado).

## Tareas

- [ ] Spec: emparejar → 3 capturas offline → online → estados finales correctos → reenvío del
      mismo lote (forzar segundo flush) ⇒ duplicate y BD sin filas nuevas.
- [ ] Spec: token inválido ⇒ desempareje con aviso.
- [ ] Script npm separado + readme breve de cómo correrla.
- [ ] Gates completos en verde (pest no se toca; build si hace falta).
- [ ] Verificar: ./vendor/bin/pint && ./vendor/bin/phpstan analyse && ./vendor/bin/pest
- [ ] DETENERSE: presentar los cambios para revisión y sugerir mensaje de commit;
      no continuar con otra actividad sin aprobación

## Criterios de aceptación

- [ ] ISSUE-152 mínima: la suite corre verde de punta a punta contra el sitio servido y
      certifica idempotencia extrema a extremo (INV-02) en navegador.

## Bitácora

| Fecha | Estado | Nota |
|-------|--------|------|
| 2026-11-28 | abierto | Creado como fase 3 del plan TQ-020 |
| 2026-11-28 | en-curso | Delegado a subagent (orquestador); noche autónoma autorizada |
