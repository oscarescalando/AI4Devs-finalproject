---
id: TQ-005
titulo: Perfil propio y cambio de contraseña con la actual
tipo: feature
prioridad: P1
estimacion: S
estado: abierto
refiere_a: ISSUE-113
parte_de: TQ-001
rama: feature/entrega-2-ol-gt
creado: 2026-11-26
actualizado: 2026-11-26
---

# TQ-005 — Perfil propio y cambio de contraseña con la actual

## Objetivo

Pantalla de perfil de la cuenta propia en Livewire: datos no sensibles, cambio de contraseña
exigiendo la actual, confirmación operativa e invalidación de las demás sesiones al cambiarla.

## Contexto

- `spec/04-backlog/EP-24-fundaciones-livewire.md` ISSUE-113 (alineada con ISSUE-013 heredada;
  complementa ISSUE-044 heredada, fuera de alcance aquí).
- Requiere TQ-002 (login/sesiones) y TQ-003 (formulario del kit).
- `accounts` ya existe (persona ≠ cuenta, ADR-015); sin vista de perfil hoy.

## Contratos públicos

- Nuevos: componente Livewire de perfil (clase + vista), ruta nombrada protegida, Action de
      cambio de contraseña con sus pruebas.
- Modificados: menú del panel (entrada Perfil).

## Tareas

- [ ] Action de cambio de contraseña: exige la actual, validación de confirmación, invalida las
      otras sesiones.
- [ ] Pantalla de perfil con el formulario del kit; campos de contraseña vacíos tras guardar
      (nunca el hash).
- [ ] Reglas de auto-protección: la propia cuenta no se cambia el rol ni se desactiva a sí
      misma; un `user` no ve selector de rol.
- [ ] Pruebas: sin la actual → 422; con la actual → éxito + sesiones invalidadas.
- [ ] Verificar: ./vendor/bin/pint && ./vendor/bin/phpstan analyse && ./vendor/bin/pest
- [ ] DETENERSE: presentar los cambios para revisión y sugerir mensaje de commit;
      no continuar con otra actividad sin aprobación

## Criterios de aceptación

- [ ] ISSUE-113 completo (4 criterios del backlog verificados con pruebas).

## Bitácora

| Fecha | Estado | Nota |
|-------|--------|------|
| 2026-11-26 | abierto | Creado como fase 4 del plan TQ-001 |
