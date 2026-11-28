/*
 * TDChronos — service worker del shell (ISSUE-114, TQ-006, ADR-014).
 *
 * Alcance mínimo para ser instalable y dar pantalla offline sin estorbar:
 *   - precache de /offline.html, los iconos del manifest y la estación
 *     /station (ISSUE-147, TQ-021): su HTML queda listo antes de activar;
 *   - navegaciones: red primero, /offline.html precacheada como respaldo —
 *     salvo /station, que se respalda a sí misma (ver abajo);
 *   - assets Vite con hash (/build/assets/*): caché primero (inmutables);
 *   - /livewire/* y /api/* NUNCA se interceptan (estado y datos siempre frescos).
 *
 * La versión de caché (CACHE) sube a mano; un build nuevo NO la toca: los
 * assets se invalidan solos porque su nombre lleva hash y los viejos dejan
 * de pedirse. Sobre esa base, EP-27/TQ-008 extenderá el shell de la
 * estación de cronometraje.
 */

const CACHE = 'tdchronos-shell-v2';
const OFFLINE_URL = '/offline.html';
const STATION_URL = '/station';

/* ---------- install: precache del shell ---------- */
/* La "T" offline, los iconos del manifest y la estación quedan listos antes
 * de activar; skipWaiting() para que un SW nuevo tome el control sin esperar
 * pestañas. OJO: los assets con hash de /station NO se precachean aquí (el
 * nombre cambia con cada build): se calientan en caché en la PRIMERA visita
 * con red por la rama /build/assets/ — hasta entonces la estación no abre
 * offline. */
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(CACHE)
            .then((cache) => cache.addAll([
                OFFLINE_URL,
                '/icons/icon-192.png',
                '/icons/icon-512.png',
                '/icons/icon-maskable-512.png',
                STATION_URL,
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

    // Navegación a la estación (TQ-021, ADR-014): red primero para tener el
    // HTML vigente (referencia assets con hash nuevos) y el acierto se
    // re-guarda; sin red se sirve la copia cacheada — el respaldo siempre
    // corresponde a la última build vista, cuyos assets ya quedaron en
    // caché por la rama /build/assets/.
    if (request.mode === 'navigate' && url.pathname === STATION_URL) {
        event.respondWith(
            fetch(request)
                .then((response) => {
                    const copy = response.clone();
                    caches.open(CACHE).then((cache) => cache.put(STATION_URL, copy));

                    return response;
                })
                .catch(() => caches.match(STATION_URL).then((hit) => hit || Response.error())),
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
