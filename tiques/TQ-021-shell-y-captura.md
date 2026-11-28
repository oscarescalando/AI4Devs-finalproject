---
id: TQ-021
titulo: "Shell de la estación: emparejamiento + captura local en IndexedDB"
tipo: feature
prioridad: P0
estimacion: L
estado: hecho
refiere_a: ISSUE-147, ISSUE-148
parte_de: TQ-020
rama: feature/entrega-2-ol-gt
creado: 2026-11-28
actualizado: 2026-11-28
---

# TQ-021 — Shell de la estación: emparejamiento + captura local en IndexedDB

## Objetivo

Pantalla `/station` (sin sesión web) con emparejamiento por token de dispositivo (pegado/leído,
guardado localmente) y captura de dorsales 100 % local: cada marca nace en IndexedDB con
`client_mark_id` UUIDv7 del cliente y la hora del reloj local — funciona con la red caída.

## Contexto

- ADR-014; ISSUE-147 (shell PWA amplía ISSUE-069) e ISSUE-148 (captura IndexedDB).
- Kit x-ui.* disponible; SW actual precachea shell+offline (extender precache a /station).
- El token de dispositivo se crea hoy solo por tinker/seed — añadir comando artesanal
  `station:token` (imprime token con ability device:marks) para el emparejamiento manual F1;
  la pantalla de emparejamiento con QR llega con ISSUE-149/v1 completa.

## Contratos públicos

- Nuevos: ruta pública `/station` (Volt o página estática servida por Blade sin auth),
  módulo JS de la estación (Alpine + IndexedDB) con estado en localStorage, comando
  `station:token`, extensión del SW (precache /station).
- Modificados: nada del panel; la API no se toca (sync es TQ-022).

## Tareas

- [x] Comando station:token (evento por ULID, imprime token + endpoint; sintético, sin PII).
- [x] Pantalla /station: emparejamiento (input token + ULID del evento, valida contra
      GET /time con el token antes de aceptar), luego modo captura: input dorsal gigante,
      Enter captura, cola visible con estado local (pendiente), borrado de pendiente, contador.
- [x] IndexedDB (librería mínima propia, sin dependencias): store de marcas {client_mark_id
      uuidv7, bib, captured_at ISO ms, synced:false}; uuidv7 en cliente (timestamp).
- [x] Reloj local visible con mono tabular; el offset llega en TQ-022 (hoy hora del equipo).
- [x] SW: /station al precache (funciona offline tras primera visita).
- [x] Pruebas: Pest del comando (token válido con ability), ruta /station 200 sin sesión,
      render con estados; la lógica JS se valida en TQ-023 con Playwright.
- [x] Verificar: ./vendor/bin/pint && ./vendor/bin/phpstan analyse && ./vendor/bin/pest
- [ ] DETENERSE: presentar los cambios para revisión y sugerir mensaje de commit;
      no continuar con otra actividad sin aprobación

## Criterios de aceptación

- [x] ISSUE-147 mínima: shell instalable y navegable offline tras primera visita.
- [x] ISSUE-148 mínima: captura offline persistente en IndexedDB con client_mark_id uuidv7
      y sobrevive recarga/navegación (verificado manualmente + Playwright en TQ-023).

## Bitácora

| Fecha | Estado | Nota |
|-------|--------|------|
| 2026-11-28 | abierto | Creado como fase 1 del plan TQ-020 |
| 2026-11-28 | en-curso | Delegado a subagent (orquestador) |
| 2026-11-28 | hecho | Verificado por el orquestador: Pint ✓ · Larastan 8 con 0 errores · Pest 289/289 (7 nuevas) · build (entry station separado de 97 kB; panel intacto) · humo HTTP (/station 200 sin sesión; /time 401 con bearer basura). Verificación offline manual del agente documentada (SW + IndexedDB + captura sin red). Decisiones: entry Vite propio sin Livewire/Echo; uuidv7 a mano con variante corregida (5000 muestras); cuenta sintética de estación sin roles ni active; /time ahora exige Bearer VÁLIDO si se presenta (aditivo, cierre de hueco del emparejamiento) |
