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

// CRUD demo del directorio (ISSUE-110/TQ-003): la entrada del menú aparece
// sola porque config/tc-ui.nav ya declara esta ruta.
Route::get('/directory/organizations', ManageOrganizations::class)
    ->name('directory.organizations')
    ->middleware(['auth', 'permission:directory.view']);
