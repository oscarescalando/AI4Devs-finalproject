---
id: TQ-006
titulo: "PWA base instalable (manifest + shell offline)"
tipo: chore
prioridad: P1
estimacion: M
estado: hecho
refiere_a: ISSUE-114
parte_de: TQ-001
rama: feature/entrega-2-ol-gt
creado: 2026-11-26
actualizado: 2026-11-27
---

# TQ-006 — PWA base instalable (manifest + shell offline)

## Objetivo

Manifest, service worker con estrategia offline para el *shell* (layout, assets Vite con hash,
pantalla "sin conexión") y actualización controlada de assets. Base sobre la que EP-27
construirá la estación de cronometraje (ADR-014); el resto de las pantallas funciona online
normal con el mismo service worker.

## Contexto

- `spec/04-backlog/EP-24-fundaciones-livewire.md` ISSUE-114; `spec/04-backlog/EP-27-estacion-offline-pwa.md`
  (consumidor futuro); ADR-014.
- Requiere el layout del panel (TQ-002) y el build Vite con hash (ya operativo desde ISSUE-106).

## Contratos públicos

- Nuevos: `public/manifest.webmanifest` (o equivalente), service worker del shell, pantalla
  offline, registro del SW desde el layout.
- Modificados: `vite.config.js`/assets si hace falta para el precache del shell.

## Tareas

- [x] Manifest completo (nombre, iconos 192/512 + maskable, standalone, tema) — Lighthouse en
      navegador queda para la revisión manual documentada (TQ-008 lo lleva a CI).
- [x] Service worker: precache del shell e iconos, assets por hash, navegaciones network-first
      con fallback a /offline.html; /livewire* y /api/ nunca se interceptan.
- [x] Actualización controlada: assets con hash inmutable; activate limpia cachés viejas.
- [x] El SW no interfiere con las peticiones de Livewire (solo GET propio, /livewire-<hash>/ excluido; probado).
- [x] Verificar: ./vendor/bin/pint && ./vendor/bin/phpstan analyse && ./vendor/bin/pest
- [ ] DETENERSE: presentar los cambios para revisión y sugerir mensaje de commit;
      no continuar con otra actividad sin aprobación

## Criterios de aceptación

- [x] ISSUE-114 completo (4 criterios del backlog; instalabilidad/offline con verificación manual
      documentada para Chrome — Lighthouse en CI llega con TQ-008).

## Bitácora

| Fecha | Estado | Nota |
|-------|--------|------|
| 2026-11-26 | abierto | Creado como fase 5 del plan TQ-001 |
| 2026-11-27 | hecho | Delegado y verificado por el orquestador: Pint ✓ · Larastan 8 con 0 errores · Pest 183/183 (9 tests nuevos) · humo HTTP (manifest/sw/offline/iconos 200). Ajuste del orquestador: el estático /manifest.webmanifest bajaba como octet-stream (nginx sirve el archivo antes que PHP) → ruta canónica /pwa/manifest con application/manifest+json y layouts apuntando ahí. Iconos placeholder (gradiente chrono + T) a sustituir por branding real. Pendientes: Lighthouse en CI y mime de nginx si se sirve el estático (TQ-008) |
