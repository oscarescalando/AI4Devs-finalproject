# ADR-015 — Modelo de datos to-be (arranque limpio)

**Estado:** Aceptada — decisión del product owner 2026-10-19.

## Contexto

- El nuevo sistema necesita un **modelo canónico del dominio** que garantice por diseño: persona ≠ cuenta, marcas
  inmutables con correcciones como eventos, resultados derivados y nunca editados a mano, integridad reforzada en la
  base de datos (FK/CHECK/unicidad), tiempos con resolución de milisegundos y privacidad por defecto.
- **Documentos previos:** la propuesta base del corpus de origen ya especificó ese modelo con detalle (12 principios de
  diseño, ERD to-be, restricciones de unicidad y CHECK, coherencia de evento, políticas de borrado, índices críticos y
  cálculo de tiempos). Este ADR lo declara **vigente** para el nuevo sistema.
- **Hecho nuevo:** el arranque es **limpio**: la base de datos se crea íntegramente con migraciones desde cero, sin
  datos heredados. Este ADR fija el modelo y deja constancia de que nada de él depende del stack de interfaz decidido
  en ADR-010/011.

## Decision drivers

| # | Driver | Peso |
|---|---|---|
| 1 | El modelo resuelve los requisitos de integridad, trazabilidad y privacidad con reglas concretas y verificables | Alto |
| 2 | El cambio decidido de stack de interfaz (ADR-010/011) es de capa de presentación: no toca dominio ni datos | Alto |
| 3 | Re-abrir el modelo retrasaría F1/F2 sin beneficio (los RF no cambian) | Alto |
| 4 | Arranque limpio: sin deuda de datos que limpiar ni conciliar; el esquema nace de migraciones versionadas | Alto |

## Opciones consideradas

| Opción | Pros | Contras |
|---|---|---|
| **Adoptar el modelo to-be especificado en el corpus de origen, sin cambios** (elegida) | Cero retrabajo; trazabilidad completa con los RF/RNF; especificación ya verificada | Ninguna identificada: el modelo no depende del stack de UI |
| Revisar el modelo para "adaptarlo a Livewire" | — | Basado en un malentendido: Livewire consume Actions sobre el mismo dominio; ningún ajuste de datos procede de la capa de vista |
| Rediseñar el modelo desde el modelo SaaS del corpus de origen desde el día 1 | Evitaría una conversión posterior | Contradice ADR-016 (SaaS como fase posterior); alarga el camino crítico del núcleo |

## Decisión

El sistema nuevo implementa **tal cual** el modelo to-be especificado en el corpus de origen, con la base de datos
creada **íntegramente por migraciones desde cero**:

| Se adopta | Contenido clave |
|---|---|
| 12 principios de diseño (persona ≠ cuenta; marcas inmutables append-only; resultados derivados; ajustes como filas; oleadas con hora programada y real; liga configurable; integridad en BD; PK `bigint` + identificador público ULID; tiempos en `datetime(3)`/ms `bigint`; `utf8mb4_0900_ai_ci`; enums explícitos; nombres consistentes) | Cada principio enuncia el requisito de integridad o privacidad que garantiza |
| ERD, restricciones (unicidad, CHECK, coherencia de evento, políticas de borrado), índices críticos y cálculo de tiempos | Incluye `timing_mark_corrections`, `entry_adjustments` |

Sobre identificadores: en el núcleo el identificador público es **ULID** (ADR-009, `ulid char(26)` del modelo); el
`client_mark_id` de la estación es **UUIDv7** generado por el dispositivo (ADR-014). En la fase SaaS (ADR-016),
**ADR-S08** sustituye ULID por UUIDv7 como identidad pública y de sincronización: ese cambio de esquema se aplica solo
en la fase SaaS.

Cualquier desviación del modelo que se detecte durante la construcción **no se decide en código**: se registra como
ADR nuevo o enmienda en [README.md](README.md).

## Enmienda (2026-10-20) — Sin migración de datos heredados

La **migración de datos históricos queda FUERA DE ALCANCE** de este arranque limpio:

- La base de datos se crea **íntegramente con migraciones desde cero**; `migrate:fresh --seed` deja el sistema
  operativo completo.
- Los datos de prueba son **sintéticos** (seeders reproducibles, sin datos reales).
- Los usuarios son **nuevos**: admin bootstrap + seeders.
- **No existe conexión ni migración de datos de versiones anteriores** del sistema.
- Las reglas de limpieza **C1-C17** y las verificaciones de conciliación **V1-V9** del corpus de origen **no se aplican
  en este proyecto** (pertenecen al plan de migración del sistema anterior, fuera de alcance).

## Consecuencias

| Tipo | Consecuencia |
|---|---|
| Positiva | El trabajo de F1 puede empezar de inmediato: el esquema está especificado y es verificable por tests desde la primera migración |
| Positiva | La integridad de marcas y resultados queda garantizada por restricciones de BD (no por disciplina de aplicación) |
| Positiva | Arranque sin deuda de datos: no hay limpieza, conciliación ni ventanas de corte que planificar |
| Negativa (mitigada) | La conversión SaaS introducirá `tenant_id`/UUIDv7 después (ADR-016): mitigada por no introducir supuestos mono-tenant duros en las Actions |
| Neutral | El modelo queda congelado salvo ADR nuevo o enmienda registrada; las extensiones futuras se documentan en este log |

## ADRs y documentos afectados

| Relación | Referencia |
|---|---|
| **Adopta sin cambios** | El modelo de datos to-be del corpus de origen (12 principios, ERD, restricciones, índices, cálculo de tiempos) |
| **Hereda sin cambios** | **ADR-004** (marcas inmutables + correcciones como eventos), **ADR-008** (ms `bigint`), **ADR-009** (ULID en el núcleo), **ADR-007** (BD propia) — decisiones previas del corpus de origen |
| Subordinado a | ADR-S08 (UUIDv7) — solo en la fase SaaS (ADR-016) |
| Indiferente a | ADR-010, ADR-011, ADR-012, ADR-013 (ninguno toca el esquema) |
| Verificación | DoD de esquema/modelo de datos en [../05-dod.md §2](../05-dod.md); criterios de salida de F1 en [../08-roadmap.md](../08-roadmap.md) |

## Cumplimiento verificable

- La base de datos **nace de migraciones versionadas**: `migrate:fresh --seed` deja el sistema operativo completo
  (esquema + datos sintéticos), **sin tablas ni datos heredados**.
- Las migraciones del nuevo repositorio se revisan una a una contra el diccionario del modelo (test de esquema: FKs,
  UNIQUE, CHECK, collation `utf8mb4_0900_ai_ci` presentes desde F1).
- Los seeders son reproducibles y generan solo datos sintéticos (test que verifica que no incluyen datos personales
  reales: `identity_number`, `birthdate`, `email`).
- Prueba de arquitectura: ningún modelo del dominio permite mutar `timing_marks` (sin métodos de actualización; solo
  inserción y correcciones como filas nuevas).
