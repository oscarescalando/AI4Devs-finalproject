# ADR-013 — Tiempo real con Laravel Reverb + canales privados por evento; broadcast afterCommit y nunca bloqueante

**Estado:** Aceptada — decisión del product owner 2026-10-19.

## Contexto

- El tiempo real del nuevo sistema debe cumplir tres condiciones: **ninguna caída del servidor WebSocket puede impedir
  registrar una marca** (la marca es sagrada, O1); la **clasificación en vivo debe llegar a la pantalla en < 2 s**
  (O4) aunque haya 200+ espectadores; y **todo canal debe estar autorizado**, con payloads sin PII en lo público y sin
  tormentas de eventos redundantes.
- **Documentos previos:** la propuesta base del corpus de origen ya proponía Reverb (ADR-003) y cola real para los
  broadcasts; el contrato de canales to-be define `event.{ulid}.public`, `private-event.{ulid}.ops`,
  `private-account.{ulid}` con payloads `{v, seq, ...}`.
- **Hecho nuevo:** el product owner pide fijar las **reglas de emisión** (persistencia antes de emitir, cola,
  autorización) como decisión propia, no como detalle de implementación.

## Decision drivers

| # | Driver | Peso |
|---|---|---|
| 1 | **La marca es sagrada**: ningún fallo de WebSocket puede impedir registrar una llegada | Alto |
| 2 | O4: clasificación en vivo < 2 s desde la marca hasta la pantalla con ≥ 200 espectadores | Alto |
| 3 | Cero canales sin autorización y sin tormentas de eventos (agregación en lugar de un evento por fila) | Alto |
| 4 | Operación en sede **sin Internet** (O5): el servidor WS debe correr en el nodo local | Alto |
| 5 | Reverb es oficial del framework, compatible con el protocolo Pusher y escala con Redis | Medio |

## Opciones consideradas

| Opción | Pros | Contras |
|---|---|---|
| **Reverb + reglas de emisión** (elegida) | Oficial del framework (ADR-010), corre en el nodo de sede sin Internet, escala con Redis; protocolo Pusher (Echo no cambia); permite forzar persistencia-before-emit y cola | Un servicio más que operar en la sede (runbook y supervisión; la cola absorbe caídas) |
| Servidor WebSocket comunitario de terceros | Madurez histórica del paquete | Archivado upstream, sin soporte; su diseño empuja a emitir dentro del request síncrono |
| Pusher / Ably (SaaS) | Cero operación WS | Exigen Internet en la sede: contradice O5 |
| Soketi | Protocolo Pusher autogestionado | Proceso Node aparte con mantenimiento más lento que Reverb; descartada |
| Sin WebSocket (polling) | Simplicidad | Incumple O4 con 200+ espectadores y re-baja la experiencia de la estación |

## Decisión

Tiempo real con **Laravel Reverb**, con estas **reglas obligatorias**:

1. **Persistir antes de emitir.** La marca (y cualquier cambio de dominio) se confirma en la base de datos **antes** de
   que exista cualquier emisión; el feedback local del operador no depende del WebSocket.
2. **Emisión en cola real y afterCommit.** Los eventos de dominio se despachan con `ShouldDispatchAfterCommit` (nunca
   dentro de la transacción) e implementan `ShouldQueue`: los emite un worker de Horizon con reintentos. El request HTTP
   nunca bloquea por el WS.
3. **Canales por evento, con autorización, privados por defecto** (contrato de canales del corpus de origen; el canal
   privado se nombra aquí `event.{ulid}.ops`, equivalente al `private-event.{ulid}.ops` del contrato):
   - `event.{ulid}.ops` — **privado**, operadores del evento (policy); payload operativo con IDs y detalle
     (`mark.created`, `mark.corrected`, `entry.changed`, `checkin.changed`, `sync.status`).
   - `event.{ulid}.public` — **solo lectura** para pantallas públicas/kiosco; payload sin PII
     (`finish.recorded`, `result.changed`, `wave.started`, `results.published`).
   - `private-account.{ulid}` — notificaciones personales (`export.ready`, `import.finished`).
   - **No existe ningún canal público genérico**: la emisión operativa va solo por canales privados con policy.
