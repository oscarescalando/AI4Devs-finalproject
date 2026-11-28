/*
 * TDChronos — estación de cronometraje (ISSUE-147/148/149/150, TQ-021/022,
 * ADR-014).
 *
 * Entry EXCLUSIVO de la pantalla /station; el panel (app.js) no lo carga.
 * Decisiones documentadas:
 *
 * · Alpine sin Livewire: se importa del ESM que publica Livewire en vendor
 *   (misma familia de stack, ADR-011/014, cero dependencias npm nuevas).
 *   La vista define un stub de window.livewireScriptConfig ANTES de este
 *   módulo para evitar el autoarranque de Livewire que trae el ESM: aquí
 *   solo corre Alpine, sin runtime de Livewire ni Echo y sin round-trips
 *   al servidor — la captura no se rompe con la red caída (O5/O1).
 * · IndexedDB a mano con promesas (sin librerías): base
 *   tdchronos-station, store 'marks' con clave client_mark_id e índices
 *   captured_at/synced para la sincronización (índice synced ⇒ cola de
 *   pendientes sin releer la tabla completa). OJO: los booleanos NO son
 *   claves IndexedDB válidas, así que synced guarda 0/1 — la versión 2 de
 *   la BD reescribe los false/true booleanos que dejó TQ-021.
 * · UUIDv7 a mano (sin dependencias): 48 bits de timestamp en ms + versión
 *   7 + variante RFC 4122 + aleatoriedad de crypto. El timestamp del UUID
 *   y captured_at salen del MISMO instante corregido para que sean
 *   coherentes.
 * · Emparejamiento: el ULID y el token viajan en localStorage ('tc.station')
 *   y se validan contra GET /api/v1/time con Bearer ANTES de aceptarlos
 *   (401/403/otro ⇒ error visible).
 * · Sync (TQ-022, ISSUE-149): lotes POST /timing-marks de 50 en orden
 *   captured_at; una marca pasa a synced SOLO con respuesta definitiva
 *   (assigned/orphan/duplicate se persisten como status en la cola); error
 *   de red/5xx/422 ⇒ todo el lote queda pendiente con attempts++ y backoff
 *   exponencial por marca (2^n × 5 s, tope 5 min) que aplaza el reintento
 *   AUTOMÁTICO (online + cada 30 s); el botón manual lo ignora. 401/403 en
 *   el lote (o al re-medir el reloj) ⇒ desempareja con aviso claro.
 * · Reloj (ISSUE-150): offset = server_ms − Date.now() medido con 3
 *   muestras contra /time tomando la MEDIANA (NTP ligero), al emparejar y
 *   cada 5 min. Display y captured_at de capturas NUEVAS usan hora local +
 *   offset; la marca guarda también el offset aplicado para que el servidor
 *   reconstruya la hora oficial (device_time_ms = captured_at − offset,
 *   clock_offset_ms = offset) sin doble corrección. |offset| > 500 ms ⇒
 *   banner persistente con el desfase con signo.
 */

import { Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm.js';
import {
    AlertTriangle,
    CloudOff,
    createIcons,
    Link2,
    RefreshCw,
    Timer,
    Trash2,
    Wifi,
    XCircle,
} from 'lucide';

const STORAGE_KEY = 'tc.station';
const DB_NAME = 'tdchronos-station';
// v1 (TQ-021): store + índices. v2 (TQ-022): synced pasa de booleano a
// 0/1 — los booleanos no son claves de índice válidas y getAll(0) es la
// cola de pendientes; la subida reescribe los registros viejos.
const DB_VERSION = 2;
const STORE = 'marks';
const QUEUE_SIZE = 20;
const CLOCK_TICK_MS = 47;

// Sync (TQ-022): lote, cadencia y backoff exponencial por marca.
const BATCH_SIZE = 50;
const SYNC_EVERY_MS = 30_000;
const BACKOFF_BASE_MS = 5_000;
const BACKOFF_MAX_MS = 300_000;

// Reloj (ISSUE-150): muestras para la mediana y umbral de aviso de desfase.
const OFFSET_SAMPLES = 3;
const OFFSET_EVERY_MS = 5 * 60_000;
const SKEW_ALERT_MS = 500;

// ULID canónico: base32 de Crockford (sin i, l, o, u), 26 caracteres.
const ULID_RE = /^[0-9a-hjkmnp-tv-z]{26}$/;

/* ---------- uuidv7 mínimo (RFC 9562): timestamp + aleatoriedad ---------- */

const uuidV7 = (timestampMs = Date.now()) => {
    const bytes = new Uint8Array(16);
    crypto.getRandomValues(bytes);

    // División y máscara (no desplazamientos: bit a bit es int32): los 48
    // bits del timestamp van big-endian en los primeros 6 bytes.
    bytes[0] = Math.floor(timestampMs / 2 ** 40) & 0xff;
    bytes[1] = Math.floor(timestampMs / 2 ** 32) & 0xff;
    bytes[2] = Math.floor(timestampMs / 2 ** 24) & 0xff;
    bytes[3] = Math.floor(timestampMs / 2 ** 16) & 0xff;
    bytes[4] = Math.floor(timestampMs / 2 ** 8) & 0xff;
    bytes[5] = timestampMs & 0xff;
    bytes[6] = (bytes[6] & 0x0f) | 0x70; // versión 7 (byte 6: ver + rand_a)
    bytes[8] = (bytes[8] & 0x3f) | 0x80; // variante RFC 4122 (byte 8: var + rand_b)

    const hex = Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('');

    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
};

/* ---------- formato de reloj local H:i:s.v, mono tabular ---------- */

const two = (value) => String(value).padStart(2, '0');
const three = (value) => String(value).padStart(3, '0');

const formatClock = (ms) => {
    const date = new Date(ms);

    return `${two(date.getHours())}:${two(date.getMinutes())}:${two(date.getSeconds())}.${three(date.getMilliseconds())}`;
};

// Plural mínimo de lang (':count pendiente|:count pendientes').
const plural = (entry, count) => {
    const [one, other] = String(entry).split('|');

    return (count === 1 ? one : other ?? one).replace(':count', String(count));
};

// Mediana de las muestras de offset (3 ⇒ la del medio descarta la peor).
const median = (values) => {
    const sorted = [...values].sort((a, b) => a - b);

    return sorted[Math.floor(sorted.length / 2)];
};

// Backoff exponencial por marca: 2^attempts × 5 s con tope de 5 min.
const backoffDelay = (attempts) => Math.min(BACKOFF_BASE_MS * 2 ** attempts, BACKOFF_MAX_MS);

/* ---------- IndexedDB mínima con promesas (sin dependencias) ---------- */

const openDb = () => new Promise((resolve, reject) => {
    const request = indexedDB.open(DB_NAME, DB_VERSION);

    request.onupgradeneeded = () => {
        const db = request.result;

        if (!db.objectStoreNames.contains(STORE)) {
            const store = db.createObjectStore(STORE, { keyPath: 'client_mark_id' });
            store.createIndex('captured_at', 'captured_at');
            store.createIndex('synced', 'synced');

            return;
        }

        // v1 → v2 (TQ-022): reescribe synced booleano a 0/1 dentro de la
        // mismísima transacción de versión — los registros con clave de
        // índice inválida quedaban fuera del índice synced.
        if (request.transaction !== null) {
            const store = request.transaction.objectStore(STORE);
            const cursorRequest = store.openCursor();

            cursorRequest.onsuccess = () => {
                const cursor = cursorRequest.result;

                if (cursor === null) {
                    return;
                }

                const mark = cursor.value;

                if (typeof mark.synced === 'boolean') {
                    cursor.update({ ...mark, synced: mark.synced ? 1 : 0 });
                }

                cursor.continue();
            };
        }
    };
    request.onsuccess = () => resolve(request.result);
    request.onerror = () => reject(request.error);
});

const withStore = async (mode, run) => {
    const db = await openDb();

    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE, mode);
        const request = run(tx.objectStore(STORE));

        tx.oncomplete = () => resolve(request?.result);
        tx.onerror = () => reject(tx.error);
        tx.onabort = () => reject(tx.error);
    });
};

