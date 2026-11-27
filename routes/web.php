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
