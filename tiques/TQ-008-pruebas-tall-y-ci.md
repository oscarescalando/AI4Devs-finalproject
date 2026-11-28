---
id: TQ-008
titulo: Pruebas de la capa TALL y CI del monolito
tipo: test
prioridad: P1
estimacion: L
estado: hecho
refiere_a: ISSUE-116, ISSUE-117, ISSUE-109
parte_de: TQ-001
rama: feature/entrega-2-ol-gt
creado: 2026-11-26
actualizado: 2026-11-27
---

# TQ-008 — Pruebas de la capa TALL y CI del monolito

## Objetivo

Cerrar EP-24 con la calidad amarrada: pruebas de componente de la capa TALL, el recorrido E2E
del flujo crítico, la regla de arquitectura "ninguna acción pública de componente sin policy"
(que completa ISSUE-109) y el pipeline CI del monolito con los gates en el orden oficial.

## Contexto

- `spec/04-backlog/EP-24-fundaciones-livewire.md` ISSUE-116 (pruebas TALL) e ISSUE-117 (CI).
- Orden de gates de CI: Pint → Larastan 8 → Pest (MySQL real) → Pest Arch → gitleaks →
  `composer audit` → Playwright smoke → build Docker (`CLAUDE.md` §Proceso SDD,
  `spec/07-tests.md` §3).
- Requiere TQ-002…TQ-007 construidos para tener qué probar.

## Contratos públicos

- Nuevos: suite de pruebas de componente del kit, prueba E2E del recorrido crítico (login →
  crear evento → categorías/oleadas → inscribir → confirmar y dar salida → registrar llegada →
  corregir marca → ver ranking), regla Pest Arch de policies en componentes, pipeline de CI.
- Modificados: `phpunit.xml`/`phpstan.neon` si los umbrales cambian.

## Tareas

- [x] Pruebas de componente del kit: authorize, validación de servidor, estados de error.
- [x] Reglas Pest Arch: App\Livewire sin DB ni App\Models (componente → Action → modelo) +
      inspección de rutas (auth/permission por ruta). Volt no es reglable por Arch (compila fuera
      del árbol PSR-4): limitación documentada con resguardo indirecto de la suite.
- [x] Playwright smoke (landing + login→consola + pública) 3/3 en 4 s; el recorrido completo
      llega con las pantallas de EP-25.
- [x] GitHub Actions (.github/workflows/ci.yml) con los gates en orden (Pint → Larastan → Pest
      MySQL → Arch → gitleaks → audit → build+manifest → Playwright), PRs a main.
- [x] Gitleaks y composer audit en el pipeline. Imagen OCI DEFER: registry (propuesto ghcr.io) y
      runtime (fpm+nginx vs Octane) son decisión de la mañana; anotado en el workflow.
- [ ] Cobertura con umbral ≥80 % Timing/Results/Registration: DEFER (PCOV en CI), decisión anotada.
- [x] Verificar: ./vendor/bin/pint && ./vendor/bin/phpstan analyse && ./vendor/bin/pest
- [ ] DETENERSE: presentar los cambios para revisión y sugerir mensaje de commit;
      no continuar con otra actividad sin aprobación

## Criterios de aceptación

- [x] ISSUE-116 (3 de 4: el recorrido E2E completo y la cobertura con umbral quedan para EP-25/PCOV;
      smoke + inspección de rutas + kit cubiertos).
- [x] ISSUE-117 (3 de 4: pipeline bloqueante en PRs, build con manifest verificado, gitleaks; la
      imagen OCI al etiquetar queda diferida con decisión registrada).
- [x] ISSUE-109 cerrado (regla en CI + matriz por rol; el ámbito por evento llega con los roles
      de evento de F2).

## Bitácora

| Fecha | Estado | Nota |
|-------|--------|------|
| 2026-11-26 | abierto | Creado como fase 7 del plan TQ-001; cierra también ISSUE-109 |
| 2026-11-27 | hecho | Delegado y verificado por el orquestador: Pint ✓ · Larastan 8 con 0 errores · Pest 209/209 (16 nuevas) · Playwright 3/3 (chromium local, 4 s) · workflow YAML validado. Decisiones: Gate directory.write = admin/superadmin como convención hasta directory.manage (documentada en AppServiceProvider); Livewire AuthorizationException → assertForbidden; smoke con .env normal + test:e2e que resiembra. DEFERs para la mañana: imagen OCI (ghcr.io + runtime), cobertura con umbral, Lighthouse-PWA, suites nocturnas |