const putMark = (mark) => withStore('readwrite', (store) => store.put(mark));
const allMarks = () => withStore('readonly', (store) => store.getAll());
// Cola de pendientes por el índice synced (0 = sin respuesta definitiva).
const unsyncedMarks = () => withStore('readonly', (store) => store.index('synced').getAll(0));
const deleteMark = (clientMarkId) => withStore('readwrite', (store) => store.delete(clientMarkId));

/* ---------- emparejamiento persistente en localStorage ---------- */

const readPairing = () => {
    try {
        const parsed = JSON.parse(window.localStorage.getItem(STORAGE_KEY) ?? 'null');

        return parsed !== null && typeof parsed === 'object' ? parsed : null;
    } catch {
        return null; // almacenamiento corrupto o no disponible
    }
};

const writePairing = (pairing) => {
    try {
        window.localStorage.setItem(STORAGE_KEY, JSON.stringify(pairing));
    } catch {
        /* sin almacenamiento: empareja solo por esta sesión */
    }
};

const clearPairing = () => {
    try {
        window.localStorage.removeItem(STORAGE_KEY);
    } catch {
        /* nada que limpiar */
    }
};

/* ---------- iconos del shell (subconjunto, sin lucide completo) ---------- */

const renderIcons = () => {
    createIcons({
        icons: { AlertTriangle, CloudOff, Link2, RefreshCw, Timer, Trash2, Wifi, XCircle },
        nameAttr: 'data-lucide',
    });
};

