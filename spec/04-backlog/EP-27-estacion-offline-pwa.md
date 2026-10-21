# EP-27 — Estación de cronometraje offline-first (PWA)

> **Fecha:** 2026-10-19 · **Estado:** backlog vigente · Documento de especificación de TDChronos 2.0

**Objetivo:** que la meta de los días de carrera funcione aunque caiga la red: captura local en IndexedDB con idempotencia (`client_mark_id`), sincronización en segundo plano sin duplicados, operación solo con teclado y reloj con desfase corregido (ADR-014). La estación es una PWA instalable servida por el monolito; su captura **no** pasa por Livewire porque debe funcionar sin servidor: consume `/api/v1` con token de dispositivo como cliente de integración de primera parte (ADR-012).

**Relación con el backlog heredado:** esta épica **amplía el alcance heredado de ISSUE-069** (estación de meta PWA offline-first); sus criterios se conservan y formalizan aquí. Se mantienen intactas las issues de backend que la estación consume: ISSUE-067 (bitácora `timing_marks` append-only + API idempotente por lotes), ISSUE-068 (motor de asignación marca → inscripción), ISSUE-070 (correcciones auditadas) e ISSUE-073 (fotos vía proxy). ISSUE-071 (bitácora en vivo) se construye como pantalla Livewire en EP-25 (ISSUE-134). El flujo offline conectado de la meta en el panel es ISSUE-130; esta épica lo reemplaza operativamente en la meta.

