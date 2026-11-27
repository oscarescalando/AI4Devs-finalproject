---
id: TQ-008
titulo: Pruebas de la capa TALL y CI del monolito
tipo: test
prioridad: P1
estimacion: L
estado: abierto
refiere_a: ISSUE-116, ISSUE-117, ISSUE-109
parte_de: TQ-001
rama: feature/entrega-2-ol-gt
creado: 2026-11-26
actualizado: 2026-11-26
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

- [ ] Pruebas de componente del kit: authorize, validación de servidor, estados de error.
- [ ] Regla Pest Arch: ningún método público de componente Livewire/Volt sin comprobación de
      policy (o convención equivalente verificable en CI).
- [ ] Recorrido E2E del flujo crítico contra MySQL real.
- [ ] Pipeline CI con los gates en el orden oficial, bloqueante y < 10 min.
- [ ] Gitleaks y `composer audit` en verde; build de imagen versionada al etiquetar.
- [ ] Cobertura del paquete de UI publicada con umbral definido.
- [ ] Verificar: ./vendor/bin/pint && ./vendor/bin/phpstan analyse && ./vendor/bin/pest
- [ ] DETENERSE: presentar los cambios para revisión y sugerir mensaje de commit;
      no continuar con otra actividad sin aprobación

## Criterios de aceptación

- [ ] ISSUE-116 completo (4 criterios del backlog).
- [ ] ISSUE-117 completo (4 criterios del backlog).
- [ ] ISSUE-109 cerrado: regla de arquitectura verificada en CI + matriz de permisos de la UI
      probada por rol.

## Bitácora

| Fecha | Estado | Nota |
|-------|--------|------|
| 2026-11-26 | abierto | Creado como fase 7 del plan TQ-001; cierra también ISSUE-109 |
