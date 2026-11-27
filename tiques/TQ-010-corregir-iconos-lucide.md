---
id: TQ-010
titulo: Corregir iconos Lucide: pasar objeto icons a createIcons
tipo: bug
prioridad: P1
estimacion: S
estado: hecho
refiere_a: —
parte_de: —
rama: feature/entrega-2-ol-gt
creado: 2026-11-27
actualizado: 2026-11-27
---

# TQ-010 — Corregir iconos Lucide: pasar objeto icons a createIcons

## Objetivo

Eliminar el error de consola `Please provide an icons object` en páginas con iconos Lucide (p. ej. `/events`) haciendo que `window.tcRenderIcons` llame a `createIcons` con el objeto `icons` de `lucide`.

## Contexto

- `resources/js/app.js`: arranque del cliente; importa `createIcons` de `lucide`, define `window.tcRenderIcons(root = document)` y lo invoca en `DOMContentLoaded`, `livewire:load`, `livewire:morphed`, toasts y confirmación.
- `package.json`: dependencia `lucide` `^1.81.0`.
- `node_modules/lucide/dist/esm/lucide.mjs`: `createIcons({ icons = {}, nameAttr = 'data-lucide', attrs = {}, root = document, inTemplates })` lanza `Please provide an icons object` si `Object.values(icons).length` es 0; `lucide` exporta `icons` (`iconAndAliases`).
- Error reportado: `Uncaught Error: Please provide an icons object... at createIcons ... at window.tcRenderIcons (app.js:14) ... at HTMLDocument.<anonymous> (app.js:184)`.
- `spec/09-ui-estilo.md` §2 — soporte liviano: iconos Lucide; sin cambios de spec.

## Contratos públicos

- Sin cambios de API, rutas, componentes ni textos de UI.
- `window.tcRenderIcons(root = document)` conserva su firma; internamente llama `createIcons({ icons, nameAttr: 'data-lucide', root })`.
- `import { createIcons, icons } from 'lucide';`.
- Sin cambios en `package.json`, `package-lock.json`, PHP ni Blade.

## Tareas

- [x] Confirmar firma de `createIcons` en la versión instalada de `lucide`.
- [x] Corregir `resources/js/app.js`: importar `icons` y pasar `{ icons, nameAttr: 'data-lucide', root }`.
- [x] Verificar que no haya error de consola en `/events` tras recargar la preview.
- [x] Verificar: `npm run build`
- [x] Verificar: `./vendor/bin/pint && ./vendor/bin/phpstan analyse && ./vendor/bin/pest`
- [x] DETENERSE: presentar los cambios para revisión y sugerir mensaje de commit; no continuar con otra actividad sin aprobación

## Criterios de aceptación

- [x] `window.tcRenderIcons` llama `createIcons` con `icons` no vacío; no se lanza `Please provide an icons object`.
- [x] Los iconos `data-lucide` se siguen renderizando (incluido `root` en toasts/morphs).
- [x] `npm run build` en verde.
- [x] Pint, Larastan nivel 8 y Pest en verde (o se indica qué no se pudo ejecutar).
- [x] Sin cambios fuera de `resources/js/app.js` y `tiques/`.

## Bitácora

| Fecha | Estado | Nota |
|-------|--------|------|
| 2026-11-27 | abierto | Creado |
| 2026-11-27 | en-curso | Confirmado: `createIcons` en `lucide@1.81.0` exige `icons`; la app llamaba `createIcons({ nameAttr }, root)` sin `icons` y con `root` como segundo argumento ignorado. |
| 2026-11-27 | en-revision | Corregido `resources/js/app.js`: `import { createIcons, icons } from 'lucide'` y `createIcons({ icons, nameAttr: 'data-lucide', root })`. Verificado: `npm run build`, Pint, Larastan nivel 8, Pest 209/209, preview recargada en `/events` sin salida de consola. La captura no se pudo revisar como imagen en este modelo. |
| 2026-11-27 | hecho | Aprobada por el usuario al continuar hacia el PR de la entrega; reverificado por el orquestador (build + pruebas) y publicada en 0.13.1 |
