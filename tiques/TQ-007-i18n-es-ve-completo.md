---
id: TQ-007
titulo: "i18n es-VE completo: todo texto por lang/"
tipo: chore
prioridad: P2
estimacion: S
estado: hecho
refiere_a: ISSUE-118
parte_de: TQ-001
rama: feature/entrega-2-ol-gt
creado: 2026-11-26
actualizado: 2026-11-27
---

# TQ-007 — i18n es-VE completo: todo texto por lang/

## Objetivo

Cerrar el resto de ISSUE-118: textos de interfaz en archivos `lang/` PHP con locale `es-VE` por
defecto, mensajes de validación en español, fechas `dd/mm/yyyy` en la UI y cero literales de
interfaz en componentes. (`FormatDuration` con pruebas ya existe desde 0.5.0.)

## Contexto

- `spec/04-backlog/EP-24-fundaciones-livewire.md` ISSUE-118 (re-expresión de ISSUE-039
  heredada); `app/Support/FormatDuration.php` ya cubierto.
- Aplica sobre todo lo construido en TQ-002…TQ-006 (sus textos nacen ya por `lang/`, pero este
  tique barre y verifica el conjunto).

## Contratos públicos

- Nuevos: archivos `lang/es_VE/…` (o `lang/es/…` según convención Laravel 13) con todas las
  claves de UI y validación.
- Modificados: vistas/componentes que contengan literales; provider de locale si hace falta.

## Tareas

- [x] Inventario de literales de interfaz en vistas/componentes; migración a `lang/` (199 claves
      en lang/es/ui.php por dominio; textos renderizados idénticos).
- [x] Locale es con fallback es; mensajes de validación en español (completados en TQ-005).
- [x] Fechas `dd/mm/yyyy` en UI (ISO en BD) con helper único `FormatDate` (bisiesto y fin de mes
      probados).
- [x] Prueba NoInlineTextTest que escanea las vistas y falla con archivo:línea:texto ante literales
      (excepciones documentadas en excepciones-literales.txt).
- [x] Revisión ortográfica de ~250 cadenas: cero erratas en UI (un typo en comentario corregido).
- [x] Verificar: ./vendor/bin/pint && ./vendor/bin/phpstan analyse && ./vendor/bin/pest
- [ ] DETENERSE: presentar los cambios para revisión y sugerir mensaje de commit;
      no continuar con otra actividad sin aprobación

## Criterios de aceptación

- [x] ISSUE-118 completo (4 criterios del backlog; `formatDuration` verde desde 0.5.0; los literales
      de `#[Title]` de Volt quedan documentados como excepción técnica del atributo PHP).

## Bitácora

| Fecha | Estado | Nota |
|-------|--------|------|
| 2026-11-26 | abierto | Creado como fase 6 del plan TQ-001 |
| 2026-11-27 | hecho | Delegado y verificado por el orquestador: Pint ✓ · Larastan 8 con 0 errores · Pest 191/191 (FormatDate + chequeo de literales) · humo HTTP con textos idénticos en /login y /e/{slug}. Decisiones: config devuelve claves y las vistas traducen (evita congelar traducciones con config:cache); atributos de validación por-validador en el perfil; excepciones del chequeo documentadas |