| Issue | Tipo | Prio | Est. | Título |
|---|---|---|---|---|
| [ISSUE-147](#issue-147) | chore | P1 | M | Shell PWA de la estación (amplía ISSUE-069) |
| [ISSUE-148](#issue-148) | feature | P0 | L | Captura offline en IndexedDB |
| [ISSUE-149](#issue-149) | feature | P0 | M | Sincronización idempotente por lotes |
| [ISSUE-150](#issue-150) | feature | P0 | M | Reloj de la estación con desfase corregido |
| [ISSUE-151](#issue-151) | feature | P1 | M | Doble estación, diagnóstico y operación de respaldo |
| [ISSUE-152](#issue-152) | test | P1 | L | Pruebas e2e y piloto de la estación |

---

<a id="issue-147"></a>
### ISSUE-147 — Shell PWA de la estación (amplía ISSUE-069)

| Tipo | Prioridad | Estimación | Labels | Depende de |
|---|---|---|---|---|
| chore | P1 | M | `chore`, `frontend`, `pwa`, `offline`, `cronometraje`, `rebuild` | ISSUE-114, ISSUE-067 |

**Descripción.** Pantalla dedicada de la estación de meta, instalable como PWA. Layout de estación (tipografía grande, alto contraste, operable 100 % con teclado), selección persistente de evento/estación, pantalla "sin conexión" con estado, y arranque directo a la última estación usada. Sobre la PWA base de ISSUE-114 (EP-24). Amplía el alcance heredado de ISSUE-069.

**Criterios de aceptación**
- [ ] Instalable en Chrome/Edge y arrancable sin red (abre el shell y la última estación configurada).
- [ ] La identidad de la estación (evento, punto de cronometraje, dispositivo) persiste entre sesiones.
- [ ] Operable sin ratón desde el primer segundo; el foco queda en el campo de dorsal tras cualquier acción.
- [ ] Emparejamiento con token de dispositivo (abilities `device:marks`, ISSUE-067 heredada) mostrado una sola vez en la configuración.

---

<a id="issue-148"></a>
### ISSUE-148 — Captura offline en IndexedDB

| Tipo | Prioridad | Estimación | Labels | Depende de |
|---|---|---|---|---|
| feature | P0 | L | `feature`, `frontend`, `offline`, `cronometraje`, `ux`, `rebuild` | ISSUE-147 |

**Descripción.** Toda pulsación crea una marca (RF-37) guardada **primero en IndexedDB** con `{client_mark_id (UUID), event_id, station_id, bib (nullable), device_time, offset, observation, source}`; Enter sin dorsal = marca huérfana (RF-38, sin códigos centinela; amplía ISSUE-006 heredada). Feedback inmediato sonoro/visual: asignada / duplicada / desconocida / pendiente de sincronizar — el feedback "desconocida" usa el **roster local en caché** del evento (descargado al abrir con conexión). Criterios del alcance heredado de ISSUE-069 formalizados aquí: ≥ 500 marcas con red caída; captura local < 50 ms; lista de las últimas 20 marcas de la estación con observación editable; recarga del navegador sin perder pendientes; aviso al cerrar con pendientes.

**Criterios de aceptación**
- [ ] Con la red desconectada se registran ≥ 500 marcas; ninguna se pierde al recargar o cerrar el navegador (aviso previo si hay pendientes).
- [ ] Captura local (pulsación → confirmación visual) < 50 ms medida en hardware de sede modesto.
- [ ] Enter sin dorsal crea marca huérfana, sin códigos centinela (RF-38; amplía ISSUE-006 heredada).
- [ ] Roster local en caché con versión; feedback "dorsal desconocido" consistente con la asignación posterior del servidor (el servidor manda: si difiere, la marca queda con el estado del servidor y la UI lo muestra).
- [ ] Últimas 20 marcas visibles con estado de sincronización por marca; observación editable hasta sincronizar.

---

<a id="issue-149"></a>
### ISSUE-149 — Sincronización idempotente por lotes

| Tipo | Prioridad | Estimación | Labels | Depende de |
|---|---|---|---|---|
| feature | P0 | M | `feature`, `offline`, `cronometraje`, `api`, `rebuild` | ISSUE-148, ISSUE-067 |

**Descripción.** Cliente de sincronización de la estación contra `POST /api/v1/events/{e}/timing-marks` (contrato de ISSUE-067 heredada, que se mantiene): lotes de hasta 100 marcas, idempotencia por `client_mark_id` (reenviar nunca duplica), resultado por marca (`assigned` / `orphan` / `duplicate`), reintento con backoff ante fallos, y sincronización en segundo plano con disparo manual forzado. Indicadores permanentes: conexión, pendientes, última sincronización.

**Criterios de aceptación**
- [ ] Reenvío del mismo `client_mark_id` responde con el mismo resultado sin duplicar (prueba de idempotencia de extremo a extremo).
- [ ] Red intermitente (caos: caída cada 30 s): la cola local se drena completa al volver, sin duplicados ni pérdidas (prueba automatizada).
- [ ] La app sigue capturando mientras sincroniza; ninguna sincronización bloquea el teclado.
- [ ] El resultado por marca actualiza la lista local (asignada/duplicada/huérfana) y el roster caché si cambia el estado.
- [ ] Token de dispositivo revocado → mensaje claro y cola local preservada (no se descarta nada por un 401/403).

---

<a id="issue-150"></a>
### ISSUE-150 — Reloj de la estación con desfase corregido

| Tipo | Prioridad | Estimación | Labels | Depende de |
|---|---|---|---|---|
| feature | P0 | M | `feature`, `offline`, `cronometraje`, `rebuild` | ISSUE-147, ISSUE-149 |

**Descripción.** El reloj de la estación se sincroniza con `GET /api/v1/time` (offset estilo NTP) cada 60 s (criterio del alcance heredado de ISSUE-069; RF-41/RF-42) y toda marca guarda `device_time + offset` como `captured_at` (datetime(3), ADR-015: precisión ms). Con la red caída se mantiene el último offset con aviso de antigüedad. Alerta si el desfase supera 1 s o cambia bruscamente entre mediciones (posible cambio de reloj del sistema).

**Criterios de aceptación**
- [ ] Desfase visible en la pantalla de estación en todo momento; alerta > 1 s o salto brusco (pruebas con reloj manipulado).
- [ ] Marca guardada con hora corregida verificable contra la hora del servidor (prueba de conciliación con ISSUE-067).
- [ ] Offline: captura sigue operando con el último offset y la marca queda etiquetada con la antigüedad de la medición.
- [ ] La corrección manual de hora del sistema operativo entre mediciones dispara la alerta (prueba).

---

<a id="issue-151"></a>
### ISSUE-151 — Doble estación, diagnóstico y operación de respaldo

| Tipo | Prioridad | Estimación | Labels | Depende de |
|---|---|---|---|---|
| feature | P1 | M | `feature`, `offline`, `cronometraje`, `rebuild` | ISSUE-149, ISSUE-068 |

**Descripción.** Criterio del alcance heredado de ISSUE-069 formalizado: **modo doble estación** — dos estaciones pueden marcar el mismo evento como respaldo mutuo; si ambas capturan el mismo dorsal, el motor de ISSUE-068 resuelve (primera marca gana por `captured_at`) y la otra queda `duplicate` visible en la bitácora (ISSUE-134). Además, pantalla de diagnóstico de la estación (versión, token, offset, pendientes, último lote) y procedimiento de evacuación de emergencia: exportar las marcas pendientes de la cola local a archivo y cargarlas desde otra estación si el navegador queda inservible.

**Criterios de aceptación**
- [ ] Dos estaciones marcan el mismo evento en paralelo: ninguna marca se pierde ni duplica; los duplicados quedan visibles con su estación de origen (prueba con dos navegadores).
- [ ] Pantalla de diagnóstico completa y exportable (soporte remoto).
- [ ] Evacuación: exportación de pendientes a archivo y carga en otra estación restaura la cola sin duplicar (prueba).
- [ ] Las marcas de respaldo de la segunda estación no alteran la llegada vigente (regla de ISSUE-068: primera gana).

---

<a id="issue-152"></a>
### ISSUE-152 — Pruebas e2e y piloto de la estación

| Tipo | Prioridad | Estimación | Labels | Depende de |
|---|---|---|---|---|
| test | P1 | L | `test`, `offline`, `cronometraje`, `rebuild` | ISSUE-148…ISSUE-151 |

**Descripción.** Verificación de extremo a extremo y piloto en condiciones reales: caos de red (cortes cada 30 s, cortes de 10 min), batería de 500 marcas offline, medición de latencia de captura, recarga y cierre con pendientes, evacuación de cola, y doble estación. Piloto en un evento real (análogo al alcance heredado de SAAS-337) con conciliación de marcas estación ↔ servidor.

**Criterios de aceptación**
- [ ] Suite de caos automatizada en CI: 0 marcas perdidas y 0 duplicadas en todos los escenarios.
- [ ] Piloto en evento real: conciliación de marcas estación vs. servidor al 100 % (toda marca de la estación tiene correspondencia en el servidor) y tiempos de captura < 50 ms en el hardware de sede.
- [ ] Checklist del día de carrera de la estación documentado (aporta al runbook de ISSUE-102 heredada).
- [ ] Informe del piloto con incidencias y decisiones (alimenta la DoD de la fase F1, [../08-roadmap.md](../08-roadmap.md)).
