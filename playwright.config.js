import { defineConfig, devices } from '@playwright/test';

/**
 * Suite `smoke` de Playwright (ADR-018, ISSUE-117/TQ-008 — gate 7 de CI).
 *
 * Configuración deliberadamente mínima:
 *
 * - El webServer levanta `php artisan serve` en 127.0.0.1:8088 con la
 *   configuración NORMAL de .env (misma BD de desarrollo). No hay entorno
 *   APP_ENV=testing cómodo para el servidor de artesania: el smoke corre
 *   contra la app tal cual y el script npm `test:e2e` siembra la BD antes
 *   (migrate:fresh --seed), igual que hace CI antes de este gate. En CI la
 *   BD apunta al servicio MySQL del workflow.
 * - Chromium solamente: es el gate de humo, no la matriz de navegadores.
 * - Las suites `cu-paridad` y `estacion-offline` (nocturnas, spec/07 §2.4)
 *   llegan con sus épicas y correrán sobre esta misma config con proyectos
 *   separados.
 */
export default defineConfig({
    testDir: './tests/e2e',
    fullyParallel: false,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 1 : 0,
    workers: 1,
    reporter: process.env.CI ? [['github'], ['list']] : [['list']],
    timeout: 30_000,
    expect: { timeout: 5_000 },

    use: {
        baseURL: process.env.E2E_BASE_URL ?? 'http://127.0.0.1:8088',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        locale: 'es-VE',
    },

    projects: [
        { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
    ],

    webServer: {
        command: 'php artisan serve --host=127.0.0.1 --port=8088',
        url: 'http://127.0.0.1:8088',
        reuseExistingServer: !process.env.CI,
        timeout: 60_000,
        stdout: 'ignore',
    },
});