/* ---------- componente Alpine de la estación ---------- */
// Recibe el dominio ui.station de lang/es/ui.php vía @js(): una sola fuente
// de textos, sin literales en este módulo.
window.tcStation = (texts) => ({
    t: texts ?? {},
    paired: false,
    dbReady: false,
    pairing: false,
    pairError: '',
    authError: false,
    form: { eventUlid: '', token: '' },
    eventUlid: '',
    token: '',
    bib: '',
    bibError: false,
    clock: '--:--:--.---',
    clockOffsetMs: 0,
    clockMeasured: false,
    online: true,
    syncing: false,
    syncingCount: 0,
    marks: [],
    pending: 0,
    clockTimer: null,
    bibTimer: null,
    syncTimer: null,
    offsetTimer: null,

    async init() {
        const stored = readPairing();

        if (
            stored !== null
            && ULID_RE.test(String(stored.eventUlid ?? ''))
            && typeof stored.token === 'string' && stored.token !== ''
        ) {
            this.paired = true;
            this.eventUlid = stored.eventUlid;
            this.token = stored.token;
        }

        this.online = navigator.onLine;

        this.clock = formatClock(Date.now() + this.clockOffsetMs);
        this.clockTimer = window.setInterval(() => {
            this.clock = formatClock(Date.now() + this.clockOffsetMs);
        }, CLOCK_TICK_MS);

        // Sync (TQ-022): 'online' relanza el flush apenas vuelve la red;
        // 'offline' solo actualiza el indicador; el interval reintenta
        // mientras quede algo pendiente.
        this.onOnline = () => {
            this.online = true;
            this.flush();
        };
        this.onOffline = () => {
            this.online = false;
        };
        window.addEventListener('online', this.onOnline);
        window.addEventListener('offline', this.onOffline);

        this.syncTimer = window.setInterval(() => {
            if (this.pending > 0 && this.online) {
                this.flush();
            }
        }, SYNC_EVERY_MS);

        // Reloj (ISSUE-150): re-medición periódica del offset.
        this.offsetTimer = window.setInterval(() => {
            this.measureOffset();
        }, OFFSET_EVERY_MS);

        // Cerrar la pestaña con pendientes: el navegador pregunta antes.
        this.onBeforeUnload = (event) => {
            if (this.pending > 0) {
                event.preventDefault();
                event.returnValue = '';
            }
        };
        window.addEventListener('beforeunload', this.onBeforeUnload);

        // La cola vive en el dispositivo: recargar la página no la pierde.
        await this.reload();
        this.dbReady = true;

        if (this.paired) {
            this.measureOffset();
        }
    },

    destroy() {
        window.clearInterval(this.clockTimer);
        window.clearTimeout(this.bibTimer);
        window.clearInterval(this.syncTimer);
        window.clearInterval(this.offsetTimer);
        window.removeEventListener('online', this.onOnline);
        window.removeEventListener('offline', this.onOffline);
        window.removeEventListener('beforeunload', this.onBeforeUnload);
    },

    get pendingLabel() {
        return plural(this.t.pending_count ?? ':count pendientes', this.pending);
    },

    get syncLabel() {
        return this.syncing
            ? String(this.t.syncing ?? '').replace(':count', String(this.syncingCount))
            : (this.t.sync ?? '');
    },

    // «desfase +X ms» con signo, solo tras haber medido contra el servidor.
    get skewLabel() {
        const offset = Math.round(this.clockOffsetMs);

        return String(this.t.clock_skew ?? '').replace(':offset', `${offset >= 0 ? '+' : ''}${offset}`);
    },

    get skewVisible() {
        return this.clockMeasured && Math.abs(this.clockOffsetMs) > SKEW_ALERT_MS;
    },

    clockOf(ms) {
        return formatClock(ms);
    },

    /* Estado final por marca (TQ-022): pending hasta respuesta definitiva. */
    markStatus(mark) {
        return mark.synced === 1 && typeof mark.status === 'string' ? mark.status : 'pending';
    },

    // Mapeo fijo de dominio (spec/09-ui-estilo §3): assigned→success,
    // orphan→warning, duplicate→info, pendiente→warning.
    badgeVariant(mark) {
        const variants = { pending: 'warning', assigned: 'success', orphan: 'warning', duplicate: 'info' };

        return variants[this.markStatus(mark)] ?? 'neutral';
    },

    badgeLabel(mark) {
        return this.t[`status_${this.markStatus(mark)}`] ?? '';
    },

    /* Emparejamiento (ISSUE-147): valida contra GET /api/v1/time con el
     * Bearer — 200 acepta y persiste; cualquier rechazo se muestra. */
    async pair() {
        this.pairError = '';

        const eventUlid = this.form.eventUlid.trim().toLowerCase();
        const token = this.form.token.trim();

        if (eventUlid === '' || token === '') {
            this.pairError = this.t.pair_err_empty;

            return;
        }

        if (!ULID_RE.test(eventUlid)) {
            this.pairError = this.t.pair_err_ulid;

            return;
        }

        this.pairing = true;

        try {
            const response = await window.fetch('/api/v1/time', {
                headers: { Accept: 'application/json', Authorization: `Bearer ${token}` },
            });

            if (response.ok) {
                this.eventUlid = eventUlid;
                this.token = token;
                this.paired = true;
                this.authError = false;
                // bibPrefijo llega con ISSUE-148 completo; hoy «ninguno».
                writePairing({ eventUlid, token, bibPrefix: null });
                this.form = { eventUlid: '', token: '' };

                // ISSUE-150: primera medición del desfase contra el servidor.
                this.clockOffsetMs = 0;
                this.clockMeasured = false;
                this.measureOffset();

                return;
            }

            this.pairError = response.status === 401
                ? this.t.pair_err_unauthorized
                : (response.status === 403
                    ? this.t.pair_err_forbidden
                    : String(this.t.pair_err_status ?? '').replace(':status', String(response.status)));
        } catch {
            this.pairError = this.t.pair_err_network;
        } finally {
            this.pairing = false;
        }
    },

    unpair() {
        if (!window.confirm(this.t.unpair_confirm)) {
            return;
        }

        this.doUnpair(false);
    },

    /* Desempareja conservando las marcas (pertenecen al dispositivo: el
     * próximo emparejamiento las sincroniza). `invalidToken` distingue el
     * desempareje voluntario del forzado por un 401/403 del sync. */
    doUnpair(invalidToken) {
        clearPairing();
        this.paired = false;
        this.eventUlid = '';
        this.token = '';
        this.authError = invalidToken;
        this.syncing = false;
        this.clockOffsetMs = 0;
        this.clockMeasured = false;
    },

    /* Reloj (ISSUE-150): offset = server_ms − Date.now() con 3 muestras y
     * mediana (el punto medio de la muestra descarta el round-trip peor).
     * Sin red se conserva el offset vigente; un 401/403 desempareja. */
    async measureOffset() {
        if (!this.paired) {
            return;
        }

        const samples = [];

        for (let sample = 0; sample < OFFSET_SAMPLES; sample++) {
            try {
                const before = Date.now();
                const response = await window.fetch('/api/v1/time', {
                    headers: { Accept: 'application/json', Authorization: `Bearer ${this.token}` },
                    cache: 'no-store',
                });
                const after = Date.now();

                if (response.status === 401 || response.status === 403) {
                    this.doUnpair(true);

                    return;
                }

                if (response.ok) {
                    const body = await response.json();

                    if (body !== null && typeof body === 'object' && Number.isFinite(body.server_ms)) {
                        samples.push(Number(body.server_ms) - (before + after) / 2);
                    }
                }
            } catch {
                /* sin red: se conserva el offset vigente */
            }
        }

        if (samples.length > 0) {
            this.clockOffsetMs = Math.round(median(samples));
            this.clockMeasured = true;
        }
    },

    /* Botón manual (TQ-022): ignora el backoff — siempre disponible. */
    synchronize() {
        this.flush(true);
    },

    /* Sync idempotente (ISSUE-149): pendientes ordenados por captured_at en
     * lotes de 50. Por marca, la respuesta definitiva la deja synced con su
     * estado persistido; sin respuesta o con error de red/5xx el lote queda
     * pendiente con attempts++ y backoff que aplaza el reintento automático
     * (el manual lo salta). 401/403 ⇒ desempareje con aviso. */
    async flush(manual = false) {
        if (this.syncing || !this.paired) {
            return;
        }

        const now = Date.now();
        const eligible = (await unsyncedMarks())
            .filter((mark) => manual || typeof mark.retry_at !== 'number' || mark.retry_at <= now)
            .sort((a, b) => (a.captured_at ?? 0) - (b.captured_at ?? 0));

        if (eligible.length === 0) {
            return;
        }

        this.syncing = true;
        this.syncingCount = eligible.length;

        try {
            let queue = eligible;

            while (queue.length > 0 && this.paired) {
                const batch = queue.slice(0, BATCH_SIZE);

                try {
                    const response = await window.fetch(`/api/v1/events/${this.eventUlid}/timing-marks`, {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            Authorization: `Bearer ${this.token}`,
                        },
                        body: JSON.stringify({
                            marks: batch.map((mark) => ({
                                client_mark_id: mark.client_mark_id,
                                bib: mark.bib,
                                // Reconstrucción sin doble corrección: la
                                // marca guarda la hora corregida y el offset
                                // con el que se corrigió (server: device +
                                // offset = captured_at).
                                device_time_ms: (mark.captured_at ?? 0) - (mark.clock_offset_ms ?? 0),
                                clock_offset_ms: mark.clock_offset_ms ?? 0,
                                source: 'station',
                            })),
                        }),
                    });

                    if (response.status === 401 || response.status === 403) {
                        this.doUnpair(true);

                        return;
                    }

                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}`);
                    }

                    const body = await response.json();
                    const results = body !== null && Array.isArray(body.results) ? body.results : [];
                    const byId = new Map(results.map((result) => [result.client_mark_id, result]));

                    for (const mark of batch) {
                        const result = byId.get(mark.client_mark_id);

                        if (result !== undefined && typeof result.status === 'string') {
                            mark.synced = 1;
                            mark.status = result.status;
                            await putMark(mark);
                        }
                        // sin resultado definitivo: queda pendiente y se
                        // recoge en el próximo flush
                    }

                    queue = queue.slice(batch.length);
                } catch {
                    // Error de red (o 5xx/422): TODO el lote sigue pendiente
                    // y cada marca aplaza su reintento automático.
                    const failedAt = Date.now();

                    for (const mark of batch) {
                        mark.attempts = (typeof mark.attempts === 'number' ? mark.attempts : 0) + 1;
                        mark.retry_at = failedAt + backoffDelay(mark.attempts);
                        await putMark(mark);
                    }

                    return;
                }
            }
        } finally {
            this.syncing = false;
            this.syncingCount = 0;
            await this.reload();
        }
    },

    /* Captura (ISSUE-148): Enter ⇒ marca local en IndexedDB, sin red. */
    async capture() {
        const typed = this.bib.trim();

        this.bib = '';

        if (typed === '') {
            return; // Enter vacío: no-op (la huérfana sin dorsal se registra desde el panel)
        }

        if (!/^\d{1,5}$/.test(typed)) {
            this.flashBibError();

            return;
        }

        const bib = Number.parseInt(typed, 10);

        if (bib < 1 || bib > 99999) {
            this.flashBibError();

            return;
        }

        // ISSUE-150: captured_at en hora corregida (local + offset) y el
        // offset aplicado guardado junto a la marca — el servidor reconstruye
        // la oficial sin doble corrección (device_time_ms = captured_at −
        // clock_offset_ms). El UUIDv7 sale del mismo instante corregido.
        const capturedAt = Date.now() + this.clockOffsetMs;
        const mark = {
            client_mark_id: uuidV7(capturedAt),
            bib,
            captured_at: capturedAt,
            clock_offset_ms: this.clockOffsetMs,
            synced: 0,
            attempts: 0,
        };

        await putMark(mark);
        await this.reload();
    },

    flashBibError() {
        this.bibError = true;
        window.clearTimeout(this.bibTimer);
        this.bibTimer = window.setTimeout(() => {
            this.bibError = false;
        }, 2500);
    },

    async removeMark(mark) {
        const message = String(this.t.delete_confirm ?? '')
            .replace(':bib', String(mark.bib))
            .replace(':time', formatClock(mark.captured_at));

        if (!window.confirm(message)) {
            return;
        }

        await deleteMark(mark.client_mark_id);
        await this.reload();
    },

    /* Relee la cola: últimas QUEUE_SIZE invertidas + total de pendientes. */
    async reload() {
        const all = await allMarks();

        all.sort((a, b) => (b.captured_at ?? 0) - (a.captured_at ?? 0));
        this.marks = all.slice(0, QUEUE_SIZE);
        this.pending = all.filter((mark) => !mark.synced).length;

        // Las filas del x-for se clonan después del render inicial de
        // iconos: se vuelven a pintar los que traiga la cola.
        await this.$nextTick();
        renderIcons();
    },
});

/* ---------- arranque: iconos estáticos + Alpine + SW del shell ---------- */

renderIcons();
Alpine.start();

// SW (ISSUE-114/TQ-021): /station queda precacheada y abre offline tras la
// primera visita con red (ver public/sw.js). Solo contexto seguro.
if ('serviceWorker' in navigator) {
    const host = window.location.hostname;

    if (window.location.protocol === 'https:' || host === 'localhost' || host === '127.0.0.1') {
        navigator.serviceWorker.register('/sw.js').catch(() => {
            /* sin soporte o sin permiso: la estación sigue online-normal */
        });
    }
}
