import { execFileSync } from 'node:child_process';
import { expect, test } from '@playwright/test';

/**
 * Suite `estacion-offline` (ADR-018 · ISSUE-152 mínima · TQ-023).
 *
 * Cómo correrla:
 *
 *   npm run test:e2e:station
 *
 * El script resiembra la BD (migrate:fresh --seed, igual que test:e2e) y
 * corre SOLO este archivo. El smoke queda intacto apuntando a
 * smoke.spec.js: esta suite NO entra al gate 7 de CI — integrarla al runner
 * nocturno de `cu-paridad`/`estacion-offline` es un DEFER (spec/07 §2.4).
 *
 * Token determinista: Sanctum solo imprime el token plano al crearlo (la BD
 * guarda su hash SHA-256), así que «fijar» uno exigiría re-implementar la
 * emisión a mano. La vía robusta elegida: la suite ejecuta el comando REAL
 * (`php artisan station:token <ulid>`) con execFileSync desde Node y captura
 * la línea `token: …` de su salida — determinista por construcción y de paso
 * certifica el camino real de emisión. El ULID del evento demo y los tres
 * dorsales inscritos se leen por tinker contra el seeder.
 *
 * Service worker en http: 127.0.0.1 SÍ es contexto seguro y el SW registra,
 * pero la emulación de red de Playwright (context.setOffline) y el
 * route.abort no alcanzan de forma confiable a las peticiones que pasan por
 * él (target distinto). Como el SW jamás intercepta /api/* (public/sw.js) y
 * la captura es 100 % local, la suite bloquea su registro
 * (serviceWorkers: 'block') y certifica la lógica de la estación
 * (IndexedDB + sync idempotente) de forma determinista; la cáscara PWA
 * offline tiene su propia cobertura (ISSUE-114/TQ-006).
 *
 * Aislamiento: cada test recibe un contexto NUEVO de Playwright —
 * localStorage e IndexedDB nacen vacíos (equivale al beforeEach de
 * limpieza) y ningún spec comparte cola de marcas con otro.
 *
 * Specs:
 *   1. captura offline → sincroniza al volver (assigned ×3, BD con 3).
 *   2. reenvío del mismo lote sin duplicar (INV-02): la respuesta del
 *      primer envío se pierde (route.abort tras route.fetch) y el segundo
 *      flush reporta duplicate sin crear filas.
 *   3. token revocado ⇒ 401 ⇒ desempareje con aviso y formulario visible.
 */

/* ---------- helpers: artisan/tinker desde Node (misma BD del serve) ---------- */

const projectRoot = import.meta.dirname !== undefined ? `${import.meta.dirname}/../..` : process.cwd();

const artisan = (args) => execFileSync('php', ['artisan', ...args], {
    cwd: projectRoot,
    encoding: 'utf8',
});

const tinker = (code) => artisan(['tinker', `--execute=${code}`]).trim();

/* ---------- datos del seeder (evento demo) + token REAL del comando ---------- */

const seeded = JSON.parse(tinker(
    'echo json_encode(['
    + '"ulid" => App\\Models\\Event::where("slug", "vuela-caracas-21k")->value("ulid"),'
    + '"bibs" => App\\Models\\Entry::where("event_id", App\\Models\\Event::where("slug", "vuela-caracas-21k")->value("id"))'
    + '->orderBy("bib")->limit(3)->pluck("bib"),'
    + ']);',
));

if (typeof seeded.ulid !== 'string' || seeded.ulid.length !== 26 || seeded.bibs.length !== 3) {
    throw new Error(
        'La BD no tiene el evento demo vuela-caracas-21k con inscritos: corre la suite con npm run test:e2e:station (resiembra antes).',
    );
}

const eventUlid = seeded.ulid;
const bibs = seeded.bibs;

// Emisión real vía comando (ver cabecera): la línea «token: N|…» solo vive
// en la salida del proceso que creó el token.
const tokenOutput = artisan(['station:token', eventUlid]);
const stationToken = /token:\s*(\S+)/m.exec(tokenOutput)?.[1] ?? '';

if (stationToken === '' || !stationToken.includes('|')) {
    throw new Error(`No se pudo extraer el token de la salida de station:token: ${tokenOutput}`);
}

/* ---------- helpers de BD (asserts del lado del servidor) ---------- */

