# EP-26 — Tiempo real con Reverb en las pantallas

> **Fecha:** 2026-10-19 · **Estado:** backlog vigente · Documento de especificación de TDChronos 2.0

**Objetivo:** que el tiempo real sea seguro por construcción: broadcast afterCommit en cola real, canales por evento con autorización, resincronización con versión y secuencia (`v`/`seq`), pantallas Livewire reactivas y pruebas de caos con criterio **0 marcas perdidas o duplicadas**. Un fallo del WebSocket no afecta a la operación ni a los datos: la marca nunca falla por un fallo de broadcast.

**Relación con el backlog heredado:** el backend de tiempo real ya está especificado en la épica heredada EP-11 y **se mantiene**: ISSUE-084 (Reverb + Redis + workers), ISSUE-085 (eventos de dominio afterCommit con delta y `seq`), ISSUE-086 (canales por evento), ISSUE-088 (agregación de broadcasts) quedan intactas; SAAS-111 y SAAS-124 (canales por tenant/evento y `outbox_events` afterCommit) también. Lo que esta épica **re-expresa** es el alcance heredado de ISSUE-087 (cliente de tiempo real, ligado a un front SPA) y añade la verificación de extremo a extremo con las pantallas Livewire de [EP-25](EP-25-pantallas-livewire.md).

