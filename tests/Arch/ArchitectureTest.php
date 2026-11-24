<?php

declare(strict_types=1);

/**
 * Reglas de arquitectura del monolito (ADR-011/ADR-012, ISSUE-105):
 * el endpoint llama a Actions; los controladores no tocan modelos
 * Eloquent ni la base de datos directamente; las Actions son finales.
 */
arch('los controladores de la API no importan modelos Eloquent')
    ->expect('App\Models')
    ->not->toBeUsedIn('App\Http\Controllers\Api');

arch('los controladores no acceden a la base de datos directamente')
    ->expect('Illuminate\Support\Facades\DB')
    ->not->toBeUsedIn('App\Http\Controllers');

arch('las Actions de dominio son clases finales')
    ->expect('App\Actions')
    ->classes()
    ->toBeFinal();

arch('las Actions no responden HTTP')
    ->expect('Illuminate\Http\JsonResponse')
    ->not->toBeUsedIn('App\Actions');

arch('los modelos no conocen la capa HTTP')
    ->expect('Illuminate\Http\Request')
    ->not->toBeUsedIn('App\Models');
