# ADR-010 — Stack base del nuevo sistema: Laravel 13 + PHP 8.4+

**Estado:** Aceptada — decisión del product owner 2026-10-19.

## Contexto

- El sistema se construye **desde cero** (arranque limpio): la elección de versión se hace una sola vez, en el arranque,
  y conviene maximizar el ciclo de vida de soporte y de seguridad desde el primer día del proyecto.
- **Documentos previos:** la propuesta base del corpus de origen (ADR-001) y el anexo de plataforma SaaS asumieron
  **PHP 8.3+ / Laravel 12** cuando se redactaron. Era un supuesto de versión, no una decisión cerrada.
- **Hecho nuevo:** el product owner decide ir a la **versión nueva estable** del framework en el arranque de la
  reconstrucción (2026-10-19), que es Laravel 13 con PHP 8.4+.

## Decision drivers

| # | Driver | Peso |
|---|---|---|
| 1 | Ciclo de vida: maximizar meses de soporte activo y de seguridad desde el inicio del proyecto | Alto |
| 2 | Reverb y Horizon **nativos** de serie (sin paquetes de terceros para WebSocket y colas) | Alto |
| 3 | Sintaxis moderna disponible de serie (enums respaldados, clases readonly, `ShouldDispatchAfterCommit`), necesaria para el diseño de eventos y colas decidido | Alto |
| 4 | Continuidad: el equipo ya conoce Laravel; sin fricción de aprendizaje frente al diseño definido | Alto |
| 5 | Cero cambios de diseño: MySQL 8.4, Redis 7, Sanctum, spatie/laravel-permission siguen igual | Medio |

## Opciones consideradas

| Opción | Pros | Contras |
|---|---|---|
| **Laravel 13 + PHP 8.4+** (elegida) | Máxima ventana de soporte; Reverb/Horizon nativos; sin cambios de diseño; es la versión contra la que se libera el ecosistema nuevo | Material comunitario (recetas, paquetes) más escaso los primeros meses; versión PHP muy reciente exige validar extensiones e imagen Docker base en la primera semana de F1 |
| Laravel 12 + PHP 8.3 | Ecosistema maduro y documentación abundante | Menos meses de soporte dentro de la vida prevista del proyecto; desperdicia la ventana de decisión única del arranque limpio |
| Otro framework/lenguaje (Node/NestJS, Go) | — | Descartada: re-aprendizaje total sin ganancia para este volumen |

## Decisión

El nuevo sistema se construye con **Laravel 13 (última estable) y PHP 8.4 o superior**, con MySQL 8.4 LTS, Redis 7,
Horizon, Reverb, Sanctum y spatie/laravel-permission. Las recomendaciones de versión de los documentos del corpus de
origen ("PHP 8.3 / Laravel 12") quedan **actualizadas de lectura** por esta decisión; el resto de cada tabla de stack
no cambia.

## Consecuencias

| Tipo | Consecuencia |
|---|---|
| Positiva | Soporte y parches garantizados durante toda la vida prevista del proyecto |
| Positiva | Reverb, Horizon y el broadcasting `afterCommit` (necesario para ADR-013) son funcionalidad de framework, no paquetes de terceros |
| Positiva | Sin rediseño: los diagramas C4, módulos y contratos definidos para el nuevo sistema siguen válidos tal cual |
| Negativa (mitigada) | Menos material de terceros al principio; mitigación: recetas oficiales del framework y fijación de versiones exactas en la espiga de F1 ([../08-roadmap.md](../08-roadmap.md)) |
| Negativa (mitigada) | PHP 8.4 muy reciente: validar extensiones e imagen Docker base en la primera semana de F1 |
| Neutral | La versión puntual de PHP patch se fija en el `Dockerfile` de F1 y se revisa en cada iteración |

## ADRs y documentos afectados

| Relación | Referencia |
|---|---|
| Adapta | **ADR-001** (monolito modular: principio vigente, versión actualizada; decisión previa del corpus de origen) |
| Actualiza de lectura | La recomendación de versión ("PHP 8.3 / Laravel 12") de la propuesta base y del anexo SaaS del corpus de origen |
| Base para | ADR-011, ADR-012, ADR-013, ADR-014, ADR-016 (todo el resto de la serie asume este stack) |
| Hereda sin cambios | ADR-007 (MySQL 8.4, BD propia; decisión previa del corpus de origen) |

## Cumplimiento verificable

- `composer.json` del nuevo repositorio declara `laravel/framework` en su rama `^13` y `php: ^8.4`.
- CI ejecuta la suite sobre PHP 8.4 (imagen Docker única dev/CI/sede); `composer audit` en el pipeline.
- `config/reverb.php` y `config/horizon.php` presentes y usados (coherente con ADR-013 y el roadmap F1).
- Prueba de arquitectura que falla si se introduce un paquete de WebSocket o colas de terceros ajeno al stack decidido.
- Revisión trimestral documentada de versión de framework/PHP (acta en la demo correspondiente).