| Issue | Tipo | Prio | Est. | Título |
|---|---|---|---|---|
| [ISSUE-141](#issue-141) | docs | P0 | S | Contrato de eventos de dominio por pantalla |
| [ISSUE-142](#issue-142) | feature | P0 | M | Puente Livewire ↔ Echo (re-expresa el alcance heredado de ISSUE-087) |
| [ISSUE-143](#issue-143) | feature | P1 | M | Resincronización `v`/`seq`, estado de conexión y reloj de servidor |
| [ISSUE-144](#issue-144) | feature | P0 | M | Canales por evento y autorización en las pantallas |
| [ISSUE-145](#issue-145) | test | P0 | L | Pruebas de caos del tiempo real |
| [ISSUE-146](#issue-146) | feature | P1 | M | Ráfagas y estabilidad de pantallas de proyección |

---

<a id="issue-141"></a>
### ISSUE-141 — Contrato de eventos de dominio por pantalla

| Tipo | Prioridad | Estimación | Labels | Depende de |
|---|---|---|---|---|
| docs | P0 | S | `docs`, `tiempo-real`, `rebuild`, `architecture` | ISSUE-085 |

**Descripción.** Matriz que consolida el catálogo de eventos de dominio de ISSUE-085 heredada contra las pantallas de EP-25: qué evento emite cada flujo, por qué canal (público/privado), qué pantallas lo consumen y qué hacen al recibirlo. Incluye versión del payload (`v`) y secuencia monotónica por evento (`seq`), sin PII en el canal público.

| Evento | Canal | Consumidores (pantalla → acción) |
|---|---|---|
| `finish.recorded` | público + ops | Captura de llegada y bitácora → fila nueva; Ranking → delta de posición; Entradas → refresco dirigido |
| `mark.corrected` | ops | Bitácora → estado de la marca; Ranking → recalcula |
| `wave.started` | público + ops | Salidas → widget "Iniciado"; Ranking → horas de salida |
| `waves.rescheduled` | ops | Oleadas → recarga; Ranking → horas |
| `entry.changed` (inscripción/ajuste/DSQ) | ops | Entradas, Salidas, Ranking → refresco dirigido |
| `checkin.changed` | ops | Salidas → contadores |
| `results.published` | público | Ranking → modo resultados oficiales |
| `bulk.changed {scope}` | ops | Todas → un único resync (ISSUE-088) |

**Criterios de aceptación**
- [ ] La matriz completa vive en este repositorio y es la referencia de ISSUE-142/144; todo evento del catálogo ISSUE-085 tiene canal, consumidores y payload documentado.
- [ ] Cada payload documentado declara `v` y `seq` y enumera sus campos sin PII en el canal público (revisión cruzada con ISSUE-004: ni documento ni fecha de nacimiento jamás).
- [ ] Cambio de contrato exige subir `v` (regla de versión escrita en el documento).

---

<a id="issue-142"></a>
### ISSUE-142 — Puente Livewire ↔ Echo (re-expresa el alcance heredado de ISSUE-087)

| Tipo | Prioridad | Estimación | Labels | Depende de |
|---|---|---|---|---|
| feature | P0 | M | `feature`, `frontend`, `livewire`, `tiempo-real`, `rebuild` | ISSUE-141, ISSUE-106 |

**Descripción.** Re-expresión del alcance heredado de ISSUE-087 para TALL: **el estado vive en el componente servidor**. Se implementa un módulo JS mínimo (Laravel Echo + Alpine) que: se suscribe a los canales del evento activo al montar la pantalla, reenvía cada mensaje al componente Livewire por evento de navegador (y este aplica el delta o refresca de forma dirigida), se desuscribe al desmontar, y expone el estado de conexión. Sin store global de cliente ni fugas de listeners (RF-77).

**Criterios de aceptación**
- [ ] Suscripción/desuscripción simétrica: sin listeners duplicados tras 50 navegaciones entre pantallas (criterio heredado de ISSUE-087, prueba automatizada).
- [ ] Una llegada aplica delta o refresco dirigido — nunca `wire:refresh` completo de la página (medición en Ranking con 1 000 inscritos).
- [ ] El módulo no acumula suscripciones al mismo canal desde dos pantallas del mismo evento (prueba).
- [ ] Documentación de uso para las pantallas de EP-25 (una sola forma de consumir, nada ad-hoc por pantalla).

---

<a id="issue-143"></a>
### ISSUE-143 — Resincronización `v`/`seq`, estado de conexión y reloj de servidor

| Tipo | Prioridad | Estimación | Labels | Depende de |
|---|---|---|---|---|
| feature | P1 | M | `feature`, `frontend`, `livewire`, `tiempo-real`, `rebuild` | ISSUE-142, ISSUE-085 |

**Descripción.** Re-expresión de la resincronización del alcance heredado de ISSUE-087 y del reloj de servidor en TALL: el cliente descarta mensajes con `seq` antiguo, detecta huecos y pide resync (snapshot por evento desde el backend, coherente con SAAS-111); todas las pantallas operativas muestran un badge de conexión (en vivo / reconectando / sin conexión) y activan polling de respaldo (15 s) solo cuando la conexión está caída. El reloj de servidor se resincroniza periódicamente (RF-42) y alimenta Salidas/Ranking.

**Criterios de aceptación**
- [ ] Hueco de `seq` → resync automático < 2 s (prueba inyectando un hueco).
- [ ] Reconexión tras caída → resync completo sin duplicados visibles (prueba).
- [ ] Badge de conexión en las 4 pantallas operativas (Salidas, Registros, Entradas, Ranking); polling solo en estado desconectado (prueba que verifica que en vivo no hay polling).
- [ ] Reloj con deriva < 1 s tras 1 h de pantalla abierta (prueba prolongada).

---

<a id="issue-144"></a>
### ISSUE-144 — Canales por evento y autorización en las pantallas

| Tipo | Prioridad | Estimación | Labels | Depende de |
|---|---|---|---|---|
| feature | P0 | M | `feature`, `livewire`, `tiempo-real`, `security`, `rebuild` | ISSUE-086, ISSUE-142 |

**Descripción.** Consumo disciplinado de los canales de ISSUE-086 heredada (que se mantiene intacta): las pantallas operativas escuchan `private-event.{ulid}.ops`; Ranking y pantallas públicas escuchan `event.{ulid}.public` (sin PII). Solo existen canales por evento con autorización; ningún resultado en vivo se emite antes de su publicación formal por un canal sin autorización.

**Criterios de aceptación**
- [ ] Suscripción anónima a los canales ops rechazada (prueba de autorización de canales).
- [ ] Un cronometrador de otro evento no puede suscribirse al canal ops del evento (criterio heredado de ISSUE-086, probado de extremo a extremo desde la pantalla).
- [ ] El payload público verificablemente carece de documento, fecha de nacimiento, email y teléfono (prueba automatizada sobre los eventos emitidos — complementa ISSUE-046 heredada).
- [ ] El registro de canales declara solo canales por evento con autorización; no existe ningún canal global sin autorización (revisión en CI).

---

<a id="issue-145"></a>
### ISSUE-145 — Pruebas de caos del tiempo real

| Tipo | Prioridad | Estimación | Labels | Depende de |
|---|---|---|---|---|
| test | P0 | L | `test`, `tiempo-real`, `rebuild`, `observability` | ISSUE-084, ISSUE-085, ISSUE-142, ISSUE-143 |

**Descripción.** Suite de caos que blinda el invariante central: **un fallo del WebSocket jamás afecta a la persistencia ni a la respuesta del operador** — 0 marcas perdidas o duplicadas, y la marca nunca falla por un fallo de broadcast. Escenarios:

1. Reverb detenido → captura de marca responde 200, marca persistida con auditoría, pantalla pasa a "sin conexión".
2. Reverb se recupera → clientes resincronizan (resync por `seq`) sin duplicados ni huecos.
3. Cola `broadcasts` atascada → la operación sigue; alerta por atraso de cola (umbral de ISSUE-084 heredada).
4. Rollback de transacción → no se emite nada (criterio heredado de ISSUE-085).
5. Drenado de cola tras reconexión masiva → todas las pantallas convergen al mismo estado (comparación de snapshot).

**Criterios de aceptación**
- [ ] Los 5 escenarios automatizados en CI (con Reverb y Redis reales); cada uno con aserción explícita de respuesta, persistencia y auditoría.
- [ ] El escenario 1 ejecuta el recorrido de captura completo de ISSUE-130 y de la estación EP-27.
- [ ] Métricas de atraso de la cola de broadcasts exportadas (ISSUE-100 heredada) con alerta probada.
- [ ] Informe de caos firmado en la DoD de la fase F1 (ver [../08-roadmap.md](../08-roadmap.md)).

---

<a id="issue-146"></a>
### ISSUE-146 — Ráfagas y estabilidad de pantallas de proyección

| Tipo | Prioridad | Estimación | Labels | Depende de |
|---|---|---|---|---|
| feature | P1 | M | `feature`, `frontend`, `livewire`, `tiempo-real`, `performance`, `rebuild` | ISSUE-142, ISSUE-088 |

**Descripción.** Asegura que las pantallas sobreviven al pico real del día de carrera: consumo del evento agregado `bulk.changed` de ISSUE-088 con un único resync por operación masiva, agrupación de ráfagas de llegadas, y estabilidad de las pantallas de proyección de larga duración (sin degradación de memoria, sin estilos fugados al resto de la app — RF-55 — y sin CSS duplicado entre pantallas).

**Criterios de aceptación**
- [ ] 60 llegadas en 10 s → ≤ 40 mensajes en el canal público sin perder ninguna llegada (criterio heredado de ISSUE-088, medido).
- [ ] Reprogramar 20 oleadas → 1 mensaje y 1 refresco por cliente (criterio heredado).
- [ ] Ranking proyectada 4 h: memoria estable, fps de animación constantes, 0 listeners huérfanos (prueba prolongada automatizada).
- [ ] Los estilos de proyección están aislados (sin contaminar el panel — RF-55 verificada).
