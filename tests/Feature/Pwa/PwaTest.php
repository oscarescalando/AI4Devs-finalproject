<?php

declare(strict_types=1);

use App\Models\Account;
use Database\Seeders\RolesPermissionsSeeder;

/**
 * ISSUE-114 (TQ-006) — PWA base instalable: manifest con iconos y tema del
 * spec/09, service worker que nunca intercepta Livewire ni la API, pantalla
 * offline en español y registro solo en los layouts con sesión (las
 * pantallas públicas/kiosco viven siempre frescas, ADR-014).
 */
beforeEach(function () {
    $this->seed(RolesPermissionsSeeder::class);
});

function pwaAccount(): Account
{
    $account = Account::factory()->create();
    $account->assignRole('timekeeper');

    return $account;
}

it('sirve el manifest instalable con nombre, tema y colores del spec/09', function () {
    $response = $this->get('/manifest.webmanifest');

    $response->assertOk()->assertHeader('Content-Type', 'application/manifest+json');

    $manifest = json_decode($response->getContent(), true);

    expect($manifest)->not->toBeNull()
        ->and($manifest['name'])->toBe('TDChronos')
        ->and($manifest['short_name'])->toBe('TDChronos')
        ->and($manifest['start_url'])->toBe('/')
        ->and($manifest['display'])->toBe('standalone')
        ->and($manifest['lang'])->toBe('es')
        ->and($manifest['background_color'])->toBe('#020617')
        ->and($manifest['theme_color'])->toBe('#0284C7');

    expect(array_column($manifest['icons'], 'sizes'))
        ->toContain('192x192')
        ->toContain('512x512');

    expect(array_column($manifest['icons'], 'purpose'))->toContain('maskable');
});

it('sirve el service worker del shell sin interceptar Livewire ni la API', function () {
    $sw = $this->get('/sw.js');

    $sw->assertOk();

    // Las exclusiones viven literalmente en el SW servido (ISSUE-114).
    expect($sw->getContent())
        ->toContain('/livewire/')
        ->toContain('/api/')
        ->toContain('navigate')
        ->toContain('/offline.html')
        ->toContain('/build/assets/');
});

it('sirve la pantalla offline autocontenida en español', function () {
    $this->get('/offline.html')
        ->assertOk()
        ->assertSee('Sin conexión')
        ->assertSee('TDChronos necesita red para esta pantalla. Reintenta cuando vuelvas la conexión.')
        ->assertSee('Reintentar')
        ->assertSee('location.reload()');
});

it('precachea la estación para abrir offline tras la primera visita con red', function () {
    $sw = $this->get('/sw.js');

    $sw->assertOk();

    // ISSUE-147 (TQ-021): el HTML de /station entra al precache del
    // install y su navegación se respalda a sí misma (los assets con hash
    // se calientan en la primera visita con red por la rama /build/assets/).
    expect($sw->getContent())
        ->toContain("'/station'")
        ->toContain('STATION_URL');
});

it('sirve los tres iconos del manifest como PNG', function (string $file) {
    $this->get("/icons/{$file}")
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
})->with(['icon-192.png', 'icon-512.png', 'icon-maskable-512.png']);

it('el layout del panel declara manifest, tema e icono iOS y registra el SW', function () {
    $html = $this->actingAs(pwaAccount())->get(route('panel.home'))->getContent();

    expect($html)
        ->toContain('<link rel="manifest" href="/pwa/manifest">')
        ->toContain('<meta name="theme-color" content="#0284C7">')
        ->toContain('<link rel="apple-touch-icon" href="/icons/icon-192.png">')
        ->toContain('<meta name="apple-mobile-web-app-capable" content="yes">')
        ->toContain('tcRegisterSW');
});

it('el layout de invitados declara manifest, tema e icono iOS y registra el SW', function () {
    $html = $this->get(route('login'))->getContent();

    expect($html)
        ->toContain('<link rel="manifest" href="/pwa/manifest">')
        ->toContain('<meta name="theme-color" content="#0284C7">')
        ->toContain('<link rel="apple-touch-icon" href="/icons/icon-192.png">')
        ->toContain('<meta name="apple-mobile-web-app-capable" content="yes">')
        ->toContain('tcRegisterSW');
});

it('el layout público no declara la PWA ni registra el service worker', function () {
    // La landing usa el layout público: siempre fresca, sin shell instalable.
    $html = $this->get('/')->getContent();

    expect($html)
        ->not->toContain('manifest')
        ->not->toContain('theme-color')
        ->not->toContain('serviceWorker')
        ->not->toContain('/sw.js');
});
