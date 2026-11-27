<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * ISSUE-159 — Esquema de la base de autenticación/autorización: la columna
 * activa de la cuenta (RF-68) y las tablas de spatie con teams (RF-69).
 */
uses(RefreshDatabase::class);

it('accounts tiene la columna active para RF-68', function () {
    $columns = DB::select(
        'SELECT COLUMN_NAME AS name, COLUMN_DEFAULT AS def FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
        ['accounts', 'active'],
    );

    expect($columns)->toHaveCount(1)
        ->and($columns[0]->def)->toBe('1');
});

it('model_has_roles particiona por equipo (spatie teams, RF-69)', function () {
    $columns = array_column(
        DB::select(
            'SELECT COLUMN_NAME AS name FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            ['model_has_roles'],
        ),
        'name',
    );

    expect($columns)->toContain('team_id');
});

it('model_has_permissions particiona por equipo (spatie teams, RF-69)', function () {
    $columns = array_column(
        DB::select(
            'SELECT COLUMN_NAME AS name FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            ['model_has_permissions'],
        ),
        'name',
    );

    expect($columns)->toContain('team_id');
});