4. **Payloads con `v` (versión de contrato) y `seq` (secuencia por evento)** para que una pantalla que se reconecte
   pida resincronización (deltas o instantánea) en lugar de recargar la colección completa.
5. **Agregación:** la reprogramación en bloque emite un resumen (no N eventos) y las correcciones agrupadas emiten el
   estado resultante; el mapeo evento de dominio → canal vive en el módulo Realtime.

## Consecuencias

| Tipo | Consecuencia |
|---|---|
| Positiva | Una caída de Reverb no pierde marcas ni bloquea la estación: solo retrasa pantallas hasta la resincronización |
| Positiva | Las pantallas públicas solo leen de un canal de solo lectura y sin PII; los canales operativos quedan autorizados por policy y los resultados pre-publicación quedan protegidos |
| Positiva | Latencia acotada y medible (marca → pantalla) para el O4; la cola permite absorber ráfagas de llegadas |
| Positiva | Sin tormentas de re-broadcast: la agregación emite el estado resultante, no un evento por fila |
| Negativa (mitigada) | La cola añade unos milisegundos/segundos entre persistencia y emisión: aceptable dentro del O4 (< 2 s), se mide en el ensayo M3 |
| Negativa (mitigada) | Dos piezas stateful nuevas (Reverb, Redis de colas) en el nodo de sede: runbook de F2 y panel de salud (HU-45) |
| Neutral | Echo sigue siendo el cliente; con Livewire (ADR-011) la suscripción se centraliza en el layout, sin lógica de reconexión por página |

## ADRs y documentos afectados

| Relación | Referencia |
|---|---|
| Formaliza y amplía | **ADR-003** (Reverb; decisión previa del corpus de origen, vigente vía esta decisión) |
| Adapta | **ADR-S09** (anexo SaaS del corpus de origen): existía la restricción "sin canales públicos de resultados"; ADR-013 fija el canal público de **solo lectura y sin PII** para pantallas de meta. Prevalencia por la regla de precedencia de [README §4](README.md) |
| Matiza | **ADR-S16** (resultados solo autenticados): se mantiene la política configurable de resultados completos; las pantallas de meta reciben solo el feed público |
| Hereda | El contrato de canales y payloads del corpus de origen y ADR-003 (Reverb + Horizon) |
| Requiere | ADR-010 (Reverb nativo), ADR-011 (consumo desde Livewire) |
| Verificación | DoD "evento/broadcast" en [../05-dod.md §2](../05-dod.md) |

## Cumplimiento verificable

- Los eventos de dominio que se emiten implementan `ShouldQueue` + `ShouldDispatchAfterCommit`; prueba de arquitectura
  que falla si un evento con broadcast se despacha sin cola o dentro de la transacción.
- **Test de fallo del WS** (obligatorio): con Reverb detenido (o `BROADCAST_DRIVER=log`), registrar una marca vía el
  flujo real → la marca persiste, el job de broadcast queda en cola y se entrega al reconectar.
- `routes/channels.php` sin ningún `Channel()` público salvo `event.{ulid}.public`; test que verifica la autorización de
  `event.{ulid}.ops` por policy del evento (rol no autorizado → 403 de suscripción).
- Test de payload: los eventos del canal `.public` se serializan sin `identity_number`, `birthdate`, `email` ni
  documento (suite de O2) e incluyen `v` y `seq`.
- Test de resincronización: una pantalla que se reconecta pide deltas por `seq` y recupera el estado sin recargar todo
  (E2E Playwright, ADR-018).
- Prueba de carga k6: latencia marca→pantalla < 2 s p95 con 200 suscriptores y 60 marcas/min (M3).
