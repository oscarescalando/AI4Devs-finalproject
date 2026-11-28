<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Matriz base de roles y permisos (ISSUE-159, trae al repo la pieza heredada
 * de ISSUE-041/043 que el primer corte necesita). Roles del SRS §2; spatie
 * con teams = evento: los roles operativos (timekeeper, official, organizer)
 * se asignan por evento en tiempo de ejecución; aquí se siembra la base global.
 */
class RolesPermissionsSeeder extends Seeder
{
    /** Permisos del panel: ver/gestionar por superficie (SRS §2). */
    private const PERMISSIONS = [
        'events.view', 'events.manage',
        'directory.view', 'accounts.manage',
        'championships.view',
        'entries.view', 'entries.manage',
        'start.manage',
        'marks.view', 'marks.manage', 'corrections.manage',
        'results.view', 'results.publish',
        'roles.manage', 'platform.manage',
    ];

    /** Rol => permisos globales (sin equipo). */
    private const MATRIX = [
        'superadmin' => '*',
        'admin' => [
            'events.view', 'directory.view', 'accounts.manage', 'championships.view',
            'entries.view', 'marks.view', 'results.view',
        ],
        'organizer' => [
            'events.view', 'events.manage', 'entries.view', 'entries.manage',
            'start.manage', 'marks.view', 'corrections.manage',
            'results.view', 'results.publish',
        ],
        'official' => [
            'entries.view', 'start.manage', 'marks.view', 'marks.manage',
            'corrections.manage', 'results.view',
        ],
        'timekeeper' => ['marks.view', 'marks.manage'],
        'athlete' => [],
    ];

    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();
        // Contexto de siembra: equipo 0 = plataforma (roles globales).
        $registrar->setPermissionsTeamId(0);

        foreach (self::PERMISSIONS as $name) {
            Permission::findOrCreate($name, 'web');
        }

        foreach (self::MATRIX as $role => $permissions) {
            $roleModel = Role::findOrCreate($role, 'web');
            $roleModel->syncPermissions($permissions === '*' ? self::PERMISSIONS : $permissions);
        }
    }
}
