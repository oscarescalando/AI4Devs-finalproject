<?php

declare(strict_types=1);

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
