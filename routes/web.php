<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\LogoutController;
use App\Livewire\Directory\ManageOrganizations;
use App\Models\Event;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('landing', [
        'events' => Event::query()->orderByDesc('date')->limit(20)->get(),
    ]);
});

// Pantalla pública de clasificación en vivo (ISSUE-137, corte 1: sondeo).
Volt::route('/e/{slug}', 'public.leaderboard-page')->name('public.event');

// Estación de cronometraje (ISSUE-147, TQ-021, ADR-014): shell público SIN
// sesión — la captura corre 100 % local (Alpine + IndexedDB en station.js)
// y solo habla con la API con el token de dispositivo emparejado. Nada de
// auth() aquí; el layout del panel no viaja en esta página.
Route::view('/station', 'station')->name('station.home');

// Acceso (RF-68): login por documento o correo + contraseña, sesiones web.
Volt::route('/login', 'auth.login')->name('login')->middleware('guest');
Route::post('/logout', LogoutController::class)->name('logout')->middleware('auth');

// Panel: requiere sesión; las rutas por evento y permisos crecen con ISSUE-115.
Volt::route('/home', 'panel.home')->name('panel.home')->middleware('auth');

// Perfil propio (ISSUE-113, RF-71): cualquier cuenta autenticada, sin
// permiso específico — la propia cuenta no se autogestiona roles ni estado.
Volt::route('/profile', 'profile.show')->name('profile')->middleware('auth');

// CRUD demo del directorio (ISSUE-110/TQ-003): la entrada del menú aparece
// sola porque config/tc-ui.nav ya declara esta ruta.
Route::get('/directory/organizations', ManageOrganizations::class)
    ->name('directory.organizations')
    ->middleware(['auth', 'permission:directory.view']);

/*
|--------------------------------------------------------------------------
| Mapa de rutas to-be del panel (ISSUE-115, TQ-004)
|--------------------------------------------------------------------------
| El evento vive en el path y se resuelve por su ULID público — jamás el id
| entero (ADR-015): Route::pattern acota el segmento a 26 caracteres y el
| binding implícito de Event (getRouteKeyName = ulid) devuelve la 404 propia
| si no existe. Cada ruta nace con el permiso del catálogo sembrado
| (RolesPermissionsSeeder): la UI oculta, el servidor autoriza (ADR-011).
| Las pantallas completas llegan con EP-25; hoy son placeholders
| navegables — sin rutas fantasmas ni resource sin implementación.
*/

Route::pattern('event', '[0-9A-Za-z]{26}');

Volt::route('/events', 'events.event-list')->name('events.index')
    ->middleware(['auth', 'permission:events.view']);
Volt::route('/events/{event}', 'events.event-hub')->name('events.show')
    ->middleware(['auth', 'permission:events.view']);
Volt::route('/events/{event}/entries', 'events.entry-list')->name('events.entries')
    ->middleware(['auth', 'permission:entries.view']);
Volt::route('/events/{event}/start', 'events.start-board')->name('events.start')
    ->middleware(['auth', 'permission:start.manage']);
Volt::route('/events/{event}/marks', 'events.mark-log')->name('events.marks')
    ->middleware(['auth', 'permission:marks.view']);
// Captura de llegadas en vivo (ISSUE-130, TQ-012): dorsal + Enter por el
// conducto del lote; escribir marcas exige marks.manage, no solo verlas.
Volt::route('/events/{event}/arrivals', 'events.arrival-capture')->name('events.arrivals')
    ->middleware(['auth', 'permission:marks.manage']);
Volt::route('/events/{event}/results', 'events.result-board')->name('events.results')
    ->middleware(['auth', 'permission:results.view']);
Volt::route('/events/{event}/categories', 'events.category-list')->name('events.categories')
    ->middleware(['auth', 'permission:events.manage']);
Volt::route('/events/{event}/waves', 'events.wave-list')->name('events.waves')
    ->middleware(['auth', 'permission:events.manage']);

Volt::route('/directory/persons', 'directory.person-list')->name('directory.persons')
    ->middleware(['auth', 'permission:directory.view']);
Volt::route('/directory/teams', 'directory.team-list')->name('directory.teams')
    ->middleware(['auth', 'permission:directory.view']);

// Liga: sin tablas de liga en v1 (ADR-015); placeholder "Liga — v2".
Volt::route('/championships', 'championships.index')->name('championships.index')
    ->middleware(['auth', 'permission:championships.view']);

/*
|--------------------------------------------------------------------------
| PWA base instalable (ISSUE-114, TQ-006)
|--------------------------------------------------------------------------
| Manifest, service worker, pantalla offline e iconos viven como estáticos
| en public/ y el servidor web los sirve directo; estas rutas declaran el
| Content-Type correcto del manifest (application/manifest+json, que los
| mime.types por defecto no garantizan) y cubren el shell donde no hay
| servidor de estáticos — p. ej. las pruebas HTTP. El SW no toca /livewire
| ni /api (ISSUE-114).
*/

// Texto plano leído del estático (response()->file() no expone cuerpo en
// pruebas); el icono binario sí va por BinaryFileResponse.
// El manifest se sirve por /pwa/manifest: el servidor web entrega el archivo
// estático de /manifest.webmanifest antes que PHP y con un mime que Chrome
// puede rechazar; la ruta sin colisión garantiza application/manifest+json.
Route::get('/manifest.webmanifest', fn () => response()->make(
    file_get_contents(public_path('manifest.webmanifest')) ?: '',
    200,
    ['Content-Type' => 'application/manifest+json'],
))->name('pwa.manifest');

Route::get('/pwa/manifest', fn () => response()->make(
    file_get_contents(public_path('manifest.webmanifest')) ?: '',
    200,
    ['Content-Type' => 'application/manifest+json'],
))->name('pwa.manifest.canonical');

Route::get('/sw.js', fn () => response()->make(
    file_get_contents(public_path('sw.js')) ?: '',
    200,
    ['Content-Type' => 'application/javascript'],
))->name('pwa.sw');

Route::get('/offline.html', fn () => response()->make(
    file_get_contents(public_path('offline.html')) ?: '',
    200,
    ['Content-Type' => 'text/html; charset=UTF-8'],
))->name('pwa.offline');

Route::get('/icons/{file}', fn (string $file) => response()->file(
    public_path("icons/{$file}"),
    ['Content-Type' => 'image/png'],
))->where('file', 'icon-(192|512|maskable-512)\.png')->name('pwa.icon');
