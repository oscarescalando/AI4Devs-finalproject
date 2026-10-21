# EP-25 — Reconstrucción de las 14 pantallas del panel como componentes Livewire

> **Fecha:** 2026-10-19 · **Estado:** backlog vigente · Documento de especificación de TDChronos 2.0

**Objetivo:** paridad funcional de toda la capa visual del panel — CRUD operativo, dos pantallas operativas de tiempo real y dos pantallas de proyección — reconstruida como **componentes Livewire/Volt** sobre las fundaciones de [EP-24](EP-24-fundaciones-livewire.md). Las pantallas consumen el dominio to-be (ADR-015), el tiempo real de [EP-26](EP-26-tiempo-real-reverb.md) y cumplen los requisitos de calidad de la nueva capa visual: validación en servidor, estados de carga/error unificados, menú por permisos reales y branding configurable.

**Fuentes de trazabilidad.** Pantallas: el catálogo de pantallas del panel (referencia funcional). CU-xx: numeración heredada del corpus de origen, mantenida sin cambios (continuidad de IDs). RF-xx: SRS ([../02-srs.md](../02-srs.md)).

## Mapeo pantalla del panel → componente Livewire → CU → RF

| Pantalla del panel | Componente Livewire propuesto | CU | RF | Issue |
|---|---|---|---|---|
| Login | `Auth\Login` | CU-01 | RF-68 | [ISSUE-119](#issue-119) |
| Home / consola | `Home\Dashboard` | — | RF-02 | [ISSUE-120](#issue-120) |
| Personas/Cuentas | `Directory\PersonTable` + `Directory\AccountForm` | CU-09, CU-10 | RF-21, RF-22 | [ISSUE-121](#issue-121) |
| Organizaciones | `Directory\OrganizationTable` | CU-08 | RF-17, RF-18 | [ISSUE-122](#issue-122) |
| Equipos | `Directory\TeamTable` | CU-11 | RF-19, RF-20 | [ISSUE-123](#issue-123) |
| Eventos | `Events\EventTable` + ficha de evento | CU-03, CU-23 | RF-01…RF-04, RF-56 | [ISSUE-124](#issue-124) |
| Categorías | `Events\CategoryTable` | CU-04 | RF-07…RF-10 | [ISSUE-125](#issue-125) |
| Oleadas/Hits (modal de reprogramación) | `Events\WaveTable` + `Events\WaveReschedule` | CU-06, CU-07 | RF-13, RF-14 | [ISSUE-126](#issue-126) |
| Distancias | `Events\DistanceTable` | CU-05 | RF-11, RF-12 | [ISSUE-127](#issue-127) |
| Inscripciones — listado | `Registration\EntryTable` | CU-13, CU-15 | RF-35, RF-31 | [ISSUE-128](#issue-128) |
| Inscripciones — formulario | `Registration\EntryForm` | CU-13, CU-14 | RF-08, RF-26…RF-28 | [ISSUE-129](#issue-129) |
| Inscripciones — captura de llegada en vivo | `Registration\ArrivalCapture` | CU-19 | RF-37, RF-38 | [ISSUE-130](#issue-130) |
| Inscripciones — recálculo de categorías | `Registration\CategoryRecalculate` | CU-16 | RF-09 | [ISSUE-131](#issue-131) |
| Salidas — reloj y confirmaciones | `Timing\StartBoard` + reloj de servidor | CU-17 | RF-16, RF-42 | [ISSUE-132](#issue-132) |
| Salidas — marcar salida | `Timing\StartBoard` (acción) | CU-18 | RF-15 | [ISSUE-133](#issue-133) |
| Registros — bitácora | `Timing\MarkLog` | CU-20 | RF-45, RF-54, RF-77 | [ISSUE-134](#issue-134) |
| Registros — corrección (operaciones 1/2/3) | `Timing\MarkCorrectionModal` | CU-20 | RF-46…RF-50 | [ISSUE-135](#issue-135) |
| Registros — foto de llegada | `Timing\MarkCorrectionModal` (foto vía proxy) | CU-21 | RF-43 | [ISSUE-136](#issue-136) |
| Ranking — Clasificación | `PublicViews\RankingBoard` (modo clasificación) | CU-22 | RF-51, RF-52, RF-55 | [ISSUE-137](#issue-137) |
| Ranking — Tiempo de llegada | `PublicViews\RankingBoard` (modo llegadas) | CU-22 | RF-53 | [ISSUE-138](#issue-138) |
| Ranking — Puntos por Box + Liga de organizaciones | `PublicViews\PointsBoard` + `PublicViews\LeagueStandings` | CU-24, CU-25 | RF-57, RF-58, RF-61, RF-62 | [ISSUE-139](#issue-139) |
| (todas) Paridad funcional | suites E2E y checklist de paridad | — | — | [ISSUE-140](#issue-140) |

| Issue | Tipo | Prio | Est. | Título |
|---|---|---|---|---|
| [ISSUE-119](#issue-119) | feature | P1 | S | Pantalla Login |
| [ISSUE-120](#issue-120) | feature | P2 | M | Home como consola operativa |
| [ISSUE-121](#issue-121) | feature | P1 | L | Pantalla Personas/Cuentas |
| [ISSUE-122](#issue-122) | feature | P1 | M | Pantalla Organizaciones |
| [ISSUE-123](#issue-123) | feature | P1 | M | Pantalla Equipos |
| [ISSUE-124](#issue-124) | feature | P1 | M | Pantalla Eventos (hub de operación y cierre) |
| [ISSUE-125](#issue-125) | feature | P1 | S | Pantalla Categorías |
| [ISSUE-126](#issue-126) | feature | P1 | M | Pantalla Oleadas/Hits con reprogramación en bloque |
| [ISSUE-127](#issue-127) | feature | P1 | S | Pantalla Distancias |
| [ISSUE-128](#issue-128) | feature | P1 | L | Inscripciones: listado con filtros |
| [ISSUE-129](#issue-129) | feature | P1 | L | Inscripciones: formulario individual/equipo con categoría automática |
| [ISSUE-130](#issue-130) | feature | P0 | M | Inscripciones: captura de llegada en vivo |
| [ISSUE-131](#issue-131) | feature | P1 | S | Inscripciones: recalcular categorías con previsualización |
| [ISSUE-132](#issue-132) | feature | P0 | M | Salidas: tablero con reloj y confirmaciones |
| [ISSUE-133](#issue-133) | feature | P0 | M | Salidas: marcar la salida real de la oleada |
| [ISSUE-134](#issue-134) | feature | P0 | M | Registros: bitácora de marcas en vivo |
| [ISSUE-135](#issue-135) | feature | P0 | L | Registros: corrección con operaciones 1/2/3 |
| [ISSUE-136](#issue-136) | feature | P1 | S | Registros: foto de llegada vía proxy |
| [ISSUE-137](#issue-137) | feature | P0 | L | Ranking: clasificación en vivo (proyección) |
| [ISSUE-138](#issue-138) | feature | P1 | M | Ranking: modo últimas llegadas |
| [ISSUE-139](#issue-139) | feature | P2 | M | Ranking: puntos por box y liga de organizaciones |
| [ISSUE-140](#issue-140) | test | P1 | L | Paridad funcional de las 14 pantallas |

---

<a id="issue-119"></a>
### ISSUE-119 — Pantalla Login

| Tipo | Prioridad | Estimación | Labels | Depende de |
|---|---|---|---|---|
| feature | P1 | S | `feature`, `frontend`, `livewire`, `auth`, `rebuild` | ISSUE-108 |

**Descripción.** Pantalla de login como componente Volt sobre la autenticación por sesiones de ISSUE-108: documento + contraseña + recordar, cuadro centrado y limpio. Validación única del servidor con errores por campo; Livewire redirige a la ruta intentada sin recargar el shell; textos en `lang/`.

**Criterios de aceptación**
- [ ] Login y error 422 pintado bajo cada campo; foco al primer inválido.
- [ ] Redirección a la ruta intentada tras autenticar, sin recarga de página completa.
- [ ] Textos revisados en `lang/`; enlaces a recuperación de contraseña de ISSUE-044 heredada presentes.

---

<a id="issue-120"></a>
### ISSUE-120 — Home como consola operativa

| Tipo | Prioridad | Estimación | Labels | Depende de |
|---|---|---|---|---|
| feature | P2 | M | `feature`, `frontend`, `livewire`, `rebuild`, `ux` | ISSUE-107 |

**Descripción.** Consola operativa de aterrizaje: eventos por fase (borrador/inscripción/en curso/finalizado) con accesos directos a sus pantallas operativas, próximos eventos con fecha, enlaces rápidos a la estación ([EP-27](EP-27-estacion-offline-pwa.md)) y a la liga. Filtrada por permisos reales (ISSUE-107).

**Criterios de aceptación**
- [ ] El aterrizaje muestra al menos: eventos del mes por fase con enlace directo a Inscripciones/Salidas/Registros/Clasificación.
- [ ] Ningún widget muestra datos sin el permiso correspondiente (prueba con dos roles).
- [ ] Rendimiento: primera pintura del panel < 500 ms con datos de demo (ISSUE-040 heredada).

---

<a id="issue-121"></a>
### ISSUE-121 — Pantalla Personas/Cuentas

| Tipo | Prioridad | Estimación | Labels | Depende de |
|---|---|---|---|---|
| feature | P1 | L | `feature`, `frontend`, `livewire`, `identity`, `rebuild` | ISSUE-110, ISSUE-041, ISSUE-043 |

**Descripción.** Pantalla de personas y cuentas (paridad: listado con búsqueda/autocomplete, formulario completo, foto, rol, estado) sobre el modelo to-be **persona ≠ cuenta** (ADR-015, ISSUE-041 heredada). Dos pestañas: **Personas** (PII enmascarada según rol, RF-21) y **Cuentas** (rol, estado, invitación, RF-22). Requisitos clave: la edición nunca destruye la contraseña existente; el campo de confirmación de contraseña es operativo; la edad se calcula con la fecha de nacimiento y la fecha del evento (nunca el año en curso); la política de borrado vive en el servidor.

**Criterios de aceptación**
- [ ] Paridad de campos del formulario (nombre, apellido, género, documento con máscara, nacimiento, foto, contacto, organización) con validación servidor única.
- [ ] Cuentas: alta por invitación (ISSUE-044 heredada, sin contraseñas por defecto — ISSUE-028); nadie asigna rol superior al propio (prueba); selector de rol solo con el permiso correspondiente.
- [ ] Borrado de persona solo sin inscripciones y sin cuenta activa; confirmación y motivo (política en servidor, no lógica de cliente).
- [ ] Búsqueda con autocompletar mínima para inscribir (RF-23) que **no expone** documento completo ni fecha de nacimiento a roles sin permiso (complementa ISSUE-004 heredada).
- [ ] Listado con paginación/orden/filtros del kit (ISSUE-110); sin edición silenciosa de estado.

---

<a id="issue-122"></a>
### ISSUE-122 — Pantalla Organizaciones

| Tipo | Prioridad | Estimación | Labels | Depende de |
|---|---|---|---|---|
| feature | P1 | M | `feature`, `frontend`, `livewire`, `rebuild` | ISSUE-110, ISSUE-056 |

**Descripción.** Pantalla de organizaciones (paridad: listado, formulario con logo, región y estado) sobre ISSUE-056 heredada. Requisitos: errores de validación visibles junto al campo correspondiente (región incluida), logo por subida multipart con límites coherentes con el resto de pantallas (kit ISSUE-110), y borrado bloqueado con vínculos, resuelto en policy.

**Criterios de aceptación**
- [ ] CRUD completo con validación servidor; error de región visible junto al campo.
- [ ] Logo por subida multipart con límites coherentes con el resto de pantallas (ISSUE-110).
- [ ] Borrado bloqueado con inscripciones o miembros, con mensaje explicativo desde la policy (prueba).

---

<a id="issue-123"></a>
### ISSUE-123 — Pantalla Equipos

| Tipo | Prioridad | Estimación | Labels | Depende de |
|---|---|---|---|---|
| feature | P1 | M | `feature`, `frontend`, `livewire`, `rebuild` | ISSUE-110, ISSUE-057 |

**Descripción.** Pantalla de equipos (paridad: listado, formulario con organización, miembros múltiples con autocompletar y logo) sobre ISSUE-057 heredada. En to-be el equipo existe como directorio y su alineación es por evento (RF-20). La integridad de la relación miembro-equipo vive en la base de datos (clave única), no en la interfaz.

**Criterios de aceptación**
- [ ] Miembros con autocompletar de personas (no de cuentas); miembro duplicado rechazado por BD (prueba de integridad).
- [ ] Borrado bloqueado con inscripciones activas (policy; prueba).
- [ ] Alineación por evento editable al inscribir (coherente con ISSUE-129).

---

<a id="issue-124"></a>
### ISSUE-124 — Pantalla Eventos (hub de operación y cierre)

| Tipo | Prioridad | Estimación | Labels | Depende de |
|---|---|---|---|---|
| feature | P1 | M | `feature`, `frontend`, `livewire`, `eventos`, `rebuild` | ISSUE-110, ISSUE-048, ISSUE-074 |

**Descripción.** Pantalla de eventos (paridad: listado con badges de condición, formulario, acciones por fila hacia Categorías/Oleadas/Distancias/Inscripciones/Clasificación) sobre la máquina de estados de ISSUE-048 heredada (`draft → registration → live → finished → closed`). El cierre del evento (CU-23) es una **acción con confirmación** que dispara el cálculo oficial de resultados en job (ISSUE-074 heredada, RF-56), no un recálculo acoplado a la request.

**Criterios de aceptación**
- [ ] Transiciones de fase validadas server-side; botón de cierre solo en la fase que corresponde y con confirmación explícita (auditoría, ISSUE-045 heredada).
- [ ] Acciones por fila presentes siempre que exista el permiso; las dependientes de configuración muestran aviso accionable.
- [ ] Cierre: job en cola con resultado visible al terminar; re-cierre/reapertura solo con rol organizador y auditada (RF-04).
- [ ] Badges de fase y fecha obligatoria (RF-01).

---

<a id="issue-125"></a>
### ISSUE-125 — Pantalla Categorías

| Tipo | Prioridad | Estimación | Labels | Depende de |
|---|---|---|---|---|
| feature | P1 | S | `feature`, `frontend`, `livewire`, `categorias`, `rebuild` | ISSUE-110, ISSUE-050 |

**Descripción.** Pantalla de categorías (paridad: listado sin paginar del evento, formulario con modalidad y rango de edad, edición bloqueada en evento finalizado) sobre ISSUE-050 heredada. Añade la validación de rangos: `min_age ≤ max_age` y sin solape dentro de la misma modalidad/distancia (RF-07).

**Criterios de aceptación**
- [ ] Formulario rechaza `min > max` y rangos que solapen dentro de la misma modalidad/distancia (pruebas).
- [ ] Edición/creación bloqueadas en evento finalizado (policy de fase).
- [ ] Borrado bloqueado con inscripciones; recuento visible.

---

<a id="issue-126"></a>
### ISSUE-126 — Pantalla Oleadas/Hits con reprogramación en bloque

| Tipo | Prioridad | Estimación | Labels | Depende de |
|---|---|---|---|---|
| feature | P1 | M | `feature`, `frontend`, `livewire`, `salidas`, `rebuild` | ISSUE-110, ISSUE-051 |

**Descripción.** Pantalla de oleadas (paridad: listado "HIT nn HH:MM:SS", fila resaltada si la salida real difiere de la programada, formulario con hora programada y hora marcada de solo lectura) sobre las oleadas de ISSUE-051 heredada. Incluye el **modal de reprogramación en bloque**: hora inicial + intervalo regeneran todas las oleadas activas, y el backend emite **un** evento agregado `waves.rescheduled` (ISSUE-088 heredada). La hora programada se precarga del evento sin condiciones de carrera en el arranque de la pantalla.

**Criterios de aceptación**
- [ ] Reprogramar 20 oleadas → 1 solo evento y 1 refresco en las pantallas suscritas (criterio de ISSUE-088 heredada).
- [ ] La hora programada se precarga del evento sin carrera async (prueba de componente).
- [ ] Hora marcada visible y de solo lectura; resaltado de filas con salida real ≠ programada.
- [ ] Intervalo validado en servidor (0–999 minutos) y confirmación del modal.

---

<a id="issue-127"></a>
### ISSUE-127 — Pantalla Distancias

| Tipo | Prioridad | Estimación | Labels | Depende de |
|---|---|---|---|---|
| feature | P1 | S | `feature`, `frontend`, `livewire`, `rebuild` | ISSUE-110, ISSUE-049 |

**Descripción.** Pantalla de distancias (paridad: listado simple `K-45`, formulario numérico) sobre las distancias numéricas de ISSUE-049 heredada (`decimal(6,2)`, RF-11). Textos propios de la pantalla, revisados ortográficamente.

**Criterios de aceptación**
- [ ] Distancia `decimal(6,2)` con validación servidor; etiqueta derivada K-n correcta.
- [ ] Borrado bloqueado con inscripciones; textos propios de la pantalla (revisión ortográfica).
- [ ] Precios/cupos por distancia (RF-12) visibles si el evento los define (fase SaaS posterior).

---

<a id="issue-128"></a>
### ISSUE-128 — Inscripciones: listado con filtros

| Tipo | Prioridad | Estimación | Labels | Depende de |
|---|---|---|---|---|
| feature | P1 | L | `feature`, `frontend`, `livewire`, `inscripciones`, `rebuild` | ISSUE-110, ISSUE-060, ISSUE-066 |

**Descripción.** Listado de inscripciones — la vista operativa más completa: los **10 filtros** de paridad (persona, género, equipo, categoría, oleada, distancia, dorsal, organización, estado, orden) con autocompletes del kit, columnas de paridad (competidor/equipo, organización, dorsal, categoría, oleada, km, llegada, penalización/descuento, tiempo, estado) y coloreado de filas (verde con llegada, rojo DSQ). Ordenación por lista blanca en servidor (alineada con ISSUE-012 heredada). El listado se actualiza en vivo por los eventos de dominio del evento (EP-26).

**Criterios de aceptación**
- [ ] Los 10 filtros funcionan combinados server-side; orden solo por columnas permitidas (prueba).
- [ ] Paginación server-side conservando filtros; estado en query string compartible.
- [ ] Una llegada nueva actualiza el listado < 2 s sin recarga manual (integración EP-26).
- [ ] Exportación de inscritos (ISSUE-066 heredada) accesible desde el listado.

---

<a id="issue-129"></a>
### ISSUE-129 — Inscripciones: formulario individual/equipo con categoría automática

| Tipo | Prioridad | Estimación | Labels | Depende de |
|---|---|---|---|---|
| feature | P1 | L | `feature`, `frontend`, `livewire`, `inscripciones`, `business-rule`, `rebuild` | ISSUE-128, ISSUE-060, ISSUE-062 |

**Descripción.** Formulario de inscripción (paridad: panel informativo del participante, organización precargada, dorsal, categoría automática deshabilitada, oleada, distancia, chip, observación; edición añade estado DSQ). Categoría automática por **edad de competencia a la fecha del evento** (RF-08; alineado con ISSUE-008 heredada) y para equipos por categoría de equipo seleccionable (RF-10). Autocompletes que excluyen ya inscritos en el evento. Dorsal único garantizado en BD (`UNIQUE (event_id, bib)`, ISSUE-062 heredada). Con llegada registrada el borrado se bloquea, y los ajustes son **acumulables** con motivo (RF-32, ISSUE-064 heredada).

**Criterios de aceptación**
- [ ] Categoría sugerida por edad a la fecha del evento; si ningún rango cubre, se informa y no se inscribe en silencio (RF-08).
- [ ] Solo aparecen personas/equipos sin inscripción en el evento (prueba); una inscripción por persona y evento (RF-26, CHECK en BD).
- [ ] Dorsal duplicado → error 422 claro (integridad BD probada); oleada y distancia siempre del mismo evento (RF-26).
- [ ] Penalización y bonificación acumulables con motivo y autor (RF-32); estado DSQ conserva la marca y excluye del ranking (RF-33).
- [ ] Organización editable tras elegir participante; textos revisados en `lang/`.

---

<a id="issue-130"></a>
### ISSUE-130 — Inscripciones: captura de llegada en vivo

| Tipo | Prioridad | Estimación | Labels | Depende de |
|---|---|---|---|---|
| feature | P0 | M | `feature`, `frontend`, `livewire`, `cronometraje`, `tiempo-real`, `rebuild` | ISSUE-128, ISSUE-067, ISSUE-144 |

**Descripción.** Pantalla de captura de llegada en vivo del panel (RF-37): campo dorsal + observación + Enter. Toda pulsación crea una marca en `timing_marks` vía el servicio de asignación de ISSUE-068 heredada; nunca se escribe `end_at` directo en `registrations`. Feedback de paridad: alerta roja "número no encontrado, inválido o ya registrado" que se oculta al teclear, y resultado sonoro/visual del estado (asignada/huérfana/duplicada). El broadcast es afterCommit en cola: si Reverb está caído la marca igualmente responde 200 (criterio central de EP-26). Esta pantalla es la versión conectada; la estación offline ([EP-27](EP-27-estacion-offline-pwa.md)) la reemplaza en la meta los días de carrera.

**Criterios de aceptación**
- [ ] Cada Enter crea una marca persistida con auditoría; respuesta < 300 ms p95 en LAN (alineado con ISSUE-067).
- [ ] Dorsal inexistente/vacío/repetido → marca registrada como huérfana o duplicada con feedback correcto (pruebas por estado).
- [ ] Con Reverb detenido la captura responde 200 y la fila aparece al reconectar (prueba de caos EP-26/ISSUE-145).
- [ ] El campo queda limpio y enfocado tras cada captura; marca huérfana sin teclear "0" (RF-38).

---

<a id="issue-131"></a>
### ISSUE-131 — Inscripciones: recalcular categorías con previsualización

| Tipo | Prioridad | Estimación | Labels | Depende de |
|---|---|---|---|---|
| feature | P1 | S | `feature`, `frontend`, `livewire`, `categorias`, `inscripciones`, `rebuild` | ISSUE-129, ISSUE-065 |

**Descripción.** Recálculo de categorías por edad con **previsualización (dry-run)**: lista de cambios e incidencias, confirmación, ejecución transaccional e informe final (RF-09, ISSUE-065 heredada; alineado con ISSUE-008 heredada). Las personas sin categoría aplicable se reportan; nunca abortan el proceso.

**Criterios de aceptación**
- [ ] Dry-run lista altas/cambios/sin-categoría antes de aplicar; nada se escribe sin confirmar (prueba).
- [ ] Ejecución transaccional: o aplica todo o nada; las personas sin categoría se reportan, no abortan el proceso (prueba con una persona sin categoría aplicable).
- [ ] Al aplicar, emite un evento agregado (ISSUE-088) y las pantallas suscritas se refrescan una vez.

---

<a id="issue-132"></a>
### ISSUE-132 — Salidas: tablero con reloj y confirmaciones

| Tipo | Prioridad | Estimación | Labels | Depende de |
|---|---|---|---|---|
| feature | P0 | M | `feature`, `frontend`, `livewire`, `salidas`, `tiempo-real`, `rebuild` | ISSUE-110, ISSUE-052, ISSUE-143 |

**Descripción.** Tablero de salidas (paridad: selector de oleadas activas con inscritos, widgets programada/total/confirmados/finalizados, listado con confirmar/desmarcar) sobre el control de salida de ISSUE-052 heredada. Dos requisitos clave: (1) los contadores Confirmados/Finalizados se calculan **sobre el total de la oleada**, no sobre la página cargada (RF-16); (2) el reloj se **resincroniza periódicamente** con el servidor (RF-42), integrando el reloj de ISSUE-143 (EP-26). Confirmar/desmarcar con errores visibles como toast y autorización server-side en toda acción.

**Criterios de aceptación**
- [ ] Confirmados/Finalizados reflejan el total de la oleada, no la página (prueba con > 100 inscripciones paginadas).
- [ ] Reloj con deriva < 1 s tras 1 h (resincronización automática; RF-42).
- [ ] Confirmar/desmarcar: errores visibles como toast, acción reversible, autorización server-side (prueba con rol sin permiso).
- [ ] Confirmaciones de otros operadores se reflejan < 2 s (canal del evento, EP-26).

---

<a id="issue-133"></a>
### ISSUE-133 — Salidas: marcar la salida real de la oleada

| Tipo | Prioridad | Estimación | Labels | Depende de |
|---|---|---|---|---|
| feature | P0 | M | `feature`, `frontend`, `livewire`, `salidas`, `tiempo-real`, `business-rule`, `rebuild` | ISSUE-132 |

**Descripción.** Acción "Marcar salida" con confirmación mostrando la hora de sistema: visible solo para la primera oleada sin marcar. Guarda **ambas** horas (programada y real) sin sobrescribir la programada (RF-15), permite deshacer con auditoría, y emite `wave.started` afterCommit para que Salidas/Ranking reaccionen. Solo existen canales por evento con autorización; sin canales globales.

**Criterios de aceptación**
- [ ] Guarda programada ≠ real como columnas distintas; la vista Oleadas resalta la diferencia.
- [ ] Deshacer salida con confirmación y registro de auditoría (ISSUE-045); solo el rol con permiso la ejecuta (prueba).
- [ ] `wave.started` afterCommit: rollback → nada emitido (prueba integrada con ISSUE-145).
- [ ] El widget pasa a "Iniciado" en las pantallas abiertas < 2 s tras la salida (EP-26).

---

<a id="issue-134"></a>
### ISSUE-134 — Registros: bitácora de marcas en vivo

| Tipo | Prioridad | Estimación | Labels | Depende de |
|---|---|---|---|---|
| feature | P0 | M | `feature`, `frontend`, `livewire`, `cronometraje`, `correcciones`, `tiempo-real`, `rebuild` | ISSUE-135, ISSUE-071, ISSUE-142 |

**Descripción.** Bitácora del juez (paridad: listado paginado, filas verdes con marca válida/azules con operación, paginación server-side) sobre ISSUE-071 heredada, con los estados to-be: asignada, huérfana, duplicada, anulada, corregida (RF-45). Se actualiza en vivo por el **canal privado del evento** (ISSUE-144). Las correcciones de otros jueces son visibles < 2 s, y los listeners se liberan al salir (RF-77).

**Criterios de aceptación**
- [ ] Contadores por estado (asignadas/huérfanas/duplicadas/anuladas) coherentes con el listado (ISSUE-071).
- [ ] Nueva marca visible < 2 s; corrección de otro juez visible < 2 s (prueba con dos sesiones).
- [ ] Atajo de teclado para asignar dorsal a una huérfana (ISSUE-071); paginación por cursor en eventos con miles de marcas.
- [ ] Sin listeners duplicados ni fugas tras 50 navegaciones (criterio heredado de ISSUE-087, verificado en ISSUE-142).
- [ ] Ningún dato llega por un canal no autorizado (prueba de suscripción anónima rechazada).

---

<a id="issue-135"></a>
### ISSUE-135 — Registros: corrección con operaciones 1/2/3

| Tipo | Prioridad | Estimación | Labels | Depende de |
|---|---|---|---|---|
| feature | P0 | L | `feature`, `frontend`, `livewire`, `correcciones`, `audit`, `business-rule`, `rebuild` | ISSUE-134, ISSUE-070 |

**Descripción.** Modal de corrección con paridad de las **tres operaciones** (RF-46…RF-50): (1) modificar tiempo con botones ±1 s (en to-be también ±0,1 s), (2) asignar a nuevo número con validación de 1-3 dígitos y distinto del original, (3) limpiar/anular la marca; motivo siempre; confirmación previa cuando hay operación. Sobre el modelo to-be de ISSUE-070 heredada: la marca original **nunca** se modifica — la corrección es append-only (`timing_mark_corrections`) y recalcula resultados de origen y destino en transacción (alineado con ISSUE-011 heredada).

**Criterios de aceptación**
- [ ] Las tres operaciones funcionan; la marca original queda intacta y el estado efectivo se deriva de la última corrección (prueba).
- [ ] Reasignar a un dorsal que ya tiene llegada exige confirmación explícita y deja la anterior como duplicada (RF-47, ISSUE-070).
- [ ] Cada corrección recalcula resultados de las inscripciones afectadas en la misma transacción (prueba de integridad).
- [ ] Historial de correcciones visible por marca y por inscripción (RF-50, ISSUE-045).
- [ ] Una marca huérfana se puede corregir/asignar sin error (prueba).

---

<a id="issue-136"></a>
### ISSUE-136 — Registros: foto de llegada vía proxy

| Tipo | Prioridad | Estimación | Labels | Depende de |
|---|---|---|---|---|
| feature | P1 | S | `feature`, `frontend`, `livewire`, `cronometraje`, `security`, `rebuild` | ISSUE-135, ISSUE-073 |

**Descripción.** Cara Livewire de ISSUE-073 heredada (que se mantiene como backend): el modal de corrección muestra la miniatura de la foto de llegada y lightbox con la imagen completa, **vía proxy autenticado HTTPS** del backend (`GET /timing-marks/{m}/photo`); ninguna petición del navegador a hosts externos sin cifrar. Si el host de fotos no responde, la corrección sigue operable sin evidencia fotográfica con un aviso visible.

**Criterios de aceptación**
- [ ] Ninguna petición del navegador a hosts externos por `http://` (prueba e2e).
- [ ] Miniatura y lightbox operativos con URL firmada de corta vida; 404 con mensaje si la foto no existe.
- [ ] Fallo del host de fotos: aviso visible y el resto del modal sigue funcional.

---

<a id="issue-137"></a>
### ISSUE-137 — Ranking: clasificación en vivo (proyección)

| Tipo | Prioridad | Estimación | Labels | Depende de |
|---|---|---|---|---|
| feature | P0 | L | `feature`, `frontend`, `livewire`, `clasificacion`, `tiempo-real`, `rebuild` | ISSUE-074, ISSUE-075, ISSUE-142 |

**Descripción.** Modo Clasificación de la pantalla de Ranking como pantalla de proyección (paridad: fondo oscuro, cabecera fija con branding, reloj, filtros de categoría/género/oleada/distancia/dorsal, columna de diferencia con el primero, filas rojas DSQ, resaltado de llegadas ≤ 30 s, modo fullscreen con estilos aislados — RF-55). Cambios estructurales: (1) las posiciones (general/categoría/género) se calculan **en el backend** con el servicio de resultados de ISSUE-074 heredada (RF-51); (2) cero `eval` o construcción dinámica de código en cliente (chequeo estático en CI); (3) el endpoint de ranking **sin PII** — solo nombre, organización, categoría y tiempos (RF-52; complementa ISSUE-004 heredada); (4) actualización por delta del canal del evento < 2 s (RF-54), sin recargar la colección completa.

**Criterios de aceptación**
- [ ] Posiciones, diferencia y tiempo neto provienen del backend y coinciden con el cálculo oficial al cerrar (prueba de conciliación).
- [ ] Ningún `eval` ni construcción de código dinámico en cliente (chequeo estático en CI).
- [ ] La vista es accesible sin sesión con solo datos sin PII (prueba que verifica ausencia de `document`/`birthdate`/`email`/`phone` en el payload).
- [ ] Una llegada se refleja < 2 s aplicando delta, sin recargar la colección completa (criterio RF-54; medición con 1 000 inscritos).
- [ ] Proyección 4 h sin degradación de memoria ni listeners huérfanos (prueba prolongada; ISSUE-146).

---

<a id="issue-138"></a>
### ISSUE-138 — Ranking: modo últimas llegadas

| Tipo | Prioridad | Estimación | Labels | Depende de |
|---|---|---|---|---|
| feature | P1 | M | `feature`, `frontend`, `livewire`, `clasificacion`, `tiempo-real`, `rebuild` | ISSUE-137 |

**Descripción.** Modo "Tiempo de llegada" de la pantalla de Ranking (paridad: tarjetas grandes ordenadas por llegada descendente con nombre, dorsal, categoría, oleada, llegada y tiempo; tarjeta resaltada con animación para llegadas ≤ 30 s), sobre el modo de últimas llegadas de ISSUE-053/RF-53 y el canal delta de EP-26. Muestra solo llegadas válidas (estado activo).

**Criterios de aceptación**
- [ ] Las tarjetas se alimentan del stream de llegadas (delta), sin re-descargar el ranking por llegada.
- [ ] Resaltado de llegadas recientes configurable por evento (RF-53); animación no bloqueante.
- [ ] 60 llegadas en 10 s: sin pérdida de ninguna en pantalla (criterio heredado de ISSUE-088, medido en ISSUE-146).

---

<a id="issue-139"></a>
### ISSUE-139 — Ranking: puntos por box y liga de organizaciones

| Tipo | Prioridad | Estimación | Labels | Depende de |
|---|---|---|---|---|
| feature | P2 | M | `feature`, `frontend`, `livewire`, `liga`, `rebuild` | ISSUE-137, ISSUE-081, ISSUE-082 |

**Descripción.** Reconstrucción de los dos tableros de puntos: puntos por box de un evento (CU-24) y la clasificación de la liga de organizaciones (CU-25), sobre el dominio de campeonatos de ISSUE-081/082 heredadas. Liga, temporada y branding configurables por configuración/BD (RF-61) con el branding del layout (ISSUE-107); sin ids ni logos fijos en código. Estilos compartidos con Ranking sin duplicación.

**Criterios de aceptación**
- [ ] Liga por campeonato configurado (sin ids fijos en código; prueba con una liga nueva sin tocar componentes).
- [ ] Columnas correctas: corredores, puntos, equipos, puntos de equipo y **total** (prueba).
- [ ] Branding y temporada desde configuración; estilos compartidos con Ranking sin duplicación.

---

<a id="issue-140"></a>
### ISSUE-140 — Paridad funcional de las 14 pantallas

| Tipo | Prioridad | Estimación | Labels | Depende de |
|---|---|---|---|---|
| test | P1 | L | `test`, `frontend`, `livewire`, `rebuild`, `tests` | ISSUE-119…ISSUE-139 |

**Descripción.** Consolida la verificación de paridad: cada issue de esta épica cierra su checklist individual; esta issue ejecuta y firma el checklist global contra el catálogo de pantallas del panel, recorridos por rol y verificación de los requisitos de calidad de la capa visual (validación en servidor, estados de carga/error, menú por permisos reales, branding configurable, textos revisados, rutas sin trampas de query string).

**Criterios de aceptación**
- [ ] Checklist de paridad por pantalla revisado y firmado (cada campo/acción de la pantalla: presente, mejorado o decididamente omitido con justificación).
- [ ] Recorridos E2E por rol (root/admin/operador/solo lectura) en CI para: configuración de evento, inscripción individual y por equipo, salida, captura, corrección y ranking.
- [ ] Checklist de calidad de la capa visual verificado pantalla a pantalla: errores de validación visibles por campo, estados de carga/error presentes, accesos sin trampas de query string, textos correctos.
- [ ] La UI es 100 % TALL: sin frameworks de SPA en `package.json` ni código de front heredado en el repositorio (chequeo en CI).
