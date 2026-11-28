import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    // VITE_REVERB_* viaja al cliente Echo (resources/js/echo.js, TQ-017):
    // clave pública y host/puerto/esquema del WS. Nada de secretos aquí.
    envPrefix: 'VITE_',
    plugins: [
        laravel({
            // TQ-021: station.js es un entry APARTE que solo carga la
            // pantalla /station (Alpine + IndexedDB, sin Livewire ni Echo);
            // el bundle del panel (app.js) no lo referencia ni lo paga.
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/station.js'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
