# ADR-014 — Estación de cronometraje offline-first como PWA con Livewire + IndexedDB

**Estado:** Aceptada — decisión del product owner 2026-10-19.

## Contexto

- La estación de cronometraje es el requisito duro **O5** (cronometrar sin Internet en la sede) y el portador de
  **O1** (cero marcas perdidas o duplicadas): la captura debe ser **local-first** — cada marca se registra primero en
  el dispositivo y se sincroniza después por lotes idempotentes —, de modo que un corte de red no rompa el teclado ni
  pierda marcas.
- **Documentos previos:** la propuesta base del corpus de origen ya diseña la estación como PWA con captura local:
  `client_mark_id` UUIDv7, sincronización por lotes idempotentes, reloj con offset tipo NTP contra `GET /time`,
  estados captured → synced → assigned / orphan / duplicate, dos estaciones con `device_id` y ventana
  anti-duplicados. El anexo de plataforma prevé la app móvil para la fase SaaS (R-g/ADR-S12), con esta PWA como
  respaldo.
- **Hecho nuevo:** la UI general es TALL (ADR-011); la estación debe ser coherente con ese stack en el núcleo (F2), sin
  mantener un segundo front de framework JS solo para una pantalla. La regla de hora oficial se fija aquí directamente:
  **hora oficial = hora de captura en la estación, corregida por el offset del servidor**.

## Decision drivers

| # | Driver | Peso |
|---|---|---|
| 1 | **O5, requisito duro:** cronometrar sin Internet en la sede (evento simulado sin WAN en M3) | Alto |
| 2 | O1: cero marcas perdidas o duplicadas, incluso con cortes de red de 10 min | Alto |
| 3 | Una sola familia de stack de UI (Livewire/Alpine), sin un front paralelo de framework JS | Alto |
| 4 | Hora oficial correcta aunque la sincronización llegue minutos tarde | Alto |
| 5 | UX de captura: solo teclado, feedback inmediato por color/sonido, contador de pendientes (PRD UX 1) | Medio |

## Opciones consideradas

| Opción | Pros | Contras |
|---|---|---|
| **PWA con Livewire + Alpine + IndexedDB** (elegida) | Misma stack de UI que el resto del sistema; instalación en el portátil/tablet de la estación; funciona offline con diseño local-first; reutiliza el diseño de estación del corpus de origen | La parte offline no puede depender de idas y venidas del servidor: exige un componente local explícito (Alpine + IndexedDB) embebido en la página; service worker y caché del shell son trabajo real (espiga en F1) |
| SPA offline con framework JS | SPA offline "natural" | Introduce y mantiene un segundo framework de UI solo para la estación; contradice ADR-011 |
| App móvil nativa | Offline robusto, cámara/BLE | Es la fase SaaS (ADR-016); el núcleo no puede esperar a F4 para cumplir O5 |
| Aplicación de escritorio (Electron/Tauri) | Offline total | Otro runtime que empaquetar y actualizar; sobredimensionado para una pantalla de teclado |

## Decisión

La estación de cronometraje es una **PWA (manifest + service worker) cuya página es Livewire + Alpine, con captura
local en IndexedDB**, siguiendo el diseño de estación definido en el corpus de origen:

- **Captura local primero:** cada Enter genera el registro `{client_mark_id: uuidv7, bib, device_time, clock_offset_ms,
  device_id, seq_local}` y lo escribe **primero en IndexedDB**; el feedback "capturada" es inmediato y no depende de red
  (color/icono/sonido según PRD UX 1, [../01-prd.md](../01-prd.md)).
- **Capa Livewire:** estado de la estación, bitácora local, contadores y ajustes cuando hay conexión; la captura en sí
  corre en un componente local (Alpine + IndexedDB) embebido en la página, de modo que **un corte de red no rompe el
  teclado**. Al reconectar, Livewire refleja el estado sincronizado.
