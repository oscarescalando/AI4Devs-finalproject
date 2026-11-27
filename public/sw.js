/*
 * TDChronos — service worker del shell (ISSUE-114, TQ-006, ADR-014).
 *
 * Alcance mínimo para ser instalable y dar pantalla offline sin estorbar:
 *   - precache de /offline.html y los iconos del manifest;
 *   - navegaciones: red primero, /offline.html precacheada como respaldo;
 *   - assets Vite con hash (/build/assets/*): caché primero (inmutables);
 *   - /livewire/* y /api/* NUNCA se interceptan (estado y datos siempre frescos).
 *
 * La versión de caché (CACHE) sube a mano; un build nuevo NO la toca: los
 * assets se invalidan solos porque su nombre lleva hash y los viejos dejan
 * de pedirse. Sobre esa base, EP-27/TQ-008 extenderá el shell de la
 * estación de cronometraje.
 */

const CACHE = 'tdchronos-shell-v1';
const OFFLINE_URL = '/offline.html';

/* ---------- install: precache del shell ---------- */
/* La "T" offline y los iconos del manifest quedan listos antes de activar;
 * skipWaiting() para que un SW nuevo tome el control sin esperar pestañas. */
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(CACHE)
            .then((cache) => cache.addAll([
                OFFLINE_URL,
                '/icons/icon-192.png',
                '/icons/icon-512.png',
                '/icons/icon-maskable-512.png',
            ]))
            .then(() => self.skipWaiting()),
    );
});

/* ---------- activate: limpieza de cachés viejas ---------- */
/* Solo sobrevive la caché de la versión actual: un despliegue no sirve
 * mezclas obsoletas. clients.claim() toma el control de pestañas abiertas. */
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) => Promise.all(
                keys.filter((key) => key !== CACHE).map((key) => caches.delete(key)),
            ))
            .then(() => self.clients.claim()),
    );
});

/* ---------- fetch: solo GET y solo mismo origen ---------- */
self.addEventListener('fetch', (event) => {
    const { request } = event;

    // Solo lecturas: cualquier POST/PUT/DELETE de Livewire o la API pasa directo.
    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    // Otros orígenes (CDN, etc.) no son asunto de este SW.
    if (url.origin !== self.location.origin) {
        return;
    }

    // Livewire y la API jamás se sirven de caché: sin esto se romperían
    // sesiones, sondeos del leaderboard y lotes de marcas (ISSUE-114).
    // Livewire 4 publica sus endpoints bajo /livewire-<hash>/: el prefijo
    // /livewire- cubre el script y el update; /api/ a la API /api/v1.
    if (url.pathname.startsWith('/livewire/') || url.pathname.startsWith('/livewire-') || url.pathname.startsWith('/api/')) {
        return;
    }

    // Assets Vite con hash en el nombre: inmutables -> caché primero.
    // El HTML que los referencia no se cachea, así que un deploy nuevo
    // referencia hashes nuevos y estas entradas quedan huérfanas (las
    // barre el activate de la próxima subida manual de CACHE).
    if (url.pathname.startsWith('/build/assets/')) {
        event.respondWith(
            caches.match(request).then((hit) => hit || fetch(request).then((response) => {
                const copy = response.clone();
                caches.open(CACHE).then((cache) => cache.put(request, copy));

                return response;
            })),
        );

        return;
    }

    // Navegaciones de documento: red primero (la app es online-normal);
    // sin red se sirve la pantalla offline precacheada.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() => caches.match(OFFLINE_URL).then((hit) => hit || Response.error())),
        );
    }
});
