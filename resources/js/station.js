/*
 * TDChronos — estación de cronometraje (ISSUE-147/148, TQ-021, ADR-014).
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
 *   captured_at/synced para la sincronización de TQ-022.
 * · UUIDv7 a mano (sin dependencias): 48 bits de timestamp en ms + versión
 *   7 + variante RFC 4122 + aleatoriedad de crypto. El timestamp del UUID
 *   y captured_at salen del MISMO Date.now() para que sean coherentes.
 * · Emparejamiento: el ULID y el token viajan en localStorage ('tc.station')
 *   y se validan contra GET /api/v1/time con Bearer ANTES de aceptarlos
 *   (401/403/otro ⇒ error visible). La sincronización del lote es TQ-022:
 *   este módulo no hace más red que esa validación puntual.
 */

import { Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm.js';
import { createIcons, Link2, Timer, Trash2 } from 'lucide';

const STORAGE_KEY = 'tc.station';
const DB_NAME = 'tdchronos-station';
const DB_VERSION = 1;
const STORE = 'marks';
const QUEUE_SIZE = 20;
const CLOCK_TICK_MS = 47;

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

/* ---------- IndexedDB mínima con promesas (sin dependencias) ---------- */

const openDb = () => new Promise((resolve, reject) => {
    const request = indexedDB.open(DB_NAME, DB_VERSION);

    request.onupgradeneeded = () => {
        const db = request.result;

        if (!db.objectStoreNames.contains(STORE)) {
            const store = db.createObjectStore(STORE, { keyPath: 'client_mark_id' });
            store.createIndex('captured_at', 'captured_at');
            store.createIndex('synced', 'synced');
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
    createIcons({ icons: { Link2, Timer, Trash2 }, nameAttr: 'data-lucide' });
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
    form: { eventUlid: '', token: '' },
    eventUlid: '',
    token: '',
    bib: '',
    bibError: false,
    clock: '--:--:--.---',
    marks: [],
    pending: 0,
    clockTimer: null,
    bibTimer: null,

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

        this.clock = formatClock(Date.now());
        this.clockTimer = window.setInterval(() => {
            this.clock = formatClock(Date.now());
        }, CLOCK_TICK_MS);

        // La cola vive en el dispositivo: recargar la página no la pierde.
        await this.reload();
        this.dbReady = true;
    },

    destroy() {
        window.clearInterval(this.clockTimer);
        window.clearTimeout(this.bibTimer);
    },

    get pendingLabel() {
        return plural(this.t.pending_count ?? ':count pendientes', this.pending);
    },

    clockOf(ms) {
        return formatClock(ms);
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
                // bibPrefijo llega con ISSUE-148 completo; hoy «ninguno».
                writePairing({ eventUlid, token, bibPrefix: null });
                this.form = { eventUlid: '', token: '' };

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

        // Las marcas locales se conservan: pertenecen al dispositivo y el
        // sync de TQ-022 las resolverá con el próximo emparejamiento.
        clearPairing();
        this.paired = false;
        this.eventUlid = '';
        this.token = '';
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

        const capturedAt = Date.now();
        const mark = {
            client_mark_id: uuidV7(capturedAt),
            bib,
            captured_at: capturedAt,
            synced: false,
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
        this.pending = all.filter((mark) => mark.synced !== true).length;

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