const marksCountFor = (ids) => Number(
    tinker(`echo App\\Models\\TimingMark::whereIn("client_mark_id", [${ids.map((id) => `"${id}"`).join(', ')}])->count();`),
);

const totalMarks = () => Number(tinker('echo App\\Models\\TimingMark::count();'));

const revokeStationToken = () => {
    // El id de un token Sanctum es la parte antes de «|»: borrar la fila
    // revoca el token del lado del servidor sin tocar el dispositivo.
    const tokenId = stationToken.split('|')[0];

    tinker(`echo Illuminate\\Support\\Facades\\DB::table("personal_access_tokens")->where("id", ${tokenId})->delete();`);
};

/* ---------- helpers de página (selectores reales de station.blade.php) ---------- */

async function pairStation(page) {
    await page.goto('/station');

    await page.locator('#station-event').fill(eventUlid);
    await page.locator('#station-token').fill(stationToken);
    await page.getByRole('button', { name: 'Emparejar' }).click();

    // Modo captura visible y formulario de emparejamiento fuera.
    await expect(page.locator('#station-bib')).toBeVisible();
    await expect(page.locator('#station-event')).toBeHidden();
}

async function captureBib(page, bib) {
    await page.locator('#station-bib').fill(String(bib));
    await page.locator('#station-bib').press('Enter');
}

// Fuerza el flush una vez la red vuelve: el evento 'online' lo dispara solo;
// si el evento no llegara a dispararse en este navegador, el botón manual
// (que ignora el backoff) produce exactamente el mismo envío.
async function flushAfterReconnect(page) {
    await expect(async () => {
        if (await page.getByText('Asignada', { exact: true }).count() === 3) {
            return;
        }

        const sync = page.getByRole('button', { name: 'Sincronizar' });

        if (await sync.isVisible().catch(() => false) && await sync.isEnabled().catch(() => false)) {
            await sync.click().catch(() => {});
        }
    }).toPass({ timeout: 15_000 });
}

// Lee los client_mark_id de la cola local (IndexedDB tdchronos-station).
const queuedMarkIds = (page) => page.evaluate(() => new Promise((resolve, reject) => {
    const request = indexedDB.open('tdchronos-station');

    request.onsuccess = () => {
        const db = request.result;
        const tx = db.transaction('marks', 'readonly');
        const getAll = tx.objectStore('marks').getAll();

        getAll.onsuccess = () => resolve(getAll.result.map((mark) => mark.client_mark_id));
        getAll.onerror = () => reject(getAll.error);
    };
    request.onerror = () => reject(request.error);
}));

