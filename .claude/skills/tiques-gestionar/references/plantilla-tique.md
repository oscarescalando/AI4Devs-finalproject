# Plantilla canónica de tique

> Copia este contenido en `tiques/TQ-NNN-<slug>.md` y sustituye los marcadores `{…}`.
> Ninguna sección se elimina: la que no aplique queda con "—" o "Sin cambios".

---

```markdown
---
id: TQ-NNN
titulo: {título corto de la actividad, en español}
tipo: {feature|bug|chore|docs|test|spike|tech-debt|security|plan}
prioridad: {P0|P1|P2|P3}
estimacion: {S|M|L|XL}
estado: {abierto|en-curso|en-revision|hecho|cancelado}
refiere_a: {ISSUE-… de spec/04-backlog que materializa, separadas por coma; — si ninguna}
parte_de: {TQ-… del tique plan padre; — si es raíz}
rama: {rama de git donde se trabaja}
creado: {AAAA-MM-DD}
actualizado: {AAAA-MM-DD}
---

# TQ-NNN — {Título}

## Objetivo

{1 a 3 frases: qué se consigue al cerrar este tique, en términos observables
para el usuario o el proyecto.}

## Contexto

{Archivos, carpetas y documentos de spec/ implicados, con sus rutas. Qué leer
antes de empezar. Qué ya existe y qué falta.}

## Contratos públicos

{Qué se crea, modifica o elimina: clases Action con sus firmas, endpoints,
componentes/vistas, tablas, textos de UI. En un tique tipo plan esta sección
se sustituye por "Fases" (ver abajo).}

## Tareas

- [ ] {tarea concreta y verificable}
- [ ] Verificar: ./vendor/bin/pint && ./vendor/bin/phpstan analyse && ./vendor/bin/pest
- [ ] DETENERSE: presentar los cambios para revisión y sugerir mensaje de commit;
      no continuar con otra actividad sin aprobación

## Criterios de aceptación

- [ ] {criterio medible, trazable a la issue del backlog en refiere_a}

## Bitácora

| Fecha | Estado | Nota |
|-------|--------|------|
| {AAAA-MM-DD} | abierto | Creado |
```

---

## Variante para tiques plan

Un tique plan (`tipo: plan`) agrupa fases como tiques hijos. Sustituye la sección
"Contratos públicos" por:

```markdown
## Fases

- [ ] [TQ-NNN](TQ-NNN-<slug>.md) — {título de la fase}
- [ ] …

## Siguiente paso

{Una frase: cuál es la próxima fase a ejecutar.}
```

Reglas del corte en fases:

- **Rebanadas verticales**: cada fase entrega algo navegable, ejecutable o verificable de punta
  a punta (UI + lógica + pruebas felices); nunca una capa técnica aislada.
- Cada fase se commitea sin romper el build y con la verificación en verde.
- Una responsabilidad por fase; sin mezclar dependencias con el primer uso que las necesita.
- Cada hijo lleva `parte_de: TQ-…` del plan, y el plan marca su casilla al cerrar cada fase.
