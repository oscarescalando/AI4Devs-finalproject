import { expect, test } from '@playwright/test';

/**
 * Suite `smoke` (ADR-018, ISSUE-116/TQ-008): el camino principal contra la
 * app real servida por `php artisan serve` con la BD sembrada (el script
 * `npm run test:e2e` siembra antes). Corre en CI como gate 7.
 *
 * Datos: evento demo `vuela-caracas-21k` (114 inscritos) y el admin
 * bootstrap `admin@tdchronos.test` / `tdchronos-admin-2026` (DatabaseSeeder,
 * 100 % sintéticos).
 */

test('la landing lista los eventos disponibles', async ({ page }) => {
    await page.goto('/');

    // Título de marca o encabezado de la lista de eventos (lang/es/ui.php).
    await expect(page).toHaveTitle(/TDChronos/);
    await expect(page.getByRole('heading', { level: 1 })).toContainText('TDChronos');
    await expect(page.getByText('Eventos disponibles')).toBeVisible();
});

test('login del admin llega a la consola del panel', async ({ page }) => {
    await page.goto('/login');

    await page.locator('#login').fill('admin@tdchronos.test');
    await page.locator('#password').fill('tdchronos-admin-2026');
    await page.getByRole('button', { type: 'submit' }).click();

    await expect(page).toHaveURL(/\/home$/);
    await expect(page.getByText('Consola', { exact: true }).first()).toBeVisible();
});

test('la pantalla pública del evento muestra la clasificación con tiempos', async ({ page }) => {
    await page.goto('/e/vuela-caracas-21k');

    await expect(page.getByRole('heading', { level: 1 })).toContainText('VuelaCaracas 21K');

    const tabla = page.locator('table');
    await expect(tabla).toBeVisible();
    await expect(tabla.getByRole('columnheader', { name: 'Tiempo' })).toBeVisible();

    // Al menos una fila en el cuerpo: llegadas o el aviso "Sin llegadas
    // todavía" (el demo no siembra marcas; la tabla jamás queda fantasma).
    await expect(tabla.locator('tbody tr').first()).toBeVisible();
});