test.describe('estación de cronometraje offline', () => {
    // Ver cabecera: sin SW el setOffline/route de Playwright es 100 %
    // determinista; el shell PWA offline queda cubierto aparte (ISSUE-114).
    test.use({ serviceWorkers: 'block' });
    test.setTimeout(60_000);

    test('captura offline y sincroniza al volver', async ({ page }) => {
        await pairStation(page);
        await expect(page.getByText('En línea', { exact: true })).toBeVisible();

        // Red caída: el indicador lo refleja y la captura sigue viva.
        await page.context().setOffline(true);
        await expect(page.getByText('Sin conexión', { exact: true })).toBeVisible();

        for (const [index, bib] of bibs.entries()) {
            await captureBib(page, bib);
            await expect(page.locator('ul.list-divide > li')).toHaveCount(index + 1);
        }

        await expect(page.getByText('3 pendientes').first()).toBeVisible();
        await expect(page.getByText('Pendiente', { exact: true })).toHaveCount(3);

        const ids = await queuedMarkIds(page);
        expect(ids).toHaveLength(3);

        // Red vuelve: flush automático → respuesta definitiva por marca.
        await page.context().setOffline(false);
        await expect(page.getByText('En línea', { exact: true })).toBeVisible();
        await flushAfterReconnect(page);

        await expect(page.getByText('Asignada', { exact: true })).toHaveCount(3);
        await expect(page.getByText('0 pendientes').first()).toBeVisible();

        // BD: exactamente esas 3 marcas (por client_mark_id) y nada más.
        expect(marksCountFor(ids)).toBe(3);
        expect(totalMarks()).toBe(3);
    });

    test('reenvío idempotente: el mismo lote no duplica en BD', async ({ page }) => {
        await pairStation(page);

        await page.context().setOffline(true);
        await expect(page.getByText('Sin conexión', { exact: true })).toBeVisible();

        for (const [index, bib] of bibs.entries()) {
            await captureBib(page, bib);
            await expect(page.locator('ul.list-divide > li')).toHaveCount(index + 1);
        }

        const ids = await queuedMarkIds(page);

        // Escenario real de duplicado: el PRIMER envío llega al servidor
        // (route.fetch lo deja pasar y persiste) pero la respuesta se pierde
        // (route.abort): la estación cree que falló y reintentará el mismo
        // lote con los mismos client_mark_id.
        let serverReceived = 0;

        const batchUrl = '**/api/v1/events/*/timing-marks';

        await page.context().route(batchUrl, async (route) => {
            await route.fetch();
            serverReceived += 1;
            await route.abort('timedout');
        });

        await page.context().setOffline(false);
        await expect(page.getByText('En línea', { exact: true })).toBeVisible();

        // Primer flush (auto u manual): sale, persiste… y se pierde.
        await expect(async () => {
            if (serverReceived >= 1) {
                return;
            }

            const sync = page.getByRole('button', { name: 'Sincronizar' });

            if (await sync.isEnabled().catch(() => false)) {
                await sync.click().catch(() => {});
            }
        }).toPass({ timeout: 15_000 });

        // Sin respuesta definitiva: el lote completo queda pendiente.
        await expect(page.getByText('3 pendientes').first()).toBeVisible();
        await expect(page.getByText('Pendiente', { exact: true })).toHaveCount(3);

        const afterFirstSend = totalMarks();

        expect(afterFirstSend).toBeGreaterThan(0);
        expect(marksCountFor(ids)).toBe(3);

        // Segundo flush del MISMO lote. Puede llegar por el botón manual
        // (ignora el backoff) o por el reintento automático (backoff 10 s +
        // timer 30 s): si el automático gana la carrera, pending baja a 0 y
        // el botón queda deshabilitado — el desenlace idempotente es lo que
        // se certifica, no la vía.
        await page.context().unroute(batchUrl);
        await expect(async () => {
            const sync = page.getByRole('button', { name: 'Sincronizar' });

            if (await sync.isEnabled().catch(() => false)) {
                await sync.click().catch(() => {});
            }
        }).toPass({ timeout: 15_000 });

        // duplicate es un estado final más, no un error: badge Duplicada.
        await expect(page.getByText('Duplicada', { exact: true })).toHaveCount(3, { timeout: 45_000 });
        await expect(page.getByText('0 pendientes').first()).toBeVisible();

        // INV-02: el reenvío no creó ni una fila nueva.
        expect(marksCountFor(ids)).toBe(3);
        expect(totalMarks()).toBe(afterFirstSend);
    });

    test('token inválido desempareja con aviso', async ({ page }) => {
        await pairStation(page);

        const marksBefore = totalMarks();

        // Capturamos con red caída…
        await page.context().setOffline(true);
        await expect(page.getByText('Sin conexión', { exact: true })).toBeVisible();

        await captureBib(page, bibs[0]);
        await expect(page.locator('ul.list-divide > li')).toHaveCount(1);
        await expect(page.getByText('1 pendiente', { exact: true }).first()).toBeVisible();

        // …y mientras tanto el token muere del lado del servidor (revocación:
        // borrar la fila de personal_access_tokens). Corromper el localStorage
        // no sirve sin recargar: el componente vive del token en memoria; y
        // recargar offline exigiría la cáscara del SW (bloqueada aquí).
        revokeStationToken();

        // Al volver la red, el flush del lote responde 401 ⇒ desempareje.
        // El badge «En línea» se auto-sana por intervalo (station.js): no se
        // afirma aquí porque el evento 'online' del navegador no siempre
        // llega al alternar la red por CDP — lo que importa es el desenlace.
        await page.context().setOffline(false);

        await expect(async () => {
            const sync = page.getByRole('button', { name: 'Sincronizar' });

            if (await sync.isVisible().catch(() => false) && await sync.isEnabled().catch(() => false)) {
                await sync.click().catch(() => {});
            }
        }).toPass({ timeout: 15_000 });

        await expect(page.getByText('Token de estación inválido')).toBeVisible();
        await expect(page.locator('#station-event')).toBeVisible();
        await expect(page.locator('#station-bib')).toBeHidden();

        // El 401 no persistió nada: la marca sigue solo en el dispositivo.
        expect(totalMarks()).toBe(marksBefore);
    });
});
