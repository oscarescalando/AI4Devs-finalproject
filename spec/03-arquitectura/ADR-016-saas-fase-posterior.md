# ADR-016 — La plataforma SaaS multitenant se mantiene como fase posterior al núcleo

**Estado:** Aceptada — decisión del product owner 2026-10-19.

## Contexto

- El núcleo del nuevo sistema se reconstruye como **sistema mono-organizador** (supuesto 7 del PRD,
  [../01-prd.md](../01-prd.md)): cronometraje, inscripciones, resultados y liga, sin tenancy, sin planes y sin pagos.
- **Documentos previos:** el anexo **Plataforma SaaS** del corpus de origen define la plataforma B2B multi-tenant
  completa: documento rector (bloques B1-B5, entidades canónicas, tres entradas de acceso) con sus **resoluciones de
  consistencia R-a…R-i**, los ADR **S01…S16**, el modelo de datos SaaS y un **roadmap propio** que, en su versión de
  2026, integraba la multi-tenantía desde la F1 del núcleo.
- **Hecho nuevo:** el product owner confirma que el núcleo (cronometraje, resultados) se reconstruye **primero** como
  sistema mono-organizador y la plataforma SaaS llega **después**, conservando el orden interno del roadmap SaaS.

## Decision drivers

| # | Driver | Peso |
|---|---|---|
| 1 | El valor más urgente es un núcleo confiable (O1-O7); los objetivos del núcleo no dependen de tenancy ni de pagos | Alto |
| 2 | Equipo de 2 personas: el alcance crítico debe ser mínimo ([../08-roadmap.md](../08-roadmap.md), riesgo R5) | Alto |
| 3 | Los bloques SaaS (pagos, portal, móvil, visor) tienen preguntas de negocio abiertas propias (monedas, pasarelas, planes) que no bloquean el núcleo | Medio |
| 4 | El anexo del corpus de origen ya está completo y decidido: no se re-discute, solo se ordena en el tiempo | Medio |

## Opciones consideradas

| Opción | Pros | Contras |
|---|---|---|
| **Núcleo primero; SaaS después, en el orden del anexo** (elegida) | Camino crítico corto hasta el MVP; riesgos de cronometraje aislados de los de tenancy/pagos; cada fase tiene un hito verificable | Parte del trabajo se toca dos veces (`tenant_id`, UUIDv7 llegan en F4): mitigado por el mapeo del modelo SaaS del corpus de origen y por no cerrar el dominio a ámbitos |
| Construir multi-tenant desde F1 | Sin remapeo posterior | Añade tenancy, identidad global, planes y derecho de uso al camino crítico del núcleo; retrasa el MVP con un equipo de 2 |
| No hacer SaaS | — | Contradice el objetivo acordado del anexo (apertura a organizadores externos) |

## Decisión

1. **F1-F3 (núcleo):** se construye **sin tenancy, sin planes, sin pagos, sin portal y sin app móvil**. Ámbito por
   evento con spatie teams, modelo ADR-015. El organizador promotor es el único usuario del núcleo.
2. **F4 (v2/SaaS):** se ejecuta el anexo del corpus de origen **en su propio orden**, re-basado tras el MVP del núcleo:
   contratos y multi-tenant (EP-15/EP-16) → panel/portal/planes (EP-17/EP-19) → pagos (EP-18) → app móvil y sync
   (EP-20/EP-21) → visor (EP-22) → carga, pentest y apertura GA (EP-23). Las fases del anexo conservan sus hitos,
   criterios y MVP, con las fechas re-basadas según [../08-roadmap.md](../08-roadmap.md).
3. **El anexo se hereda íntegro:** documento rector, R-a…R-i y ADR-S01…S16 siguen mandando para la fase SaaS, con las
   únicas excepciones ya registradas en la serie nueva (ADR-010: versión de Laravel; ADR-011: toda la UI web es
   Livewire; ADR-012: papel de la API; ADR-013: canal público de solo lectura que adapta S09/S16). El portal público
   SaaS será **Livewire** (ADR-011).
4. **Preparación sin adelantarse:** el núcleo evita supuestos mono-tenant duros (ámbito por evento en las Actions,
   sin URLs ni lógica que asuman "el único organizador"), de modo que la conversión futura use el mapeo ya documentado
   en el modelo SaaS del corpus de origen (incluida la provisión del primer tenant).

## Consecuencias

| Tipo | Consecuencia |
|---|---|
| Positiva | El MVP del núcleo llega antes; los riesgos técnicos mayores (estación offline, motor de resultados) no comparten camino crítico con tenancy y pagos |
| Positiva | Las preguntas abiertas de negocio del anexo (monedas, pasarelas, planes, visor) se pueden resolver en paralelo sin bloquear F1-F3 |
| Positiva | El núcleo opera como sistema mono-organizador desde su MVP, y la apertura a terceros (GA) llega con el pentest y la carga ya hechos según el anexo |
| Negativa (mitigada) | Coste de conversión posterior (tenant_id, UUIDv7, membresías): mitigado por el mapeo del modelo SaaS y por la disciplina de ámbito por evento desde F1 |
| Negativa (mitigada) | Los pagos de inscripciones y el portal quedan fuera del núcleo hasta F4: se gestionan por el proceso del organizador, sin regresión de requisitos |
| Neutral | Los ADR-S heredados se reevalúan al arrancar F4 (una lectura de vigencia, no una re-escritura) |

## ADRs y documentos afectados

| Relación | Referencia |
|---|---|
| **Hereda íntegro** | El anexo SaaS del corpus de origen: documento rector + resoluciones **R-a…R-i** + **ADR-S01…S16** + modelo/protocolo/planes/pagos/portal/app/visor |
| **Hereda el orden** | El roadmap SaaS del corpus de origen (fases propias, MVP, equipo); única diferencia: **arranca tras el MVP del núcleo**, no desde la F1 global |
| Cambia (plazo) | La multi-tenantía ya no se construye durante la F1 del núcleo |
| Excluye de F1-F3 | Tenancy, planes, pagos, portal, app móvil, visor — todo entra en [../08-roadmap.md F4](../08-roadmap.md) |
| Se apoya en | ADR-015 (modelo base estable que luego se mapea), ADR-011/ADR-012 (UI y API que la plataforma reutiliza) |
| Deja en suspenso | ADR-S08 (UUIDv7 global) hasta F4; ADR-S07 (guard platform) hasta F4 |

## Cumplimiento verificable

- En el repositorio del núcleo **no existen** `tenants`, `tenant_members`, `plans`, `subscriptions`, `payments`, `orders`
  ni rutas de consola/portal (test de esquema y de rutas); su aparición solo es legítima en F4 con su propio conjunto de
  cambios aprobado.
- Las Actions del dominio reciben el ámbito por evento/modelo y no consultan "el organizador actual" como global
  (revisión de arquitectura en cada módulo).
- El backlog de F4 es el del anexo (issues SAAS-100…599) sin duplicación con el del núcleo (EP-01…EP-13).
- La apertura GA no se declara sin los criterios MG del roadmap SaaS del corpus de origen (SAAS-507 superada,
  SAAS-508 sin hallazgos altos, términos y DPA publicados, copias por tenant probadas).