- **Sincronización por lotes idempotentes:** un worker del cliente envía lotes (≤ 100) con reintento exponencial contra
  `POST /api/v1/events/{e}/timing-marks` (ADR-012); el servidor deduplica por `client_mark_id` (único) y responde el
  estado de cada marca (`assigned` / `orphan` / `duplicate`), que actualiza el feedback local.
- **Reloj con offset:** `GET /api/v1/time` cada 60 s con estimación tipo NTP; alerta si el offset varía más de 500 ms
  entre muestras.
- **Hora oficial = `captured_at`:** la hora de captura en la estación, corregida por el offset del servidor — regla ya
  apuntada por la decisión previa **ADR-005** del corpus de origen, que aquí se fija como propia. El servidor guarda
  también `received_at` para auditoría. La confirmación formal del negocio sigue pendiente (pregunta abierta del PRD,
  [../01-prd.md](../01-prd.md)), bloqueante antes de M1.
- **Dos estaciones:** cada una con `device_id`; duplicados entre estaciones resueltos por el motor (primera marca por
  `captured_at`, la otra `duplicate`, ventana configurable).

## Consecuencias

| Tipo | Consecuencia |
|---|---|
| Positiva | O5 se cumple en F2 con el mismo stack que el resto del sistema; sin front paralelo de framework JS |
| Positiva | Ninguna marca se pierde por corte de red (O1); la recarga de la página no pierde pendientes (aviso `beforeunload`; export CSV manual como último recurso) |
| Positiva | El diseño de la estación (estados, reloj, duplicados, seguridad `device:marks`) definido en el corpus de origen se reutiliza íntegro; solo cambia la capa de vista |
| Positiva | La PWA no guarda PII (solo dorsales y horas): superficie de privacidad mínima |
| Negativa (mitigada) | La parte offline exige disciplina: pruebas E2E con red cortada obligatorias (ADR-018) y espiga técnica en F1 antes de comprometer F2 |
| Negativa (mitigada) | Livewire no funciona sin servidor: el diseño local-first de la captura es obligatorio y su incumplimiento es un fallo de arquitectura detectable en revisión |
| Neutral | Cuando llegue la fase SaaS (ADR-016), esta PWA convive con la app móvil (operadores distintos, protocolo de sync compartido) |

## ADRs y documentos afectados

| Relación | Referencia |
|---|---|
| **Mantiene como decisión previa** | **ADR-005** (corpus de origen: hora oficial = hora de captura corregida por offset; vigente, enunciada ahora en este ADR) |
| Hereda (diseño) | El diseño de estación del corpus de origen (topología de sede, estados de marca) y el endpoint del lote idempotente del contrato to-be |
| Requiere | ADR-011 (stack TALL), ADR-012 (endpoint API del lote idempotente), ADR-010 (Sanctum abilities) |
| Relacionado | ADR-S12 y R-g (nube fuente de verdad y nodo de sede como relé en la fase SaaS); la app móvil no sustituye a la PWA en el núcleo |
| Verificación | DoD "evento/broadcast" y escenario offline en [../05-dod.md §2](../05-dod.md); ensayo M3 en [../08-roadmap.md](../08-roadmap.md) |

## Cumplimiento verificable

- El build publica `manifest.json` y service worker con caché del shell de la estación; Playwright verifica la
  instalación y la carga sin red.
- **Test E2E offline** (obligatorio, ADR-018): capturar 10 marcas con red cortada → persisten en IndexedDB (recarga de
  página incluida) → restaurar red → los 10 lotes se sincronizan con **0 duplicadas y 0 perdidas**.
- Migración con `unique` sobre `client_mark_id`; test de idempotencia del lote (reenviar el mismo lote no crea marcas).
- Test del reloj: offset calculado, alerta por variación > 500 ms, `captured_at` conservado aunque `received_at` llegue
  tarde (comparación en un caso simulado de sync diferido).
- Test de dispositivo: token con ability `device:marks` y ámbito de evento; sin token no se escribe (401/403).
- El ensayo M3 ([../08-roadmap.md](../08-roadmap.md)) repite el escenario a escala: 300 inscritos, 4 estaciones, corte
  de red de 10 min, 0 perdidas/duplicadas.
