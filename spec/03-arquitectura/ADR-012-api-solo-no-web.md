# ADR-012 — La API REST /api/v1 existe SOLO para consumidores no-web

**Estado:** Aceptada — decisión del product owner 2026-10-19.

## Contexto

- El nuevo sistema debe exponer un **contrato externo explícito y versionado** para los consumidores que no son la UI
  web: estación de cronometraje como dispositivo, app móvil, visor e integraciones de terceros. Ese contrato debe
  reutilizar las mismas policies que la UI, sin una segunda lógica de permisos.
- **Documentos previos:** los documentos del corpus de origen ya definieron el contrato to-be: REST JSON bajo
  `/api/v1`, API pública (sin sesión ni PII, cacheable) y API privada (sesión + policy), OpenAPI 3.1 (Scramble),
  errores `application/problem+json`, paginación/orden/filtros con listas blancas, identificadores ULID (herencia de
  ADR-009); el anexo de plataforma añade `/api/v1/sync` para app y visor.
- **Hecho nuevo:** con la UI en Livewire (ADR-011), la interfaz web **no necesita** la API. El papel de `/api/v1` queda
  reducido y definido: servir únicamente a los consumidores que no son la UI web.

## Decision drivers

| # | Driver | Peso |
|---|---|---|
| 1 | La UI (Livewire → Actions, ADR-011) hace redundante una API para el panel | Alto |
| 2 | Existen consumidores reales que sí necesitan API: app móvil, visor, estación como dispositivo, integraciones | Alto |
| 3 | Contrato explícito y versionado (OpenAPI 3.1) para clientes externos con ciclos de vida propios (ADR-S04/S11) | Alto |
| 4 | Superficie mínima de ataque: menos endpoints, todos con propósito | Medio |
| 5 | Continuidad del contrato ya definido en el corpus de origen (no re-escribirlo) | Medio |

## Opciones consideradas

| Opción | Pros | Contras |
|---|---|---|
| **API solo para no-web; UI consume cero** (elegida) | Una sola capa de permisos (policies); sin duplicidad de rutas web+API para lo mismo; contrato externo acotado y versionable | La API pública cacheable deja de alimentar al kiosco (que pasa a ser Livewire); se conserva como contrato para consumidores externos |
| API como única fuente, UI la consume | Cliente y servidor desacoplados; permite equipos separados | Doble capa para la misma lógica con equipo de 2; estado y permisos duplicados, también en cliente |
| Sin API en el núcleo; posponerla a la fase SaaS | Menos trabajo inicial | Bloquea la estación offline (ADR-014), que necesita el endpoint idempotente de marcas desde F2, y descarta el contrato ya definido |

## Decisión

`/api/v1` se construye según el contrato to-be definido en el corpus de origen, pero sus **únicos consumidores** son:

| Consumidor | Endpoints que usa | Autenticación |
|---|---|---|
| **Estación de cronometraje** (PWA de ADR-014, actúa como dispositivo) | `POST /events/{e}/timing-marks` (lote idempotente por `client_mark_id`), `GET /time`, bitácora | Token Sanctum con ability `device:marks` y ámbito de evento |
| **App móvil** (fase SaaS, ADR-016) | Contrato completo del panel operativo + `/api/v1/sync` | Token Sanctum por dispositivo (ADR-S06, protocolo del corpus de origen) |
| **Visor** (fase SaaS, ADR-016) | `/api/v1/sync/pull` con scope `event:{uuid}:vision` (R-b) y push de detecciones | Token de dispositivo |
| **Integraciones** (servidores de fotos, lectores de chip, pantallas embebibles de terceros) | API pública (sin sesión, sin PII, cacheable) y endpoints privados acordados | Token con abilities |

**La UI web consume cero API**: todo componente Livewire llama a los Actions/Queries del dominio (mismo proceso,
mismas policies, misma transacción). La autorización de `/api/v1` reutiliza las mismas policies: no hay una segunda
lógica de permisos. El contrato se documenta con **OpenAPI 3.1** (Scramble, publicado en `/docs/api`), errores
**`application/problem+json`**, y identificadores públicos opacos: **ULID en el núcleo** (ADR-009, heredado por
ADR-015) y **UUIDv7 para entidades sincronizables en la fase SaaS** (ADR-S08, que tiene precedencia para esa fase;
`client_mark_id` ya es UUIDv7 desde ADR-014).

## Consecuencias

| Tipo | Consecuencia |
|---|---|
| Positiva | Una sola lógica de autorización (policies compartidas por Livewire y la API); cero riesgo de divergencia panel/API |
| Positiva | Superficie reducida: no se duplica en `/api/v1` el CRUD que ya existe como Actions del panel |
| Positiva | El contrato externo (estación, móvil, visor, integraciones) queda con OpenAPI publicado y tests de contrato |
| Negativa (mitigada) | El kiosco/TV ya no consume la API pública cacheable: la caché de borde, si se necesita, se aplica a la respuesta de la página Livewire pública |
| Negativa (mitigada) | Dos estilos de acceso (HTTP API vs Actions) exigen disciplina: prueba de arquitectura que separa los dos mundos y prohíbe mezclarlos |
| Neutral | ADR-S04 (polyrepo + cliente generado, corpus de origen) se reinterpreta: el cliente OpenAPI queda para móvil/visor/integraciones, no para la web |

## ADRs y documentos afectados

| Relación | Referencia |
|---|---|
| Hereda (diseño) | El contrato to-be del corpus de origen: API pública, API privada, contrato WS y protocolo de sincronización del anexo SaaS |
| Habilitado por | ADR-011 (la UI deja de ser consumidora) |
| Precisa | ADR-010 (stack), ADR-013 (tiempo real por canales, no por polling de API) |
| Matiza | ADR-009 (ULID vigente en el núcleo) frente a ADR-S08 (UUIDv7 en fase SaaS) |
| Verificación | DoD "API endpoint" en [../05-dod.md §2](../05-dod.md) |

## Cumplimiento verificable

- `routes/api.php` (y archivos de rutas del módulo API) solo contiene endpoints del contrato documentado; prueba de
  arquitectura que falla si un endpoint de `/api/v1` no está en la spec OpenAPI generada.
- Prueba de arquitectura que falla si cualquier componente Livewire usa cliente HTTP (`Http::`, `curl`, `fetch`) hacia
  `/api/v1` o internamente.
- Suite de **tests de contrato** por endpoint (esquema de respuesta, códigos de estado, `problem+json` en errores,
  idempotencia donde aplique) contra la spec OpenAPI 3.1 publicada en `/docs/api`.
- Tests de autorización por endpoint con las mismas policies del panel (matriz rol × endpoint, heredera de ISSUE-046).
- `/api/v1/public/*` con suite automática que verifica ausencia de PII (objetivo O2).
- Filtros/orden con lista blanca obligatoria (prueba que inyecta columna inválida y espera `problem+json`, no 500).
