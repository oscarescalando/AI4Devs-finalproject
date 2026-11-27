---
id: TQ-006
titulo: "PWA base instalable (manifest + shell offline)"
tipo: chore
prioridad: P1
estimacion: M
estado: abierto
refiere_a: ISSUE-114
parte_de: TQ-001
rama: feature/entrega-2-ol-gt
creado: 2026-11-26
actualizado: 2026-11-26
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

- [ ] Manifest completo (nombre, iconos, display standalone, tema) con los criterios de
      instalabilidad de Lighthouse en verde.
- [ ] Service worker: precache del shell y assets versionados por hash; pantalla "sin conexión"
      para rutas operativas sin red.
- [ ] Actualización controlada: un despliegue nuevo invalida la caché sin servir mezclas
      obsoletas.
- [ ] El SW no interfiere con las peticiones de Livewire (solo assets y shell).
- [ ] Verificar: ./vendor/bin/pint && ./vendor/bin/phpstan analyse && ./vendor/bin/pest
- [ ] DETENERSE: presentar los cambios para revisión y sugerir mensaje de commit;
      no continuar con otra actividad sin aprobación

## Criterios de aceptación

- [ ] ISSUE-114 completo (4 criterios del backlog; los de navegador verificados manualmente y
      documentado cómo reproducirlos).

## Bitácora

| Fecha | Estado | Nota |
|-------|--------|------|
| 2026-11-26 | abierto | Creado como fase 5 del plan TQ-001 |
