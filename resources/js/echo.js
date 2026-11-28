/*
 * TDChronos — cliente Echo sobre Reverb (ADR-013, ISSUE-141/TQ-017).
 *
 * Solo el sustrato global: instancia window.tcEcho disponible para las
 * pantallas (que se conectan en TQ-018) y un evento DOM tc-echo-status con
 * el estado de la conexión (connecting/connected/disconnected/unavailable)
 * que el live-indicator consumirá. Nada de estado de dominio aquí
 * (ADR-011): sin suscripciones en este módulo.
 *
 * Degradación (O1): si falta la clave (entorno sin VITE_) el cliente no
 * arranca y la app sigue operativa por sondeo; si Reverb está caído,
 * pusher-js reintenta en segundo plano sin romper el bundle — los errores
 * de conexión van a consola, jamás a la pantalla.
 */
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

const STATUS_EVENT = 'tc-echo-status';

const emitStatus = (state) => {
    window.dispatchEvent(new CustomEvent(STATUS_EVENT, { detail: { state } }));
};

const bindStatus = (echo) => {
    const connection = echo.connector?.connection;
    if (! connection) {
        return;
    }
    const pushState = (states) => emitStatus(states.current);
    const relay = (state) => () => emitStatus(state);
    try {
        connection.bind('connecting', relay('connecting'));
        connection.bind('connected', relay('connected'));
        connection.bind('unavailable', relay('unavailable'));
        connection.bind('disconnected', relay('disconnected'));
        connection.bind('state_change', pushState);
    } catch {
        /* nunca romper el arranque por telemetría del WS */
    }
};

const bootEcho = () => {
    const key = import.meta.env.VITE_REVERB_APP_KEY;

    if (! key) {
        return null;
    }

    try {
        const port = Number(import.meta.env.VITE_REVERB_PORT ?? 8080);

        // laravel-echo no empaqueta el cliente WS: se le inyecta
        // (options.client) con su propia configuración de transporte.
        const client = new Pusher(key, {
            cluster: 'mt1',
            wsHost: import.meta.env.VITE_REVERB_HOST,
            wsPort: port,
            wssPort: port,
            forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https',
            enabledTransports: ['ws', 'wss'],
            disableStats: true,
            // Con cliente inyectado, pusher-js NO toma el endpoint de
            // Echo: se fija el de Laravel (mismo origen, cookies de sesión).
            authEndpoint: '/broadcasting/auth',
        });

        const echo = new Echo({
            broadcaster: 'reverb',
            key,
            client,
        });
        bindStatus(echo);
        emitStatus('connecting');

        return echo;
    } catch (error) {
        // Sin WebSocket utilizable: la app sigue siendo online-normal y
        // las pantallas operan por sondeo (degradación O1). Se deja rastro
        // en consola para operación, nunca en la interfaz.
        console.warn('tcEcho: no se pudo iniciar el cliente Reverb', error);
        emitStatus('offline');

        return null;
    }
};

const echo = bootEcho();

window.tcEcho = echo;
window.tcEchoStatusEvent = STATUS_EVENT;
