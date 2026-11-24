<?php

declare(strict_types=1);

use App\Http\Controllers\Api\EntryRosterController;
use App\Http\Controllers\Api\ServerTimeController;
use App\Http\Controllers\Api\TimingMarkController;
use Illuminate\Support\Facades\Route;

// API /api/v1 — solo consumidores no-web (ADR-012): la estación de meta
// es la primera consumidora. La UI web (Livewire) nunca pasa por aquí.

Route::prefix('v1')->group(function (): void {
    Route::get('/time', ServerTimeController::class);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/events/{eventUlid}/timing-marks', [TimingMarkController::class, 'store'])
            ->middleware('ability:device:marks');

        Route::get('/events/{eventUlid}/entries', [EntryRosterController::class, 'index'])
            ->middleware('ability:device:roster');
    });
});
